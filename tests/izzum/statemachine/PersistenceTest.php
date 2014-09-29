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
        $this->assertFalse($result, 'default writer returns false when not present');
        
        $result = $io->setState($object, "test");
        $this->assertTrue($result, 'default writer returns true when data is present');
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
        $this->assertTrue($result, 'already present');
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
        $this->assertFalse($io->setState($object, 'bogus'));
        $this->assertTrue($io->setState($object, 'bogus2'));
        $this->assertFalse($io->add($object), 'already there');
    }
    
    
    public static function tearDownAfterClass() {
        parent::tearDownAfterClass();
        //we have started output buffering in the bootstrap file
        ob_flush();
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