<?php
namespace izzum\statemachine;
use izzum\statemachine\utils\ContextNull;
use izzum\statemachine\Context;
use izzum\statemachine\EntityBuilder;
use izzum\statemachine\State;
use izzum\statemachine\persistence\Memory;
use izzum\statemachine\persistence\Adapter;
use izzum\statemachine\persistence\StorageData;
use izzum\statemachine\persistence\Session;

use izzum\statemachine\persistence\PDO;
/**
 * @group statemachine
 * @author rolf
 *
 */
class PersistenceTest extends \PHPUnit_Framework_TestCase {
    
    
    public function testStorageData()
    {
        $machine = 'test';
        $id = 'testid1123';
        $state = 'done';
        $state_from = 'some-state';
        
        //scenario:  constructor with all params
        $time = time();
        $data = new StorageData($machine, $id, $state, $state_from);
        $this->assertEquals($id, $data->id);
        $this->assertEquals($machine, $data->machine);
        $this->assertEquals($state, $data->state);
        $this->assertEquals($state_from, $data->state_from);
        $this->assertEquals($time, $data->timestamp);
        
        //scenario: use the factory method
        $object = ContextNull::get($id, $machine);
        $time = time();
        $data = StorageData::get($object, $state);
        $this->assertEquals($id, $data->id);
        $this->assertEquals($machine, $data->machine);
        $this->assertEquals($state, $data->state);
        $this->assertEquals(State::STATE_NEW, $data->state_from);
        $this->assertEquals($time, $data->timestamp);
        
        
        //scenario: optional param left out of constructor
        $time = time();
        $data = new StorageData($machine, $id, $state);
        $this->assertEquals($id, $data->id);
        $this->assertEquals($machine, $data->machine);
        $this->assertEquals($state, $data->state);
        $this->assertNull($data->state_from);
        $this->assertEquals($time, $data->timestamp);
        
    }
    

    
    public function testMemoryAdapter()
    {
        //create Context in default state. this is enough to pass it 
        //to the reader object
        $object = ContextNull::get(-1, 'order', 'ordermachine');
        
        $io = new Memory();
        $state = $io->getState($object);
        $this->assertEquals(State::STATE_NEW, $state,'default reader should return new if not present');
        $this->assertEquals('izzum\statemachine\persistence\Memory', $io->toString());
        $this->assertEquals(State::STATE_NEW, $io->getInitialState($object));
        

        
        //create Context in default state. this is enough to pass it
        //to the writer object
        $object = ContextNull::get(-1, 'order', 'ordermachine');
        $io = new Memory();
        $result = $io->setState($object, "test");
        $this->assertTrue($result, 'default writer returns true when not present');
        
        $result = $io->setState($object, "test");
        $this->assertFalse($result, 'default writer returns false when data is present');
        $this->assertEquals('izzum\statemachine\persistence\Memory', $io->toString());
        
        $result = $io->getState($object);
        $this->assertEquals('test', $result);
        
        
        
        $io = new Memory();
        $output = Memory::get();//for coverage
        Memory::clear();
        
        //scenario
        $this->assert_Add_GetEntityIds_Set($io);

        
    }
    
    protected function assert_Add_GetEntityIds_Set(Adapter $io) {
        $machine = 'a-machine';
        $id1 = '555';
        $id2 = '666';
        $object1 = ContextNull::get($id1, $machine);
        $object2 = ContextNull::get($id2, $machine);
    
        
        $state = $io->getState($object1);
        $this->assertEquals($state, State::STATE_NEW);
        $this->assertCount(0, $io->getEntityIds($machine));
        $this->assertTrue(is_array($io->getEntityIds($machine)));
        $this->assertTrue($io->add($object1),'first time added');
        $this->assertFalse($io->add($object1),'already present');
        
        $this->assertCount(1, $io->getEntityIds($machine));
        $this->assertTrue(in_array($id1, $io->getEntityIds($machine)));
        $this->assertFalse(in_array($id2, $io->getEntityIds($machine)));
        $this->assertTrue(is_array($io->getEntityIds($machine)));
        $this->assertCount(0, $io->getEntityIds('bogus'));
        $this->assertTrue(is_array($io->getEntityIds('bogus')));
        
        $this->assertTrue($io->add($object2),'first time added');
        $this->assertFalse($io->add($object2),'already present');
        $this->assertCount(2, $io->getEntityIds($machine));
        $this->assertTrue(is_array($io->getEntityIds($machine)));
        $this->assertCount(0, $io->getEntityIds('bogus'));
        $this->assertTrue(is_array($io->getEntityIds('bogus')));
        $this->assertTrue(in_array($id1, $io->getEntityIds($machine)));
        $this->assertTrue(in_array($id2, $io->getEntityIds($machine)));
        $this->assertCount(2, $io->getEntityIds($machine, State::STATE_NEW));
       
        //move 1 to anoter state
        $result = $io->setState($object1, State::STATE_DONE);
        $this->assertFalse($result, 'already present');
        $this->assertCount(1, $io->getEntityIds($machine, State::STATE_NEW));
        $this->assertCount(1, $io->getEntityIds($machine, State::STATE_DONE));
        $this->assertCount(2, $io->getEntityIds($machine));
        
    }
    
    

    
    /**
     * tests an overriden implementation to see if the hook functions correctly.
     */
    public function testMemoryAdapterSubclassFunctionality()
    {
        //create Entity in default state. this is enough to pass it
        //to the writer object
        $object = ContextNull::get(-1,'smt');
        
        //internal test class
        $io = new MemoryEntityConcatenator();
        
        Memory::clear();
        $result = $io->setState($object, "TEST");
        $this->assertEquals("smt_-1_TEST", $result, 'concatenated stuff');
        $this->assertContains('MemoryEntityConcatenator', $io->toString());
    }
    
    /**
     * not working in travis-ci because output already started. ob_flush is not 
     * working
     * @group uses-sessions
     * @group not-on-production
     */
    public function testSessionAdapter()
    {
        //I have verified (by forcing a session id) that sessions actually
        //do work after consecutive calls.
        //I'm not sure how to test this in phpunit though..
        //$io = new Session('izzum', '123ab');
        $io = new Session();
        $this->assert_Add_GetEntityIds_Set($io);
        $this->assertEquals('izzum\statemachine\persistence\Session', $io->toString());
        
        
        
        $id = '1234124sdf';
        $machine = "new-machine";
        $builder = new EntityBuilder();
        $object = new Context($id, $machine, $builder, $io);
        $this->assertTrue($io->setState($object, 'bogus'));
        $this->assertFalse($io->setState($object, 'bogus2'));
        $this->assertFalse($io->add($object), 'already there');
        //we should have started output buffering in the bootstrap file
        ob_flush();
    }
    
    
    public static function tearDownAfterClass() {
        parent::tearDownAfterClass();        
    }
    
    /**
     * @test
     */
    public function shouldThrowExceptions()
    {
        $context = ContextNull::forTest();
        $io = new MemoryException(true);
        
        try {
            $io->setState($context, 'new');
            $this->fail('should throw exception');
        } catch (Exception $e) {
            $this->assertEquals(Exception::IO_FAILURE_SET, $e->getCode());
        }
        
        try {
            $io->getState($context);
            $this->fail('should throw exception');
        } catch (Exception $e) {
            $this->assertEquals(Exception::IO_FAILURE_GET, $e->getCode());
        }
        
        $io = new MemoryException(false);
        
        try {
            $io->setState($context, 'new');
            $this->fail('should throw exception');
        } catch (Exception $e) {
            $this->assertEquals(123, $e->getCode());
        }
        
        try {
            $io->getState($context);
            $this->fail('should throw exception');
        } catch (Exception $e) {
            $this->assertEquals(345, $e->getCode());
        }
    }
    
    
    /**
     * helper method for different backend adapters
     * that use a database (postgres, pdo)
     * @param PDO $adapter
     * @param string $machine
     */
    protected function assertPersistenceAdapterPDO($adapter, $machine) {
         //transitions
        $this->assertCount(9, $adapter->getTransitions($machine));
        $this->assertCount(9, $adapter->getLoaderData($machine));
        
        $this->assertEquals('', $adapter->getPrefix());
        $adapter->setPrefix('testing 123');
        $this->assertEquals('testing 123', $adapter->getPrefix());
        $adapter->setPrefix('');
        
        
        //get all the entitty ids.
        //since, if we run this test over multiple iterations, stuff will be added,
        //we use the >=assertion to be able to run this test on a fresh dataset and on 
        //a dataset that has repeatedly been altered by this test.
        $ids = $adapter->getEntityIds($machine);
        $this->assertGreaterThanOrEqual(14, $ids);
        $ids = $adapter->getEntityIds($machine, 'new');
        $this->assertGreaterThanOrEqual(5, $ids);
        $ids = $adapter->getEntityIds($machine, 'done');
        $this->assertGreaterThanOrEqual(2, $ids);
        $ids = $adapter->getEntityIds($machine,'bad');
        $this->assertGreaterThanOrEqual(2, $ids);
        $ids = $adapter->getEntityIds($machine, 'fine');
        $this->assertGreaterThanOrEqual(1, $ids);
        $ids = $adapter->getEntityIds($machine, 'ok');
        $this->assertGreaterThanOrEqual(3, $ids);
        $ids = $adapter->getEntityIds($machine, 'excellent');
        $this->assertGreaterThanOrEqual(1, $ids);
        $ids = $adapter->getEntityIds($machine, 'bogus');
        $this->assertCount(0, $ids);
        
        
        //diverse tests for the persistance of anon existing fully random id
        $random_id = rand(1,999999999) . "-" . microtime();
        $context = new Context($random_id, $machine, null, $adapter);
        try {
            $this->assertEquals(State::STATE_NEW, $context->getState());
            $this->fail('should not come here');
        } catch (Exception $e) {
            $this->assertEquals(Exception::PERSISTENCE_LAYER_EXCEPTION, $e->getCode());
            $this->assertContains('no state found for', $e->getMessage());
            $this->assertContains('Did you add', $e->getMessage());
        }
        $this->assertFalse($adapter->isPersisted($context), 'not persisted yet');
        $count = count($adapter->getEntityIds($machine, 'new'));
        $this->assertTrue($adapter->add($context), 'first time');
        $this->assertCount($count + 1, $adapter->getEntityIds($machine, 'new'), '1 extra in state new');
        $this->assertFalse($adapter->add($context), 'already added');
        $this->assertEquals(State::STATE_NEW, $context->getState(), 'state is now new');
        
        
        
        //the postgres persistence adapter doubles as a loader, so we do some tests
        //for that
        $sm = new StateMachine($context);
        $this->assertCount(0, $sm->getTransitions());
        $this->assertCount(0, $sm->getStates());
        $adapter->load($sm);
        $this->assertCount(9, $sm->getTransitions());
        $this->assertCount(6, $sm->getStates());
        $count_done = count($adapter->getEntityIds($machine, 'done'));
        
        //take the happy flow to completion
        $total = $sm->runToCompletion();
        $this->assertEquals(4, $total);
        $this->assertCount($count_done + 1, $adapter->getEntityIds($machine, 'done'));
        
        
        //create a new context to take the unhappy flow
        $random_id = rand(1, 999999999) . "-" . microtime();
        $other_context = new Context($random_id, $machine, null, $adapter);
        $sm->changeContext($other_context);
        $this->assertCount(9, $sm->getTransitions());
        $this->assertCount(6, $sm->getStates());
        //load again, not necessary, but should not be a problem either
        $adapter->load($sm);
        $this->assertCount(9, $sm->getTransitions());
        $this->assertCount(6, $sm->getStates());
        $this->assertFalse($adapter->isPersisted($other_context));
        
        
        //run via 'bad' path, priority 2, this will also 'add' it to the backend
        try {
            $sm->can('new_to_bad');
            $this->fail('should not come here, not added');
        } catch (Exception $e) {
            $this->assertEquals(Exception::PERSISTENCE_LAYER_EXCEPTION, $e->getCode());
        }
        
        
        try {
            $sm->apply('new_to_bad');
            $this->fail('should not come here, not added');
        } catch (Exception $e) {
            $this->assertEquals(Exception::PERSISTENCE_LAYER_EXCEPTION, $e->getCode());
        }
        //do this directly on adapter to see if will actually insert into the history
        //and entity tables
        $this->assertTrue($adapter->setState($other_context, 'new'));
        $this->assertEquals($other_context->getState(), 'new');
        $this->assertTrue($sm->can('new_to_bad'));
        $this->assertTrue($sm->can('new_to_ok'));
        
        $count = count($adapter->getEntityIds($machine, 'bad'));
        $sm->apply('new_to_bad');
        $this->assertCount($count + 1, $adapter->getEntityIds($machine, 'bad'));
        $this->assertTrue(in_array($random_id, $adapter->getEntityIds($machine, 'bad')));
        $this->assertTrue(in_array($random_id, $adapter->getEntityIds($machine)));
        $this->assertFalse(in_array($random_id, $adapter->getEntityIds($machine, 'new')));
        
        try {
            $sm->run();
            $this->fail('should not come here. bad to done will throw an exception via the rule');
        } catch (Exception $e) {
            $this->assertEquals(Exception::RULE_APPLY_FAILURE, $e->getCode());
        }
    }
    
    
    /**
     * this test will only run when the \assets\sql\postgresql.sql file has been 
     * executed on a postgres backend, providing test data.
     * @group not-on-production
     * @group pdo
     * @group postgresql
     */
    public function testPDOAdapterPOSTGRES()
    {
        echo "PLEASE CREATE THE CORRECT POSTGRES DATABASE AND USE THE RIGHT DSN" . PHP_EOL;
        $machine = 'izzum';
        $user = 'postgres';
        $password = "izzum";
        $dsn = "pgsql:host=localhost;port=5432;dbname=postgres";
        $adapter = new PDO($dsn, $user, $password);   
        $this->assertPersistenceAdapterPDO($adapter, $machine);
    }
    
       /**
     * this test will only run when the \assets\sql\sqlite.sql file has been 
     * executed on a sqlite backend, providing test data.
     * @group not-on-production
     * @group sqlite
     */
    public function testPDOAdapterSQLITE()
    {
        echo "PLEASE CREATE THE CORRECT SQLITE DATABASE AND USE THE RIGHT DSN" . PHP_EOL;
        $machine = 'izzum';
        $dsn = "sqlite:sqlite.db";
        $adapter = new PDO($dsn);   
        $this->assertPersistenceAdapterPDO($adapter, $machine);
    }
       
    /**
     * this test will only run when the \assets\sql\mysql.sql file has been 
     * executed on a mysql backend, providing test data.
     * @group not-on-production
     * @group mysql
     */
    public function testPDOAdapterMYSQL()
    {
        echo "PLEASE CREATE THE CORRECT MYSQL DATABASE AND USE THE RIGHT DSN" . PHP_EOL;
        $dsn = 'mysql:host=localhost;dbname=test';
        $username = null;
        $password = null;
        $options = array(
            \PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8',
        );

        $machine = 'izzum';
        $adapter = new PDO($dsn, $username, $password, $options); 
        $adapter->setPrefix('izzum_');
        $adapter->setPrefix('');
        $this->assertPersistenceAdapterPDO($adapter, $machine);

    }
    
    
    
    
    
}

class MemoryEntityConcatenator extends Memory {
    /**
     * overriden implementation
     */
    protected function processSetState(Context $context, $state){
        return $context->getMachine() . "_" . 
        $context->getEntityId() . "_" .
        $state;
    }
    
    protected function processGetState(Context $context) {
        return $context->getMachine() .  "_" . $context->getEntityId();
    }
}

class MemoryException extends Memory {
    private $bool;
    public function __construct($bool) {
        $this->bool = $bool;
    }
    protected function processSetState(Context $context, $state){
        if($this->bool) {
            throw new \Exception('processing setstate exception');
        } else {
            throw new Exception('processing setstate exception', 123);
        }
    }
    
    protected function processGetState(Context $context) {
       if($this->bool) {
            throw new \Exception('processing setstate exception');
        } else {
            throw new Exception('processing setstate exception', 345);
        }
    }
}