<?php
namespace izzum\statemachine;
use izzum\statemachine\persistence\Memory;

/**
 * @group statemachine
 * @group Context
 * 
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
        $identifier = new Identifier($entity_id, $machine);
        
        // only mandatory parameters
        $o = Context::get($identifier);
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
        // defaulting to database readers and writers
        $this->assertTrue(is_a($o->getPersistenceAdapter(), 'izzum\statemachine\persistence\Memory'));
        $this->assertTrue(is_a($o->getBuilder(), 'izzum\statemachine\EntityBuilder'));
        $this->assertEquals($o->getIdentifier(), $o->getEntity());
        $this->assertTrue(is_string($o->getEntityId()));
        
        $this->assertEquals($o->getIdentifier(), $identifier);
        
        $this->assertTrue(is_string($o->toString()));
        $this->assertContains($entity_id, $o->toString());
        $this->assertContains($machine, $o->toString());
        $this->assertContains('izzum\statemachine\Context', $o->toString());
        
        $this->assertEquals(State::STATE_UNKNOWN, $o->getState());
    }

    public function testConversionOfContextIdToString()
    {
        $entity_id = 1;
        $machine = 'test';
        $identifier = new Identifier($entity_id, $machine);
        $o = new Context($identifier);
        $this->assertFalse($entity_id === $o->getEntityId());
        $this->assertEquals($entity_id, $o->getEntityId());
        $this->assertTrue(is_string($o->getEntityId()));
        $this->assertEquals("1", $o->getEntityId());
    }

    public function testFull()
    {
        $entity_id = "id";
        $machine = "test machine";
        $identifier = new Identifier($entity_id, $machine);
        $builder = new EntityBuilder();
        $io = new Memory();
        
        // all parameters
        $o = new Context($identifier, $builder, $io);
        $this->assertEquals($entity_id, $o->getEntityId());
        $this->assertEquals($machine, $o->getMachine());
        $this->assertNull($o->getStateMachine());
        $this->assertTrue(is_a($o->getPersistenceAdapter(), 'izzum\statemachine\persistence\Memory'));
        $this->assertTrue(is_a($o->getBuilder(), 'izzum\statemachine\EntityBuilder'));
        $this->assertEquals($o->getIdentifier(), $o->getEntity());
        $this->assertTrue(is_string($o->getEntityId()));
        
        $this->assertTrue(is_string($o->toString()));
        $this->assertContains($entity_id, $o->toString());
        $this->assertContains($machine, $o->toString());
        $this->assertContains('izzum\statemachine\Context', $o->toString());
        
        // even though we have a valid reader, the state machine does not exist.
        $this->assertEquals(State::STATE_UNKNOWN, $o->getState());
        $this->assertTrue($o->setState('lala'));
        $this->assertEquals('lala', $o->getState());
        
        // for coverage.
        $this->assertNotNull($o->getId());
        $this->assertNotNull($o->getId(true));
        $this->assertNotNull($o->getId(false));
        $this->assertNotNull($o->getId(true, true));
        $this->assertNotNull($o->getId(false, true));
        
        // adding
        $machine = 'add-experiment-machine';
        $context = new Context(new Identifier('add-experiment-id', $machine), $builder, $io);
        $sm = new StateMachine($context);
        $sm->addTransition(new Transition(new State('c', State::TYPE_FINAL), new State('d'), State::TYPE_NORMAL));
        $sm->addTransition(new Transition(new State('a', State::TYPE_INITIAL), new State('b')));
        $this->assertCount(0, $context->getPersistenceAdapter()->getEntityIds($machine));
        $state = $sm->getInitialState()->getName();
        $this->assertEquals('a', $state);
        $this->assertTrue($context->add($state));
        // var_dump( Memory::get());
        $this->assertCount(1, $context->getPersistenceAdapter()->getEntityIds($machine));
    }

    /**
     * test the factory method with all parameters provided
     * implicitely tests the constructor
     */
    public function testContext()
    {
        $entity_id = "id1";
        $machine = "test machine";
        $identifier = new Identifier($entity_id, $machine);
        $builder = new EntityBuilder();
        $io = new Memory();
        
        // all parameters
        $o = Context::get($identifier, $builder, $io);
        $this->assertEquals($entity_id, $o->getEntityId());
        $this->assertEquals($machine, $o->getMachine());
        $this->assertNull($o->getStateMachine());
        $this->assertTrue(is_a($o->getPersistenceAdapter(), 'izzum\statemachine\persistence\Memory'));
        $this->assertTrue(is_a($o->getBuilder(), 'izzum\statemachine\EntityBuilder'));
        $this->assertEquals($identifier, $o->getEntity());
        $this->assertTrue(is_string($o->getEntityId()));
        
        $this->assertTrue(is_string($o->toString()));
        $this->assertContains($entity_id, $o->toString());
        $this->assertContains($machine, $o->toString());
        $this->assertContains('izzum\statemachine\Context', $o->toString());
        
        // even though we have a valid reader, the state machine does not exist.
        $this->assertEquals(State::STATE_UNKNOWN, $o->getState());
        $this->assertTrue($o->setState(State::STATE_NEW), 'added');
        $this->assertFalse($o->setState(State::STATE_NEW), 'already there');
        
        // for coverage.
        $statemachine = new StateMachine($o);
        $this->assertNull($o->setStateMachine($statemachine));
    }
}