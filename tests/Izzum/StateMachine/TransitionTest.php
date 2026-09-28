<?php

namespace Izzum\StateMachine;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use Codeception\Attribute\Group;
use Izzum\StateMachine\Transition;
use Izzum\StateMachine\Exception;
use Izzum\Rules\Exception as ExceptionInRulePackage;

/**
 *
 * @author rolf
 *
 */
#[Group('statemachine', 'transition')]
class TransitionTest extends TestCase
{
    #[Test]
    public function shouldWorkWhenCallingPublicMethods()
    {
        $from = new State('a');
        $to = new State('b');
        $rule = 'Izzum\Rules\TrueRule';
        $command = 'Izzum\Command\NullCommand';
        $object = new Context(new Identifier(Identifier::NULL_ENTITY_ID, Identifier::NULL_STATEMACHINE));
        $transition = new Transition($from, $to, null, $rule, $command);
        $this->assertEquals($from . '_to_' . $to, $transition->getName());
        $this->assertEquals($from, $transition->getStateFrom());
        $this->assertEquals($to, $transition->getStateTo());
        $this->assertStringContainsString($transition->getName(), $transition->toString());
        $command = $transition->getCommand($object);
        $rule = $transition->getRule($object);
        $this->assertTrue(is_a($command, 'Izzum\Command\Composite'), $command::class);
        $this->assertTrue(is_a($rule, 'Izzum\Rules\AndRule'));


        $this->assertEquals('', $transition->getDescription());
        $description = 'test description';
        $transition->setDescription($description);
        $this->assertEquals($description, $transition->getDescription());

        $this->assertEquals($transition->getName(), $transition->getEvent());
        $event = 'anEvent';
        $this->assertFalse($transition->isTriggeredBy($event));
        $transition->setEvent($event);
        $this->assertEquals($event, $transition->getEvent());
        $this->assertTrue($transition->isTriggeredBy($event));
        $transition->setEvent(null);
        $this->assertEquals($transition->getName(), $transition->getEvent());
        $transition->setEvent('');
        $this->assertEquals($transition->getName(), $transition->getEvent());
    }

    #[Test]
    public function shouldBeAbleToCopy()
    {
        $a = new State('a');
        $b = new State('b');
        $aCopy = new State('a');
        $bCopy = new State('b');
        $event = 'my-event';
        $rule = 'foo-rule';
        $command = 'foo-command';
        $description = 'foobar';
        $gc = function () {
            echo "guard callable";
            return true;
        };
        $tc = function (): void {
            echo "transition callable";
        };
        $t = new Transition($a, $b, $event, $rule, $command, $gc, $tc);
        $t->setDescription($description);

        $copy = $t->getCopy($aCopy, $bCopy);

        $this->assertNotSame($a, $aCopy);
        $this->assertNotSame($b, $bCopy);
        $this->assertNotSame($copy, $t);

        $this->assertEquals($description, $copy->getDescription());
        $this->assertEquals($rule, $copy->getRuleName());
        $this->assertEquals($command, $copy->getCommandName());
        $this->assertEquals($event, $copy->getEvent());
        $this->assertEquals($t->getName(), $copy->getName());
        $this->assertEquals($t->getGuardCallable(), $copy->getGuardCallable());
        $this->assertEquals($gc, $copy->getGuardCallable());
        $this->assertEquals($tc, $copy->getTransitionCallable());
    }

    #[Test]
    public function shouldSetBiDirectionalReferenceOnFromStateOnlyForInitialOrNormalStates()
    {
        $a = new State('a', State::TYPE_INITIAL);
        $b = new State('b', State::TYPE_NORMAL);
        $c = new State('regex:/.*/', State::TYPE_REGEX);
        $d = new State('done', State::TYPE_FINAL);

        $this->assertCount(0, $a->getTransitions());
        $this->assertCount(0, $b->getTransitions());
        $this->assertCount(0, $c->getTransitions());
        $this->assertCount(0, $d->getTransitions());

        $t = new Transition($a, $b);
        $this->assertCount(1, $a->getTransitions());
        $this->assertCount(0, $b->getTransitions());
        $this->assertCount(0, $c->getTransitions());
        $this->assertCount(0, $d->getTransitions());

        $t = new Transition($b, $a);
        $this->assertCount(1, $a->getTransitions());
        $this->assertCount(1, $b->getTransitions());
        $this->assertCount(0, $c->getTransitions());
        $this->assertCount(0, $d->getTransitions());

        //no bi-directional association for 'regex' type in from state
        $t = new Transition($c, $a);
        $this->assertCount(1, $a->getTransitions());
        $this->assertCount(1, $b->getTransitions());
        $this->assertCount(0, $c->getTransitions());
        $this->assertCount(0, $d->getTransitions());

        //no bi-directional association for 'done' type in from state
        $t = new Transition($d, $a);
        $this->assertCount(1, $a->getTransitions());
        $this->assertCount(1, $b->getTransitions());
        $this->assertCount(0, $c->getTransitions());
        $this->assertCount(0, $d->getTransitions());

        //no bi-directional association for 'regex' because it is in the 'to' state
        $t = new Transition($a, $c);
        $this->assertCount(2, $a->getTransitions());
        $this->assertCount(1, $b->getTransitions());
        $this->assertCount(0, $c->getTransitions());
        $this->assertCount(0, $d->getTransitions());

        //no bi-directional association for 'done' because it is in the 'to' state
        $t = new Transition($a, $d);
        $this->assertCount(3, $a->getTransitions());
        $this->assertCount(1, $b->getTransitions());
        $this->assertCount(0, $c->getTransitions());
        $this->assertCount(0, $d->getTransitions());
    }

    #[Test]
    public function shouldHaveBidirectionalAssociation()
    {
        $from = new State('a');
        $to = new State('b');
        $transition = new Transition($from, $to);
        $this->assertTrue($from->hasTransition($transition->getName()));
        $this->assertFalse($to->hasTransition($transition->getName()), 'not on an incoming transition');
    }

    #[Test]
    public function shouldThrowExceptionWhenRuleAndCommandNotCreated()
    {
        $from = new State('a');
        $to = new State('b');
        $context = new Context(new Identifier(Identifier::NULL_ENTITY_ID, Identifier::NULL_STATEMACHINE));
        $rule = 'Izzum\Rules\ExceptionOnConstructionRule';
        $command = 'Izzum\Command\ExceptionOnConstructionCommand';
        $transition = new Transition($from, $to, null, $rule, $command);
        try {
            $transition->getRule($context);
            $this->fail('rule creation throws exception');
        } catch (Exception $e) {
            $this->assertEquals(Exception::RULE_CREATION_FAILURE, $e->getCode());
        }

        try {
            $transition->getCommand($context);
            $this->fail('command creation throws exception');
        } catch (Exception $e) {
            $this->assertEquals(Exception::COMMAND_CREATION_FAILURE, $e->getCode());
        }
    }

    #[Test]
    public function shouldReturnTrueRuleAndNullCommandWhenRuleEmpty()
    {
        $from = new State('a');
        $to = new State('b');
        $context = new Context(new Identifier(Identifier::NULL_ENTITY_ID, Identifier::NULL_STATEMACHINE));
        $transition = new Transition($from, $to, null, '', '');
        $this->assertTrue(is_a($transition->getRule($context), Transition::RULE_TRUE));
        $this->assertTrue(is_a($transition->getCommand($context), Transition::COMMAND_NULL));
    }

    #[Test]
    public function shouldWorkWhenCallingPublicMethodsWithOptionalConstructorParams()
    {
        $from = new State('a');
        $to = new State('b');
        $object = new Context(new Identifier(Identifier::NULL_ENTITY_ID, Identifier::NULL_STATEMACHINE));
        $transition = new Transition($from, $to);
        $this->assertEquals($from . '_to_' . $to, $transition->getName());
        $this->assertEquals($from, $transition->getStateFrom());
        $this->assertEquals($to, $transition->getStateTo());
        $this->assertStringContainsString($transition->getName(), $transition->toString());
        $command = $transition->getCommand($object);
        $rule = $transition->getRule($object);
        $this->assertTrue(is_a($command, Transition::COMMAND_NULL));
        $this->assertTrue(is_a($rule, Transition::RULE_TRUE));
    }

    #[Test]
    public function shouldWorkWhenCallingPublicMethodsWithNonDefaultConstructorValues()
    {
        $from = new State('a');
        $to = new State('b');
        $rule = 'Izzum\Rules\FalseRule';
        $command = 'Izzum\Command\SimpleCommand'; // declared in this file
        $object = new Context(new Identifier(Identifier::NULL_ENTITY_ID, Identifier::NULL_STATEMACHINE));
        $transition = new Transition($from, $to, null, $rule, $command);
        $this->assertEquals($from . '_to_' . $to, $transition->getName());
        $this->assertEquals($from, $transition->getStateFrom());
        $this->assertEquals($to, $transition->getStateTo());
        $this->assertStringContainsString($transition->getName(), $transition->toString());
        $command = $transition->getCommand($object);
        $rule = $transition->getRule($object);
        $this->assertTrue(is_a($command, 'Izzum\Command\Composite'));
        $this->assertStringContainsString('Izzum\Command\SimpleCommand', $command->toString());
        $this->assertTrue(is_a($rule, 'Izzum\Rules\AndRule'));
        $this->assertFalse($rule->applies());
    }

    #[Test]
    public function shouldWorkWithMultipleCommands()
    {
        $from = new State('a');
        $to = new State('b');
        $rule = Transition::RULE_EMPTY;
        $command = 'Izzum\Command\SimpleCommand,Izzum\Command\NullCommand';
        $object = new Context(new Identifier(Identifier::NULL_ENTITY_ID, Identifier::NULL_STATEMACHINE));
        $transition = new Transition($from, $to, null, $rule, $command);
        $command = $transition->getCommand($object);
        $this->assertTrue(is_a($command, 'Izzum\Command\Composite'));
        $this->assertStringContainsString('Izzum\Command\SimpleCommand', $command->toString());
        $this->assertStringContainsString('Izzum\Command\NullCommand', $command->toString());
        $this->assertEquals('Izzum\Command\Composite consisting of: [Izzum\Command\SimpleCommand, Izzum\Command\NullCommand]', $command->toString());
    }

    #[Test]
    public function shouldWorkWhenUsingMultipleRules()
    {
        $from = new State('a');
        $to = new State('b');
        $rule = 'Izzum\Rules\TrueRule,Izzum\Rules\FalseRule';
        $command = 'Izzum\Command\SimpleCommand'; // declared in this file
        $object = new Context(new Identifier(Identifier::NULL_ENTITY_ID, Identifier::NULL_STATEMACHINE));
        $transition = new Transition($from, $to, null, $rule);
        $rule = $transition->getRule($object);
        $this->assertTrue(is_a($rule, 'Izzum\Rules\AndRule'));
        $this->assertFalse($rule->applies());
        $this->assertEquals('((Izzum\Rules\TrueRule and Izzum\Rules\TrueRule) and Izzum\Rules\FalseRule)', $rule->toString());
    }

    #[Test]
    public function shouldExpectExceptionsWhenCallingPublicMethodsWithNonDefaultConstructorValues()
    {
        $from = new State('a');
        $to = new State('b');
        $rule = 'Izzum\Rules\BOGUS';
        $command = 'Izzum\Command\BOGUS';
        $object = new Context(new Identifier(Identifier::NULL_ENTITY_ID, Identifier::NULL_STATEMACHINE));
        $transition = new Transition($from, $to, null, $rule, $command);
        $this->assertEquals($from . '_to_' . $to, $transition->getName());
        $this->assertEquals($from, $transition->getStateFrom());
        $this->assertEquals($to, $transition->getStateTo());
        $this->assertStringContainsString($transition->getName(), $transition->toString());
        try {
            $command = $transition->getCommand($object);
            $this->fail('should not come here');
        } catch (Exception $e) {
            $this->assertEquals(Exception::COMMAND_CREATION_FAILURE, $e->getCode());
        }
        try {
            $rule = $transition->getRule($object);
            $this->fail('should not come here');
        } catch (Exception $e) {
            $this->assertEquals(Exception::RULE_CREATION_FAILURE, $e->getCode());
        }
    }

    #[Test]
    public function shouldBeAllowedAndAbleToProcess()
    {
        $from = new State('a');
        $to = new State('b');
        $rule = 'Izzum\Rules\TrueRule';
        $command = 'Izzum\Command\NullCommand';
        $object = new Context(new Identifier(Identifier::NULL_ENTITY_ID, Identifier::NULL_STATEMACHINE));
        $transition = new Transition($from, $to, null, $rule, $command);
        $this->assertTrue($transition->can($object));
        $transition->process($object);
    }

    #[Test]
    public function shouldNotBeAllowedToButAbleToProcess()
    {
        $from = new State('a');
        $to = new State('b');
        $rule = 'Izzum\Rules\FalseRule';
        $command = 'Izzum\Command\NullCommand';
        $object = new Context(new Identifier(Identifier::NULL_ENTITY_ID, Identifier::NULL_STATEMACHINE));
        $transition = new Transition($from, $to, null, $rule, $command);
        $this->assertFalse($transition->can($object));
        $transition->process($object);
    }

    #[Test]
    public function shouldNotBeAllowedToTransitionByCallable()
    {
        $context = new Context(new Identifier('123', 'foo-machine'));
        $event = 'foo';
        $a = new State('a');
        $b = new State('b');
        $guardCallable = (fn($entity) => false);

        //scenario 1. inject in constructor
        $t = new Transition($a, $b, $event, null, null, $guardCallable);
        $this->assertFalse($t->can($context));
        $t->setGuardCallable(Transition::CALLABLE_NULL);
        $this->assertTrue($t->can($context));

        //scenario 2. do not inject in constructor
        $t = new Transition($a, $b, $event);
        $this->assertTrue($t->can($context));
        $t->setGuardCallable($guardCallable);
        $this->assertFalse($t->can($context));


        //scenario 3. callable does not return a boolean
        $guardCallable = function ($entity): void {};
        $t = new Transition($a, $b, $event, null, null, $guardCallable);
        $this->assertFalse($t->can($context));
    }

    #[Test]
    public function shouldTransitionWithCallable()
    {
        $context = new Context(new Identifier('123', 'foo-machine'));
        $event = 'foo';
        $a = new State('a');
        $b = new State('b');
        $x = 0;
        $transitionCallable = function ($entity): void {
            $entity->setEntityId('234');
        };
        $t = new Transition($a, $b, $event, null, null, null, $transitionCallable);
        $this->assertEquals('123', $context->getEntityId());
        $t->process($context);
        $this->assertEquals('234', $context->getEntityId());
    }

    #[Test]
    public function shouldAcceptMultipleCallableTypes()
    {
        //there are diverse ways to use callables: closures, anonymous function, instance methods
        //static methods.

        //https://php.net/manual/en/functions.anonymous.php
        //https://php.net/manual/en/language.types.callable.php

        $context = new Context(new Identifier('123', 'foo-machine'));
        $event = 'foo';
        $a = new State('a');
        $b = new State('b');


        //scenario 1: Closure without variables from the parent scope
        $transitionCallable = function ($entity): void {
            $entity->setEntityId('234');
        };
        $t = new Transition($a, $b, $event, null, null, null, $transitionCallable);
        $this->assertEquals('123', $context->getEntityId());
        $t->process($context);
        $this->assertEquals('234', $context->getEntityId());


        //scenario 2: Closure with Inheriting variables from the parent scope
        $x = 4;
        $transitionCallable = function ($entity) use (&$x): void {
            $x += 1;
        };
        $t = new Transition($a, $b, $event, null, null, null, $transitionCallable);
        $this->assertEquals(4, $x);
        $t->process($context);
        $this->assertEquals(5, $x);

        //scenario 3: Anonymous function / literal
        $context->getIdentifier()->setEntityId('123');
        $t = new Transition($a, $b, $event, null, null, null, function ($entity): void {
            $entity->setEntityId('234');
        });
        $this->assertEquals('123', $context->getEntityId());
        $t->process($context);
        $this->assertEquals('234', $context->getEntityId());

        //scenario 4: instance method invocation (method as string)
        $helper = new CallableHelper();
        $transitionCallable = $helper->increaseInstanceId(...);
        $t = new Transition($a, $b, $event, null, null, null, $transitionCallable);
        $this->assertEquals(0, $helper->instanceId);
        $t->process($context);
        $this->assertEquals(1, $helper->instanceId);
        $t->process($context);
        $this->assertEquals(2, $helper->instanceId);

        //scenario 5: static method invocation in array (use fully qualified name)
        $helper = new CallableHelper();
        $transitionCallable = ['Izzum\StateMachine\CallableHelper', 'increaseId'];
        $t = new Transition($a, $b, $event, null, null, null, $transitionCallable);
        $this->assertEquals(0, CallableHelper::$id);
        $t->process($context);
        $this->assertEquals(1, CallableHelper::$id);

        //scenario 6: static method invocation in string (use fully qualified name)
        //THIS IS THE WAY TO be able to specify a callable in a configuration file.
        $helper = new CallableHelper();
        $transitionCallable = 'Izzum\StateMachine\CallableHelper::increaseId';
        $t = new Transition($a, $b, $event, null, null, null, $transitionCallable);
        $this->assertEquals(1, CallableHelper::$id);
        $t->process($context);
        $this->assertEquals(2, CallableHelper::$id);

        //scenario 7: wrap an existing method in a closure (this is THE way to reuse an existing method)
        $jo = function ($entity): void {
            $entity->setEntityId(($entity->getEntityId() + 1));
        };
        $callable = function ($context) use ($jo): void {
            $jo($context);
        };
        $context->getIdentifier()->setEntityId('123');
        $t = new Transition($a, $b, $event, null, null, null, $callable);
        $this->assertEquals('123', $context->getEntityId());
        $t->process($context);
        $this->assertEquals('124', $context->getEntityId());

    }

    /**
     * https://github.com/rolfvreijdenberger/izzum-statemachine/issues/7
     */
    #[Test]
    public function shouldFailWithBadCallableDefinitions()
    {

        $context = new Context(new Identifier('123', 'foo-machine'));
        $event = 'foo';
        $a = new State('a');
        $b = new State('b');


        //scenario 5: static method invocation in array (use fully qualified name)
        $transitionCallable = ['Foo', 'Bar'];
        $t = new Transition($a, $b, $event, null, null, null, $transitionCallable);
        try {
            $t->process($context);
            $this->fail('should not come here');
        } catch (Exception $e) {
            $this->assertEquals(Exception::COMMAND_EXECUTION_FAILURE, $e->getCode());
        }

        //scenario 6: static method invocation in string (use fully qualified name)
        //THIS IS THE WAY TO be able to specify a callable in a configuration file.
        $transitionCallable = 'Foo::Bar';
        $t = new Transition($a, $b, $event, null, null, null, $transitionCallable);
        try {
            $t->process($context);
            $this->fail('should not come here');
        } catch (Exception $e) {
            $this->assertEquals(Exception::COMMAND_EXECUTION_FAILURE, $e->getCode());
        }

        //guard should fail
        $guardCallable = 'Foo::Bar';
        $t = new Transition($a, $b, $event, null, null, $guardCallable, $transitionCallable);
        try {
            $t->can($context);
            $this->fail('should not come here');
        } catch (Exception $e) {
            $this->assertEquals(Exception::RULE_APPLY_FAILURE, $e->getCode());
        }


    }

    #[Test]
    public function shouldThrowExceptionFromAppliedRule()
    {
        $from = new State('a');
        $to = new State('b');
        $rule = 'Izzum\Rules\ExceptionRule';
        $command = 'Izzum\Command\NullCommand';
        $object = new Context(new Identifier(Identifier::NULL_ENTITY_ID, Identifier::NULL_STATEMACHINE));
        $transition = new Transition($from, $to, null, $rule, $command);
        try {
            $transition->can($object);
            $this->fail('should not come here');
        } catch (Exception $e) {
            $this->assertEquals(Exception::RULE_APPLY_FAILURE, $e->getCode());
            $this->assertEquals(ExceptionInRulePackage::CODE_GENERAL, $e->getPrevious()->getCode());
        }
        $transition->process($object);
    }

    #[Test]
    public function shouldThrowExceptionFromAppliedCommand()
    {
        $from = new State('a');
        $to = new State('b');
        $rule = 'Izzum\Rules\TrueRule';
        $command = 'Izzum\Command\ExceptionCommand';
        $object = new Context(new Identifier(Identifier::NULL_ENTITY_ID, Identifier::NULL_STATEMACHINE));
        $transition = new Transition($from, $to, null, $rule, $command);
        try {
            $transition->process($object);
            $this->fail('should not come here');
        } catch (Exception $e) {
            $this->assertEquals(Exception::COMMAND_EXECUTION_FAILURE, $e->getCode());
        }
        $this->assertTrue($transition->can($object));
    }
}
class CallableHelper
{
    //used to check that callables using static/instance method invocation work
    public static $id = 0;
    public $instanceId = 0;
    public static function increaseId($entity)
    {
        self::$id++;
    }

    public function increaseInstanceId($entity)
    {
        $this->instanceId++;
    }
}

namespace Izzum\Command;

class SimpleCommand extends \Izzum\Command\Command
{
    protected function _execute(): void
    {
        // nothing
    }
}

namespace Izzum\Command;

class ExceptionOnConstructionCommand extends \Izzum\Command\Command
{
    public function __construct()
    {
        throw new Exception('construction failed');
    }

    protected function _execute(): void
    {
        // nothing
    }
}

namespace Izzum\Rules;

class ExceptionOnConstructionRule extends \Izzum\Rules\Rule
{
    public function __construct()
    {
        throw new Exception('construction failed');
    }

    protected function _applies()
    {
        return true;
    }
}
