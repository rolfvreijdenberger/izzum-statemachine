<?php
namespace izzum\statemachine;
use izzum\statemachine\Transition;
use izzum\statemachine\Exception;
use izzum\rules\Exception as ExceptionInRulePackage ;

/**
 * @group statemachine
 * @group transition
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
        $transition = new Transition($from, $to, $rule, $command);
        $this->assertEquals($from . '_to_'. $to, $transition->getName());
        $this->assertEquals($from, $transition->getStateFrom());
        $this->assertEquals($to, $transition->getStateTo());
        $this->assertContains($transition->getName(), $transition->toString());
        $command = $transition->getCommand($object);
        $rule = $transition->getRule($object);
        $this->assertTrue(is_a($command, Transition::COMMAND_NULL));
        $this->assertTrue(is_a($rule, Transition::RULE_TRUE));
        
        $this->assertNotNull($transition->toString());
        $this->assertNotNull($transition->__toString());
        
        $this->assertEquals('', $transition->getDescription());
        $description = 'test description';
        $transition->setDescription($description);
        $this->assertEquals($description, $transition->getDescription());
        
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
        $transition = new Transition($from, $to, $rule, $command);
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
    
    /**
     * @test
     */
    public function shouldReturnTrueRuleAndNullCommandWhenRuleEmpty()
    {
        $from = new State('a');
        $to = new State('b');
        $context = Context::get(new Identifier(Identifier::NULL_ENTITY_ID, Identifier::NULL_STATEMACHINE));
        $transition = new Transition($from, $to, '', '');
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
        $this->assertEquals($from . '_to_'. $to, $transition->getName());
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
        $command = 'izzum\command\SimpleCommand';//declared in this file
        $object = Context::get(new Identifier(Identifier::NULL_ENTITY_ID, Identifier::NULL_STATEMACHINE));
        $transition = new Transition($from, $to, $rule, $command);
        $this->assertEquals($from . '_to_'. $to, $transition->getName());
        $this->assertEquals($from, $transition->getStateFrom());
        $this->assertEquals($to, $transition->getStateTo());
        $this->assertContains($transition->getName(), $transition->toString());
        $command = $transition->getCommand($object);
        $rule = $transition->getRule($object);
        $this->assertTrue(is_a($command, 'izzum\command\SimpleCommand'));
        $this->assertTrue(is_a($rule, 'izzum\rules\False'));
        
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
        $transition = new Transition($from, $to, $rule, $command);
        $this->assertEquals($from . '_to_'. $to, $transition->getName());
        $this->assertEquals($from, $transition->getStateFrom());
        $this->assertEquals($to, $transition->getStateTo());
        $this->assertContains($transition->getName(), $transition->toString());
        try {
            $command = $transition->getCommand($object);
            $this->fail('should not come here');
        }catch (Exception $e) {
            $this->assertEquals(Exception::COMMAND_CREATION_FAILURE, $e->getCode());
        }
        try {
            $rule = $transition->getRule($object);
            $this->fail('should not come here');
        }catch (Exception $e) {
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
        $transition = new Transition($from, $to, $rule, $command);
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
        $transition = new Transition($from, $to, $rule, $command);
        $this->assertFalse($transition->can($object));
        $transition->process($object);
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
        $transition = new Transition($from, $to, $rule, $command);
        try {
            $transition->can($object);
            $this->fail('should not come here');
        }catch (Exception $e) {
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
        $transition = new Transition($from, $to, $rule, $command);
        try {
            $transition->process($object);
            $this->fail('should not come here');
        }catch (Exception $e) {
            $this->assertEquals(Exception::COMMAND_EXECUTION_FAILURE, $e->getCode());
        }
        $this->assertTrue($transition->can($object));
    }
    
}

namespace izzum\command;
class SimpleCommand extends \izzum\command\Command {
    protected function _execute() {
        //nothing
    }

}

namespace izzum\command;
class ExceptionOnConstructionCommand extends \izzum\command\Command {
    public function __construct()
    {
        throw new Exception('construction failed');
    }
    protected function _execute() {
        //nothing
    }

}

namespace izzum\rules;
class ExceptionOnConstructionRule extends \izzum\rules\Rule {
    public function __construct()
    {
        throw new Exception('construction failed');
    }
    protected function _applies() {
        return true;
    }

}

