<?php
namespace izzum\examples\inheritance;
use izzum\statemachine\StateMachine;
use izzum\statemachine\Transition;
use izzum\statemachine\Context;
use izzum\statemachine\Identifier;
use izzum\statemachine\State;
use izzum\statemachine\builder\ModelBuilder;
use izzum\statemachine\builder\izzum\statemachine\builder;
use izzum\statemachine\utils\Utils;
/**
 * Example class that uses the 'inheritance mode' as one of the four usage models for the statemachine.
 * The other three usage models being standalone, composition and delegation.
 * @author rolf
 *
 */
class SuperHero extends StateMachine {
    private $name;
    private $alias;
    private $statistics = array();
    
	public function __construct($name, $alias)
	{
	    $this->name = $name;
	    $this->alias = $alias;
		//create machine with unique superhero name. 
		//associate the domain object (that will be used on callables) with $this
		$context = new Context(new Identifier($name. ":" . $alias, "superhero-machine"), new ModelBuilder($this));
		//call parent constructor
		parent::__construct($context);

		
		
		//define the states and the state types, and some entry states
		$start = new State('start', State::TYPE_INITIAL);
		$callback_dress_normal_for_entering_state = array($this, 'changeIntoNormalClothes');
		$normal = new State('normal', State::TYPE_NORMAL, null, null, $callback_dress_normal_for_entering_state);
		$callback_entering_superhero_state = array($this, 'changeIntoCostume');
		$super = new State('superhero', State::TYPE_NORMAL, null, null, $callback_entering_superhero_state);
		$posing = new State('posing');
		$fighting = new State('fighting');
		$resqueing = new State('resqueing');
		$done = new State('done', State::TYPE_FINAL);
		
		//add transitions to this class (a subclass of statmachine), with event names
		$this->addTransition(new Transition($start, $normal, 'wakeup'));
		$this->addTransition(new Transition($normal, $done, 'done'));
		$this->addTransition(new Transition($super, $fighting, 'fight'));
		$this->addTransition(new Transition($super, $posing, 'pose'));
		$this->addTransition(new Transition($super, $resqueing, 'rescue'));
		//allow to go from super to every other state
		$this->addTransition(new Transition($super, new State('regex:/.*/')));
		//allow to go from every state to super
		$this->addTransition(new Transition(new State('regex:/.*/'), $super, 'beSuper'));
		//allow to go from every state to normal
		$this->addTransition(new Transition(new State('regex:/.*/'), $normal, 'standDown'));
		//allow to pose, rescue of fight from every state except start and normal
		$this->addTransition(new Transition(new State('not-regex:/start|normal/'), $posing, 'pose'));
		$this->addTransition(new Transition(new State('not-regex:/start|normal/'), $resqueing, 'resque'));
		$this->addTransition(new Transition(new State('not-regex:/start|normal/'), $fighting, 'fight'));
	}
	
	public function changeIntoCostume(SuperHero $entity, $event) {
	    echo $this->name . " is changing into ". $this->name ." superhero costume: enter " . $this->alias . PHP_EOL;
	}
	public function changeIntoNormalClothes(SuperHero $entity, $event) {
	    echo $this->name . " is changing into normal clothes. The human alter ego of ". $this->alias . PHP_EOL;
	    $this->printMyAwesomeness(false);
	}
	public function stopBeingSuper(SuperHero $entity, $event) {
	    $this->printMyAwesomeness(true);
	}
	
	private function printMyAwesomeness($as_superhero = true) {
	    $name = $as_superhero ? $this->alias : $this->name;
	    $output = $name . " says: ";
	    if(count($this->statistics) == 0) $output .= "... nothing done yet.";
	    foreach($this->statistics as $key=>$value) {
	        $output .= "I was $key for $value times. ";
	    }
	    $output .= PHP_EOL;
	    echo $output;
	}
	
	protected function _onExitState(Transition $transition) {
		//echo '_onExitState: ' . $transition . PHP_EOL;
	}
	
	protected function _onTransition(Transition $transition) {
		//echo '_onTransition: ' . $transition . PHP_EOL;
		
	}
	
	private function updateStatistics($state) {
	    if(!isset($this->statistics[$state])) {
	        $this->statistics[$state] = 0;
	    }
	    $this->statistics[$state] = $this->statistics[$state] + 1;
	}
	
	protected function _onEnterState(Transition $transition) {
		//echo '_onEnterState: ' . $transition . PHP_EOL;
		$state = $transition->getStateTo()->getName();
		switch($state) {
		    case "posing":
		    case "fighting":
		    case "resqueing":
		    case "superhero":
		    case "normal":
		        echo "$state ";
		        $this->updateStatistics($state);
		        break;
		    default:
		        break;
		}
	}
	
	protected function _onCheckCanTransition(Transition $transition) {
		//echo '_onCheckCanTransition: ' . $transition . PHP_EOL;
		return true;
	}
	
}