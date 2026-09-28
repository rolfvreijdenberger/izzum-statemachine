<?php

namespace Izzum\StateMachine;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use Codeception\Attribute\Group;
use Izzum\StateMachine\Transition;
use Izzum\StateMachine\Exception;
use Izzum\StateMachine\Context;
use Izzum\StateMachine\Persistence\Memory;
use Izzum\StateMachine\Utils\PlantUml;
use Izzum\StateMachine\Loader\LoaderArray;
use Izzum\StateMachine\Builder\ModelBuilder;

/**
 *
 * @author rolf
 *
 */
#[Group('statemachine')]
class StateMachineTest extends TestCase
{
    public function setUp(): void
    {
        parent::setUp();
        // clear in memory storage
        Memory::clear();
    }

    #[Test]
    public function shouldWorkWhenInitialized()
    {
        $object = new Context(new Identifier(Identifier::NULL_ENTITY_ID, Identifier::NULL_STATEMACHINE));
        $machine = new StateMachine($object);
        $this->assertEquals(Identifier::NULL_STATEMACHINE, $machine->getContext()->getMachine());
        $this->assertEquals($machine->getContext(), $object);
        $this->assertCount(0, $machine->getStates());
        $this->assertCount(0, $machine->getTransitions());
        try {
            $machine->getCurrentState();
            $this->fail('should not come here');
        } catch (Exception $e) {
            $this->assertEquals(Exception::SM_NO_CURRENT_STATE_FOUND, $e->getCode());
            // echo $e->getMessage();
        }
        $this->assertStringContainsString('StateMachine', $machine . '', '__toString()');
        $this->assertStringContainsString('transitions', $machine->toString(true));
        $this->assertStringNotContainsString('transitions', $machine->toString(false));
        //echo $machine->toString(false);
        $this->assertStringContainsString('states', $machine->toString(true));
        $this->assertStringNotContainsString('states', $machine->toString(false));
    }

    #[Group('regex')]
    #[Test]
    public function shouldBeAbleToTransition()
    {
        $object = new Context(new Identifier(Identifier::NULL_ENTITY_ID, Identifier::NULL_STATEMACHINE));
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

        try {
            $machine->transition('foo_to_bar');
            $this->fail('should not come here..');
        } catch (Exception $e) {
            $this->assertEquals(Exception::SM_NO_TRANSITION_FOUND, $e->getCode());
        }
    }

    #[Test]
    public function shouldBeAbleToUseAddTransitions()
    {
        $object = new Context(new Identifier(Identifier::NULL_ENTITY_ID, Identifier::NULL_STATEMACHINE));
        $machine = new StateMachine($object);

        $sNew = new State(State::STATE_NEW, State::TYPE_INITIAL);
        $sA = new State('a', State::TYPE_NORMAL);
        $sB = new State('b', State::TYPE_NORMAL);
        $sC = new State('c', State::TYPE_NORMAL);
        $sD = new State('d', State::TYPE_NORMAL);
        $sDone = new State(State::STATE_DONE, State::TYPE_FINAL);

        $tNewToA = new Transition($sNew, $sA, null, Transition::RULE_TRUE, Transition::COMMAND_NULL);
        $tAToB = new Transition($sA, $sB, null, Transition::RULE_TRUE, Transition::COMMAND_NULL);
        $tBToC = new Transition($sB, $sC, null, Transition::RULE_TRUE, Transition::COMMAND_NULL);
        $tBToD = new Transition($sB, $sD, null, Transition::RULE_FALSE, Transition::COMMAND_NULL);
        $tCToD = new Transition($sC, $sD, null, Transition::RULE_TRUE, Transition::COMMAND_NULL);
        $tDDone = new Transition($sD, $sDone, null, Transition::RULE_TRUE, Transition::COMMAND_NULL);

        $this->assertEquals(1, $machine->addTransition($tNewToA));
        $this->assertCount(2, $machine->getStates());
        $this->assertCount(1, $machine->getTransitions());

        $this->assertEquals(1, $machine->addTransition($tAToB));
        $this->assertCount(3, $machine->getStates());
        $this->assertCount(2, $machine->getTransitions());

        $machine->addTransition($tBToC);
        $this->assertCount(4, $machine->getStates());
        $this->assertCount(3, $machine->getTransitions());

        $machine->addTransition($tBToD);
        $this->assertCount(5, $machine->getStates());
        $this->assertCount(4, $machine->getTransitions());

        $machine->addTransition($tCToD);
        $this->assertCount(5, $machine->getStates());
        $this->assertCount(5, $machine->getTransitions());

        $machine->addTransition($tDDone);
        $this->assertCount(6, $machine->getStates());
        $this->assertCount(6, $machine->getTransitions());

        // same, should not be added again
        $this->assertEquals(0, $machine->addTransition($tDDone));
        $this->assertCount(6, $machine->getStates());
        $this->assertCount(6, $machine->getTransitions());

        // same, should not be added again
        $this->assertEquals(0, $machine->addTransition($tBToC));
        $this->assertCount(6, $machine->getStates());
        $this->assertCount(6, $machine->getTransitions());
    }

    public function testGetInitialState()
    {
        $object = new Context(new Identifier(Identifier::NULL_ENTITY_ID, Identifier::NULL_STATEMACHINE));
        $machine = new StateMachine($object);
        $this->assertNull($machine->getInitialState(true), 'try to get initial state and expect null if not there');
        try {
            // try to get initial state and expect exception if not there
            $machine->getInitialState();
            $this->fail("should not come here");
        } catch (Exception $e) {
            $this->assertEquals(Exception::SM_NO_INITIAL_STATE_FOUND, $e->getCode());
        }

        $this->addTransitionsToMachine($machine);
        $this->assertEquals(State::STATE_NEW, $machine->getInitialState()->getName());
    }

    #[Group('regex')]
    #[Test]
    public function shouldAddRegexFromState()
    {
        $object = new Context(new Identifier(Identifier::NULL_ENTITY_ID, Identifier::NULL_STATEMACHINE));
        $machine = new StateMachine($object);

        $regexFromAll = new State('regex:/.+/'); // regex: all states
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
        $this->assertEquals(4, $machine->addTransition(new Transition($regexFromAll, $done)), '5 to done, but a to done was already added');
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

    #[Group('regex')]
    #[Test]
    public function shouldAddRegexWithSelfTransitions()
    {
        $object = new Context(new Identifier(Identifier::NULL_ENTITY_ID, Identifier::NULL_STATEMACHINE));
        $machine = new StateMachine($object);

        $regexAll = new State('regex:/.+/'); // regex: all states
        $a = new State('a', State::TYPE_INITIAL);
        $b = new State('b');
        $c = new State('c');
        $this->assertCount(0, $machine->getStates());
        $this->assertEquals(0, $machine->addTransition(new Transition($regexAll, $regexAll), true), 'no valid states known to the machine');
        $this->assertTrue($machine->addState($a));
        $this->assertTrue($machine->addState($b));
        $this->assertTrue($machine->addState($c));
        $this->assertFalse($machine->addState($regexAll), 'no regex states allowed');
        $this->assertCount(3, $machine->getStates());
        $this->assertEquals(9, $machine->addTransition(new Transition($regexAll, $regexAll), true), 'create full mesh with self transitions');

        $this->assertNotNull($machine->getTransition('a_to_a'));
        $this->assertNotNull($machine->getTransition('a_to_b'));
        $this->assertNotNull($machine->getTransition('a_to_c'));
        $this->assertNotNull($machine->getTransition('b_to_b'));
        $this->assertNotNull($machine->getTransition('b_to_a'));
        $this->assertNotNull($machine->getTransition('b_to_c'));
        $this->assertNotNull($machine->getTransition('c_to_c'));
        $this->assertNotNull($machine->getTransition('c_to_a'));
        $this->assertNotNull($machine->getTransition('c_to_b'));
    }

    #[Group('regex')]
    #[Test]
    public function shouldAddRegexWithoutSelfTransitions()
    {
        $object = new Context(new Identifier(Identifier::NULL_ENTITY_ID, Identifier::NULL_STATEMACHINE));
        $machine = new StateMachine($object);

        $regexAll = new State('regex:/.+/'); // regex: all states
        $a = new State('a', State::TYPE_INITIAL);
        $b = new State('b');
        $c = new State('c');
        $this->assertCount(0, $machine->getStates());
        $this->assertEquals(0, $machine->addTransition(new Transition($regexAll, $regexAll)), 'no valid states known to the machine');
        $this->assertTrue($machine->addState($a));
        $this->assertTrue($machine->addState($b));
        $this->assertTrue($machine->addState($c));
        $this->assertCount(3, $machine->getStates());
        $this->assertEquals(6, $machine->addTransition(new Transition($regexAll, $regexAll)), 'create mesh with self transitions');

        $this->assertNull($machine->getTransition('a_to_a'));
        $this->assertNotNull($machine->getTransition('a_to_b'));
        $this->assertNotNull($machine->getTransition('a_to_c'));
        $this->assertNull($machine->getTransition('b_to_b'));
        $this->assertNotNull($machine->getTransition('b_to_a'));
        $this->assertNotNull($machine->getTransition('b_to_c'));
        $this->assertNull($machine->getTransition('c_to_c'));
        $this->assertNotNull($machine->getTransition('c_to_a'));
        $this->assertNotNull($machine->getTransition('c_to_b'));
    }

    #[Group('regex')]
    #[Test]
    public function shouldAddState()
    {
        $object = new Context(new Identifier(Identifier::NULL_ENTITY_ID, Identifier::NULL_STATEMACHINE));
        $machine = new StateMachine($object);

        $regexAll = new State('regex:/.+/'); // regex: all states
        $a = new State('a', State::TYPE_INITIAL);
        $b = new State('b');
        $c = new State('c');
        $this->assertCount(0, $machine->getStates());
        $this->assertTrue($machine->addState($a));
        $this->assertTrue($machine->addState($b));
        $this->assertTrue($machine->addState($c));
        $this->assertCount(3, $machine->getStates());
        $this->assertFalse($machine->addState($a));
        $this->assertFalse($machine->addState($b));
        $this->assertFalse($machine->addState($c));
        $this->assertCount(3, $machine->getStates());
        $this->assertFalse($machine->addState($regexAll), 'cannot add regex state');
        $this->assertCount(3, $machine->getStates());
    }

    #[Group('guard')]
    #[Test]
    public function shouldBeAbleToBlockTransitionInSubclass()
    {
        $object = new Context(new Identifier(Identifier::NULL_ENTITY_ID, Identifier::NULL_STATEMACHINE));
        $machine = new SubClassedStateMachine($object);

        $regexAll = new State('regex:/.+/'); // regex: all states
        $a = new State('a', State::TYPE_INITIAL);
        $b = new State('b');
        $c = new State('c');
        $machine->addState($a);
        $machine->addState($b);
        $machine->addState($c);
        $machine->addTransition(new Transition($regexAll, $regexAll));//full mesh
        $this->assertCount(6, $machine->getTransitions());
        $this->assertTrue($machine->transition('a_to_b'));
        $this->assertEquals('b', $machine->getCurrentState());
        $this->assertFalse($machine->transition('b_to_c'));
    }

    #[Group('regex')]
    #[Test]
    public function shouldAddRegexToState()
    {
        $object = new Context(new Identifier(Identifier::NULL_ENTITY_ID, Identifier::NULL_STATEMACHINE));
        $machine = new StateMachine($object);

        $regexToAll = new State('regex:/.+/'); // regex: to all states
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
        $machine->addTransition(new Transition($a, $regexToAll));
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

    #[Group('regex')]
    #[Test]
    public function shouldAddRegexToAndFromState()
    {
        $object = new Context(new Identifier(Identifier::NULL_ENTITY_ID, Identifier::NULL_STATEMACHINE));
        $machine = new StateMachine($object);

        $regexTo = new State('regex:/.+/'); // regex: to all states
        $regexFrom = new State('regex:/.+/'); // regex: from all states
        $a = new State('a', State::TYPE_INITIAL);
        $done = new State('done', State::TYPE_FINAL);
        $b = new State('b');
        $c = new State('c');
        $machine->addTransition(new Transition($a, $b));
        $machine->addTransition(new Transition($b, $c));
        $machine->addTransition(new Transition($c, $done));
        $machine->addTransition(new Transition($regexFrom, $regexTo));
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
        $object = new Context(new Identifier(Identifier::NULL_ENTITY_ID, Identifier::NULL_STATEMACHINE));
        $machine = new StateMachine($object);
        $this->addTransitionsToMachine($machine);
        $transitions = $machine->getTransitions();
        $states = $machine->getStates();
        $this->assertCount(6, $states);
        $this->assertCount(6, $transitions);
        $transition1 = $machine->getTransition('a_to_b');
        $sa = $transition1->getStateFrom();
        $this->assertEquals('a', $sa->getName());
        $sb = $transition1->getStateTo();
        $this->assertEquals('b', $sb->getName());
        $this->assertEquals('a_to_b', $transition1->getName());

        $transition2 = $machine->getTransition('b_to_c');
        $sbb = $transition2->getStateFrom();
        $this->assertEquals('b', $sbb->getName());
        $sc = $transition2->getStateTo();
        $this->assertEquals('c', $sc->getName());
        $this->assertEquals('b_to_c', $transition2->getName());

        $this->assertEquals($sb, $sbb, 'referencing the same object');
        $this->assertNotEquals($sa, $sb);
        $this->assertNotEquals($sb, $sc);

        $sat = $sa->getTransitions();
        $sat0 = $sat [0];
        $this->assertEquals($sat0, $transition1, 'bidirectional association');
        $sbbt = $sbb->getTransitions();
        $sbbt0 = $sbbt [0];
        $this->assertEquals($sbbt0, $transition2, 'bidirectional association');
        $sbbt1 = $sbbt [1];
        $this->assertNotEquals($sbbt1, $transition2, 'no association, transition not on state');
    }

    #[Test]
    public function shouldExecuteSimpleBenchmark()
    {
        $a = new State('a', State::TYPE_INITIAL);
        $b = new State('b');
        $tab = new Transition($a, $b, 'ab');
        $tba = new Transition($b, $a, 'ba');
        $machine = new StateMachine(new Context(new Identifier('benchmark', 'benchmark-machine')));
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
        for ($i = 0; $i < $total ;$i++) {
            $machine->run();
        }
        $stop = microtime(true);
        //echo "stopping benchmark: $total took " . ($stop - $start);

        //on my fairly old machine, 10.000 transitions with the bare algorithm (no guards/logic)
        //took about 0.5 seconds

    }

    /**
     * tests: handle, canHandle, hasEvent, __call
     */
    #[Test]
    public function shouldBeAbleToUseEventHandlingMethods()
    {
        $object = new Context(new Identifier(Identifier::NULL_ENTITY_ID, Identifier::NULL_STATEMACHINE));
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

    #[Test]
    public function shouldBeAbleToUseEventForMoreTransitionsInCurrentState()
    {
        $object = new Context(new Identifier(Identifier::NULL_ENTITY_ID, Identifier::NULL_STATEMACHINE));
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
        $sNew = new State(State::STATE_NEW, State::TYPE_INITIAL);
        $sA = new State('a', State::TYPE_NORMAL);
        $sB = new State('b', State::TYPE_NORMAL);
        $sC = new State('c', State::TYPE_NORMAL);
        $sD = new State('d', State::TYPE_NORMAL);
        $sDone = new State(State::STATE_DONE, State::TYPE_FINAL);

        $tNewToA = new Transition($sNew, $sA, 'newAAH', Transition::RULE_TRUE, Transition::COMMAND_NULL);
        $tAToB = new Transition($sA, $sB, null, Transition::RULE_TRUE, Transition::COMMAND_NULL);
        $tBToC = new Transition($sB, $sC, 'goBC', Transition::RULE_TRUE, Transition::COMMAND_NULL);
        $tBToD = new Transition($sB, $sD, 'goBD', Transition::RULE_FALSE, Transition::COMMAND_NULL);
        $tCToD = new Transition($sC, $sD, 'goCD', Transition::RULE_TRUE, Transition::COMMAND_NULL);
        $tDDone = new Transition($sD, $sDone, null, Transition::RULE_TRUE, Transition::COMMAND_NULL);

        $machine->addTransition($tNewToA);
        $machine->addTransition($tAToB);
        $machine->addTransition($tBToC);
        $machine->addTransition($tBToD);
        $machine->addTransition($tCToD);
        $machine->addTransition($tDDone);
    }

    public function testMultipleTransitionsFromOneStateAndAlsoWithEvents()
    {
        $context = new Context(new Identifier(54321, Identifier::NULL_STATEMACHINE));
        $machine = new StateMachine($context);

        $sNew = new State(State::STATE_NEW, State::TYPE_INITIAL);
        $sA = new State('a', State::TYPE_NORMAL);
        $sB = new State('b', State::TYPE_NORMAL);
        $sC = new State('c', State::TYPE_NORMAL);
        $sD = new State('d', State::TYPE_NORMAL);
        $sDone = new State(State::STATE_DONE, State::TYPE_FINAL);

        $tNewToA = new Transition($sNew, $sA, null, Transition::RULE_FALSE, Transition::COMMAND_NULL);
        $tNewToDone = new Transition($sNew, $sDone, null, Transition::RULE_FALSE, Transition::COMMAND_NULL);
        $tNewToB = new Transition($sNew, $sB, null, Transition::RULE_FALSE, Transition::COMMAND_NULL);
        $tNewToC = new Transition($sNew, $sC, null, Transition::RULE_TRUE, Transition::COMMAND_NULL);
        $tNewToD = new Transition($sNew, $sD, null, Transition::RULE_FALSE, Transition::COMMAND_NULL);
        // set an event name. since this transition is not allowed, the event
        // based transition should fail later.
        $tNewToD->setEvent('event-foo-bar');
        $tDToDone = new Transition($sD, $sDone, null, Transition::RULE_TRUE, Transition::COMMAND_NULL);
        $tCToDone = new Transition($sC, $sDone, null, Transition::RULE_FALSE, Transition::COMMAND_NULL);
        $tCToD = new Transition($sC, $sD, null, Transition::RULE_TRUE, Transition::COMMAND_NULL);

        $machine->addTransition($tNewToA);
        $machine->addTransition($tNewToDone);
        $machine->addTransition($tNewToB);
        $machine->addTransition($tNewToC);
        $machine->addTransition($tNewToD);
        $machine->addTransition($tDToDone);
        $machine->addTransition($tCToDone);
        $machine->addTransition($tCToD);

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
        $tCToD->setEvent('event-c-to-d');
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

    #[Test]
    public function shouldBeAbleToSwitchContext()
    {
        $context1 = new Context(new Identifier(1, Identifier::NULL_STATEMACHINE));
        $machine = new StateMachine($context1);
        $this->assertEquals($context1, $machine->getContext());

        try {
            $this->assertEquals($machine->getCurrentState()->getName(), State::STATE_NEW);
            $this->fail('current state not found, no transitions on machine');
        } catch (Exception $ex) {
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
        $context2 = new Context($identifier);
        $context2->setState(State::STATE_NEW);
        $machine->setContext($context2);
        $this->assertEquals($machine->getCurrentState()->getName(), State::STATE_NEW);
        $this->assertTrue($machine->getCurrentState()->isInitial());
        $this->assertEquals($context2, $machine->getContext());
        $total = $machine->runToCompletion();
        $this->assertEquals(5, $total);
        $this->assertEquals($machine->getCurrentState()->getName(), State::STATE_DONE);
        $this->assertTrue($machine->getCurrentState()->isFinal());

        // switch to different machine for context
        try {
            $context3 = new Context(new Identifier(123, 'different machine'));
            $context3->setState(State::STATE_DONE);
            $machine->setContext($context3);
            $this->fail("cannot switch context with different machine");
        } catch (Exception $ex) {
            $this->assertEquals(Exception::SM_CONTEXT_DIFFERENT_MACHINE, $ex->getCode());
        }
    }

    #[Group('3.1')]
    #[Test]
    public function shouldBeAbleToAddToPersistenceLayerAndsetStateWithAndWithoutMessage()
    {
        $identifier = new Identifier('123', 'test-machine');
        $machine = new StateMachine(new Context($identifier));
        $a = new State('a');
        $b = new State('b', State::TYPE_INITIAL);
        $t = new Transition($b, $a, 'go');
        $machine->addTransition($t);
        // var_dump(Memory::get());
        $this->assertEquals($b, $machine->getCurrentState());
        $message = 'this is a message about the initial adding to the persistence layer: adding from ' . __METHOD__;
        $this->assertTrue($machine->add($message));
        $memory = new Memory();
        $storage = $memory->getStorageFromRegistry($identifier);
        $this->assertEquals($message, $storage->message, 'persisted with the message');
        $this->assertFalse($machine->add(), 'second addition will not work, since we already added.');
        $this->assertEquals($message, $storage->message, 'still persisted with the same message');
        $anotherMessage = 'foo-bar';
        $machine->go($anotherMessage);
        $storage = $memory->getStorageFromRegistry($identifier);
        $this->assertEquals($anotherMessage, $storage->message, 'persisted with another message');
        // var_dump(Memory::get());
        $this->assertEquals($a, $machine->getCurrentState());
        $machine->setState($b, "this is a message to be stored for why we set this state: testing, setting state to b");
        $this->assertEquals($b, $machine->getCurrentState());
        $machine->transition("b_to_a");
        $storage = $memory->getStorageFromRegistry($identifier);
        $this->assertNull($storage->message, 'persisted without message');

        try {
            $machine->setState(new State('state not known to machine'));
            $this->fail('should not come here');
        } catch (Exception $e) {
            $this->assertEquals(Exception::SM_UNKNOWN_STATE, $e->getCode());
        }
    }

    #[Group('3.1')]
    #[Test]
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

    #[Test]
    public function shouldBeAbleToUseRunAndCanTransitionAndTestStateTypes()
    {
        $object = new Context(new Identifier(Identifier::NULL_ENTITY_ID, Identifier::NULL_STATEMACHINE));
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

    #[Test]
    public function shouldThrowExceptionFromRuleOrCommand()
    {
        $context = new Context(new Identifier(54321, Identifier::NULL_STATEMACHINE));
        $machine = new StateMachine($context);

        $sNew = new State(State::STATE_NEW, State::TYPE_INITIAL);
        $sA = new State('a', State::TYPE_NORMAL);

        $tNewToA = new Transition($sNew, $sA, null, 'Izzum\Rules\ExceptionRule', Transition::COMMAND_NULL);
        $machine->addTransition($tNewToA);

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

    #[Group('not-on-production', 'plantuml')]
    #[Test]
    public function shouldCreatePlantUmlStateDiagram()
    {
        $machine = 'order-flow';
        $id = 123;
        $context = new Context(new Identifier($id, $machine));
        $machine = new StateMachine($context);
        $sNew = new State(State::STATE_NEW, State::TYPE_INITIAL);
        $sA = new State('order-confirmation', State::TYPE_NORMAL);
        $sB = new State('technical-delivery', State::TYPE_NORMAL);
        $sC = new State('contract-creation', State::TYPE_NORMAL);
        $sD = new State('services-activation', State::TYPE_NORMAL);
        $sDone = new State(State::STATE_DONE, State::TYPE_FINAL);

        $tNewToA = new Transition($sNew, $sA, null, Transition::RULE_TRUE, 'Izzum\Command\ValidateOrder');
        $tAToB = new Transition($sA, $sB, null, 'Izzum\Rules\IsReadyForDelivery', 'Izzum\Command\SendConfirmation');
        $tBToC = new Transition($sB, $sC, null, Transition::RULE_TRUE, 'Izzum\Command\TechnicalDelivery');
        $tBToD = new Transition($sB, $sD, null, Transition::RULE_FALSE, Transition::COMMAND_NULL);
        $tCToD = new Transition($sC, $sD, null, 'Izzum\Rules\ReadyForContract', 'Izzum\Command\CreateContract');
        $tDDone = new Transition($sD, $sDone, null, Transition::RULE_TRUE, 'Izzum\Command\ActivateServices');

        $machine->addTransition($tNewToA);
        $machine->addTransition($tAToB);
        $machine->addTransition($tBToC);
        $machine->addTransition($tBToD);
        $machine->addTransition($tCToD);
        $machine->addTransition($tDDone);

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
        $transitions = [];

        $new = new State('new', State::TYPE_INITIAL, State::COMMAND_EMPTY, State::COMMAND_NULL);
        $new->setDescription("the initial state");
        $initialize = new State('initialize', State::TYPE_NORMAL, "Izzum\Command\InitializeCommand");
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
        $done = new State('done', State::TYPE_FINAL, "Izzum\Command\AnEntryCommand");

        $ni = new Transition($new, $initialize, null, Transition::RULE_TRUE, 'Izzum\Command\Initialize');
        $ni->setDescription("initialize the coffee machine");
        $transitions [] = $ni;
        $transitions [] = new Transition($initialize, $cup, null, Transition::RULE_TRUE, 'Izzum\Command\DropCup');
        $transitions [] = new Transition($cup, $coffee, null, Transition::RULE_TRUE, 'Izzum\Command\AddCoffee');
        $transitions [] = new Transition($coffee, $sugar, null, 'Izzum\Rules\WantsSugar', 'Izzum\Command\AddSugar');
        $transitions [] = new Transition($sugar, $coffee, null, Transition::RULE_TRUE, Transition::COMMAND_NULL);
        $transitions [] = new Transition($coffee, $milk, null, 'Izzum\Rules\WantsMilk', 'Izzum\Command\AddMilk');
        $transitions [] = new Transition($milk, $coffee, null, Transition::RULE_TRUE, Transition::COMMAND_NULL);
        $transitions [] = new Transition($coffee, $spoon, null, 'Izzum\Rules\MilkOrSugar', 'Izzum\Command\AddSpoon');
        $transitions [] = new Transition($coffee, $done, null, 'Izzum\Rules\CoffeeTakenOut', 'Izzum\Command\Cleanup');
        $transitions [] = new Transition($spoon, $done, null, 'Izzum\Rules\CoffeeTakenOut', 'Izzum\Command\CleanUp');

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
        $this->assertStringContainsString("@startuml", $result);
        $this->assertStringContainsString("@enduml", $result);
        $this->assertStringContainsString("new", $result);
        $this->assertStringContainsString("rule", $result);
        $this->assertStringContainsString("command", $result);
        $this->assertStringContainsString("_to_", $result);
    }

    #[Test]
    public function shouldBeAbleToUseCallablesOnEntity()
    {
        $model = new CallableHandler();

        // pass the model to the builder that uses that model as entity
        $builder = new ModelBuilder($model);

        $object = new Context(new Identifier(Identifier::NULL_ENTITY_ID, Identifier::NULL_STATEMACHINE), $builder);
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
        $expected = [
            $machine->getTransition('new_to_a'),
        ];
        $this->assertEquals($expected, $model->oncheckcantransition);
        $this->assertEquals($expected, $model->onexitstate);
        $this->assertEquals($expected, $model->ontransition);
        $this->assertEquals($expected, $model->onenterstate);
    }
}

// implements all the callables that can be called as part of a transition
// and lets us test if the right parameters are passed
class CallableHandler
{
    public $oncheckcantransition;
    public $onexitstate;
    public $ontransition;
    public $onenterstate;

    public function __construct(public $allow = true) {}

    public function onExitState($identifier, $transition)
    {
        $this->onexitstate = [
            $transition,
        ];
    }

    public function onCheckCanTransition($identifier, $transition)
    {
        $this->oncheckcantransition = [
            $transition,
        ];
        return $this->allow;
    }

    public function onTransition($identifier, $transition)
    {
        $this->ontransition = [
            $transition,
        ];
    }

    public function onEnterState($identifier, $transition)
    {
        $this->onenterstate = [
            $transition,
        ];
    }
}

namespace Izzum\StateMachine;

/**
 * helper class that implements the 'hook' methods
 * @author rolf
 *
 */
class SubClassedStateMachine extends StateMachine
{
    #[\Override]
    protected function _onCheckCanTransition(Transition $transition): bool
    {
        //only block a specific transition
        if ($transition->getName() == 'b_to_c') {
            return false;
        }
        return true;
    }

    protected function _onExitState(Transition $transition): void {}
    protected function _onTransition(Transition $transition): void {}
    protected function _onEnterState(Transition $transition): void {}
}
