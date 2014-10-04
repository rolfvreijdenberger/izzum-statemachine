<?php
namespace izzum\statemachine;
use izzum\statemachine\utils\ContextNull;
use izzum\statemachine\persistence\Memory;

/**
 * @group statemachine
 * @group Context
 * @author rolf
 *
 */
class ContextTest extends \PHPUnit_Framework_TestCase {
    
    /**
     * test the factory method with default parameters only
     * implicitely tests the constructor
     */
    public function testFactoryDefault()
    {

        $entity_id = "id123";
        $machine = "test-machine";
        
        //only mandatory parameters
        $o = Context::get($entity_id, $machine);
        $this->assertNotNull($o->toString());
        $this->assertContains($entity_id, $o->getId());
        $this->assertContains($machine, $o->getId());
        $this->assertContains($entity_id, $o->getId(false));
        $this->assertContains($machine, $o->getId(false));
        $this->assertContains($entity_id, $o->getId(true));
        $this->assertContains($machine, $o->getId(true));
        
        $this->assertEquals($entity_id, $o->getEntityId());
        $this->assertEquals($machine, $o->getMachine());
        $this->assertNull($o->getStateMachine());
        //defaulting to database readers and writers
        $this->assertTrue(is_a($o->getPersistenceAdapter(), 'izzum\statemachine\persistence\Memory'));
        $this->assertTrue(is_a($o->getBuilder(), 'izzum\statemachine\EntityBuilder'));
        $this->assertEquals($o, $o->getEntity());
        $this->assertTrue(is_string($o->getEntityId()));
        
        $this->assertTrue(is_string($o->toString()));
        $this->assertContains($entity_id, $o->toString());
        $this->assertContains($machine, $o->toString());
        $this->assertContains('izzum\statemachine\Context', $o->toString());
        $this->assertNotContains('izzum\statemachine\utils\ContextNull', $o->toString());
        
        
        $this->assertEquals(State::STATE_NEW, $o->getState());
        
    }
    
    
    public function testConversionOfContextIdToString()
    {
        $entity_id = 1;
        $machine = 'test';
        $o = new Context($entity_id, $machine);
        $this->assertFalse($entity_id === $o->getEntityId());
        $this->assertEquals($entity_id, $o->getEntityId());
        $this->assertTrue(is_string($o->getEntityId()));
        $this->assertEquals("1", $o->getEntityId());
    }
    
    

    public function testFull()
    {
        $entity_id = "id";
        $machine = "test machine";
        $builder = new EntityBuilder();
        $io = new Memory();
    
        //all parameters
        $o = new Context($entity_id, $machine, $builder, $io);
        $this->assertEquals($entity_id, $o->getEntityId());
        $this->assertEquals($machine, $o->getMachine());
        $this->assertNull($o->getStateMachine());
        $this->assertTrue(is_a($o->getPersistenceAdapter(), 'izzum\statemachine\persistence\Memory'));
        $this->assertTrue(is_a($o->getBuilder(), 'izzum\statemachine\EntityBuilder'));
        $this->assertEquals($o, $o->getEntity());
        $this->assertTrue(is_string($o->getEntityId()));
        
        $this->assertTrue(is_string($o->toString()));
        $this->assertContains($entity_id, $o->toString());
        $this->assertContains($machine, $o->toString());
        $this->assertContains('izzum\statemachine\Context', $o->toString());
        $this->assertNotContains('izzum\statemachine\utils\ContextNull', $o->toString());
        
        //even though we have a valid reader, the state machine does not exist.
        $this->assertEquals(State::STATE_NEW, $o->getState());
        $this->assertTrue($o->setState(State::STATE_UNKNOWN));
        $this->assertEquals(State::STATE_UNKNOWN, $o->getState());
        
        //for coverage.
        $o->getId();
        $o->getId(true);
        $o->getId(false);
        $o->getId(true, true);
        $o->getId(false, true);
    
    }
    
    /**
     * test the factory method with all parameters provided
     * implicitely tests the constructor
     */
    public function testContextNull()
    {
        $entity_id = "id1";
        $machine = "test machine";
        $builder = new EntityBuilder();
        $io = new Memory();
    
        //all parameters
        $o = ContextNull::get($entity_id, $machine, $builder, $io);
        $this->assertEquals($entity_id, $o->getEntityId());
        $this->assertEquals($machine, $o->getMachine());
        $this->assertNull($o->getStateMachine());
        $this->assertTrue(is_a($o->getPersistenceAdapter(), 'izzum\statemachine\persistence\Memory'));
        $this->assertTrue(is_a($o->getBuilder(), 'izzum\statemachine\EntityBuilder'));
        $this->assertEquals($o, $o->getEntity());
        $this->assertTrue(is_string($o->getEntityId()));
    
        $this->assertTrue(is_string($o->toString()));
        $this->assertContains($entity_id, $o->toString());
        $this->assertContains($machine, $o->toString());
        $this->assertContains('izzum\statemachine\utils\ContextNull', $o->toString());
    
        //even though we have a valid reader, the state machine does not exist.
        $this->assertEquals(State::STATE_NEW, $o->getState());
        $this->assertTrue($o->setState(State::STATE_UNKNOWN),'added');
        $this->assertFalse($o->setState(State::STATE_UNKNOWN),'already there');
        
        //for coverage.
        $statemachine = new StateMachine($o);
        $this->assertNull($o->setStateMachine($statemachine));
        
        
        
    }
    
}