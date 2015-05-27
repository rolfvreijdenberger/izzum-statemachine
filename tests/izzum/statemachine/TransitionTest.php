<?php
namespace izzum\statemachine;
use izzum\statemachine\Transition;
use izzum\statemachine\Exception;
use izzum\rules\Exception as ExceptionInRulePackage;

/**
 * @group statemachine
 * @group transition
 *
 * @author rolf
 *        
 */
class TransitionTest extends \PHPUnit_Framework_TestCase {

    /**
     * @test
     */
    public function shouldWorkWhenCallingPublicMethods()
    {
        $from = new State('a');
        $to = new State('b');
        $rule = 'izzum\rules\True';
        $command = 'izzum\command\Null';
        $object = Context::get(new Identifier(Identifier::NULL_ENTITY_ID, Identifier::NULL_STATEMACHINE));
        $transition = new Transition($from, $to, null, $rule, $command);
        $this->assertEquals($from . '_to_' . $to, $transition->getName());
        $this->assertEquals($from, $transition->getStateFrom());
        $this->assertEquals($to, $transition->getStateTo());
        $this->assertContains($transition->getName(), $transition->toString());
        $command = $transition->getCommand($object);
        $rule = $transition->getRule($object);
        $this->assertTrue(is_a($command, 'izzum\command\Composite'), get_class($command));
        $this->assertTrue(is_a($rule, 'izzum\rules\AndRule'));
        
        $this->assertNotNull($transition->toString());
        $this->assertNotNull($transition->__toString());
        
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

    /**
     * @test
     */
    public function shouldBeAbleToCopy()
    {
        $a = new State('a');
        $b = new State('b');
        $a_copy = new State('a');
        $b_copy = new State('b');
        $event = 'my-event';
        $rule = 'foo-rule';
        $command = 'foo-command';
        $description = 'foobar';
        $gc = function(){echo "guard callable";return true;};
        $tc = function(){echo "transition callable";};
        $t = new Transition($a, $b, $event, $rule, $command, $gc, $tc);
        $t->setDescription($description);
        
        $copy = $t->getCopy($a_copy, $b_copy);
        
        $this->assertNotSame($a, $a_copy);
        $this->assertNotSame($b, $b_copy);
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

    /**
     * @test
     */
    public function shouldHaveBidirectionalAssociation()
    {
        $from = new State('a');
        $to = new State('b');
        $transition = new Transition($from, $to);
        $this->assertTrue($from->hasTransition($transition->getName()));
        $this->assertFalse($to->hasTransition($transition->getName()), 'not on an incoming transition');
    }

    /**
     * @test
     */
    public function shouldThrowExceptionWhenRuleAndCommandNotCreated()
    {
        $from = new State('a');
        $to = new State('b');
        $context = Context::get(new Identifier(Identifier::NULL_ENTITY_ID, Identifier::NULL_STATEMACHINE));
        $rule = 'izzum\rules\ExceptionOnConstructionRule';
        $command = 'izzum\command\ExceptionOnConstructionCommand';
        $transition = new Transition($from, $to, null, $rule, $command);
        try {
            $transition->getRule($context);
            $this->fail('rule creation throws exception');
        } catch(Exception $e) {
            $this->assertEquals(Exception::RULE_CREATION_FAILURE, $e->getCode());
        }
        
        try {
            $transition->getCommand($context);
            $this->fail('command creation throws exception');
        } catch(Exception $e) {
            $this->assertEquals(Exception::COMMAND_CREATION_FAILURE, $e->getCode());
        }
    }

    /**
     * @test
     */
    public function shouldReturnTrueRuleAndNullCommandWhenRuleEmpty()
    {
        $from = new State('a');
        $to = new State('b');
        $context = Context::get(new Identifier(Identifier::NULL_ENTITY_ID, Identifier::NULL_STATEMACHINE));
        $transition = new Transition($from, $to, null, '', '');
        $this->assertTrue(is_a($transition->getRule($context), Transition::RULE_TRUE));
        $this->assertTrue(is_a($transition->getCommand($context), Transition::COMMAND_NULL));
    }

    /**
     * @test
     */
    public function shouldWorkWhenCallingPublicMethodsWithOptionalConstructorParams()
    {
        $from = new State('a');
        $to = new State('b');
        $object = Context::get(new Identifier(Identifier::NULL_ENTITY_ID, Identifier::NULL_STATEMACHINE));
        $transition = new Transition($from, $to);
        $this->assertEquals($from . '_to_' . $to, $transition->getName());
        $this->assertEquals($from, $transition->getStateFrom());
        $this->assertEquals($to, $transition->getStateTo());
        $this->assertContains($transition->getName(), $transition->toString());
        $command = $transition->getCommand($object);
        $rule = $transition->getRule($object);
        $this->assertTrue(is_a($command, Transition::COMMAND_NULL));
        $this->assertTrue(is_a($rule, Transition::RULE_TRUE));
    }

    /**
     * @test
     */
    public function shouldWorkWhenCallingPublicMethodsWithNonDefaultConstructorValues()
    {
        $from = new State('a');
        $to = new State('b');
        $rule = 'izzum\rules\False';
        $command = 'izzum\command\SimpleCommand'; // declared in this file
        $object = Context::get(new Identifier(Identifier::NULL_ENTITY_ID, Identifier::NULL_STATEMACHINE));
        $transition = new Transition($from, $to, null, $rule, $command);
        $this->assertEquals($from . '_to_' . $to, $transition->getName());
        $this->assertEquals($from, $transition->getStateFrom());
        $this->assertEquals($to, $transition->getStateTo());
        $this->assertContains($transition->getName(), $transition->toString());
        $command = $transition->getCommand($object);
        $rule = $transition->getRule($object);
        $this->assertTrue(is_a($command, 'izzum\command\Composite'));
        $this->assertContains('izzum\command\SimpleCommand', $command->toString());
        $this->assertTrue(is_a($rule, 'izzum\rules\AndRule'));
        $this->assertFalse($rule->applies());
    }

    /**
     * @test
     */
    public function shouldWorkWithMultipleCommands()
    {
        $from = new State('a');
        $to = new State('b');
        $rule = Transition::RULE_EMPTY;
        $command = 'izzum\command\SimpleCommand,izzum\command\Null';
        $object = Context::get(new Identifier(Identifier::NULL_ENTITY_ID, Identifier::NULL_STATEMACHINE));
        $transition = new Transition($from, $to, null, $rule, $command);
        $command = $transition->getCommand($object);
        $this->assertTrue(is_a($command, 'izzum\command\Composite'));
        $this->assertContains('izzum\command\SimpleCommand', $command->toString());
        $this->assertContains('izzum\command\Null', $command->toString());
        $this->assertEquals('izzum\command\Composite consisting of: [izzum\command\SimpleCommand, izzum\command\Null]', $command->toString());
    }

    /**
     * @test
     */
    public function shouldWorkWhenUsingMultipleRules()
    {
        $from = new State('a');
        $to = new State('b');
        $rule = 'izzum\rules\True,izzum\rules\False';
        $command = 'izzum\command\SimpleCommand'; // declared in this file
        $object = Context::get(new Identifier(Identifier::NULL_ENTITY_ID, Identifier::NULL_STATEMACHINE));
        $transition = new Transition($from, $to, null, $rule);
        $rule = $transition->getRule($object);
        $this->assertTrue(is_a($rule, 'izzum\rules\AndRule'));
        $this->assertFalse($rule->applies());
        $this->assertEquals('((izzum\rules\True and izzum\rules\True) and izzum\rules\False)', $rule->toString());
    }

    /**
     * @test
     */
    public function shouldExpectExceptionsWhenCallingPublicMethodsWithNonDefaultConstructorValues()
    {
        $from = new State('a');
        $to = new State('b');
        $rule = 'izzum\rules\BOGUS';
        $command = 'izzum\command\BOGUS';
        $object = Context::get(new Identifier(Identifier::NULL_ENTITY_ID, Identifier::NULL_STATEMACHINE));
        $transition = new Transition($from, $to, null, $rule, $command);
        $this->assertEquals($from . '_to_' . $to, $transition->getName());
        $this->assertEquals($from, $transition->getStateFrom());
        $this->assertEquals($to, $transition->getStateTo());
        $this->assertContains($transition->getName(), $transition->toString());
        try {
            $command = $transition->getCommand($object);
            $this->fail('should not come here');
        } catch(Exception $e) {
            $this->assertEquals(Exception::COMMAND_CREATION_FAILURE, $e->getCode());
        }
        try {
            $rule = $transition->getRule($object);
            $this->fail('should not come here');
        } catch(Exception $e) {
            $this->assertEquals(Exception::RULE_CREATION_FAILURE, $e->getCode());
        }
    }

    /**
     * @test
     */
    public function shouldBeAllowedAndAbleToProcess()
    {
        $from = new State('a');
        $to = new State('b');
        $rule = 'izzum\rules\True';
        $command = 'izzum\command\Null';
        $object = Context::get(new Identifier(Identifier::NULL_ENTITY_ID, Identifier::NULL_STATEMACHINE));
        $transition = new Transition($from, $to, null, $rule, $command);
        $this->assertTrue($transition->can($object));
        $transition->process($object);
    }

    /**
     * @test
     */
    public function shouldNotBeAllowedToButAbleToProcess()
    {
        $from = new State('a');
        $to = new State('b');
        $rule = 'izzum\rules\False';
        $command = 'izzum\command\Null';
        $object = Context::get(new Identifier(Identifier::NULL_ENTITY_ID, Identifier::NULL_STATEMACHINE));
        $transition = new Transition($from, $to, null, $rule, $command);
        $this->assertFalse($transition->can($object));
        $transition->process($object);
    }
    
    /**
     * @test
     */
    public function shouldNotBeAllowedToTransitionByCallable()
    {
        $context = new Context(new Identifier('123','foo-machine'));
        $event = 'foo';
        $a = new State('a');
        $b = new State('b');
        $guard_callable = function($entity, $event) {return false;};
        
        //scenario 1. inject in constructor
        $t = new Transition($a, $b, $event, null, null, $guard_callable);
        $this->assertFalse($t->can($context, $event));
        $t->setGuardCallable(Transition::CALLABLE_NULL);
        $this->assertTrue($t->can($context, $event));
        
        //scenario 2. do not inject in constructor
        $t = new Transition($a, $b, $event);
        $this->assertTrue($t->can($context, $event));
        $t->setGuardCallable($guard_callable);
        $this->assertFalse($t->can($context, $event));
        
        
        //scenario 3. callable does not return a boolean
        $guard_callable = function($entity, $event) {};
        $t = new Transition($a, $b, $event, null, null, $guard_callable);
        $this->assertFalse($t->can($context, $event));
    }
    
    /**
     * @test
     */
    public function shouldTransitionWithCallable()
    {
        $context = new Context(new Identifier('123','foo-machine'));
        $event = 'foo';
        $a = new State('a');
        $b = new State('b');
        $x = 0;
        $transition_callable = function($entity, $event)  {$entity->setEntityId('234');};
        $t = new Transition($a, $b, $event, null, null, null, $transition_callable);
        $this->assertEquals('123', $context->getEntityId());
        $t->process($context, $event);
        $this->assertEquals('234', $context->getEntityId());
    }
    
    /**
     * @test
     */
    public function shouldSetEventOnRuleAndCommand()
    {
        $context = new Context(new Identifier('123','foo-machine'));
        $event = 'block';
        $a = new State('a');
        $b = new State('b');
        $x = 0;
        $t = new Transition($a, $b, $event, 'izzum\rules\AcceptsEventRule', 'izzum\command\AcceptsEventCommand');
        $this->assertTrue($t->can($context));
        $this->assertFalse($t->can($context, $event));
        
        $this->assertNull($t->process($context, $event));
        try {
            $t->process($context);//without the 'block' event, this command throws an exception
            $this->fail('should not come here');
        }catch (\Exception $e) {
            $this->assertEquals(Exception::COMMAND_EXECUTION_FAILURE, $e->getCode());
        }
    }
    
    /**
     * @test
     */
    public function shouldAcceptMultipleCallableTypes()
    {
        //there are diverse ways to use callables: closures, anonymous function, instance methods
        //static methods.
        
        //https://php.net/manual/en/functions.anonymous.php
        //https://php.net/manual/en/language.types.callable.php
        
        $context = new Context(new Identifier('123','foo-machine'));
        $event = 'foo';
        $a = new State('a');
        $b = new State('b');
        
        
        //scenario 1: Closure without variables from the parent scope
        $transition_callable = function($entity, $event)  {$entity->setEntityId('234');};
        $t = new Transition($a, $b, $event, null, null, null, $transition_callable);
        $this->assertEquals('123', $context->getEntityId());
        $t->process($context, $event);
        $this->assertEquals('234', $context->getEntityId());
        
        
        //scenario 2: Closure with Inheriting variables from the parent scope
        $x = 0;
        $transition_callable = function($entity, $event) use (&$x) { $x+=1;};
        $t = new Transition($a, $b, $event, null, null, null, $transition_callable);
        $this->assertEquals(0, $x);
        $t->process($context, $event);
        $this->assertEquals(1, $x);
        
        //scenario 3: Anonymous function / literal
        $context->getIdentifier()->setEntityId('123');
        $t = new Transition($a, $b, $event, null, null, null, function($entity, $event)  {$entity->setEntityId('234');});
        $this->assertEquals('123', $context->getEntityId());
        $t->process($context, $event);
        $this->assertEquals('234', $context->getEntityId());
        
        //scenario 4: instance method invocation (method not as string)
        $helper = new CallableHelper();
        $transition_callable = array($helper, increaseInstanceId);
        $t = new Transition($a, $b, $event, null, null, null, $transition_callable);
        $this->assertEquals(0, $helper->instance_id);
        $t->process($context, $event);
        $this->assertEquals(1, $helper->instance_id);
        $t->process($context, $event);
        $this->assertEquals(2, $helper->instance_id);
        
        //scenario 5: instance method invocation (method as string)
        $helper = new CallableHelper();
        $transition_callable = array($helper, 'increaseInstanceId');
        $t = new Transition($a, $b, $event, null, null, null, $transition_callable);
        $this->assertEquals(0, $helper->instance_id);
        $t->process($context, $event);
        $this->assertEquals(1, $helper->instance_id);
        $t->process($context, $event);
        $this->assertEquals(2, $helper->instance_id);
        
        //scenario 6: static method invocation in array (use fully qualified name)
        $helper = new CallableHelper();
        $transition_callable = array('izzum\statemachine\CallableHelper', 'increaseId');
        $t = new Transition($a, $b, $event, null, null, null, $transition_callable);
        $this->assertEquals(0, CallableHelper::$id);
        $t->process($context, $event);
        $this->assertEquals(1, CallableHelper::$id);
        
        //scenario 7: static method invocation in string (use fully qualified name)
        $helper = new CallableHelper();
        $transition_callable = 'izzum\statemachine\CallableHelper::increaseId';
        $t = new Transition($a, $b, $event, null, null, null, $transition_callable);
        $this->assertEquals(1, CallableHelper::$id);
        $t->process($context, $event);
        $this->assertEquals(2, CallableHelper::$id);
        
        //scenario 8: wrap an existing method in a closure (this is the only way to reuse an existing method)
        function jo($entity, $event) {
            $entity->setEntityId(($entity->getEntityId() +1));
        }
        $callable = function($context, $event) { jo($context, $event); };
        $context->getIdentifier()->setEntityId('123');
        $t = new Transition($a, $b, $event, null, null, null, $callable);
        $this->assertEquals('123', $context->getEntityId());
        $t->process($context, $event);
        $this->assertEquals('124', $context->getEntityId());
        
    }

    /**
     * @test
     */
    public function shouldThrowExceptionFromAppliedRule()
    {
        $from = new State('a');
        $to = new State('b');
        $rule = 'izzum\rules\ExceptionRule';
        $command = 'izzum\command\Null';
        $object = Context::get(new Identifier(Identifier::NULL_ENTITY_ID, Identifier::NULL_STATEMACHINE));
        $transition = new Transition($from, $to, null, $rule, $command);
        try {
            $transition->can($object);
            $this->fail('should not come here');
        } catch(Exception $e) {
            $this->assertEquals(Exception::RULE_APPLY_FAILURE, $e->getCode());
            $this->assertEquals(ExceptionInRulePackage::CODE_GENERAL, $e->getPrevious()->getCode());
        }
        $transition->process($object);
    }

    /**
     * @test
     */
    public function shouldThrowExceptionFromAppliedCommand()
    {
        $from = new State('a');
        $to = new State('b');
        $rule = 'izzum\rules\True';
        $command = 'izzum\command\ExceptionCommand';
        $object = Context::get(new Identifier(Identifier::NULL_ENTITY_ID, Identifier::NULL_STATEMACHINE));
        $transition = new Transition($from, $to, null, $rule, $command);
        try {
            $transition->process($object);
            $this->fail('should not come here');
        } catch(Exception $e) {
            $this->assertEquals(Exception::COMMAND_EXECUTION_FAILURE, $e->getCode());
        }
        $this->assertTrue($transition->can($object));
    }
}
class CallableHelper {
    //used to check that callables using static/instance method invocation work
    public static $id = 0;
    public $instance_id = 0;
    public static function increaseId($entity, $event) {
        self::$id++;
    }
    
    public function increaseInstanceId($entity, $event) {
        $this->instance_id++;
    }
}

namespace izzum\command;

class SimpleCommand extends \izzum\command\Command {

    protected function _execute()
    {
        // nothing
    }
}
namespace izzum\command;

class ExceptionOnConstructionCommand extends \izzum\command\Command {

    public function __construct()
    {
        throw new Exception('construction failed');
    }

    protected function _execute()
    {
        // nothing
    }
}
namespace izzum\rules;

class ExceptionOnConstructionRule extends \izzum\rules\Rule {

    public function __construct()
    {
        throw new Exception('construction failed');
    }

    protected function _applies()
    {
        return true;
    }
}

namespace izzum\rules;

class AcceptsEventRule extends \izzum\rules\Rule {
    private $event;
    protected function _applies()
    {
        return $this->event !== 'block';
    }
    public function setEvent($event) {
        $this->event = $event;
    }
}

namespace izzum\command;

class AcceptsEventCommand extends \izzum\command\Command {
    private $event;
    protected function _execute()
    {
        if($this->event !== 'block') {
            throw new Exception('event is not "block" but ' . $this->event, 111);
        }
    }
    public function setEvent($event) {
        $this->event = $event;
    }
}



