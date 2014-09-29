<?php
namespace izzum\statemachine;
use izzum\statemachine\Context;
use izzum\statemachine\State;
use izzum\statemachine\Transition;
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
 * The statemachine acts as a manager/service to tie all objects it works with together.
 * 
 * The statemachine should be loaded with States and Transitions, which define
 * from what state to what other state transitions are allowed. The transitions 
 * are specified by a guard clause -in the form of a business Rule- and a transition
 * Command.
 * 
 * The Context object contains a reference to an underlying Entity domain model (eg:
 * an order, a customer etc) which is injected at runtime into the Rule and 
 * the Command associated with the transitions.
 * 
 * The Rule checks if the domain model (or it's derived data) applies and therefore
 * allows the transition, after which the Command is executed and can actually
 * alter data in the underlying domain models, call services etc.
 * 
 * Preferably use the StateMachineFactory to get a StateMachine.
 * 
 * All high level interaction we can expect a client to conduct with a statemachine, 
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
 * Having good naming conventions allows easy automation of generating transition
 * names from states etc.
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
      * @param Context $context a fully configured context
     */
    public function __construct(Context $context)
    {
        //sets up bidirectional association
        $this->setContext($context);
    }
    
    
    /**
     * @param string $transition name
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
     * Apply a transition by name. (<state-from>_to_<state-to>)
     * The transition should be possible and it is checked in this method.
     * 
     * @throws Exception in case something went wrong. The exceptions are logged.
     *     Furthermore,an exception will lead to a failed transition and the failed
     *     transition will lead to a notification to the Context
     * @return void
     *  @see Context::notifyFailedTransition()
     */
    public function apply($transition_name) {
        try {
            if(!$this->can($transition_name)) {
                //we tried a transition, but it is not allowed
                throw new Exception(
                        sprintf("Transition '%s' not allowed from state '%s'", 
                                $transition_name, $this->getContext()->getState()),
                        Exception::SM_TRANSITION_NOT_ALLOWED);
            }
            $transition = $this->getTransition($transition_name);

            //possible hook
            $this->preProcess($transition);
            
            $transition->process($this->getContext());
            $this->setCurrentState($transition->getStateTo());
            
            //possible hook
            $this->postProcess($transition);
            
        } catch (Exception $e) {
            $this->handleException($e, $transition_name);
            //already a statemachine exception, just rethrow
            throw $e;
        } catch (\Exception $e) {
            //a non statemachine type exception, wrap it and throw
            $e = new Exception($e->getMessage(), Exception::SM_APPLY_FAILED, $e);
            $this->handleException($e, $transition_name);
            throw $e;
        }
    }
    
    protected function handleException(Exception $e, $transition_name) {
        //override if necessary to log exceptions or to add some extra info
        //to the underlying storage facility (for example, an exception will
        //not lead to a transition, so this can be used to indicate a failed 
        //transition in some sort of history structure)
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
     * add a transition. 
     * Since a transition has complete knowledge about it's states,
     * the addition of a transition will also trigger the adding of the
     * to and from state on this class.
     * 
     * this method should be package visibility
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
    
    /**
     * called before each transition will try to run.
     * a hook to implement in subclasses if necessary, to do stuff such as
     * event handling, locking an entity, logging etc.
     * @param Transition $transition
     */
    protected function preProcess(Transition $transition) {
        //dispatch events, log, lock entity etc.
    }
    
    /**
     * called after each transition has run.
     * a hook to implement in subclasses if necessary, to do stuff such as
     * event handling, locking an entity, logging etc.
     * @param Transition $transition
     */
    protected function postProcess(Transition $transition) {
        //dispatch events, log, lock entity etc.
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
                //depending on the current state AND the rules defined
                if($this->can($transition->getName())) {
                    $this->apply($transition->getName());
                    //transition done
                    return true;
                }
            }
        } catch (Exception $e) {
            //already a statemachine exception, just rethrow
            throw $e;
        } catch (\Exception $e) {
            //a non statemachine type exception, wrap it and throw
            $e = new Exception($e->getMessage(), $e->getCode(), $e);
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
     * set the context on the statemachine and provide bidirectional association
     * @param Context $context
     */
    protected final function setContext(Context $context)
    {
        if($this->context){
            if($this->context->getMachine() !== $context->getMachine()) {
                throw new Exception(
                    sprintf("Trying to set context for a different machine. currently '%s' and new '%s'",
                        $this->context->getMachine(), $context->getMachine()), 
                    Exception::SM_CONTEXT_DIFFERENT_MACHINE);
            }
        }
        $this->context = $context;
        $context->setStateMachine($this);
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
     * sets the state
     * @param State $state
     */
    protected function setCurrentState(State $state) {
        $this->getContext()->setState($state->getName());
    }
    
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
    
    public function toString()
    {
        return get_class($this) . ": [" . $this->getContext()->getId(true) ."]";
    }
}