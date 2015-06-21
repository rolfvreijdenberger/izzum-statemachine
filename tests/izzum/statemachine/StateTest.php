<?php
namespace izzum\statemachine;
use izzum\statemachine\utils\EntityNull;
use izzum\command\ExceptionCommand;
use izzum\command\Command;
use izzum\command\Null;

/**
 * @group statemachine
 * @group state
 * 
 * @author rolf
 *        
 */
class StateTest extends \PHPUnit_Framework_TestCase {

    /**
     * @test
     */
    public function shouldWorkAsExpectedAndDoCorrectBiDirectionalAssociation()
    {
        $name = 'a';
        $type = State::TYPE_INITIAL;
        $state = new State($name, $type);
        $this->assertNotNull($state);
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

    /**
     * @test
     */
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

    /**
     * @test
     */
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
        
        $this->assertEquals(array(
                $tba
        ), $b->getTransitionsTriggeredByEvent('b-a'));
        $this->assertEquals(array(
                $tbb
        ), $b->getTransitionsTriggeredByEvent('event-self'));
        $this->assertEquals(array(
                $tbc
        ), $b->getTransitionsTriggeredByEvent('b_to_c'), 'default name is transition name');
        $this->assertEquals(array(
                $tab
        ), $a->getTransitionsTriggeredByEvent('a_to_b'), 'default name is transition name');
        $this->assertEquals(array(
                $tda,
                $tdc
        ), $d->getTransitionsTriggeredByEvent('possible-to-handle-more-than-one-from-d'));
        $this->assertEquals(array(), $a->getTransitionsTriggeredByEvent('b-a'));
        $this->assertEquals(array(), $a->getTransitionsTriggeredByEvent('even-self'));
        $this->assertEquals(array(), $a->getTransitionsTriggeredByEvent('event-self'));
        $this->assertEquals(array(), $b->getTransitionsTriggeredByEvent('bogus'));
        $this->assertEquals(array(), $a->getTransitionsTriggeredByEvent('bogus'));
        $this->assertEquals(array(), $c->getTransitionsTriggeredByEvent('bogus'));
        $this->assertEquals(array(), $c->getTransitionsTriggeredByEvent('event-self'));
        $this->assertEquals(array(), $c->getTransitionsTriggeredByEvent('b-a'));
    }

    /**
     * @test
     */
    public function shoulExecuteEntryAndExitAction()
    {
        // scenario 1
        $context = new Context(new Identifier('1', 'test'));
        $command_name = 'izzum\command\ExceptionCommand';
        $state = new State('a', State::TYPE_INITIAL, $command_name);
        $this->assertEquals($command_name, $state->getEntryCommandName());
        $this->assertEquals('', $state->getExitCommandName());
        
        try {
            $state->entryAction($context);
            $this->fail('should not come here');
        } catch(\Exception $e) {
            $this->assertEquals(Exception::COMMAND_EXECUTION_FAILURE, $e->getCode());
        }
        
        // null command
        $state->exitAction($context);
        
        // scenario 2
        $context = new Context(new Identifier('1', 'test'));
        $command_name = 'izzum\command\ExceptionCommand';
        $state = new State('a', State::TYPE_INITIAL, State::COMMAND_EMPTY, $command_name);
        $this->assertEquals($command_name, $state->getExitCommandName());
        $this->assertEquals('', $state->getEntryCommandName());
        
        // null command
        $state->entryAction($context);
        
        try {
            $state->exitAction($context);
            $this->fail('should not come here');
        } catch(\Exception $e) {
            $this->assertEquals(Exception::COMMAND_EXECUTION_FAILURE, $e->getCode());
        }
    }

    /**
     * @test
     */
    public function shoulFailInvalidAction()
    {
        // scenario 1
        $context = new Context(new Identifier('1', 'test'));
        $command_name = 'izzum\command\bogus';
        $state = new State('a', State::TYPE_INITIAL, $command_name);
        $this->assertEquals($command_name, $state->getEntryCommandName());
        $this->assertEquals('', $state->getExitCommandName());
        
        try {
            $state->entryAction($context);
            $this->fail('should not come here');
        } catch(\Exception $e) {
            $this->assertEquals(Exception::COMMAND_CREATION_FAILURE, $e->getCode());
        }
        
        // null command
        $state->exitAction($context);
        
        // scenario 2
        $context = new Context(new Identifier('1', 'test'));
        $command_name = 'izzum\command\bogus';
        $state = new State('a', State::TYPE_INITIAL, State::COMMAND_EMPTY, $command_name);
        $this->assertEquals($command_name, $state->getExitCommandName());
        $this->assertEquals('', $state->getEntryCommandName());
        
        // null command
        $state->entryAction($context);
        
        try {
            $state->exitAction($context);
            $this->fail('should not come here');
        } catch(\Exception $e) {
            $this->assertEquals(Exception::COMMAND_CREATION_FAILURE, $e->getCode());
        }
    }

    /**
     * @test
     */
    public function shouldExitWithCallable()
    {
        $state = new State('a');
        $context = new Context(new Identifier('123','foo-machine'));
        $event = 'foo';
        $callable = function($entity) {$entity->setEntityId('234');};
        $state->setExitCallable($callable);
        $this->assertEquals('123', $context->getEntityId());
        $state->entryAction($context);
        $this->assertEquals('123', $context->getEntityId());
        $state->exitAction($context);
        $this->assertEquals('234', $context->getEntityId());
    }
    
    /**
     * @test
     */
    public function shouldBeAbleToSetCallable()
    {
        $context = new Context(new Identifier('123','foo-machine'));
        $event = 'foo';
        //increase the id every time the callable is called
        $callable = function($entity) {$entity->setEntityId(($entity->getEntityId()+1));};
        
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
    
    /**
     * @test
     */
    public function shouldEnterWithCallable()
    {
        $state = new State('a');
        $context = new Context(new Identifier('123','foo-machine'));
        $event = 'foo';
        $callable = function($entity) {$entity->setEntityId('234');};
        $state->setEntryCallable($callable);
        $this->assertEquals('123', $context->getEntityId());
        $state->exitAction($context);
        $this->assertEquals('123', $context->getEntityId());
        $state->entryAction($context);
        $this->assertEquals('234', $context->getEntityId());
    }
    
    /**
     * @test
     * @group regex
     */
    public function shouldReturnRegexState(){
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
    
    /**
     * @test
     * @group regex
     */
    public function shouldNotReturnRegexState(){
        $name = 'rege:.*';
        $regex = new State($name);
        $this->assertFalse($regex->isRegex());
        $this->assertFalse($regex->isNormalRegex());
        $this->assertFalse($regex->isNegatedRegex());
    }
    
    
}

