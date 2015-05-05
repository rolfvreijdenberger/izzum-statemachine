<?php
namespace izzum\statemachine;
use izzum\statemachine\Transition;
use izzum\statemachine\Exception;
use izzum\statemachine\Context;
use izzum\statemachine\persistence\Memory;
use izzum\statemachine\utils\PlantUml;
use izzum\statemachine\loader\LoaderArray;

/**
 * @group statemachine
 * @author rolf
 *
 */
class StateMachineTest extends \PHPUnit_Framework_TestCase {
    
    public function setUp(){
        parent::setUp();
        //clear in memory storage
        Memory::clear();
    }
    
    /**
     * @test
     */
    public function shouldWorkWhenInitialized()
    {
        $object = Context::get(new Identifier(Identifier::NULL_ENTITY_ID, Identifier::NULL_STATEMACHINE));
        $machine = new StateMachine($object);
        $this->assertEquals($machine->getMachine(), Identifier::NULL_STATEMACHINE);
        $this->assertEquals($machine->getContext(), $object);
        $this->assertCount(0, $machine->getStates());
        $this->assertCount(0, $machine->getTransitions());
        try {
            $machine->getCurrentState();
            $this->fail('should not come here');
        } catch (Exception $e) {
            $this->assertEquals(Exception::SM_NO_CURRENT_STATE_FOUND, $e->getCode());
        }
        
        
       
    
    }
    
    /**
     * @test
     */
    public function shouldBeAbleToUseApply()
    {
        $object = Context::get(new Identifier(Identifier::NULL_ENTITY_ID, Identifier::NULL_STATEMACHINE));
        $machine = new StateMachine($object);
        $this->addTransitionsToMachine($machine);
        
        
        $machine->transition('new_to_a');
        $this->assertEquals('a', $machine->getCurrentState(), 'this actually works because of __toString');
    
        try {
            $machine->transition('new_to_a');
            $this->fail('should not come here');
        } catch (Exception $e) {
            $this->assertEquals(Exception::SM_TRANSITION_NOT_ALLOWED, $e->getCode());
        }
        
        $machine->transition('a_to_b');
        $this->assertEquals('b', $machine->getCurrentState(), 'this actually works because of __toString');
    
        $machine->transition('b_to_c');
        $this->assertEquals('c', $machine->getCurrentState(), 'this actually works because of __toString');
    }
    
    /**
     * @test
     */
    public function shouldBeAbleToUseAddTransitions()
    {
        $object = Context::get(new Identifier(Identifier::NULL_ENTITY_ID, Identifier::NULL_STATEMACHINE));
        $machine = new StateMachine($object);
        
        $s_new = new State(State::STATE_NEW, State::TYPE_INITIAL);
        $s_a = new State('a', State::TYPE_NORMAL);
        $s_b = new State('b', State::TYPE_NORMAL);
        $s_c = new State('c', State::TYPE_NORMAL);
        $s_d = new State('d', State::TYPE_NORMAL);
        $s_done = new State(State::STATE_DONE, State::TYPE_FINAL);
        
        $t_new_to_a = new Transition($s_new, $s_a, Transition::RULE_TRUE, Transition::COMMAND_NULL);
        $t_a_to_b = new Transition($s_a, $s_b, Transition::RULE_TRUE, Transition::COMMAND_NULL);
        $t_b_to_c = new Transition($s_b, $s_c, Transition::RULE_TRUE, Transition::COMMAND_NULL);
        $t_b_to_d = new Transition($s_b, $s_d, Transition::RULE_FALSE, Transition::COMMAND_NULL);
        $t_c_to_d = new Transition($s_c, $s_d, Transition::RULE_TRUE, Transition::COMMAND_NULL);
        $t_d_done = new Transition($s_d, $s_done, Transition::RULE_TRUE, Transition::COMMAND_NULL);
        
        $machine->addTransition($t_new_to_a);
        $this->assertCount(2, $machine->getStates());
        $this->assertCount(1, $machine->getTransitions());
        
        $machine->addTransition($t_a_to_b);
        $this->assertCount(3, $machine->getStates());
        $this->assertCount(2, $machine->getTransitions());
        
        
        $machine->addTransition($t_b_to_c);
        $this->assertCount(4, $machine->getStates());
        $this->assertCount(3, $machine->getTransitions());
        
        $machine->addTransition($t_b_to_d);
        $this->assertCount(5, $machine->getStates());
        $this->assertCount(4, $machine->getTransitions());
        
        $machine->addTransition($t_c_to_d);
        $this->assertCount(5, $machine->getStates());
        $this->assertCount(5, $machine->getTransitions());
        
        $machine->addTransition($t_d_done);
        $this->assertCount(6, $machine->getStates());
        $this->assertCount(6, $machine->getTransitions());
        
        //same, should not be added again
        $machine->addTransition($t_d_done);
        $this->assertCount(6, $machine->getStates());
        $this->assertCount(6, $machine->getTransitions());
        
        
        //same, should not be added again
        $machine->addTransition($t_b_to_c);
        $this->assertCount(6, $machine->getStates());
        $this->assertCount(6, $machine->getTransitions());
        
    }
    
    public function testGetInitialState()
    {
        $object = Context::get(new Identifier(Identifier::NULL_ENTITY_ID, Identifier::NULL_STATEMACHINE));
        $machine = new StateMachine($object);
        try {
        $machine->getInitialState();
        $this->fail('should not come here');
        } catch(Exception $e) {
            $this->assertEquals(Exception::SM_NO_INITIAL_STATE_FOUND, $e->getCode());
        }
        
        $this->addTransitionsToMachine($machine);
        $this->assertEquals(State::STATE_NEW, $machine->getInitialState()->getName());
    }

    
    public function testReferencesOnStatesAndTransitions()
    {
        $object = Context::get(new Identifier(Identifier::NULL_ENTITY_ID, Identifier::NULL_STATEMACHINE));
        $machine = new StateMachine($object);
        $this->addTransitionsToMachine($machine);
        $transitions = $machine->getTransitions();
        $states = $machine->getStates();
        $this->assertCount(6, $states);
        $this->assertCount(6, $transitions);
        $transition_1 = $machine->getTransition('a_to_b');
        $sa = $transition_1->getStateFrom();
        $this->assertEquals('a', $sa->getName());
        $sb = $transition_1->getStateTo();
        $this->assertEquals('b', $sb->getName());
        $this->assertEquals('a_to_b', $transition_1->getName());
        
        $transition_2 = $machine->getTransition('b_to_c');
        $sbb = $transition_2->getStateFrom();
        $this->assertEquals('b', $sbb->getName());
        $sc = $transition_2->getStateTo();
        $this->assertEquals('c', $sc->getName());
        $this->assertEquals('b_to_c', $transition_2->getName());
        
        $this->assertEquals($sb, $sbb, 'referencing the same object');
        $this->assertNotEquals($sa, $sb);
        $this->assertNotEquals($sb, $sc);
        
        $sat = $sa->getTransitions();
        $sat0 = $sat[0];
        $this->assertEquals($sat0, $transition_1, 'bidirectional association');
        $sbbt = $sbb->getTransitions();
        $sbbt0 = $sbbt[0];
        $this->assertEquals($sbbt0, $transition_2, 'bidirectional association');
        $sbbt1 = $sbbt[1];
        $this->assertNotEquals($sbbt1, $transition_2, 'no association, transition not on state');
        
    }
    
    protected function addTransitionsToMachine(StateMachine $machine) {
        
        $s_new = new State(State::STATE_NEW, State::TYPE_INITIAL);
        $s_a = new State('a', State::TYPE_NORMAL);
        $s_b = new State('b', State::TYPE_NORMAL);
        $s_c = new State('c', State::TYPE_NORMAL);
        $s_d = new State('d', State::TYPE_NORMAL);
        $s_done = new State(State::STATE_DONE, State::TYPE_FINAL);
        
        $t_new_to_a = new Transition($s_new, $s_a, Transition::RULE_TRUE, Transition::COMMAND_NULL);
        $t_a_to_b = new Transition($s_a, $s_b, Transition::RULE_TRUE, Transition::COMMAND_NULL);
        $t_b_to_c = new Transition($s_b, $s_c, Transition::RULE_TRUE, Transition::COMMAND_NULL);
        $t_b_to_d = new Transition($s_b, $s_d, Transition::RULE_FALSE, Transition::COMMAND_NULL);
        $t_c_to_d = new Transition($s_c, $s_d, Transition::RULE_TRUE, Transition::COMMAND_NULL);
        $t_d_done = new Transition($s_d, $s_done, Transition::RULE_TRUE, Transition::COMMAND_NULL);
        
        $machine->addTransition($t_new_to_a);
        $machine->addTransition($t_a_to_b);
        $machine->addTransition($t_b_to_c);
        $machine->addTransition($t_b_to_d);
        $machine->addTransition($t_c_to_d);
        $machine->addTransition($t_d_done);
        

    }
    
    
    public function testMultipleTransitionsFromOneStateAndAlsoWithEvents()
    {
        
        $context = new Context(new Identifier(54321, Identifier::NULL_STATEMACHINE));
        $machine = new StateMachine($context);
        $context->add();
        
        $s_new = new State(State::STATE_NEW, State::TYPE_INITIAL);
        $s_a = new State('a', State::TYPE_NORMAL);
        $s_b = new State('b', State::TYPE_NORMAL);
        $s_c = new State('c', State::TYPE_NORMAL);
        $s_d = new State('d', State::TYPE_NORMAL);
        $s_done = new State(State::STATE_DONE, State::TYPE_FINAL);
        
        $t_new_to_a = new Transition($s_new, $s_a, Transition::RULE_FALSE, Transition::COMMAND_NULL);
        $t_new_to_done = new Transition($s_new, $s_done, Transition::RULE_FALSE, Transition::COMMAND_NULL);
        $t_new_to_b = new Transition($s_new, $s_b, Transition::RULE_FALSE, Transition::COMMAND_NULL);
        $t_new_to_c = new Transition($s_new, $s_c, Transition::RULE_TRUE, Transition::COMMAND_NULL);
        $t_new_to_d = new Transition($s_new, $s_d, Transition::RULE_FALSE, Transition::COMMAND_NULL);
        //set an event name. since this transition is not allowed, the event based transition should fail later.
        $t_new_to_d->setEvent('event-foo-bar');
        $t_d_to_done = new Transition($s_d, $s_done, Transition::RULE_TRUE, Transition::COMMAND_NULL);
        $t_c_to_done = new Transition($s_c, $s_done, Transition::RULE_FALSE, Transition::COMMAND_NULL);
        $t_c_to_d = new Transition($s_c, $s_d, Transition::RULE_TRUE, Transition::COMMAND_NULL);
 
        $machine->addTransition($t_new_to_a);
        $machine->addTransition($t_new_to_done);
        $machine->addTransition($t_new_to_b);
        $machine->addTransition($t_new_to_c);
        $machine->addTransition($t_new_to_d);
        $machine->addTransition($t_d_to_done);
        $machine->addTransition($t_c_to_done);
        $machine->addTransition($t_c_to_d);
        
        //path should be: new->c->d->done;
        $this->assertCount(8, $machine->getTransitions());
        $this->assertCount(6, $machine->getStates());
        $this->assertEquals($machine->getCurrentState(), State::STATE_NEW);
        $this->assertTrue($machine->can('new_to_c'));
        //check event returns false
        $this->assertFalse($machine->handle('event-new-to-c'));
        $this->assertFalse($machine->handle('event-c-to-d'));
        $this->assertFalse($machine->handle('bogus'));
        
        $this->assertFalse($machine->can('new_to_a'));
        $this->assertFalse($machine->can('new_to_done'));
        $this->assertFalse($machine->can('new_to_b'));
        $this->assertFalse($machine->can('new_to_d'));
        try {
        	$machine->handle('event-foo-bar');//new to d dissallowed by rule
        	$this->fail('event exists but dissalowed by rule');
        }catch (\Exception $e)
        {
        	$this->assertEquals(Exception::SM_TRANSITION_NOT_ALLOWED, $e->getCode());
        }
        
        
        $this->assertTrue($machine->run());
        $this->assertEquals($machine->getCurrentState(), 'c');
        $t_c_to_d->setEvent('event-c-to-d');
        //do event based transition
        $this->assertTrue($machine->handle('event-c-to-d'));
        $this->assertEquals($machine->getCurrentState(), 'd');
        $this->assertTrue($machine->run());
        $this->assertEquals($machine->getCurrentState(), 'done');
        
        $this->assertFalse($machine->run(), 'cannot run anymore');
        $this->assertFalse($machine->can('new_to_c'));
        $this->assertFalse($machine->can('new_to_a'));
        $this->assertFalse($machine->can('new_to_done'));
        $this->assertFalse($machine->can('new_to_b'));
        $this->assertFalse($machine->can('new_to_d'));
        $this->assertFalse($machine->can('non_to_existent'));
        $this->assertFalse($machine->can('done_to_a'));
        $this->assertFalse($machine->can('new_to_d'));
        $this->assertFalse($machine->can('c_to_done'));
        $this->assertFalse($machine->can('d_to_done'));
        $this->assertFalse($machine->can('c_to_d'));
        
        
       
        
    }
    
    
    /**
     * @test
     */
    public function shouldBeAbleToSwitchContext()
    {
        
        $context_1 = new Context(new Identifier(1, Identifier::NULL_STATEMACHINE));
        $machine = new StateMachine($context_1);
        $context_1->add();
        $this->assertEquals($context_1, $machine->getContext());
        
        try {
            $this->assertEquals($machine->getCurrentState()->getName(), State::STATE_NEW);
            $this->fail('current state not found, no transitions on machine');
        } catch (Exception $ex) {
            $this->assertEquals(Exception::SM_NO_CURRENT_STATE_FOUND, $ex->getCode());
        }
        
        $this->addTransitionsToMachine($machine);
        $this->assertEquals($machine->getCurrentState()->getName(), State::STATE_NEW, 'still the same');
        $this->assertTrue($machine->getCurrentState()->isInitial());
        
        //run to the end
        $total = $machine->runToCompletion();
        $this->assertEquals(5, $total);
        $this->assertEquals($machine->getCurrentState()->getName(), State::STATE_DONE);
        $this->assertTrue($machine->getCurrentState()->isFinal());
        
        //new context object, reuse statemachine
        $context_2 = new Context(new Identifier(123, Identifier::NULL_STATEMACHINE));
        $context_2->add();
        $machine->changeContext($context_2);
        $this->assertEquals($machine->getCurrentState()->getName(), State::STATE_NEW);
        $this->assertTrue($machine->getCurrentState()->isInitial());
        $this->assertEquals($context_2, $machine->getContext());
        $total = $machine->runToCompletion();
        $this->assertEquals(5, $total);
        $this->assertEquals($machine->getCurrentState()->getName(), State::STATE_DONE);
        $this->assertTrue($machine->getCurrentState()->isFinal());
        
        
        
        //switch to different machine for context
        try {
            $context_3 = new Context(new Identifier(123, 'different machine'));
            $context_3->add();
            $machine->changeContext($context_3);
            $this->fail("cannot switch context with different machine");
        } catch (Exception $ex) {
           $this->assertEquals(Exception::SM_CONTEXT_DIFFERENT_MACHINE, $ex->getCode());
        }
        
        
        
        
    }
    
    public function testGettersAndCounts()
    {
        $context = new Context(new Identifier(54321, Identifier::NULL_STATEMACHINE));
        $machine = new StateMachine($context);
        $this->addTransitionsToMachine($machine);
        $this->assertCount(6, $machine->getTransitions());
        $this->assertCount(6, $machine->getStates());
        $a = $machine->getState('a');
        $b = $machine->getState('b');
        $c = $machine->getState('c');
        $d = $machine->getState('d');
        $new = $machine->getState('new');
        $done = $machine->getState('done');
        
        $this->assertEquals($machine->getTransition('new_to_a')->getStateFrom(), $new);
        $this->assertEquals($machine->getTransition('new_to_a')->getStateTo(), $a);
        
        $this->assertEquals($machine->getTransition('a_to_b')->getStateFrom(), $a);
        $this->assertEquals($machine->getTransition('a_to_b')->getStateTo(), $b);
        
        $this->assertEquals($machine->getTransition('b_to_c')->getStateFrom(), $b);
        $this->assertEquals($machine->getTransition('b_to_c')->getStateTo(), $c);
        
        $this->assertEquals($machine->getTransition('b_to_d')->getStateFrom(), $b);
        $this->assertEquals($machine->getTransition('b_to_d')->getStateTo(), $d);
        
        $this->assertEquals($machine->getTransition('c_to_d')->getStateFrom(), $c);
        $this->assertEquals($machine->getTransition('c_to_d')->getStateTo(), $d);
        
        $this->assertEquals($machine->getTransition('d_to_done')->getStateFrom(), $d);
        $this->assertEquals($machine->getTransition('d_to_done')->getStateTo(), $done);
        
        $this->assertCount(1, $machine->getState('new')->getTransitions());
        $this->assertCount(1, $machine->getState('a')->getTransitions());
        $this->assertCount(2, $machine->getState('b')->getTransitions());
        $this->assertCount(1, $machine->getState('c')->getTransitions());
        $this->assertCount(1, $machine->getState('d')->getTransitions());
        $this->assertCount(0, $machine->getState('done')->getTransitions());
        
        $this->assertEquals($context, $machine->getContext());
        $this->assertEquals($context->getMachine(), $machine->getMachine());
        
        $this->assertNull($machine->getState('nonexistent'));
        $this->assertNull($machine->getTransition('nonexistent'));
        $this->assertNotNull($machine->toString());
    }
    
    /**
     * @test
     */
    public function shouldBeAbleToUseRunAndCanAndTestStateTypes()
    {
        $object = Context::get(new Identifier(Identifier::NULL_ENTITY_ID, Identifier::NULL_STATEMACHINE));
        $machine = new StateMachine($object);
        $this->addTransitionsToMachine($machine);
        
        
        $this->assertTrue($machine->getCurrentState()->isInitial());
        $this->assertFalse($machine->can('a_to_b'), 'current transitions');
        $this->assertFalse($machine->can('new_to_done'), 'invalid transition');
        $this->assertFalse($machine->can('b_to_d'), 'false rule');
        $this->assertFalse($machine->can('b_to_c'), 'not the current state');
        
        //new to a
        $machine->run();
        $this->assertEquals('a', $machine->getCurrentState(), ' check by name actually works because of __toString');
        $this->assertTrue($machine->getCurrentState()->isNormal());
        
        
        $machine->run();
        $this->assertEquals('b', $machine->getCurrentState());
        $this->assertTrue($machine->getCurrentState()->isNormal());
        
        $this->assertFalse($machine->can('b_to_d'), 'false rule');
        $this->assertTrue($machine->can('b_to_c'), 'next transition');
        
        $machine->run();
        $this->assertEquals('c', $machine->getCurrentState());
        $this->assertTrue($machine->getCurrentState()->isNormal());
        
        $machine->run();
        $this->assertEquals('d', $machine->getCurrentState());
        $this->assertTrue($machine->getCurrentState()->isNormal());
        
        $machine->run();
        $this->assertEquals('done', $machine->getCurrentState());
        $this->assertTrue($machine->getCurrentState()->isFinal());
    }
    
        /**
     * @test
     */
    public function shouldThrowExceptionFromRuleOrCommand(){
        $context = new Context(new Identifier(54321, Identifier::NULL_STATEMACHINE));
        $machine = new StateMachine($context);
        $context->add();
        
        $s_new = new State(State::STATE_NEW, State::TYPE_INITIAL);
        $s_a = new State('a', State::TYPE_NORMAL);

        $t_new_to_a = new Transition($s_new, $s_a, 'izzum\rules\ExceptionRule', Transition::COMMAND_NULL);
        $machine->addTransition($t_new_to_a);
        
        try {
            $machine->run();
            $this->fail('will throw an error');
        } catch (Exception $e) {
            $this->assertEquals(Exception::RULE_APPLY_FAILURE, $e->getCode());
        }
        
        try {
            $machine->runToCompletion();
            $this->fail('will throw an error');
        } catch (Exception $e) {
            $this->assertEquals(Exception::RULE_APPLY_FAILURE, $e->getCode());
        }
        
        try {
            $machine->transition('new_to_a');
            $this->fail('will throw an error');
        } catch (Exception $e) {
            $this->assertEquals(Exception::RULE_APPLY_FAILURE, $e->getCode());
        }
      
    }
    
    /**
     * @test
     * @group not-on-production
     * @group plantuml
     * 
     */
    public function shouldCreatePlantUmlStateDiagram()
    {
        $machine = 'order-flow';
        $id = 123;
        $context = new Context(new Identifier($id, $machine));
        $machine = new StateMachine($context);
        $s_new = new State(State::STATE_NEW, State::TYPE_INITIAL);
        $s_a = new State('order-confirmation', State::TYPE_NORMAL);
        $s_b = new State('technical-delivery', State::TYPE_NORMAL);
        $s_c = new State('contract-creation', State::TYPE_NORMAL);
        $s_d = new State('services-activation', State::TYPE_NORMAL);
        $s_done = new State(State::STATE_DONE, State::TYPE_FINAL);
        
        $t_new_to_a = new Transition($s_new, $s_a, Transition::RULE_TRUE, 'izzum\command\ValidateOrder');
        $t_a_to_b = new Transition($s_a, $s_b, 'izzum\rules\IsReadyForDelivery', 'izzum\command\SendConfirmation');
        $t_b_to_c = new Transition($s_b, $s_c, Transition::RULE_TRUE, 'izzum\command\TechnicalDelivery');
        $t_b_to_d = new Transition($s_b, $s_d, Transition::RULE_FALSE, Transition::COMMAND_NULL);
        $t_c_to_d = new Transition($s_c, $s_d, 'izzum\rules\ReadyForContract', 'izzum\command\CreateContract');
        $t_d_done = new Transition($s_d, $s_done, Transition::RULE_TRUE, 'izzum\command\ActivateServices' );
        
        $machine->addTransition($t_new_to_a);
        $machine->addTransition($t_a_to_b);
        $machine->addTransition($t_b_to_c);
        $machine->addTransition($t_b_to_d);
        $machine->addTransition($t_c_to_d);
        $machine->addTransition($t_d_done);

        $plant = new PlantUml();
        $result =  $plant->createStateDiagram($machine);
        $this->assertPlantUml($result);
    }
    

    protected function doPlant($output = false)
    {
        $machine = 'coffee-machine';
        $id = 123;
        $context = new Context(new Identifier($id, $machine));
        $machine = new StateMachine($context);
        $transitions = array();
        
        $new = new State('new', State::TYPE_INITIAL, State::COMMAND_EMPTY, State::COMMAND_NULL);
        $new->setDescription("the initial state");
        $initialize = new State('initialize', State::TYPE_NORMAL, "izzum\command\InitializeCommand");
        $cup = new State('cup');
        $cup->setDescription("a cup to hold coffee");
        $coffee = new State('coffee');
        $coffee->setDescription("we now have a cup of coffee");
        $sugar = new State('sugar');
        $sugar->setDescription("we have added sugar");
        $milk = new State('milk');
        $milk->setDescription("added milk");
        $spoon = new State('spoon');
        $spoon->setDescription("use a spoon to stir");
        $done = new State('done', State::TYPE_FINAL, "izzum\command\AnEntryCommand");
        
        $ni = new Transition($new, $initialize, Transition::RULE_TRUE, 'izzum\command\Initialize');
        $ni->setDescription("initialize the coffee machine");
        $transitions[] = $ni;
        $transitions[] = new Transition($initialize, $cup, Transition::RULE_TRUE, 'izzum\command\DropCup');
        $transitions[] = new Transition($cup, $coffee, Transition::RULE_TRUE, 'izzum\command\AddCoffee');
        $transitions[] = new Transition($coffee, $sugar, 'izzum\rules\WantsSugar', 'izzum\command\AddSugar');
        $transitions[] = new Transition($sugar, $coffee, Transition::RULE_TRUE, Transition::COMMAND_NULL);
        $transitions[] = new Transition($coffee, $milk, 'izzum\rules\WantsMilk', 'izzum\command\AddMilk');
        $transitions[] = new Transition($milk, $coffee, Transition::RULE_TRUE, Transition::COMMAND_NULL);
        $transitions[] = new Transition($coffee, $spoon, 'izzum\rules\MilkOrSugar', 'izzum\command\AddSpoon');
        $transitions[] = new Transition($coffee, $done, 'izzum\rules\CoffeeTakenOut', 'izzum\command\Cleanup');
        $transitions[] = new Transition($spoon, $done, 'izzum\rules\CoffeeTakenOut', 'izzum\command\CleanUp');

        $loader = new LoaderArray($transitions);
        $loader->load($machine);
        
        $plant = new PlantUml();
        $result = $plant->createStateDiagram($machine);
        $this->assertPlantUml($result);
        
        if($output) {
            echo PHP_EOL;
            echo __METHOD__ . PHP_EOL;
            echo PHP_EOL;
            echo $result;
            echo PHP_EOL;
        }
    }
    
    
    public function testPlantUml() {
        $this->doPlant(false);
    }
    
    public function assertPlantUml($result) {
        $this->assertNotNull($result);
        $this->assertTrue(is_string($result));
        $this->assertContains("@startuml", $result);
        $this->assertContains("@enduml", $result);
        $this->assertContains("new", $result);
        $this->assertContains("rule", $result);
        $this->assertContains("command", $result);
        $this->assertContains("_to_", $result);
    }
}
