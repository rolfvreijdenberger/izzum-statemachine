<?php
namespace izzum\statemachine;
use izzum\statemachine\Transition;
use izzum\statemachine\Exception;
use izzum\statemachine\Context;
use izzum\statemachine\persistence\Memory;
use izzum\statemachine\utils\PlantUml;
use izzum\statemachine\loader\LoaderArray;
use izzum\statemachine\builder\ModelBuilder;
use izzum\statemachine\utils\Utils;

/**
 * @group statemachine
 * 
 * @author rolf
 *        
 */
class StateMachineTest extends \PHPUnit_Framework_TestCase {

    public function setUp()
    {
        parent::setUp();
        // clear in memory storage
        Memory::clear();
    }

    /**
     * @test
     */
    public function shouldWorkWhenInitialized()
    {
        $object = Context::get(new Identifier(Identifier::NULL_ENTITY_ID, Identifier::NULL_STATEMACHINE));
        $machine = new StateMachine($object);
        $this->assertEquals(Identifier::NULL_STATEMACHINE, $machine->getContext()->getMachine());
        $this->assertEquals($machine->getContext(), $object);
        $this->assertCount(0, $machine->getStates());
        $this->assertCount(0, $machine->getTransitions());
        try {
            $machine->getCurrentState();
            $this->fail('should not come here');
        } catch(Exception $e) {
            $this->assertEquals(Exception::SM_NO_CURRENT_STATE_FOUND, $e->getCode());
            // echo $e->getMessage();
        }
        $this->assertNotNull($machine);
    }

    /**
     * @test
     * @group regex
     */
    public function shouldBeAbleToTransition()
    {
        $object = Context::get(new Identifier(Identifier::NULL_ENTITY_ID, Identifier::NULL_STATEMACHINE));
        $machine = new StateMachine($object);
        $this->addTransitionsToMachine($machine);
        
        $this->assertTrue($machine->canTransition('new_to_a'));
        $this->assertTrue($machine->transition('new_to_a'));
        $this->assertEquals('a', $machine->getCurrentState(), 'this actually works because of __toString');
        
        $this->assertFalse($machine->canTransition('new_to_a'));
        $this->assertFalse($machine->transition('new_to_a'));
        
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
        
        $t_new_to_a = new Transition($s_new, $s_a, null, Transition::RULE_TRUE, Transition::COMMAND_NULL);
        $t_a_to_b = new Transition($s_a, $s_b, null, Transition::RULE_TRUE, Transition::COMMAND_NULL);
        $t_b_to_c = new Transition($s_b, $s_c, null, Transition::RULE_TRUE, Transition::COMMAND_NULL);
        $t_b_to_d = new Transition($s_b, $s_d, null, Transition::RULE_FALSE, Transition::COMMAND_NULL);
        $t_c_to_d = new Transition($s_c, $s_d, null, Transition::RULE_TRUE, Transition::COMMAND_NULL);
        $t_d_done = new Transition($s_d, $s_done, null, Transition::RULE_TRUE, Transition::COMMAND_NULL);
        
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
        
        // same, should not be added again
        $machine->addTransition($t_d_done);
        $this->assertCount(6, $machine->getStates());
        $this->assertCount(6, $machine->getTransitions());
        
        // same, should not be added again
        $machine->addTransition($t_b_to_c);
        $this->assertCount(6, $machine->getStates());
        $this->assertCount(6, $machine->getTransitions());
    }

    public function testGetInitialState()
    {
        $object = Context::get(new Identifier(Identifier::NULL_ENTITY_ID, Identifier::NULL_STATEMACHINE));
        $machine = new StateMachine($object);
        $this->assertNull($machine->getInitialState(true), 'try to get initial state and expect null if not there');
        try {
            // try to get initial state and expect exception if not there
            $machine->getInitialState();
            $this->fail("should not come here");
        } catch(Exception $e) {
            $this->assertEquals(Exception::SM_NO_INITIAL_STATE_FOUND, $e->getCode());
        }
        
        $this->addTransitionsToMachine($machine);
        $this->assertEquals(State::STATE_NEW, $machine->getInitialState()->getName());
    }

    /**
     * @group regex
     * @test
     */
    public function shouldAddRegexFromState()
    {
        $object = Context::get(new Identifier(Identifier::NULL_ENTITY_ID, Identifier::NULL_STATEMACHINE));
        $machine = new StateMachine($object);
        
        $regex_from_all = new State('regex:/.+/'); // regex: all states
        $a = new State('a', State::TYPE_INITIAL);
        $done = new State('done', State::TYPE_FINAL);
        $b = new State('b');
        $c = new State('c');
        $d = new State('d');
        $e = new State('e');
        $machine->addTransition(new Transition($a, $b));
        $machine->addTransition(new Transition($a, $done));
        $machine->addTransition(new Transition($b, $c));
        $machine->addTransition(new Transition($c, $d));
        $machine->addTransition(new Transition($d, $e));
        $machine->addTransition(new Transition($regex_from_all, $done));
        // echo "TRANSITIONS" . PHP_EOL;
        // echo implode(',', $machine->getTransitions());
        $this->assertNotNull($machine->getTransition('a_to_b'));
        $this->assertNotNull($machine->getTransition('a_to_done'));
        $this->assertNotNull($machine->getTransition('b_to_c'));
        $this->assertNotNull($machine->getTransition('c_to_d'));
        $this->assertNotNull($machine->getTransition('d_to_e'));
        $this->assertNotNull($machine->getTransition('b_to_done'));
        $this->assertNotNull($machine->getTransition('c_to_done'));
        $this->assertNotNull($machine->getTransition('d_to_done'));
        $this->assertNotNull($machine->getTransition('e_to_done'));
        $this->assertNull($machine->getTransition('done_to_done'), 'no self transitions for regex states');
        $this->assertNull($machine->getTransition('done_to_a'), 'not defined');
    }
    
    /**
     * @group regex
     * @test
     */
    public function shouldAddRegexToState()
    {
        $object = Context::get(new Identifier(Identifier::NULL_ENTITY_ID, Identifier::NULL_STATEMACHINE));
        $machine = new StateMachine($object);
    
        $regex_to_all = new State('regex:/.+/'); // regex: to all states
        $a = new State('a', State::TYPE_INITIAL);
        $done = new State('done', State::TYPE_FINAL);
        $b = new State('b');
        $c = new State('c');
        $d = new State('d');
        $e = new State('e');
        $machine->addTransition(new Transition($a, $b));
        $machine->addTransition(new Transition($a, $done));
        $machine->addTransition(new Transition($b, $c));
        $machine->addTransition(new Transition($c, $d));
        $machine->addTransition(new Transition($d, $e));
        $machine->addTransition(new Transition($a, $regex_to_all));
        $this->assertNotNull($machine->getTransition('a_to_b'));
        $this->assertNotNull($machine->getTransition('a_to_done'), 'already exists');
        $this->assertNotNull($machine->getTransition('b_to_c'));
        $this->assertNotNull($machine->getTransition('c_to_d'));
        $this->assertNotNull($machine->getTransition('d_to_e'));
        $this->assertNotNull($machine->getTransition('a_to_c'));
        $this->assertNotNull($machine->getTransition('a_to_d'));
        $this->assertNotNull($machine->getTransition('a_to_e'));
        $this->assertNull($machine->getTransition('a_to_a'), 'no self transitions for regex states');
        $this->assertNull($machine->getTransition('done_to_a'), 'not defined');
        $this->assertNull($machine->getTransition('b_to_a'), 'not defined');
    }
    
    /**
     * @group regex
     * @test
     */
    public function shouldAddRegexToAndFromState()
    {
        $object = Context::get(new Identifier(Identifier::NULL_ENTITY_ID, Identifier::NULL_STATEMACHINE));
        $machine = new StateMachine($object);
    
        $regex_to = new State('regex:/.+/'); // regex: to all states
        $regex_from = new State('regex:/.+/'); // regex: from all states
        $a = new State('a', State::TYPE_INITIAL);
        $done = new State('done', State::TYPE_FINAL);
        $b = new State('b');
        $c = new State('c');
        $machine->addTransition(new Transition($a, $b));
        $machine->addTransition(new Transition($b, $c));
        $machine->addTransition(new Transition($c, $done));
        $machine->addTransition(new Transition($regex_from, $regex_to));
        $this->assertNotNull($machine->getTransition('a_to_b'));
        $this->assertNotNull($machine->getTransition('b_to_c'));
        $this->assertNotNull($machine->getTransition('c_to_done'));
        $this->assertNotNull($machine->getTransition('a_to_c'));
        $this->assertNotNull($machine->getTransition('a_to_done'));
        $this->assertNotNull($machine->getTransition('b_to_a'));
        $this->assertNotNull($machine->getTransition('b_to_done'));
        $this->assertNotNull($machine->getTransition('c_to_a'));
        $this->assertNotNull($machine->getTransition('c_to_b'));
        $this->assertNotNull($machine->getTransition('c_to_done'));
        $this->assertNull($machine->getTransition('a_to_a'), 'no self transitions for regex states');
        $this->assertNull($machine->getTransition('b_to_b'), 'no self transitions for regex states');
        $this->assertNull($machine->getTransition('c_to_c'), 'no self transitions for regex states');
        $this->assertNull($machine->getTransition('done_to_done'), 'no self transitions for regex states');
        $this->assertNull($machine->getTransition('done_to_a'), 'not allowed from final state');
        $this->assertNull($machine->getTransition('done_to_b'), 'not allowed from final state');
        $this->assertNull($machine->getTransition('done_to_c'), 'not allowed from final state');
        $this->assertNull($machine->getTransition('done_to_d'), 'not allowed from final state');
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
        $sat0 = $sat [0];
        $this->assertEquals($sat0, $transition_1, 'bidirectional association');
        $sbbt = $sbb->getTransitions();
        $sbbt0 = $sbbt [0];
        $this->assertEquals($sbbt0, $transition_2, 'bidirectional association');
        $sbbt1 = $sbbt [1];
        $this->assertNotEquals($sbbt1, $transition_2, 'no association, transition not on state');
    }
    
    /**
     * @test
     */
    public function shouldExecuteSimpleBenchmark()
    {
        $a = new State('a', State::TYPE_INITIAL);
        $b = new State('b');
        $tab = new Transition($a, $b, 'ab');
        $tba = new Transition($b, $a, 'ba');
        $machine = new StateMachine(New Context(new Identifier('benchmark',  'benchmark-machine')));
        $machine->addTransition($tba);
        $machine->addTransition($tab);
        $this->assertEquals($a, $machine->getCurrentState());
        $machine->ab();
        $this->assertEquals($b, $machine->getCurrentState());
        $machine->ba();
        $this->assertEquals($a, $machine->getCurrentState());
        $start = microtime(true);
        //echo "starting benchmark: " . $start . PHP_EOL;
        $total = 100;
        for ($i = 0; $i<$total ;$i++) {
            $machine->run();
        }
        $stop = microtime(true);
        //echo "stopping benchmark: $total took " . ($stop - $start);
        
        //on my fairly old machine, 10.000 transitions with the bare algorithm (no guards/logic) 
        //took about 0.5 seconds
        
    }

    /**
     * @test
     * tests: handle, canHandle, hasEvent, __call
     */
    public function shouldBeAbleToUseEventHandlingMethods()
    {
        $object = Context::get(new Identifier(Identifier::NULL_ENTITY_ID, Identifier::NULL_STATEMACHINE));
        $machine = new StateMachine($object);
        $this->addTransitionsToMachine($machine);
        $this->assertTrue($machine->hasEvent('newAAH'), 'event name for new to a');
        $this->assertTrue($machine->canHandle('newAAH'));
        $this->assertEquals('new', $machine->getCurrentState()->getName());
        $this->assertTrue($machine->newAAH(), 'dynamic method calling event name via handle(event) via __call');
        $this->assertEquals('a', $machine->getCurrentState()->getName());
        $this->assertTrue($machine->canHandle('a_to_b'));
        $this->assertTrue($machine->a_to_b(), 'dynamic method calling default event name via handle(event) via __call');
        $this->assertEquals('b', $machine->getCurrentState()->getName());
        $this->assertTrue($machine->hasEvent('goBC'));
        $this->assertTrue($machine->hasEvent('goBD'));
        $this->assertFalse($machine->hasEvent('goBogus'), 'nonexistent event');
        $this->assertTrue($machine->canHandle('goBC'));
        $this->assertFalse($machine->canHandle('goBD'), 'false rule');
        $this->assertFalse($machine->canHandle('goBDasdf'));
        $this->assertTrue($machine->handle('goBC'), 'goBC is the event name for b_to_c');
        $this->assertEquals('c', $machine->getCurrentState()->getName());
        $this->assertFalse($machine->handle('goBC'), 'goBC is not valid for state c');
        $this->assertFalse($machine->hasEvent('goBC'));
        $this->assertFalse($machine->canHandle('goBC'), 'cannot handle an invalid event in this state');
        $this->assertTrue($machine->hasEvent('goCD'));
        $this->assertTrue($machine->canHandle('goCD'));
        $this->assertTrue($machine->goCD(), 'goCD is available on the statemachine interface when called via __call');
        $this->assertEquals('d', $machine->getCurrentState()->getName());
        $this->assertNotEquals('c', $machine->getCurrentState()->getName());
    }

    /**
     * @test
     */
    public function shouldBeAbleToUseEventForMoreTransitionsInCurrentState()
    {
        $object = Context::get(new Identifier(Identifier::NULL_ENTITY_ID, Identifier::NULL_STATEMACHINE));
        $machine = new StateMachine($object);
        $a = new State('new', State::TYPE_INITIAL);
        $b = new State('b');
        $c = new State('c', State::TYPE_FINAL);
        $d = new State('d', State::TYPE_FINAL);
        
        $event = 'fictional-event-name';
        // the order in which transitions are created make it that the
        // bidirectional association with the states are set up.
        // the first possible transition to match is therefore transition c.
        $taa = new Transition($a, $a, 'another-event', Transition::RULE_TRUE);
        $tab = new Transition($a, $b, $event, Transition::RULE_FALSE);
        $tac = new Transition($a, $c, $event, Transition::RULE_TRUE);
        $tad = new Transition($a, $d, $event, Transition::RULE_TRUE);
        
        // first add 'd', 'c' comes later but should match first
        $machine->addTransition($tad); // match, true
        $machine->addTransition($taa); // no match, true
        $machine->addTransition($tab); // match, false
        $machine->addTransition($tac); // match, true
        $this->assertEquals('new', $machine->getCurrentState());
        $this->assertTrue($machine->hasEvent($event));
        $this->assertTrue($machine->canHandle($event));
        $this->assertFalse($machine->handle(''));
        $this->assertTrue($machine->handle($event));
        $this->assertEquals('c', $machine->getCurrentState());
    }

    protected function addTransitionsToMachine(StateMachine $machine)
    {
        $s_new = new State(State::STATE_NEW, State::TYPE_INITIAL);
        $s_a = new State('a', State::TYPE_NORMAL);
        $s_b = new State('b', State::TYPE_NORMAL);
        $s_c = new State('c', State::TYPE_NORMAL);
        $s_d = new State('d', State::TYPE_NORMAL);
        $s_done = new State(State::STATE_DONE, State::TYPE_FINAL);
        
        $t_new_to_a = new Transition($s_new, $s_a, 'newAAH', Transition::RULE_TRUE, Transition::COMMAND_NULL);
        $t_a_to_b = new Transition($s_a, $s_b, null, Transition::RULE_TRUE, Transition::COMMAND_NULL);
        $t_b_to_c = new Transition($s_b, $s_c, 'goBC', Transition::RULE_TRUE, Transition::COMMAND_NULL);
        $t_b_to_d = new Transition($s_b, $s_d, 'goBD', Transition::RULE_FALSE, Transition::COMMAND_NULL);
        $t_c_to_d = new Transition($s_c, $s_d, 'goCD', Transition::RULE_TRUE, Transition::COMMAND_NULL);
        $t_d_done = new Transition($s_d, $s_done, null, Transition::RULE_TRUE, Transition::COMMAND_NULL);
        
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
        // $context->add();
        
        $s_new = new State(State::STATE_NEW, State::TYPE_INITIAL);
        $s_a = new State('a', State::TYPE_NORMAL);
        $s_b = new State('b', State::TYPE_NORMAL);
        $s_c = new State('c', State::TYPE_NORMAL);
        $s_d = new State('d', State::TYPE_NORMAL);
        $s_done = new State(State::STATE_DONE, State::TYPE_FINAL);
        
        $t_new_to_a = new Transition($s_new, $s_a, null, Transition::RULE_FALSE, Transition::COMMAND_NULL);
        $t_new_to_done = new Transition($s_new, $s_done, null, Transition::RULE_FALSE, Transition::COMMAND_NULL);
        $t_new_to_b = new Transition($s_new, $s_b, null, Transition::RULE_FALSE, Transition::COMMAND_NULL);
        $t_new_to_c = new Transition($s_new, $s_c, null, Transition::RULE_TRUE, Transition::COMMAND_NULL);
        $t_new_to_d = new Transition($s_new, $s_d, null, Transition::RULE_FALSE, Transition::COMMAND_NULL);
        // set an event name. since this transition is not allowed, the event
        // based transition should fail later.
        $t_new_to_d->setEvent('event-foo-bar');
        $t_d_to_done = new Transition($s_d, $s_done, null, Transition::RULE_TRUE, Transition::COMMAND_NULL);
        $t_c_to_done = new Transition($s_c, $s_done, null, Transition::RULE_FALSE, Transition::COMMAND_NULL);
        $t_c_to_d = new Transition($s_c, $s_d, null, Transition::RULE_TRUE, Transition::COMMAND_NULL);
        
        $machine->addTransition($t_new_to_a);
        $machine->addTransition($t_new_to_done);
        $machine->addTransition($t_new_to_b);
        $machine->addTransition($t_new_to_c);
        $machine->addTransition($t_new_to_d);
        $machine->addTransition($t_d_to_done);
        $machine->addTransition($t_c_to_done);
        $machine->addTransition($t_c_to_d);
        
        // path should be: new->c->d->done;
        $this->assertCount(8, $machine->getTransitions());
        $this->assertCount(6, $machine->getStates());
        $this->assertEquals($machine->getCurrentState(), State::STATE_NEW);
        $this->assertTrue($machine->canTransition('new_to_c'));
        // check event returns false
        $this->assertFalse($machine->handle('event-new-to-c'));
        $this->assertFalse($machine->handle('event-c-to-d'));
        $this->assertFalse($machine->handle('bogus'));
        
        $this->assertFalse($machine->canTransition('new_to_a'));
        $this->assertFalse($machine->canTransition('new_to_done'));
        $this->assertFalse($machine->canTransition('new_to_b'));
        $this->assertFalse($machine->canTransition('new_to_d'));
        $this->assertFalse($machine->handle('event-foo-bar')); // new to d
                                                               // dissallowed by
                                                               // rule
        $this->assertFalse($machine->canHandle('event-foo-bar')); // new to d
                                                                  // dissallowed
                                                                  // by rule
        
        $this->assertEquals($machine->getCurrentState(), 'new');
        $this->assertTrue($machine->run());
        $this->assertEquals($machine->getCurrentState(), 'c');
        $t_c_to_d->setEvent('event-c-to-d');
        // do event based transition
        $this->assertTrue($machine->handle('event-c-to-d'));
        $this->assertEquals($machine->getCurrentState(), 'd');
        $this->assertTrue($machine->run());
        $this->assertEquals($machine->getCurrentState(), 'done');
        
        $this->assertFalse($machine->run(), 'cannot run anymore');
        $this->assertFalse($machine->canTransition('new_to_c'));
        $this->assertFalse($machine->canTransition('new_to_a'));
        $this->assertFalse($machine->canTransition('new_to_done'));
        $this->assertFalse($machine->canTransition('new_to_b'));
        $this->assertFalse($machine->canTransition('new_to_d'));
        $this->assertFalse($machine->canTransition('non_to_existent'));
        $this->assertFalse($machine->canTransition('done_to_a'));
        $this->assertFalse($machine->canTransition('new_to_d'));
        $this->assertFalse($machine->canTransition('c_to_done'));
        $this->assertFalse($machine->canTransition('d_to_done'));
        $this->assertFalse($machine->canTransition('c_to_d'));
    }

    /**
     * @test
     */
    public function shouldBeAbleToSwitchContext()
    {
        $context_1 = new Context(new Identifier(1, Identifier::NULL_STATEMACHINE));
        $machine = new StateMachine($context_1);
        // $context_1->add();
        $this->assertEquals($context_1, $machine->getContext());
        
        try {
            $this->assertEquals($machine->getCurrentState()->getName(), State::STATE_NEW);
            $this->fail('current state not found, no transitions on machine');
        } catch(Exception $ex) {
            $this->assertEquals(Exception::SM_NO_CURRENT_STATE_FOUND, $ex->getCode());
        }
        
        $this->addTransitionsToMachine($machine);
        $this->assertEquals($machine->getCurrentState()->getName(), State::STATE_NEW, 'still the same');
        $this->assertTrue($machine->getCurrentState()->isInitial());
        
        // run to the end
        $total = $machine->runToCompletion();
        $this->assertEquals(5, $total);
        $this->assertEquals($machine->getCurrentState()->getName(), State::STATE_DONE);
        $this->assertTrue($machine->getCurrentState()->isFinal());
        
        // new context object, reuse statemachine
        $identifier = new Identifier(123, Identifier::NULL_STATEMACHINE);
        $context_2 = new Context($identifier);
        $context_2->setState(State::STATE_NEW);
        $machine->setContext($context_2);
        $this->assertEquals($machine->getCurrentState()->getName(), State::STATE_NEW);
        $this->assertTrue($machine->getCurrentState()->isInitial());
        $this->assertEquals($context_2, $machine->getContext());
        $total = $machine->runToCompletion();
        $this->assertEquals(5, $total);
        $this->assertEquals($machine->getCurrentState()->getName(), State::STATE_DONE);
        $this->assertTrue($machine->getCurrentState()->isFinal());
        
        // switch to different machine for context
        try {
            $context_3 = new Context(new Identifier(123, 'different machine'));
            $context_3->setState(State::STATE_DONE);
            $machine->setContext($context_3);
            $this->fail("cannot switch context with different machine");
        } catch(Exception $ex) {
            $this->assertEquals(Exception::SM_CONTEXT_DIFFERENT_MACHINE, $ex->getCode());
        }
    }

    /**
     * @test
     * @group 3.1
     */
    public function shouldBeAbleTosetState()
    {
        $machine = new StateMachine(new Context(new Identifier('123', 'test-machine')));
        $a = new State('a');
        $b = new State('b', State::TYPE_INITIAL);
        $t = new Transition($b, $a, 'go');
        $machine->addTransition($t);
        // var_dump(Memory::get());
        $this->assertEquals($b, $machine->getCurrentState());
        $machine->go();
        // var_dump(Memory::get());
        $this->assertEquals($a, $machine->getCurrentState());
        $machine->setState($b);
        $this->assertEquals($b, $machine->getCurrentState());
    }

    /**
     * @test
     * @group 3.1
     */
    public function shouldBeAbleToGetCorrectStateAfterContextSwitch()
    {
        $c1 = new Context(new Identifier('123', 'test-machine'));
        $machine = new StateMachine($c1);
        $a = new State('a');
        $b = new State('b', State::TYPE_INITIAL);
        $t = new Transition($b, $a, 'go');
        $machine->addTransition($t);
        $t = new Transition($a, $b, 'back');
        $machine->addTransition($t);
        // var_dump(Memory::get());
        $this->assertEquals($b, $machine->getCurrentState());
        $machine->go();
        $this->assertEquals($a, $machine->getCurrentState());
        $c2 = new Context(new Identifier('321', 'test-machine'));
        $machine->setContext($c2);
        $this->assertEquals($c2, $machine->getContext());
        $this->assertEquals($b, $machine->getCurrentState(), 'after switching context, the machine switches to the correct state');
        $machine->handle('go');
        $this->assertEquals($a, $machine->getCurrentState());
        $machine->back();
        $this->assertEquals($b, $machine->getCurrentState());
        $machine->setContext($c1);
        $this->assertEquals($a, $machine->getCurrentState(), 'switched back again. again with correct state');
        
        // var_dump(Memory::get());
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
        
        $this->assertNull($machine->getState('nonexistent'));
        $this->assertNull($machine->getTransition('nonexistent'));
        $this->assertNotNull($machine->toString());
    }

    /**
     * @test
     */
    public function shouldBeAbleToUseRunAndCanTransitionAndTestStateTypes()
    {
        $object = Context::get(new Identifier(Identifier::NULL_ENTITY_ID, Identifier::NULL_STATEMACHINE));
        $machine = new StateMachine($object);
        $this->addTransitionsToMachine($machine);
        
        $this->assertTrue($machine->getCurrentState()->isInitial());
        $this->assertFalse($machine->canTransition('a_to_b'), 'current transitions');
        $this->assertFalse($machine->canTransition('new_to_done'), 'invalid transition');
        $this->assertFalse($machine->canTransition('b_to_d'), 'false rule');
        $this->assertFalse($machine->canTransition('b_to_c'), 'not the current state');
        
        // new to a
        $machine->run();
        $this->assertEquals('a', $machine->getCurrentState(), ' check by name actually works because of __toString');
        $this->assertTrue($machine->getCurrentState()->isNormal());
        
        $machine->run();
        $this->assertEquals('b', $machine->getCurrentState());
        $this->assertTrue($machine->getCurrentState()->isNormal());
        
        $this->assertFalse($machine->canTransition('b_to_d'), 'false rule');
        $this->assertTrue($machine->canTransition('b_to_c'), 'next transition');
        
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
    public function shouldThrowExceptionFromRuleOrCommand()
    {
        $context = new Context(new Identifier(54321, Identifier::NULL_STATEMACHINE));
        $machine = new StateMachine($context);
        // $context->add();
        
        $s_new = new State(State::STATE_NEW, State::TYPE_INITIAL);
        $s_a = new State('a', State::TYPE_NORMAL);
        
        $t_new_to_a = new Transition($s_new, $s_a, null, 'izzum\rules\ExceptionRule', Transition::COMMAND_NULL);
        $machine->addTransition($t_new_to_a);
        
        try {
            $machine->run();
            $this->fail('will throw an error');
        } catch(Exception $e) {
            $this->assertEquals(Exception::RULE_APPLY_FAILURE, $e->getCode());
        }
        
        try {
            $machine->runToCompletion();
            $this->fail('will throw an error');
        } catch(Exception $e) {
            $this->assertEquals(Exception::RULE_APPLY_FAILURE, $e->getCode());
        }
        
        try {
            $machine->transition('new_to_a');
            $this->fail('will throw an error');
        } catch(Exception $e) {
            $this->assertEquals(Exception::RULE_APPLY_FAILURE, $e->getCode());
        }
    }

    /**
     * @test
     * @group not-on-production
     * @group plantuml
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
        
        $t_new_to_a = new Transition($s_new, $s_a, null, Transition::RULE_TRUE, 'izzum\command\ValidateOrder');
        $t_a_to_b = new Transition($s_a, $s_b, null, 'izzum\rules\IsReadyForDelivery', 'izzum\command\SendConfirmation');
        $t_b_to_c = new Transition($s_b, $s_c, null, Transition::RULE_TRUE, 'izzum\command\TechnicalDelivery');
        $t_b_to_d = new Transition($s_b, $s_d, null, Transition::RULE_FALSE, Transition::COMMAND_NULL);
        $t_c_to_d = new Transition($s_c, $s_d, null, 'izzum\rules\ReadyForContract', 'izzum\command\CreateContract');
        $t_d_done = new Transition($s_d, $s_done, null, Transition::RULE_TRUE, 'izzum\command\ActivateServices');
        
        $machine->addTransition($t_new_to_a);
        $machine->addTransition($t_a_to_b);
        $machine->addTransition($t_b_to_c);
        $machine->addTransition($t_b_to_d);
        $machine->addTransition($t_c_to_d);
        $machine->addTransition($t_d_done);
        
        $plant = new PlantUml();
        $result = $plant->createStateDiagram($machine);
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
        
        $ni = new Transition($new, $initialize, null, Transition::RULE_TRUE, 'izzum\command\Initialize');
        $ni->setDescription("initialize the coffee machine");
        $transitions [] = $ni;
        $transitions [] = new Transition($initialize, $cup, null, Transition::RULE_TRUE, 'izzum\command\DropCup');
        $transitions [] = new Transition($cup, $coffee, null, Transition::RULE_TRUE, 'izzum\command\AddCoffee');
        $transitions [] = new Transition($coffee, $sugar, null, 'izzum\rules\WantsSugar', 'izzum\command\AddSugar');
        $transitions [] = new Transition($sugar, $coffee, null, Transition::RULE_TRUE, Transition::COMMAND_NULL);
        $transitions [] = new Transition($coffee, $milk, null, 'izzum\rules\WantsMilk', 'izzum\command\AddMilk');
        $transitions [] = new Transition($milk, $coffee, null, Transition::RULE_TRUE, Transition::COMMAND_NULL);
        $transitions [] = new Transition($coffee, $spoon, null, 'izzum\rules\MilkOrSugar', 'izzum\command\AddSpoon');
        $transitions [] = new Transition($coffee, $done, null, 'izzum\rules\CoffeeTakenOut', 'izzum\command\Cleanup');
        $transitions [] = new Transition($spoon, $done, null, 'izzum\rules\CoffeeTakenOut', 'izzum\command\CleanUp');
        
        $loader = new LoaderArray($transitions);
        $loader->load($machine);
        
        $plant = new PlantUml();
        $result = $plant->createStateDiagram($machine);
        $this->assertPlantUml($result);
        
        if ($output) {
            echo PHP_EOL;
            echo __METHOD__ . PHP_EOL;
            echo PHP_EOL;
            echo $result;
            echo PHP_EOL;
        }
    }

    public function testPlantUml()
    {
        $this->doPlant(false);
    }

    public function assertPlantUml($result)
    {
        $this->assertNotNull($result);
        $this->assertTrue(is_string($result));
        $this->assertContains("@startuml", $result);
        $this->assertContains("@enduml", $result);
        $this->assertContains("new", $result);
        $this->assertContains("rule", $result);
        $this->assertContains("command", $result);
        $this->assertContains("_to_", $result);
    }

    /**
     * @test
     */
    public function shouldBeAbleToUseCallablesOnEntity()
    {
        $model = new CallableHandler();
        $this->assertTrue(method_exists($model, 'onCheckCanTransition'));
        $this->assertTrue(method_exists($model, 'onExitState'));
        $this->assertTrue(method_exists($model, 'onTransition'));
        $this->assertTrue(method_exists($model, 'onEnterState'));
        
        // pass the model to the builder that uses that model as entity
        $builder = new ModelBuilder($model);
        
        $object = Context::get(new Identifier(Identifier::NULL_ENTITY_ID, Identifier::NULL_STATEMACHINE), $builder);
        $machine = new StateMachine($object);
        $this->addTransitionsToMachine($machine);
        
        $this->assertNull($model->oncheckcantransition);
        $this->assertNull($model->onexitstate);
        $this->assertNull($model->ontransition);
        $this->assertNull($model->onexitstate);
        $this->assertTrue($model->allow);
        $this->assertEquals('new', $machine->getCurrentState());
        $this->assertTrue($machine->canTransition('new_to_a'));
        $model->allow = false;
        $this->assertFalse($machine->canTransition('new_to_a'));
        $model->allow = true;
        $this->assertTrue($machine->canTransition('new_to_a'));
        $machine->newAAH(); // new to a event trigger
        $this->assertEquals('a', $machine->getCurrentState());
        
        // we expect the transition and the event name to be passed as arguments
        $expected = array(
                $machine->getTransition('new_to_a'),
                'newAAH'
        );
        $this->assertEquals($expected, $model->oncheckcantransition);
        $this->assertEquals($expected, $model->onexitstate);
        $this->assertEquals($expected, $model->ontransition);
        $this->assertEquals($expected, $model->onenterstate);
    }
}

// implements all the callables that can be called as part of a transition
// and lets us test if the right parameters are passed
class CallableHandler {
    public $allow;
    public $oncheckcantransition;
    public $onexitstate;
    public $ontransition;
    public $onenterstate;

    public function __construct($allow = true)
    {
        $this->allow = $allow;
    }

    public function onExitState($transition, $event)
    {
        $this->onexitstate = array(
                $transition,
                $event
        );
    }

    public function onCheckCanTransition($transition, $event)
    {
        $this->oncheckcantransition = array(
                $transition,
                $event
        );
        return $this->allow;
    }

    public function onTransition($transition, $event)
    {
        $this->ontransition = array(
                $transition,
                $event
        );
    }

    public function onEnterState($transition, $event)
    {
        $this->onenterstate = array(
                $transition,
                $event
        );
    }
}
