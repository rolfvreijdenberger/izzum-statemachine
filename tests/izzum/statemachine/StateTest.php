<?php
namespace izzum\statemachine;
use izzum\statemachine\utils\EntityNull;
use izzum\command\ExceptionCommand;
use izzum\command\Command;
use izzum\command\Null;

/**
 * @group statemachine
 * @group state
 * @author rolf
 *
 */
class StateTest extends \PHPUnit_Framework_TestCase {
    
    /**
     * @test
     */
    public function shouldWorkAsExpected(){
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
        $this->assertEquals($t1, $trans[0], 'in order transitions were created');
        $this->assertEquals($t2, $trans[1], 'in order transitions were created');
        $this->assertTrue($state->isInitial());
        $this->assertFalse($state->isFinal());
        $this->assertFalse($state->isNormal());
        $this->assertEquals($name, $state->getName());
        $this->assertEquals(State::TYPE_INITIAL,$state->getType());
        $this->assertTrue($state->hasTransition($t1->getName()));
        $this->assertTrue($state->hasTransition($t2->getName()));
        $this->assertFalse($sb->hasTransition($t1->getName()),'no bidirectional association on incoming transition');
        $this->assertFalse($sb->hasTransition($t2->getName()),'no bidirectional association on incoming transition');
        $this->assertFalse($sc->hasTransition($t1->getName()),'no bidirectional association on incoming transition');
        $this->assertFalse($sc->hasTransition($t2->getName()),'no bidirectional association on incoming transition');
        
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
    public function shouldReturnType(){
        $name = 'state-izzum';
        $state = new State($name, State::TYPE_INITIAL);
        $this->assertTrue($state->isInitial());
        $this->assertFalse($state->isFinal());
        $this->assertFalse($state->isNormal());
        
        $state = new State($name, State::TYPE_NORMAL);
        $this->assertFalse($state->isInitial());
        $this->assertFalse($state->isFinal());
        $this->assertTrue($state->isNormal());
        
        $state = new State($name, State::TYPE_FINAL);
        $this->assertFalse($state->isInitial());
        $this->assertTrue($state->isFinal());
        $this->assertFalse($state->isNormal());
    }
    
    /**
     * @test
     */
    public function shouldReturnTransitionForEvent(){
    	$a = new State('a');
    	$b = new State('b');
    	$c = new State('c');
    	$tab = new Transition($a, $b);
    	$tac = new Transition($a, $c);
    	$tbc = new Transition($b, $c);
    	$tba = new Transition($b, $a);
    	$tbb = new Transition($b, $b);//self transition
    	$tba->setEvent('b-a');//b is the source state
    	$tbb->setEvent('event-self');
    	
    	
    	$this->assertEquals($tba, $b->getTransitionTriggeredByEvent('b-a'));
    	$this->assertEquals($tbb, $b->getTransitionTriggeredByEvent('event-self'));
    	$this->assertNull($a->getTransitionTriggeredByEvent('b-a'));
    	$this->assertNull($a->getTransitionTriggeredByEvent('even-self'));
    	$this->assertNull($a->getTransitionTriggeredByEvent('event-self'));
    	$this->assertNull($b->getTransitionTriggeredByEvent('bogus'));
    	$this->assertNull($a->getTransitionTriggeredByEvent('bogus'));
    	$this->assertNull($c->getTransitionTriggeredByEvent('bogus'));
    	$this->assertNull($c->getTransitionTriggeredByEvent('event-self'));
    	$this->assertNull($c->getTransitionTriggeredByEvent('b-a'));
    	
    	
    }
    
    
    /**
     * @test
     */
    public function shoulExecuteEntryAndExitAction()
    {
    	//scenario 1
    	$context = new Context(new Identifier('1', 'test'));
    	$command_name = 'izzum\command\ExceptionCommand';
    	$state = new State('a', State::TYPE_INITIAL, $command_name);
    	$this->assertEquals($command_name, $state->getEntryCommandName());
    	$this->assertEquals('', $state->getExitCommandName());
    	
    	try {
    		$state->entryAction($context);
    		$this->fail('should not come here');
    	} catch (\Exception $e) {
    		$this->assertEquals(Exception::COMMAND_EXECUTION_FAILURE, $e->getCode());
    	}

    	//null command
    	$state->exitAction($context);
    	
    	
    	
    	//scenario 2
    	$context = new Context(new Identifier('1', 'test'));
    	$command_name = 'izzum\command\ExceptionCommand';
    	$state = new State('a', State::TYPE_INITIAL, State::COMMAND_EMPTY, $command_name);
    	$this->assertEquals($command_name, $state->getExitCommandName());
    	$this->assertEquals('', $state->getEntryCommandName());
    	 
    	//null command
    	$state->entryAction($context);

    	try {
    		$state->exitAction($context);
    		$this->fail('should not come here');
    	} catch (\Exception $e) {
    		$this->assertEquals(Exception::COMMAND_EXECUTION_FAILURE, $e->getCode());
    	}
    }
    
    /**
     * @test
     */
    public function shoulFailInvalidAction()
    {
    	//scenario 1
    	$context = new Context(new Identifier('1', 'test'));
    	$command_name = 'izzum\command\bogus';
    	$state = new State('a', State::TYPE_INITIAL, $command_name);
    	$this->assertEquals($command_name, $state->getEntryCommandName());
    	$this->assertEquals('', $state->getExitCommandName());
    	 
    	try {
    		$state->entryAction($context);
    		$this->fail('should not come here');
    	} catch (\Exception $e) {
    		$this->assertEquals(Exception::COMMAND_CREATION_FAILURE, $e->getCode());
    	}
    
    	//null command
    	$state->exitAction($context);
    	 
    	 
    	 
    	//scenario 2
    	$context = new Context(new Identifier('1', 'test'));
    	$command_name = 'izzum\command\bogus';
    	$state = new State('a', State::TYPE_INITIAL, State::COMMAND_EMPTY, $command_name);
    	$this->assertEquals($command_name, $state->getExitCommandName());
    	$this->assertEquals('', $state->getEntryCommandName());
    
    	//null command
    	$state->entryAction($context);
    
    	try {
    		$state->exitAction($context);
    		$this->fail('should not come here');
    	} catch (\Exception $e) {
    		$this->assertEquals(Exception::COMMAND_CREATION_FAILURE, $e->getCode());
    	}
    	 
    }
    
    
    /**
     * @test
     */
    public function shouldSetEventOnEntryCommand()
    {
    	$context = new Context(new Identifier('1', 'test'));
    	$a = new State('a');
    	$b = new State('b');
    	$command = new EventCommand();
    	
    	//entry actions
    	$this->assertEquals(0, count($command->getEvents()));
    	$a->setEntryCommandName('izzum\statemachine\EventCommand');
    	$this->assertEquals(0, count($command->getEvents()));
    	$this->assertEquals('izzum\statemachine\EventCommand', $a->getEntryCommandName());
    	$this->assertEquals(State::COMMAND_EMPTY, $a->getExitCommandName());
    	$a->exitAction($context, $event);
    	$this->assertEquals(0, count($command->getEvents()));
    	$a->entryAction($context, '1');
    	$this->assertEquals(1, count($command->getEvents()));
    	$a->entryAction($context, '2');
    	$this->assertEquals(2, count($command->getEvents()));
    	$this->assertEquals(array(1,2), $command->getEvents());
    	
    	$command->reset();
    	$this->assertEquals(0, count($command->getEvents()));
    	
    }
    
    /**
     * @test
     */
    public function shouldSetEventOnExitCommand()
    {
    	$context = new Context(new Identifier('1', 'test'));
    	$a = new State('a');
    	$b = new State('b');
    	$command = new EventCommand();
    	 
    	//entry actions
    	$this->assertEquals(0, count($command->getEvents()));
    	$a->setExitCommandName('izzum\statemachine\EventCommand');
    	$this->assertEquals(0, count($command->getEvents()));
    	$this->assertEquals('izzum\statemachine\EventCommand', $a->getExitCommandName());
    	$this->assertEquals(State::COMMAND_EMPTY, $a->getEntryCommandName());
    	$a->entryAction($context, $event);
    	$this->assertEquals(0, count($command->getEvents()));
    	$a->exitAction($context, '1');
    	$this->assertEquals(1, count($command->getEvents()));
    	$a->exitAction($context, '2');
    	$this->assertEquals(2, count($command->getEvents()));
    	$this->assertEquals(array(1,2), $command->getEvents());
    	
    	$command->reset();
    	$this->assertEquals(0, count($command->getEvents()));
    	 
    }

}

//helper class
class EventCommand extends Null {
	static $event = array();
	public function setEvent($event) 
	{
		self::$event[] = $event;
	}
	public function getEvents()
	{
		return self::$event;
	}
	
	public function reset(){
		self::$event = array();
	}
}

