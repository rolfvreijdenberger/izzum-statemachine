<?php
namespace izzum\statemachine;
use izzum\command\ICommand;
use izzum\command\Null;
use izzum\statemachine\Exception;
use izzum\command\Composite;
use izzum\statemachine\utils\Utils;
/**
 * This class holds the finite state data:
 * - the name of the state
 * - the type of the state (initial/normal/final)
 * - what outgoing transitions this state has (bidirectional association initiated by a Transition)
 * - class names for the entry and exit commands (if any)
 * 
 * A State instance can (and should) be shared by multiple Transition
 * objects when it is the same State for their origin/from State.
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
     * default exit/entry command
     * @var string
     */
    const COMMAND_NULL = 'izzum\command\Null';
    
    /**
     * default exit/entry command for constructor
     * @var string
     */
    const COMMAND_EMPTY= '';
    
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
     * fully qualified command name for the command to be executed
     * when entering a state as part of a transition.
     * This can actually be a ',' seperated string of multiple commands that will be executed as a composite.
     * @var string
     */
    protected $command_entry_name;
    
    /**
     * fully qualified command name for the command to be executed
     * when exiting a state as part of a transition.
     * This can actually be a ',' seperated string of multiple commands that will be executed as a composite.
     * @var string
     */
    protected $command_exit_name;
   
    /**
     * a description for the state
     * @var string
     */
    protected $description;
    
     /**
     * 
     * @param string $name the name of the state
     * @param string $type the type of the state (on of self::TYPE_<*>)
     * @param $command_entry_name optional: a command to be executed when a transition enters this state
     * 		One or more fully qualified command (sub)class name(s) to execute when entering this state.
     * 		This can actually be a ',' seperated string of multiple commands that will be executed as a composite.
     * @param $command_exit_name optional: a command to be executed when a transition leaves this state
     * 		One or more fully qualified command (sub)class name(s) to execute when exiting this state.
     * 		This can actually be a ',' seperated string of multiple commands that will be executed as a composite.
     */
    public function __construct($name, $type = self::TYPE_NORMAL, $command_entry_name = self::COMMAND_EMPTY, $command_exit_name = self::COMMAND_EMPTY)
    {
        $this->name        			= $name;
        $this->type        			= $type;
        $this->command_entry_name 	= $command_entry_name;
        $this->command_exit_name 	= $command_exit_name;
        $this->transitions 			= array();
        
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
     * for State and Transition from a Transition instance
     * 
     * @param Transition $transition
     * @return boolan yes in case the transition was not on the State already
     */
    public function addTransition(Transition $transition)
    {
    	$output = true;
        //check all existing transitions.
        if($this->hasTransition($transition->getName())) {
            $output = false;
        }

        $this->transitions[] = $transition;
        return $output;
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
    
    /**
     * An action executed every time a state is entered.
     * An entry action will not be executed for an 'initial' state.
     * 
     * @param Context $context
     * @param string $event optional in case the transition was triggered by an event code (mealy machine)
     * @throws Exception
     */
    public function entryAction(Context $context, $event = null)
    {
   		$command = $this->getCommand($this->getEntryCommandName(), $context, $event);
   		$this->execute($command);
    }
    
    
    /**
     * An action executed every time a state is exited.
     * An exit action will not be executed for a 'final' state since a machine
     * will not leave a 'final' state.
     * 
     * @param Context $context
     * @param string $event optional in case the transition was triggered by an event code (mealy machine)
     * @throws Exception
     */
    public function exitAction(Context $context, $event = nul)
    {
    	$command = $this->getCommand($this->getExitCommandName(), $context, $event);
    	$this->execute($command);
    }

    /**
     * helper method
     * @param ICommand $command
     * @throws Exception
     */
    protected function execute(ICommand $command)
    {
    	try {
    		$command->execute();
    	} catch (\Exception $e) {
    		//command failure
    		$e = new Exception($e->getMessage(), Exception::COMMAND_EXECUTION_FAILURE, $e);
    		throw $e;
    	}
    }
    
    /**
     * returns the associated Command for the entry/exit action.
     * the Command will be configured with the 'reference' of the stateful object
     *
     * @param string $command_name entry or exit command name
     * @param Context $context
     * @param string $event optional in case the transition was triggered by an event code (mealy machine)
     * @return ICommand
     * @throws Exception
     */
    protected function getCommand($command_name, Context $context, $event = null)
    {
		return Utils::getCommand($command_name, $context, $event);
    }
    
    /**
     * get the transition for this state that can be triggered by an event code.
     * @param string $event the event code that can trigger a transition (mealy machine)
     * @return Transition[]
     */
    public function getTransitionsTriggeredByEvent($event)
    {
    	$output = array();
    	foreach ($this->getTransitions() as $transition) {
    		if($transition->isTriggeredBy($event)) {
    			$output[] =  $transition;
    		}
    	}
    	return $output;
    }
    
    
    
    /**
     * get the fully qualified command name for entry of the state
     * @return string
     */
    public function getEntryCommandName()
    {
    	return $this->command_entry_name;
    }
    
    /**
     * get the fully qualified command name for entry of the state
     * @return string
     */
    public function getExitCommandName()
    {
    	return $this->command_exit_name;
    }
    
    public function setExitCommandName($name) 
    {
    	$this->command_exit_name = $name;
    }
    
    public function setEntryCommandName($name)
    {
    	$this->command_entry_name = $name;
    }
    
    /**
     * set the description of the state (for uml generation for example)
     * @param string $description
     */
    public function setDescription($description)
    {
    	$this->description = $description;
    }
    
    /**
     * get the description for this state (if any)
     * @return string
     */
    public function getDescription()
    {
    	return $this->description;
    }
    
}