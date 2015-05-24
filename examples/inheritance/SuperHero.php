<?php
namespace izzum\examples\inheritance;
use izzum\statemachine\StateMachine;
use izzum\statemachine\Transition;
use izzum\statemachine\Context;
use izzum\statemachine\Identifier;
use izzum\statemachine\State;
use izzum\statemachine\builder\ModelBuilder;
/**
 * Example class that uses the 'inheritance mode' as one of the four usage models for the statemachine.
 * The other three usage models being standalone, composition and delegation.
 * @author rolf
 *
 */
class SuperHero extends StateMachine {
	public function __construct()
	{
		//create machine. context defaults to in-memory state handling
		//also use the ModelBuilder and provide a reference to this instance,
		//so the callbacks will be called on this instance
		$context = new Context(new Identifier("inheritance-example", "superhero-machine"), new ModelBuilder($this));
		parent::__construct($context);
		
		$b = function() {echo "b exit start". PHP_EOL;};
		$a = function() {echo "a enter start". PHP_EOL;};
		//define the states and the state types (with 1 initial state)
		$start = new State('start', State::TYPE_INITIAL, null, null, $a, $b);
		$normal = new State('normal');
		$super = new State('being-super');
		$posing = new State('posing');
		$fighting = new State('fighting');
		$resqueing = new State('resqueing');
		$flying = new State('flying');
		$done = new State('done', State::TYPE_FINAL);
		
		//add transitions to this class (a subclass of statmachine), with event names
		$c = function ($context, $event) {echo "calling transition closure c for $context and $event" . PHP_EOL;};
		function jo($context, $event) {
		    echo "calling jo for $context and $event" . PHP_EOL;
		}
		$c = function($context, $event) {jo($context, $event);};
		$this->addTransition(new Transition($start, $normal, 'start', null, null, $c));
		$this->addTransition(new Transition($normal, $super, 'super'));
		$this->addTransition(new Transition($normal, $done, 'done'));
		$this->addTransition(new Transition($super, $fighting, 'fight'));
		$this->addTransition(new Transition($super, $posing, 'pose'));
		$this->addTransition(new Transition($super, $resqueing, 'rescue'));
		$this->addTransition(new Transition($super, $flying, '$flying'));
	}
	
	protected function _onExitState(Transition $transition, $event) {
		echo '_onExitState: ' . $transition . ', event: ' . $event . PHP_EOL;
	}
	
	protected function _onTransition(Transition $transition, $event) {
		echo '_onTransition: ' . $transition . ', event: ' . $event . PHP_EOL;
		
	}
	
	protected function _onEnterState(Transition $transition, $event) {
		echo '_onEnterState: ' . $transition . ', event: ' . $event . PHP_EOL;
	}
	
	protected function _onCheckCanTransition(Transition $transition, $event) {
		echo '_onCheckCanTransition: ' . $transition . ', event: ' . $event . PHP_EOL;
		return true;
	}
	
	public function onCheckCanTransition(Transition $transition, $event) {
	    echo 'onCheckCanTransition: ' . $transition . ', event: ' . $event . PHP_EOL;
	    return true;
	}
	
	public function onStart(Transition $transition, $event)
	{
	    echo 'onStart: ' . $transition . ', event: ' . $event . PHP_EOL;
	}
	
	public function onEvent(Transition $transition, $event)
	{
	    echo 'onEvent: ' . $transition . ', event: ' . $event . PHP_EOL;
	}
	
	public function onResque(Transition $transition, $event)
	{
	    echo 'onResque: ' . $transition . ', event: ' . $event . PHP_EOL;
	}
	
	public function onTransition(Transition $transition, $event) {
	    echo 'onTransition: ' . $transition . ', event: ' . $event . PHP_EOL;
	
	}
	
}