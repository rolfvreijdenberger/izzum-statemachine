<?php

namespace Izzum\StateMachine;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use Codeception\Attribute\Group;

/**
 *
 * @author rolf
 *
 */
#[Group('statemachine', 'state')]
class StateTest extends TestCase
{
    #[Test]
    public function shouldWorkAsExpectedAndDoCorrectBiDirectionalAssociation()
    {
        $name = 'a';
        $type = State::TYPE_INITIAL;
        $state = new State($name, $type);
        $this->assertCount(0, $state->getTransitions());
        $sb = new State('b');
        $sc = new State('c');
        $t1 = new Transition($state, $sb);
        $t2 = new Transition($state, $sc);
        $trans = $state->getTransitions();
        $this->assertCount(2, $trans, 'biderectional associtation initiated through transition');
        $this->assertEquals($t1, $trans [0], 'in order transitions were created');
        $this->assertEquals($t2, $trans [1], 'in order transitions were created');
        $this->assertTrue($state->isInitial());
        $this->assertFalse($state->isFinal());
        $this->assertFalse($state->isNormal());
        $this->assertEquals($name, $state->getName());
        $this->assertEquals(State::TYPE_INITIAL, $state->getType());
        $this->assertTrue($state->hasTransition($t1->getName()));
        $this->assertTrue($state->hasTransition($t2->getName()));
        $this->assertFalse($sb->hasTransition($t1->getName()), 'no bidirectional association on incoming transition');
        $this->assertFalse($sb->hasTransition($t2->getName()), 'no bidirectional association on incoming transition');
        $this->assertFalse($sc->hasTransition($t1->getName()), 'no bidirectional association on incoming transition');
        $this->assertFalse($sc->hasTransition($t2->getName()), 'no bidirectional association on incoming transition');

        $this->assertEquals('', $state->getDescription());
        $description = 'test description';
        $state->setDescription($description);
        $this->assertEquals($description, $state->getDescription());

        $this->assertFalse($state->hasTransition('bogus'));

        $this->assertFalse($state->addTransition($t1), 'already present');
    }

    #[Test]
    public function shouldReturnType()
    {
        $name = 'state-izzum';
        $state = new State($name, State::TYPE_INITIAL);
        $this->assertTrue($state->isInitial());
        $this->assertFalse($state->isFinal());
        $this->assertFalse($state->isNormal());
        $this->assertFalse($state->isRegex());

        $state = new State($name, State::TYPE_NORMAL);
        $this->assertFalse($state->isInitial());
        $this->assertFalse($state->isFinal());
        $this->assertTrue($state->isNormal());
        $this->assertFalse($state->isRegex());

        $state = new State($name, State::TYPE_FINAL);
        $this->assertFalse($state->isInitial());
        $this->assertTrue($state->isFinal());
        $this->assertFalse($state->isNormal());
        $this->assertFalse($state->isRegex());
    }

    #[Test]
    public function shouldReturnTransitionForEvent()
    {
        $a = new State('a');
        $b = new State('b');
        $c = new State('c');
        $d = new State('d');
        $tab = new Transition($a, $b);
        $tac = new Transition($a, $c);
        $tbc = new Transition($b, $c);
        $tba = new Transition($b, $a, 'b-a');
        $tbb = new Transition($b, $b, 'event-self'); // self transition
        $tda = new Transition($d, $a, 'possible-to-handle-more-than-one-from-d');
        $tdc = new Transition($d, $c, 'possible-to-handle-more-than-one-from-d');

        $this->assertEquals([
            $tba,
        ], $b->getTransitionsTriggeredByEvent('b-a'));
        $this->assertEquals([
            $tbb,
        ], $b->getTransitionsTriggeredByEvent('event-self'));
        $this->assertEquals([
            $tbc,
        ], $b->getTransitionsTriggeredByEvent('b_to_c'), 'default name is transition name');
        $this->assertEquals([
            $tab,
        ], $a->getTransitionsTriggeredByEvent('a_to_b'), 'default name is transition name');
        $this->assertEquals([
            $tda,
            $tdc,
        ], $d->getTransitionsTriggeredByEvent('possible-to-handle-more-than-one-from-d'));
        $this->assertEquals([], $a->getTransitionsTriggeredByEvent('b-a'));
        $this->assertEquals([], $a->getTransitionsTriggeredByEvent('even-self'));
        $this->assertEquals([], $a->getTransitionsTriggeredByEvent('event-self'));
        $this->assertEquals([], $b->getTransitionsTriggeredByEvent('bogus'));
        $this->assertEquals([], $a->getTransitionsTriggeredByEvent('bogus'));
        $this->assertEquals([], $c->getTransitionsTriggeredByEvent('bogus'));
        $this->assertEquals([], $c->getTransitionsTriggeredByEvent('event-self'));
        $this->assertEquals([], $c->getTransitionsTriggeredByEvent('b-a'));
    }

    #[Test]
    public function shouldExecuteEntryAndExitAction()
    {
        // scenario 1
        $context = new Context(new Identifier('1', 'test'));
        $commandName = 'Izzum\Command\ExceptionCommand';
        $state = new State('a', State::TYPE_INITIAL, $commandName);
        $this->assertEquals($commandName, $state->getEntryCommandName());
        $this->assertEquals('', $state->getExitCommandName());

        try {
            $state->entryAction($context);
            $this->fail('should not come here');
        } catch (\Exception $e) {
            $this->assertEquals(Exception::COMMAND_EXECUTION_FAILURE, $e->getCode());
        }

        // null command
        $state->exitAction($context);

        // scenario 2
        $context = new Context(new Identifier('1', 'test'));
        $commandName = 'Izzum\Command\ExceptionCommand';
        $state = new State('a', State::TYPE_INITIAL, State::COMMAND_EMPTY, $commandName);
        $this->assertEquals($commandName, $state->getExitCommandName());
        $this->assertEquals('', $state->getEntryCommandName());

        // null command
        $state->entryAction($context);

        try {
            $state->exitAction($context);
            $this->fail('should not come here');
        } catch (\Exception $e) {
            $this->assertEquals(Exception::COMMAND_EXECUTION_FAILURE, $e->getCode());
        }
    }

    #[Test]
    public function shoulFailInvalidAction()
    {
        // scenario 1
        $context = new Context(new Identifier('1', 'test'));
        $commandName = 'Izzum\Command\bogus';
        $state = new State('a', State::TYPE_INITIAL, $commandName);
        $this->assertEquals($commandName, $state->getEntryCommandName());
        $this->assertEquals('', $state->getExitCommandName());

        try {
            $state->entryAction($context);
            $this->fail('should not come here');
        } catch (\Exception $e) {
            $this->assertEquals(Exception::COMMAND_CREATION_FAILURE, $e->getCode());
        }

        // null command
        $state->exitAction($context);

        // scenario 2
        $context = new Context(new Identifier('1', 'test'));
        $commandName = 'Izzum\Command\bogus';
        $state = new State('a', State::TYPE_INITIAL, State::COMMAND_EMPTY, $commandName);
        $this->assertEquals($commandName, $state->getExitCommandName());
        $this->assertEquals('', $state->getEntryCommandName());

        // null command
        $state->entryAction($context);

        try {
            $state->exitAction($context);
            $this->fail('should not come here');
        } catch (\Exception $e) {
            $this->assertEquals(Exception::COMMAND_CREATION_FAILURE, $e->getCode());
        }
    }

    #[Test]
    public function shouldExitWithCallable()
    {
        $state = new State('a');
        $context = new Context(new Identifier('123', 'foo-machine'));
        $event = 'foo';
        $callable = function ($entity): void {
            $entity->setEntityId('234');
        };
        $state->setExitCallable($callable);
        $this->assertEquals('123', $context->getEntityId());
        $state->entryAction($context);
        $this->assertEquals('123', $context->getEntityId());
        $state->exitAction($context);
        $this->assertEquals('234', $context->getEntityId());
    }

    #[Test]
    public function shouldBeAbleToSetCallable()
    {
        $context = new Context(new Identifier('123', 'foo-machine'));
        $event = 'foo';
        //increase the id every time the callable is called
        $callable = function ($entity): void {
            $entity->setEntityId(($entity->getEntityId() + 1));
        };

        //scenario 1: use constructor
        $state = new State('a', State::TYPE_NORMAL, null, null, $callable, $callable);
        $this->assertEquals('123', $context->getEntityId());
        $state->entryAction($context);
        $this->assertEquals('124', $context->getEntityId());
        $state->exitAction($context);
        $this->assertEquals('125', $context->getEntityId());

        //scenario 2: use setters
        $state = new State('b', State::TYPE_NORMAL);
        $state->setEntryCallable($callable);
        $state->setExitCallable($callable);
        $this->assertEquals('125', $context->getEntityId());
        $state->entryAction($context);
        $this->assertEquals('126', $context->getEntityId());
        $state->exitAction($context);
        $this->assertEquals('127', $context->getEntityId());
    }

    #[Test]
    public function shouldEnterWithCallable()
    {
        $state = new State('a');
        $context = new Context(new Identifier('123', 'foo-machine'));
        $event = 'foo';
        $callable = function ($entity): void {
            $entity->setEntityId('234');
        };
        $state->setEntryCallable($callable);
        $this->assertEquals('123', $context->getEntityId());
        $state->exitAction($context);
        $this->assertEquals('123', $context->getEntityId());
        $state->entryAction($context);
        $this->assertEquals('234', $context->getEntityId());
    }

    #[Test]
    public function shouldFailEntryAndExitWithNonCallable()
    {
        $state = new State('a');
        $context = new Context(new Identifier('123', 'foo-machine'));
        $event = 'foo';
        $callable = "Foo::BarEntry";
        $state->setEntryCallable($callable);
        $callable = "Foo::BarExit";
        $state->setExitCallable($callable);
        try {
            $state->entryAction($context);
            $this->fail('should not come here');
        } catch (Exception $e) {
            $this->assertEquals(Exception::CALLABLE_FAILURE, $e->getCode());
        }

        try {
            $state->exitAction($context);
            $this->fail('should not come here');
        } catch (Exception $e) {
            $this->assertEquals(Exception::CALLABLE_FAILURE, $e->getCode());
        }
    }

    #[Group('regex')]
    #[Test]
    public function shouldReturnRegexState()
    {
        $name = 'regex:.*';
        $regex = new State($name, State::TYPE_REGEX);
        $this->assertEquals(State::TYPE_REGEX, $regex->getType());
        $regex = new State($name, State::TYPE_FINAL);
        $this->assertEquals(State::TYPE_REGEX, $regex->getType(), 'should be converted to regex in case it is a regex name but the type was incorrectly set');
        $this->assertTrue($regex->isRegex());
        $this->assertTrue($regex->isNormalRegex());
        $this->assertFalse($regex->isNegatedRegex());

        $name = 'not-regex:/go[o,l]d/';
        $regex = new State($name);
        $this->assertEquals(State::TYPE_REGEX, $regex->getType(), 'auto convert to regex type if regex name is given');
        $this->assertTrue($regex->isRegex());
        $this->assertTrue($regex->isNegatedRegex());
        $this->assertFalse($regex->isNormalRegex());
    }

    #[Group('regex')]
    #[Test]
    public function shouldNotReturnRegexState()
    {
        $name = 'rege:.*';
        $regex = new State($name);
        $this->assertFalse($regex->isRegex());
        $this->assertFalse($regex->isNormalRegex());
        $this->assertFalse($regex->isNegatedRegex());
    }


}
