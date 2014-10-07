<?php
namespace izzum\statemachine;
/**
 * This class holds the finite state data:
 * - the name of the state
 * - the type of the state (initial/normal/final)
 * - what outgoing transitions this state has (bidirectional association initiated by a Transition)
 * 
 * TRICKY: a State instance can (and should) be shared by multiple Transition
 * objects when it is the same Staet for their origin/from State.
 * The LoaderArray class automatically takes care of this for us.
 * 
 * the order of Transitions *might* be important.
 * whenever a State is asked for it's transitions, the first transition might
 * be tried first. this might have performance and configuration benefits.
 * 
 * @author Rolf Vreijdenberger
 *
 */
class State {
    
     /**
     * state name if it is unknown (not configured)
     * @var string
     */
    const STATE_UNKNOWN = 'unknown';
    /**
     * default name for the first/only initial state
     * @var string
     */
    const STATE_NEW     = 'new';
    /**
     * default name for a normal final state
     * @var string
     */
    const STATE_DONE    = 'done';
    
    /**
     * the state types:
     *  - a statemachine has exactly 1 initial type, this is always the only 
     *      entrance into the statemachine.
     *  - a statemachine can have 0-n normal types.
     *  - a statemachine should have at least 1 final type where it has no 
     *      further transitions.
     * @var string
     */
    const
        TYPE_INITIAL = 'initial',
        TYPE_NORMAL  = 'normal',
        TYPE_FINAL   = 'final'
    ;
    

    
     /**
     * The state type:
     * - State::TYPE_INITIAL
     * - State::TYPE_NORMAL
     * - State::TYPE_FINAL
     *
     * @var string
     */
    protected $type;

    /**
     * an array of transitions that are outgoing for this state.
     * These will be set by Transition objects (they provide the association)
     * 
     * this is not a hashmap, so the order of Transitions *might* be important.
     * whenever a State is asked for it's transitions, the first transition might
     * be tried first. this might have performance and configuration benefits
     * 
     * @var Transition[]
     */
    protected $transitions;

    /**
     * The name of the state
     * @var string
     */
    protected $name;
   
     /**
     * 
     * @param string $name the name of the state
     * @param string $type the type of the state
     */
    public function __construct($name, $type = self::TYPE_NORMAL)
    {
        $this->name        = $name;
        $this->type        = $type;
        $this->transitions = array();
        
    }

    /**
     * is it an initial state
     * @return boolean
     */
    public function isInitial()
    {
        return $this->type === self::TYPE_INITIAL;
    }
    
    /**
     * is it a normal state
     * @return boolean
     */
     public function isNormal()
    {
        return $this->type === self::TYPE_NORMAL;
    }

    /**
     * is it a final state
     * @return boolean
     */
    public function isFinal()
    {
        return $this->type === self::TYPE_FINAL;
    }

    /**
     * get the state type
     * @return string
     */
    public function getType()
    {
        return $this->type;
    }

    /**
     * add an outgoing transition from this state.
     * 
     * TRICKY: this method should be package visibility only,
     * so don't use directly. it is used to set the bidirectional association
     * for State and Transition
     * 
     * @param Transition $transition
     * @return boolan yes in case the transition was not on the State already
     */
    public function addTransition(Transition $transition)
    {
        //check all existing transitions.
        if($this->hasTransition($transition->getName())) {
            return false;
        }

        $this->transitions[] = $transition;
        return true;
    }


    /**
     * get all outgoing transitions
     * @return Transition[] an array of transitions
     */
    public function getTransitions()
    {
        //a subclass might return an ordered/prioritized array
        return $this->transitions;
    }


    /**
     * gets the name of this state
     */
    public function getName()
    {
        return $this->name;
    }


    /**
     * @return string
     */
    public function __toString()
    {
        return $this->getName();
    }
    
    /**
     * Do we have a transition from this state with a certain name?
     * @param string $transition_name
     * @return boolean
     */
    public function hasTransition($transition_name)
    {
        $has = false;
        foreach($this->transitions as $transition) {
            if($transition_name === $transition->getName()) {
                $has = true;
                break;
            }
        }
        return $has;
    }
}