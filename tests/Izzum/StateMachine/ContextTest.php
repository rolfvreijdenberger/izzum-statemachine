<?php

namespace Izzum\StateMachine;

use PHPUnit\Framework\TestCase;
use Codeception\Attribute\Group;
use Izzum\StateMachine\Persistence\Memory;

/**
 *
 * @author rolf
 *
 */
#[Group('statemachine', 'Context')]
class ContextTest extends TestCase
{
    /**
     * test the factory method with default parameters only
     * implicitely tests the constructor
     */
    public function testFactoryDefault()
    {
        $entityId = "id123";
        $machine = "test-machine";
        $identifier = new Identifier($entityId, $machine);

        // only mandatory parameters
        $o = new Context($identifier);
        $this->assertStringContainsString($entityId, $o->getId());
        $this->assertStringContainsString($machine, $o->getId());
        $this->assertStringContainsString($entityId, $o->getId(false));
        $this->assertStringContainsString($machine, $o->getId(false));
        $this->assertStringContainsString($entityId, $o->getId(true));
        $this->assertStringContainsString($machine, $o->getId(true));

        $this->assertEquals($entityId, $o->getEntityId());
        $this->assertEquals($machine, $o->getMachine());
        $this->assertNull($o->getStateMachine());
        // defaulting to database readers and writers
        $this->assertTrue(is_a($o->getPersistenceAdapter(), 'Izzum\StateMachine\Persistence\Memory'));
        $this->assertTrue(is_a($o->getBuilder(), 'Izzum\StateMachine\EntityBuilder'));
        $this->assertEquals($o->getIdentifier(), $o->getEntity());

        $this->assertEquals($o->getIdentifier(), $identifier);

        $this->assertStringContainsString($entityId, $o->toString());
        $this->assertStringContainsString($machine, $o->toString());
        $this->assertStringContainsString('Izzum\StateMachine\Context', $o->toString());

        $this->assertEquals(State::STATE_UNKNOWN, $o->getState());
    }

    public function testConversionOfContextIdToString()
    {
        $entityId = 1;
        $machine = 'test';
        $identifier = new Identifier($entityId, $machine);
        $o = new Context($identifier);
        $this->assertEquals($entityId, $o->getEntityId());
        $this->assertEquals("1", $o->getEntityId());
    }

    public function testFull()
    {
        $entityId = "id";
        $machine = "test machine";
        $identifier = new Identifier($entityId, $machine);
        $builder = new EntityBuilder();
        $io = new Memory();

        // all parameters
        $o = new Context($identifier, $builder, $io);
        $this->assertEquals($entityId, $o->getEntityId());
        $this->assertEquals($machine, $o->getMachine());
        $this->assertNull($o->getStateMachine());
        $this->assertTrue(is_a($o->getPersistenceAdapter(), 'Izzum\StateMachine\Persistence\Memory'));
        $this->assertTrue(is_a($o->getBuilder(), 'Izzum\StateMachine\EntityBuilder'));
        $this->assertEquals($o->getIdentifier(), $o->getEntity());

        $this->assertStringContainsString($entityId, $o->toString());
        $this->assertStringContainsString($machine, $o->toString());
        $this->assertStringContainsString('Izzum\StateMachine\Context', $o->toString());

        // even though we have a valid reader, the state machine does not exist.
        $this->assertEquals(State::STATE_UNKNOWN, $o->getState());
        $this->assertTrue($o->setState('lala'));
        $this->assertEquals('lala', $o->getState());

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
        $entityId = "id1";
        $machine = "test machine";
        $identifier = new Identifier($entityId, $machine);
        $builder = new EntityBuilder();
        $io = new Memory();

        // all parameters
        $o = new Context($identifier, $builder, $io);
        $this->assertEquals($entityId, $o->getEntityId());
        $this->assertEquals($machine, $o->getMachine());
        $this->assertNull($o->getStateMachine());
        $this->assertTrue(is_a($o->getPersistenceAdapter(), 'Izzum\StateMachine\Persistence\Memory'));
        $this->assertTrue(is_a($o->getBuilder(), 'Izzum\StateMachine\EntityBuilder'));
        $this->assertEquals($identifier, $o->getEntity());

        $this->assertStringContainsString($entityId, $o->toString());
        $this->assertStringContainsString($machine, $o->toString());
        $this->assertStringContainsString('Izzum\StateMachine\Context', $o->toString());

        // even though we have a valid reader, the state machine does not exist.
        $this->assertEquals(State::STATE_UNKNOWN, $o->getState());
        $this->assertTrue($o->setState(State::STATE_NEW, 'this is an informational message about why we set this state: we set this state to new for a unittest'), 'added');
        $this->assertFalse($o->setState(State::STATE_NEW), 'already there');

        // for coverage.
        $statemachine = new StateMachine($o);
        $this->assertNull($o->setStateMachine($statemachine));
        $this->assertStringContainsString('Context', $o . '', '__toString()');
    }
}
