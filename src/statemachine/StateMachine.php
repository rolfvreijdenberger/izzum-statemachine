<?php
namespace izzum\statemachine;
use izzum\statemachine\Context;
use izzum\statemachine\State;
use izzum\statemachine\Transition;
use izzum\statemachine\Exception;
/**
 * StateMachine class.
 * 
 * The statemachine is used to do transitions from state to state for
 * an entity that represents a domain object. This application domain specific 
 * object can be created by means of a Context instance and it's 
 * associated EntityBuilder.
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
 * are specified by a guard clause -in the form of a business Rule- and a transition
 * Command.
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
 * allows the transition, after which the Command is executed and can actually
 * alter data in the underlying domain models, call services etc.
 * 
 * Preferably use a subclass of the AbstractFactory to get a StateMachine.
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
 * @see izzum\command\Command
 * @see izzum\rules\Rule
 * @link https://en.wikipedia.org/wiki/Finite-state_machine
 * @link https://en.wikipedia.org/wiki/Open/closed_principle
 *
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
     * Check if a transition is possible by using the transition name (convention: <state-from>_to_<state-to>)
     * @param string $transition_name
     * @return boolean
     * @throws Exception in case something went wrong. The exceptions are logged.
     */
    public function can($transition_name)
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
            
            //possible hook
            if(!$this->checkGuard($transition)) {
                return false;
            }
            //this will check the Rule defined for the transition
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
     * Apply a transition by name. (convention: <state-from>_to_<state-to>)
     * The transition should be possible (we check the 'rule' guard), else it will throw an exception. 
	 * 
     * 
     * @param string $transition_name the name of the transition (analagous to an 'event' name)
     * @throws Exception in case something went wrong.
     *     An exception will lead to a failed transition and the failed
     *     transition will lead to a notification to the Context and it's adapter
     * @return void
     */
    public function apply($transition_name) {
        $this->transition($transition_name, true);
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
     * since all rules must be mutually exclusive!
     *
     * An alternative is to use the 'apply' method: 
     *     $statemachine->apply('a_to_b');
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
                    $can = $this->can($transition->getName());
                } catch (\Exception $e) {
                    //we handle a transition exception here, since we
                    //check $this->can() outside the $this->transition() method
                    //where all the hooks are.
                    //this should be refactored to an implementation
                    //where all the hooks are in a single routine
                    $this->handleTransitionException($e, $transition->getName());
                    throw $e;
                }
                
                //we can transition
                if($can) {
                    //don't check if we can transition, since we just did that.
                    $this->transition($transition->getName(), false);
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
     * run a statemachine until it cannot run the next transition or until
     * it is in a final state.
     * 
     * preconditions: 
     *     - the transitions should be defined for each state
     *     - the transitions should be allowed by the rules
     *     - the transitions should be able to execute
     * @param string[] $transitions an array of transition names
     * @throws Exception in case something went wrong.
     * @return int the number of sucessful transitions made. 
     */
    public function runToCompletion()
    {
        $transitions = 0;
        try {
            $run = true;
            while ($run) {
               //when using cyclic graphs, you can get into an infinite loop. 
               //design your machine correctly (with the right rules)
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
     * This method is the beating hart of the statemachine: apply a transition by name.
     * @param string $transition_name
     * @param boolean $check_allowed to specify if we want to do the check to see
     *      if the transition is allowed or not. This is a performance optimalization
     *      so in case we call 'can' directly, we can use 'transition' directly 
     *      after that without doing the checks (including expensive Rules) twice.
     * @throws Exception
     * @return void
     * https://en.wikipedia.org/wiki/Template_method_pattern
     */
    protected function transition($transition_name, $check_allowed = true) 
    {
        try {
            if($check_allowed === true) {
                if(!$this->can($transition_name)) {
                    //we tried a transition, but it is not allowed
                    throw new Exception(
                            sprintf("Transition '%s' not allowed from state '%s'", 
                                    $transition_name, $this->getContext()->getState()),
                            Exception::SM_TRANSITION_NOT_ALLOWED);
                }
            }
            $transition = $this->getTransition($transition_name);

            //possible hook for subclasses to implement
            $this->preProcess($transition);
            
            $transition->process($this->getContext());
            $this->setCurrentState($transition->getStateTo());
            
            //possible hook for subclasses to implement
            $this->postProcess($transition);
            
        } catch (Exception $e) {
            $this->handleTransitionException($e, $transition_name);
            //already a statemachine exception, just rethrow
            throw $e;
        } catch (\Exception $e) {
            //a non statemachine type exception, wrap it and throw
            $e = new Exception($e->getMessage(), Exception::SM_APPLY_FAILED, $e);
            //possible hook for subclasses to implement
            $this->handleTransitionException($e, $transition_name);
            throw $e;
        }
    }
    
        
    /**
     * add a transition. 
     * Since a transition has complete knowledge about it's states,
     * the addition of a transition will also trigger the adding of the
     * to and from state on this class.
     * 
     * this method should be package visibility for the Loader, but since php
     * does not support that, it can also be used to add a Transition directly.
     * Make sure that transitions that share a common State use the same instance
     * of that State object and vice versa.
     * @param Transition $transition
     */
    public function addTransition(Transition $transition) 
    {
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
     * Before a transition is checked to be possible, you can add domain
     * specific logic here by overriding this method in a subclass
     * @param Transition $transition
     * @return boolean
     */
    protected function checkGuard(Transition $transition) 
    {
        //eg: dispatch an event and see if it is rejected by a listener
        return true;
    }
    
            
    /**
     * called whenever an exception occurs from inside 'transition()'
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
     * called before each transition will try to run.
     * a hook to implement in subclasses if necessary, to do stuff such as
     * event handling, locking an entity, logging, cleanup etc.
     * @param Transition $transition
     */
    protected function preProcess(Transition $transition) {
        //dispatch events, log, lock entity, cleanup,
        //begin transaction via persistance layer etc.
    }
    
    /**
     * called after each transition has run.
     * a hook to implement in subclasses if necessary, to do stuff such as
     * event handling, unlocking an entity, logging, cleanup etc.
     * @param Transition $transition
     */
    protected function postProcess(Transition $transition) {
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

}
