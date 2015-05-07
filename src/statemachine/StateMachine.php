<?php
namespace izzum\statemachine;
use izzum\statemachine\Context;
use izzum\statemachine\State;
use izzum\statemachine\Transition;
use izzum\statemachine\Exception;
/**
 * StateMachine class.
 * 
 * The statemachine is used to execute transitions from one state to another state for
 * an entity that represents a domain object by applying guard logic and transition logic.
 * 
 * The implementation details of this machine make it that it can act both as
 * a mealy machine and as a moore machine. the concepts can be mixed and matched.
 * 
 * An application domain specific object can be created by means of a Context 
 * instance and it's associated EntityBuilder.
 * 
 * Each transition will take place only if the transition guard (Rule) allows it.
 * Each transition will then execute specific logic (Command).
 * 
 * This whole package strives to follow the open/closed principle, making it
 * open for extension (adding your own logic through subclassing) but closed 
 * for modification. For that purpose, we provide Loader interfaces, Builders and
 * Persistence Adapters for the backend implementation.
 * 
 * Following the same philosophy: if you want more functionality in the statemachine,
 * you can use the provided methods and override them in your subclass. multiple
 * hooks are provided.
 * 
 * The statemachine acts as a manager/service to tie all objects it works with together.
 * 
 * The statemachine should be loaded with States and Transitions, which define
 * from what state to what other state transitions are allowed. The transitions 
 * are specified by a guard clause (in the form of a business Rule instance) and transition
 * logic (in the form of a Command instance).
 * 
 * Transitions can also trigger an exit action for the current state and an entry 
 * action for the new state (also via Command instances)
 * 
 * We have provided a fully functional, normalized and indexed set of tables
 * for the postgresql relational database to function as a backend to store all
 * relevant information for a statemachine.
 * 
 * The Context object contains a reference to an underlying Entity domain model (eg:
 * an order, a customer etc) which is injected at runtime into the Rule and 
 * the Command associated with the transitions.
 * 
 * The Rule checks if the domain model (or it's derived data) applies and therefore
 * allows the transition, after which the Command is executed that can actually
 * alter data in the underlying domain models, call services etc.
 * 
 * - You can use a subclass of the AbstractFactory to get a StateMachine, since that
 * 		will put the creation of all the relevant Contextual classes in a reusable model.
 * - Alternatively, subclass a statemachine and build the full Context in the
 * 		constructor of your subclass (which could be a domain model itself)
 * - Alternatively, use object composition to instantiate and build a statemachine
 * 		in a domain model and build the full Context there
 * 
 * All high level interactions that a client conducts with a statemachine
 * should expect exceptions. Exceptions that bubble up from this statemachine are
 * always izzum\statemachine\Exception types.
 * 
 * 
 * A good naming convention for states is to use lowercase-hypen-seperated names:
 *          new
 *          waiting-for-input
 *          starting-order-process
 *          enter-cancel-flow
 *          done
 * 
 * A good naming convention for transitions is to bind the input and exit state
 * with the string '_to_' which is done automatically by this package.
 * 
 * 
 * @author Rolf Vreijdenberger
 * @link https://en.wikipedia.org/wiki/Finite-state_machine
 * @link https://en.wikipedia.org/wiki/Moore_machine
 * @link https://en.wikipedia.org/wiki/Mealy_machine
 * @link https://en.wikipedia.org/wiki/Open/closed_principle
 *
 * @see izzum\command\Command
 * @see izzum\rules\Rule
 */
class StateMachine {

    /**
     * The context instance that is able to construct the stateful entity.
     *
     * @var Context
     */
    private $context;
    

    /**
     * The available states.
     * A hashmap where the key is the state name and the value is
     * the State.
     *
     * @var State[]
     */
    private $states = array();

    /**
     * The available transitions.
     * A hashmap where the key is the transition name and the value is
     * the Transition.
     *
     * @var Transition[]
     */
    private $transitions = array();
    

    
     /**
      * Preferably the AbstractFactory (or a subclass) would be used to 
      * create a statemachine associated with a stateful context that has been 
      * fully configured
      * 
      * @param Context $context a fully configured context providing all the relevant parameters/dependencies
      * 	to be able to run this statemachine for an entity.
     */
    public function __construct(Context $context)
    {
        //sets up bidirectional association
        $this->setContext($context);
    }
    
    
    /**
     * Check if a transition is possible by using the transition name.
     * @param string $transition_name convention: <state-from>_to_<state-to>
     * @return boolean
     * @throws Exception in case something went wrong. The exceptions are logged.
     */
    public function canTransition($transition_name)
    {
        return $this->_canTransition($transition_name);
    }
    
    
    /**
     * Check if a transition is possible by using the transition name.
     * @param string $transition_name convention: <state-from>_to_<state-to>
     * @param string $event
     * @return boolean
     * @throws Exception in case something went wrong. The exceptions are logged.
     */
    private function _canTransition($transition_name, $event = null)
    {
    	//use the normal transition logic AND use our custom Rule based transition logic
    	try {
    
    		if(!$this->getCurrentState()->hasTransition($transition_name)) {
    			return false;
    		}
    
    		$transition = $this->getTransition($transition_name);
    		if($transition === null) {
    			throw new Exception(sprintf("transition '%s' has not been found",
    					$transition_name), Exception::SM_NO_TRANSITION_FOUND);
    		}
    
    		//possible hook so your application can place an extra guard for the transition.
    		if(!$this->onBeforeCanTransition($transition, $event)) {
    			return false;
    		}
    		//this will check the Rule defined for the transition.
    		//if the Rule applies, then this is seen as a green light to start the transition.
    		return $transition->can($this->getContext());
    	} catch (Exception $e) {
    		//already a statemachine exception, just rethrow
    		throw $e;
    	} catch (\Exception $e) {
    		//a non statemachine type exception, wrap it and throw
    		$e = new Exception($e->getMessage(), Exception::SM_CAN_FAILED, $e);
    		throw $e;
    	}
    }

    /**
     * Apply a transition by name.
     * If the transition is not possible (we check the 'Rule' guard to see if the 
     * machine is allowed to transition) it will throw an exception.
     * If it is allowed:
     * - perform state exit logic for the current state (optional)
     * - perform transition logic
     * - perform state entry logic for the new state (optional)
     * 
     * this type of handling is found in moore machines.
     * @link https://en.wikipedia.org/wiki/Moore_machine
     * 
     * @param string $transition_name convention: <state-from>_to_<state-to>
     * @throws Exception in case something went wrong.
     *     An exception will lead to a failed transition and the failed
     *     transition will lead to a notification to the Context and it's adapter
     * @return void
     */
    public function transition($transition_name) {
        $this->_transition($transition_name, true);
    }    
    
    /**
     * Try to apply a transition from the current state by handling an event string as a trigger. 
     * If the event is applicable for a transition then that transition from the current state will be applied.
     * 
     * The event string itself will be available at runtime via the statemachine itself (no need
     * to temporarily store it) and will be set on the rules and command (if they implement 
     * the 'setEvent($event)' method)
     * 
     * This type of (event/trigger) handling is found in mealy machines.
     * @link https://en.wikipedia.org/wiki/Mealy_machine
     * 
     * @link http://martinfowler.com/books/dsl.html for event handling statemachines
     * 
     * @param string $event in case the transition will be triggered by an event code (mealy machine)
     * @return bool true in case a transition was triggered by the event, false otherwise
     * @throws Exception in case the transition is not possible via the guard logic (Rule)
     */
    public function handle($event) 
    {
    	$transition = $this->getCurrentState()->getTransitionTriggeredByEvent($event);
    	if($transition) {
	    	$this->_transition($transition->getName(), true, $event);
	    	return true;
    	} 
    	return false;
    }
    
    /**
     * check if the current state has a transition that can be triggered by an event
     * @param string $event
     * @return boolean
     */
    public function hasEvent($event) 
    {
    	$transition = $this->getCurrentState()->getTransitionTriggeredByEvent($event);
    	if($transition) {
    		return true;
    	}
    	return false;
    }
    
    /**
     * checks if a transition is possible or allowed for the current state when triggered
     * by an event
     * @param string $event
     * @return boolean
     */
    public function canHandle($event)
    {
    	$transition = $this->getCurrentState()->getTransitionTriggeredByEvent($event);
    	if($transition) {
    		return $this->canTransition($transition->getName());
    	}
    	return false;
    }

    
    /**
     * Have the statemachine do the first possible transition.
     * The first possible transition is based on the configuration of
     * Rules and the current state of the statemachine.
     * 
     * The current state of the statemachine is determined by
     * querying the Context (via it's associated persistence layer).
     * 
     * TRICKY: Be careful when using this function,
     * since all rules must be mutually exclusive! If not, you might end up
     * performing the state transition with priority n when you really want 
     * to perform transition n+1.
     *
     * An alternative is to use the 'transition' method: 
     *     $statemachine->transition('a_to_b');
     * So you are always sure that you are actually doing the intented transition
     * instead of relying on the configuration and rules (which *might* not 
     * be correctly implemented, leading to transitions that would normally not
     * be executed).
     * 
     * @return boolean true if a transition was applied.
     * @throws Exception in case something went wrong. The exceptions are logged.
     * 
     */
    public function run()
    {
        try {
            //get currently available transitions
            $transitions = $this->getCurrentState()->getTransitions();
            foreach($transitions as $transition){
                try {
                    $can = $this->canTransition($transition->getName());
                } catch (\Exception $e) {
                    //we handle a transition exception here, since we
                    //check $this->can() outside the $this->_transition() method
                    //where all the hooks are.
                    //this should be refactored to an implementation
                    //where all the hooks are in a single routine
                    $this->handleTransitionException($e, $transition->getName());
                    throw $e;
                }
                
                //we can transition
                if($can) {
                    //don't check if we can transition, since we just did that.
                    $this->_transition($transition->getName(), false);
                    //transition done
                    return true;
                }
            }
        } catch (Exception $e) {
            //already a statemachine exception, just rethrow
            throw $e;
        } catch (\Exception $e) {
            //a non statemachine type exception, wrap it and throw
            $e = new Exception($e->getMessage(), Exception::SM_RUN_FAILED, $e);
            throw $e;
        }
        //no transition done
        return false;
    }

    
    /**
     * run a statemachine until it cannot run any transition in the current state 
     * or until it is in a final state.
     * 
     * when using cyclic graphs, you could get into an infinite loop between states. 
     * design your machine correctly.
     * 
     * preconditions: 
     *     - the transitions should be defined for each state
     *     - the transitions should be allowed by the rules
     *     - the transitions should be able to execute
     * @throws Exception in case something went wrong.
     * @return int the number of sucessful transitions made. 
     */
    public function runToCompletion()
    {
        $transitions = 0;
        try {
            $run = true;
            while ($run) {
	       //run the first transition possible       
               $run = $this->run();
               if($run) {
                   //increment after succesful transition
                   $transitions++;
               }
            }
        } catch (Exception $e) {
            //already a statemachine exception, just rethrow, it is logged
            throw $e;
        } catch (\Exception $e) {
            //a non statemachine type exception, wrap it and throw
            $e = new Exception($e->getMessage(), $e->getCode(), $e);
            throw $e;
        }
        return $transitions;
    }
    
    /**
     * Perform a transition by specifiying the transitions' name from a state that the 
     * transition is allowed to run.
     * 
     * @param string $transition_name convention: <state-from>_to_<state-to>
     * @param boolean $check_allowed optional: to specify if we want to do the check to see
     *      if the transition is allowed or not. This is a performance optimalization
     *      so in case we call 'can' directly, we can use 'transition' directly 
     *      after that without doing the checks (including expensive Rules) twice.
     * @param string $event optional in case the transition was triggered by an event code (mealy machine)
     * @throws Exception
     * @return void
     * https://en.wikipedia.org/wiki/Template_method_pattern
     */
    protected function _transition($transition_name, $check_allowed = true, $event = null) 
    {
        try {
            if($check_allowed === true) {
                if(!$this->_canTransition($transition_name, $event)) {
                    //we tried a transition, but it is not allowed
                    throw new Exception(
                            sprintf("Transition '%s' not allowed from state '%s'", 
                                    $transition_name, $this->getContext()->getState()),
                            Exception::SM_TRANSITION_NOT_ALLOWED);
                }
            }
            $transition = $this->getTransition($transition_name);
	    	//state exit action: performed when exiting the state
            $this->onExitState($transition, $event);
            //the transition is performed, with the associated logic
            $this->onTransition($transition, $event);
            //state entry action: performed when entering the state
            $this->onEnterState($transition, $event);
        } catch (Exception $e) {
            $this->handleTransitionException($e, $transition_name);
            //already a statemachine exception, just rethrow
            throw $e;
        } catch (\Exception $e) {
            //a non statemachine type exception, wrap it and throw
            $e = new Exception($e->getMessage(), Exception::SM_TRANSITION_FAILED, $e);
            //possible hook for subclasses to implement
            $this->handleTransitionException($e, $transition_name);
            throw $e;
        }
    }
    
    /**
     * the exit state action method
     * @param Transition $transition
     * @param string $event
     */
    private function onExitState(Transition $transition, $event = null)
    {
    	//possible hook for subclasses to implement
    	$this->preProcess($transition, $event);
    	
    	//possible hook for subclasses to implement
    	$this->_onExitState($transition, $event);
    	if($event) {
    		$this->callEntityMethod('onLeave' . $this->getNormalizedName($event), $transition);
    	}
    	$transition->getStateFrom()->exitAction($this->getContext(), $event);
    }
    
    /**
     * hook method. override in subclass if necessary
     * @param Transition $transition
     * @param string $event
     */
    protected function _onExitState(Transition $transition, $event = null) {}
    
    /**
     * the enter state action method
     * @param Transition $transition
     * @param string $event
     */
    private function onEnterState(Transition $transition, $event = null)
    {
    	//possible hook for subclasses to implement
    	$this->_onEnterState($transition, $event);
    	if($event) {
    		$this->callEntityMethod('onEnter' . $this->getNormalizedName($event), $transition);
    	}
        $transition->getStateTo()->entryAction($this->getContext(), $event);
        
        //possible hook for subclasses to implement
        $this->postProcess($transition, $event);
    }
    
    /**
     * hook method. override in subclass if necessary
     * @param Transition $transition
     * @param string $event
     */
    protected function _onEnterState(Transition $transition, $event = null) {}
    
    /**
     * the transition action method
     * @param Transition $transition
     * @param string $event
     */
    private function onTransition(Transition $transition, $event = null)
    {
    	//possible hook for subclasses to implement
    	$this->_onTransition($transition, $event);
    	$this->callEntityMethod('onTransition', $transition->getName(), $event);
    	if($event) {
    		$this->callEntityMethod('onTransition' . $this->getNormalizedName($event), $transition);
    	}
    	$transition->process($this->getContext(), $event);
    	$this->setCurrentState($transition->getStateTo());
    }
    
    /**
     * hook method. override in subclass if necessary
     * @param Transition $transition
     * @param string $event
     */
    protected function _onTransition(Transition $transition, $event = null) {}
        
    /**
     * Add a fully configured transition to the machine.
     * 
     * the order in which transitions are added actually does matter.
     * it matters insofar that when a StateMachine::run() is called,
     * the first Transition for the current State will be tried first.
     * 
     * Since a transition has complete knowledge about it's states,
     * the addition of a transition will also trigger the adding of the
     * to and from state on this class.
     * 
     * this method should be package visibility for the Loader, but since php
     * does not support that, it can also be used to add a Transition directly.
     * Make sure that transitions that share a common State use the same instance
     * of that State object and vice versa.
     * 
     * @param Transition $transition
     */
    public function addTransition(Transition $transition) 
    {
    	//add/overwrite transition
        $this->transitions[$transition->getName()] = $transition;
        
        $from = $transition->getStateFrom();
        if(!$this->getState($from->getName())) {
            $this->addState($from);
        }
        
        
        //transitions create bidirectional references to the States
        //when they are made, but here the States that are set on the machine
        //can actually be different instances from different transitions (eg:
        //a->b and a->c => we now have two State instances of a)
        //we therefore need to merge the transitions on the existing states.
        //The LoaderArray class does this for us by default, but we do it here
        //too, just in case a client decides to call the 'addTransition' method
        //directly without a proper loader.
        $state = $this->getState($from->getName());
        if(!$state->hasTransition($transition->getName())) {
            $state->addTransition($transition);
        }
        
        $to = $transition->getStateTo();
        if(!$this->getState($to->getName())) {
            $this->addState($to);
        }
    }
    
    
    /**
     * change the context for a statemachine that already has a context.
     * When the context is changed, but it is for the same statemachine (with 
     * the same transitions), the statemachine can be used directly with the
     * new context.
     * @param Context $context
     */
    public final function changeContext(Context $context)
    {
        $this->setContext($context);
    }
    

    /**
     * Get the current context
     * @return Context
     */
    public function getContext()
    {
        return $this->context;
    }

    /**
     * gets the current state
     * @return State
     * @throws Exception in case there is no current state found
     */
    public function getCurrentState()
    {
        $state = $this->getState($this->getContext()->getState());
        if(!$state) {
            //possible wrong configuration
            throw new Exception(
                    sprintf($this->toString() . " current state not found for state '%s'. transitions/states loaded?", 
                            $this->getContext()->getState()), 
                    Exception::SM_NO_CURRENT_STATE_FOUND);
        }
        return $state;
    }
    
     /**
     * Get the initial state, the only state with type State::TYPE_INITIAL
     * @return State
     * @throws Exception
     */
    public function getInitialState() {
        $transitions = $this->getTransitions();
        foreach($transitions as $transition) {
            if($transition->getStateFrom()->isInitial())
            {
                return $transition->getStateFrom();
            }
        }
        throw new Exception('no initial state found, bad configuration', 
                Exception::SM_NO_INITIAL_STATE_FOUND);
    }
    
    /**
     * get the machine name
     * @return string
     */
    public function getMachine()
    {
        return $this->getContext()->getMachine();
    }
    
     /**
      * All known/loaded transitions for this statemachine
     * @return Transition[]
     */
    public function getTransitions()
    {
        return $this->transitions;
    }

    /**
     * All known/loaded states for this statemachine
     * @return State[]
     */
    public function getStates()
    {
        return $this->states;
    }
    
    /**
     * get a state by name.
     * @param string $name
     * @return State
     */
    public function getState($name) 
    {
        return isset($this->states[$name]) ? $this->states[$name] : null;
    }
    
    /**
     * get a transition by name.
     * @param string $name
     * @return Transition
     */
    public function getTransition($name)
    {
        return isset($this->transitions[$name]) ? $this->transitions[$name] : null;
    }
    
    public function toString()
    {
        return get_class($this) . ": [" . $this->getContext()->getId(true) ."]";
    }
    
    /**
     * helper method to derive a name from an event that results in a valid
     * method name that can be used to call the different transition callbacks on the $entity
     * @param string $event
     * @return string
     */
    protected function getNormalizedName($event) {
    	//override if necessary
    	return ucfirst($event);
    }

    /**
     * Template method to call a possible hook and to call a possible method 
     * defined on the domain object/contextual entity
	 *
     * @param Transition $transition
     * @param string $event an event name if the transition was triggered by an event.
     * @return boolean if false, the transition and its' associated logic will not take place
     */
    private function onBeforeCanTransition(Transition $transition, $event = null) 
    {
    	$hook_result = $this->_onBeforeCanTransition($transition, $event);
   		if(!$hook_result) return false;
   		if($event) {
   			return $this->callEntityMethod('onCan' . $this->getNormalizedName($event), $transition);
   		}
     	return true;
    }
    
    /**
     * Before a transition is checked to be possible, you can add domain
     * specific logic here by overriding this method in a subclass.
     * In an overriden implementation of this method you can stop the transition 
     * by returning false from this method.
     * 
     * @param Transition $transition
     * @param string $event an event name if the transition was triggered by an event.
     * @return boolean if false, the transition and it's associated logic will not take place
     */
    protected function _onBeforeCanTransition(Transition $transition, $event = null)
    {
        //eg: dispatch an event and see if it is rejected by a listener
        return true;
    	
    }
    
    /**
     * Helper method to generically call methods on the $entity.
     * Try to call a method on the contextual entity / domain model ONLY IF the method exists.
     * any arguments passed to this method will be passed on to the method called
     * on the entity.
     * 
     * @param string $method the method to call on the entity
     * @return boolean
     */
    private function callEntityMethod($method)
    {
    	//return true by default
    	$output = true;
    	$entity = $this->getContext()->getEntity();
    	if(method_exists($entity, $method)) {
	    	$args = array_shift(func_get_args());
    		$output = (bool) call_user_func_array(array($entity,$method), $args);
    	}
    	return $output;
    }
    
    /**
     * called whenever an exception occurs from inside '_transition()'
     * can be used for logging etc.
     * 
     * @param Exception $e
     * @param string $transition_name
     */
    protected function handleTransitionException(Exception $e, $transition_name) {
        //override if necessary to log exceptions or to add some extra info
        //to the underlying storage facility (for example, an exception will
        //not lead to a transition, so this can be used to indicate a failed 
        //transition in some sort of history structure)
        $this->getContext()->setFailedTransition($e, $transition_name);
    }

     /**
     * Called before each transition will run and execute the associated transition logic.
     * A hook to implement in subclasses if necessary, to do stuff such as
     * event handling, locking an entity, logging, cleanup etc.
     * @param Transition $transition
     * @param string $event in case the transition was triggered by an event code (mealy machine)
     */
    protected function preProcess(Transition $transition, $event) {
        //dispatch events, log, lock entity, cleanup,
        //begin transaction via persistance layer etc.
    }
    
    /**
     * Called after each transition has run and has executed the associated transition logic..
     * a hook to implement in subclasses if necessary, to do stuff such as
     * event handling, unlocking an entity, logging, cleanup etc.
     * @param Transition $transition
     * @param string $event in case the transition was triggered by an event code (mealy machine)
     */
    protected function postProcess(Transition $transition, $event) {
        //dispatch events, log, unlock entity, cleanup, 
        //commit transaction via persistence layer etc.
    }
    
     /**
     * Add a state. 
     * @param State $state
     */
    protected function addState(State $state)
    {
        $this->states[$state->getName()] = $state;
    }

    /**
     * set the context on the statemachine and provide bidirectional association
     * @param Context $context
     */
    protected final function setContext(Context $context)
    {
        if($this->getContext()){
            if($this->getContext()->getMachine() !== $context->getMachine()) {
                throw new Exception(
                    sprintf("Trying to set context for a different machine. currently '%s' and new '%s'",
                        $this->getContext()->getMachine(), $context->getMachine()), 
                    Exception::SM_CONTEXT_DIFFERENT_MACHINE);
            }
        }
        $this->context = $context;
        $context->setStateMachine($this);
    }

    
    /**
     * sets the state
     * @param State $state
     */
    protected function setCurrentState(State $state) {
        $this->getContext()->setState($state->getName());
    }
    
    
    /**
     * This method is used to trigger an event on the statemachine and
     * delegates the actuall call to the 'handle' method
     * 
     * $statemachine->triggerAnEvent() actually calls $this->handle('triggerAnEvent')
     * 
     * This is also very useful if you use object composition to include a statemachine 
     * in your domain model. The domain model itself can then use it's own __call
     * implementation to directly delegate to the statemachines' __call method
     * 
     * $model->walk() will actuall call $model->statemachine->walk() which will
     * then call $model->statemachine->handle('walk');
     * 
     * 
     * @param string $name the name of the unknown method called
     * @param array $arguments an array of arguments (if any)
     * @return bool true in case a transition was triggered by the event, false otherwise
     * @throws Exception in case the transition is not possible via the guard logic (Rule)
     * @link https://en.wikipedia.org/wiki/Object_composition
     */
    public function __call($name , $arguments)
    {
    	return $this->handle($name);
    }


}
