<?php
namespace izzum\statemachine;
use izzum\statemachine\Context;
use izzum\statemachine\EntityBuilder;
use izzum\statemachine\State;
use izzum\statemachine\persistence\Memory;
use izzum\statemachine\persistence\Adapter;
use izzum\statemachine\persistence\StorageData;
use izzum\statemachine\persistence\Session;
use izzum\statemachine\utils\PlantUml;

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
        $data = new StorageData($machine, $id, $state);
        $this->assertEquals($id, $data->id);
        $this->assertEquals($machine, $data->machine);
        $this->assertEquals($state, $data->state);
        $this->assertEquals($time, $data->timestamp);
        
        //scenario: use the factory method
        $object = new Identifier($id, $machine);
        $time = time();
        $data = StorageData::get($object, $state);
        $this->assertEquals($id, $data->id);
        $this->assertEquals($machine, $data->machine);
        $this->assertEquals($state, $data->state);
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
        $object = new Identifier(-1, 'order');
        
        $io = new Memory();
        $state = $io->getState($object);
        $this->assertEquals(State::STATE_NEW, $state,'default reader should return new if not present');
        $this->assertEquals('izzum\statemachine\persistence\Memory', $io->toString());
        $this->assertEquals(State::STATE_NEW, $io->getInitialState($object));
        

        
        $object = new Identifier(-1, 'order');
        $io = new Memory();
        $result = $io->setState($object, "test");
        $this->assertTrue($result, 'default writer returns true when not present');
        
        $result = $io->setState($object, "test");
        $this->assertFalse($result, 'default writer returns false when data is present');
        $this->assertEquals('izzum\statemachine\persistence\Memory', $io->toString());
        
        $result = $io->getState($object);
        $this->assertEquals('test', $result);
        
        
        
        $io = new Memory();
        $output = Memory::get();
        //var_dump($output);
        $this->assertEquals('test', $io->getState($object), 'shares the storage space in a central registry');
        $result = $io->setState($object, "joho");
        $this->assertEquals('joho', $io->getState($object));
        $output = Memory::get();
        //var_dump($output);
        Memory::clear();
        
        //scenario
        $this->assert_Add_GetEntityIds_Set($io);

        
    }
    
    protected function assert_Add_GetEntityIds_Set(Adapter $io) {
        $machine = 'a-machine';
        $id1 = '555';
        $id2 = '666';
        $object1 = new Identifier($id1, $machine);
        $object2 = new Identifier($id2, $machine);
    
        
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
        $identifier = new Identifier(-1, 'null-machine');
        
        //internal test class
        $io = new MemoryEntityConcatenator();
        
        Memory::clear();
        $result = $io->setState($identifier, "TEST");
        $this->assertEquals("null-machine_-1_TEST", $result, 'concatenated stuff');
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
        $object = new Context(new Identifier($id, $machine), $builder, $io);
        $this->assertTrue($io->setState($object->getIdentifier(), 'bogus'));
        $this->assertFalse($io->setState($object->getIdentifier(), 'bogus2'));
        $this->assertFalse($io->add($object->getIdentifier()), 'already there');
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
        $context = Context::get(new Identifier(Identifier::NULL_ENTITY_ID, Identifier::NULL_STATEMACHINE));
        $identifier = $context->getIdentifier();
        $io = new MemoryException(true);
        
        try {
            $io->setState($identifier, 'new');
            $this->fail('should throw exception');
        } catch (Exception $e) {
            $this->assertEquals(Exception::IO_FAILURE_SET, $e->getCode());
        }
        
        try {
            $io->getState($identifier);
            $this->fail('should throw exception');
        } catch (Exception $e) {
            $this->assertEquals(Exception::IO_FAILURE_GET, $e->getCode());
        }
        
        $io = new MemoryException(false);
        
        try {
            $io->setState($identifier, 'new');
            $this->fail('should throw exception');
        } catch (Exception $e) {
            $this->assertEquals(123, $e->getCode());
        }
        
        try {
            $io->getState($identifier);
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
    protected function assertPersistenceAdapterPDO($adapter, $machine, $output_plant = false) {
        
        $type = $adapter->getType();
        echo PHP_EOL;
        echo "Executing Database tests for type '$type'." . PHP_EOL;
        echo "Please check the following: php drivers present? database and tables created?" . PHP_EOL;
        echo "correct permissions set? dns correct for the PDO driver?" . PHP_EOL;
        
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
        $identifier = new Identifier($random_id, $machine);
        $context = new Context($identifier, null, $adapter);
        try {
            $this->assertEquals(State::STATE_NEW, $context->getState());
            $this->fail('should not come here');
        } catch (Exception $e) {
            $this->assertEquals(Exception::PERSISTENCE_LAYER_EXCEPTION, $e->getCode());
            $this->assertContains('no state found for', $e->getMessage());
            $this->assertContains('Did you add', $e->getMessage());
        }
        $this->assertFalse($adapter->isPersisted($identifier), 'not persisted yet');
        $count = count($adapter->getEntityIds($machine, 'new'));
        $this->assertTrue($adapter->add($identifier), 'first time');
        $this->assertCount($count + 1, $adapter->getEntityIds($machine, 'new'), '1 extra in state new');
        $this->assertFalse($adapter->add($identifier), 'already added');
        $this->assertEquals(State::STATE_NEW, $context->getState(), 'state is now new');
        
        
        
        //the postgres persistence adapter doubles as a loader, so we do some tests
        //for that
        $sm = new StateMachine($context);
        $this->assertCount(0, $sm->getTransitions());
        $this->assertCount(0, $sm->getStates());
        $adapter->load($sm);
        if($output_plant) {
        	$plant = new PlantUml();
        	$output = $plant->createStateDiagram($sm);
        	echo PHP_EOL;
        	echo PHP_EOL;
        	echo "**** generating plantuml in persistence test for type " . $type . PHP_EOL;
        	echo PHP_EOL;
        	echo PHP_EOL;
        	echo $output;
        	echo PHP_EOL;
        	echo PHP_EOL;
        	echo "**** end generating plantuml in persistence test for type " . $type . PHP_EOL;
        	echo PHP_EOL;
        	echo PHP_EOL;
        }
        $this->assertCount(9, $sm->getTransitions());
        $this->assertCount(6, $sm->getStates());
        $count_done = count($adapter->getEntityIds($machine, 'done'));
        
        //take the happy flow to completion
        $total = $sm->runToCompletion();
        $this->assertEquals(4, $total);
        $this->assertCount($count_done + 1, $adapter->getEntityIds($machine, 'done'));
        
        
        //create a new context to take the unhappy flow
        $random_id = rand(1, 999999999) . "-" . microtime();
        $identifier = new Identifier($random_id, $machine);
        $other_context = new Context($identifier, null, $adapter);
        $sm->changeContext($other_context);
        $this->assertCount(9, $sm->getTransitions());
        $this->assertCount(6, $sm->getStates());
        //load again, not necessary, but should not be a problem either
        $adapter->load($sm);
        $this->assertCount(9, $sm->getTransitions());
        $this->assertCount(6, $sm->getStates());
        $this->assertFalse($adapter->isPersisted($identifier));
        
        
        //run via 'bad' path, priority 2, this will also 'add' it to the backend
        try {
            $sm->canTransition('new_to_bad');
            $this->fail('should not come here, not added');
        } catch (Exception $e) {
            $this->assertEquals(Exception::PERSISTENCE_LAYER_EXCEPTION, $e->getCode());
        }
        
        
        try {
            $sm->transition('new_to_bad');
            $this->fail('should not come here, not added');
        } catch (Exception $e) {
            $this->assertEquals(Exception::PERSISTENCE_LAYER_EXCEPTION, $e->getCode());
        }
        //do this directly on adapter to see if will actually insert into the history
        //and entity tables
        $this->assertTrue($adapter->setState($identifier, 'new'));
        $this->assertEquals($other_context->getState(), 'new');
        $this->assertTrue($sm->canTransition('new_to_bad'));
        $this->assertTrue($sm->canTransition('new_to_ok'));
        
        $count = count($adapter->getEntityIds($machine, 'bad'));
        $sm->transition('new_to_bad');
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
        echo "tests for type '$type' OK" . PHP_EOL;
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
        $machine = 'izzum';
        $user = 'postgres';
        $password = "izzum";
        $dsn = "pgsql:host=localhost;port=5432;dbname=postgres";
        $adapter = new PDO($dsn, $user, $password);   
        $this->assertPersistenceAdapterPDO($adapter, $machine, false);
    }
    
      /**
     * this test will only run when the \assets\sql\sqlite.sql file has been 
     * executed on a sqlite backend, providing test data 
     * 
     * create file 'sqlite.db' in 'tests' directory (# sqlite3 sqlite.db) and load the assets\sql\sqlite.sql file
     * 
     * @group not-on-production
     * @group sqlite
     */
    public function testPDOAdapterSQLITE()
    {
        //create file 'sqlite.db' in 'tests' directory and load the assets/sql/sqlite.sql file
        $machine = 'izzum';
        $dsn = "sqlite:sqlite.db";
        $adapter = new PDO($dsn);   
        $this->assertPersistenceAdapterPDO($adapter, $machine, true);
    }
       
    /**
     * this test will only run when the \assets\sql\mysql.sql file has been 
     * executed on a mysql backend, providing test data.
     * @group not-on-production
     * @group mysql
     */
    public function testPDOAdapterMYSQL()
    {
        $dsn = 'mysql:host=localhost;dbname=test';
        $username = null;
        $password = null;
        $options = array(
            \PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8',
        );

        $machine = 'izzum';
        $adapter = new PDO($dsn, $username, $password, $options); 
        $this->assertPersistenceAdapterPDO($adapter, $machine);

    }
    
    
    
    
    
}

class MemoryEntityConcatenator extends Memory {
    /**
     * overriden implementation
     */
    protected function processSetState(Identifier $identifier, $state){
        return $identifier->getMachine() . "_" . 
        $identifier->getEntityId() . "_" .
        $state;
    }
    
    protected function processGetState(Identifier $identifier) {
        return $identifier->getMachine() .  "_" . $identifier->getEntityId();
    }
}

class MemoryException extends Memory {
    private $bool;
    public function __construct($bool) {
        $this->bool = $bool;
    }
    protected function processSetState(Identifier $identifier, $state){
        if($this->bool) {
            throw new \Exception('processing setstate exception');
        } else {
            throw new Exception('processing setstate exception', 123);
        }
    }
    
    protected function processGetState(Identifier $identifier) {
       if($this->bool) {
            throw new \Exception('processing setstate exception');
        } else {
            throw new Exception('processing setstate exception', 345);
        }
    }
}