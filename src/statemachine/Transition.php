<?php
namespace izzum\statemachine;
use izzum\command\Null;
use izzum\rules\True;
use izzum\statemachine\Exception;
use izzum\statemachine\utils\Utils;
use izzum\statemachine\Context;
/**
 * Transition class
 * An abstraction for everything that is needed to make an allowed and succesful
 * transition.
 *  
 * It has functionality to accept a Rule (guard logic) and a Command (transition logic).
 *
 * The Rule is used to check whether a transition can take place (a guard)
 * The Command is used to execute the transition logic.
 * 
 * Rules and commands should be able to be found/autoloaded by the application
 * 
 * If transitions share the same states (both to and from) then they should point
 * to the same object reference (same states should share the exact same state configuration)/].
 * 
 * A subclassof Transition might provide alternative behaviour eg: 
 * - an application performance optimized prioritized transition, 
 *      so that when a state has 2 outgoing transitions, 1 is always tried first
 * - accept an array of rules that will be 'and-ed' together during the process phase
 * - accept an array of commands that will be made in a composite during the process phase
 * 
 * This alternative behaviour can be generated in conjunction with a subclass
 * of State (which should return a prioritized array for the 'run' method in the
 * StateMachine) and a Loader (which should build the right objects). All these 
 * can be nicely encapsulated in a subclass of AbstractFactory.
 *
 * @author Rolf Vreijdenberger
 *
 */
class Transition {
    
    const RULE_TRUE = 'izzum\rules\True';
    const RULE_FALSE = 'izzum\rules\False';
    const COMMAND_NULL = 'izzum\command\Null';
    
    /**
     * The fully qualified Rule class name of the
     * Rule to be applied to check if we can transition
     * @var string
     */
    protected $rule;
    /**
     * the state this transition points to
     * @var State
     */
    protected $state_to;
    
    /**
     * the state this transition starts from
     * @var State 
     */
    protected $state_from;

    /**
     * the fully qualified Command class name of the Command to be 
     * executed as part of the transition logic
     * @var string 
     */
    protected $command;
    
    /**
     * a description for the state
     * @var string
     */
    protected $description;
    
    /**
     * an event code that can trigger this transitions
     * @var string
     */
    protected $event;

    /**
     * @param State $state_from
     * @param State $state_to
     * @param string $rule optional: a fully qualified Rule (sub)class name to check to see if we are allowed to transition
     * @param string $command optional: a fully qualified command (sub)class name to execute for a transition
     * @param string $event optional: an event name by which this transition can be triggered
     */
    public function __construct(State $state_from, State $state_to, $rule = self::RULE_TRUE, $command = self::COMMAND_NULL, $event = null)
    {
       $this->state_to 		= $state_to;
       $this->state_from 	= $state_from;
       $this->rule 			= $rule;
       $this->command 		= $command;
       $this->event 		= $event;
       //setup bidirectional relationship with state this transition originates from
       $state_from->addTransition($this);
    }
    
    /**
     * Can this transition be triggered by a certain event?
     * @param string $event
     * @return boolean
     */
    public function isTriggeredBy($event) 
    {
    	return $this->event === $event && $event !== null && $event !== '';
    }

    /**
     * is a transition possible?
     *
     * @param Context $object
     * @param string $event optional in case the transition was triggered by an event code (mealy machine)
     * @return boolean
     */
    public function can(Context $object, $event = null)
    {
        try {
            return $this->getRule($object, $event)->applies();
        } catch (\Exception $e) {
            $e = new Exception($e->getMessage(), Exception::RULE_APPLY_FAILURE, $e);
            throw $e;
        }
    }

    /**
     * Process the transition for the statemachine
     * @param Context $object
     * @param string $event optional in case the transition was triggered by an event code (mealy machine)
     * @return void
     */
    public function process (Context $object, $event = null)
    {
        //execute, we do not need to check if we 'can' since this is done
        //by the statemachine itself
        try {
        	$this->getCommand($object, $event)->execute();
        } catch (\Exception $e) {
            //command failure
            $e = new Exception($e->getMessage(), Exception::COMMAND_EXECUTION_FAILURE, $e);
            throw $e;
        }
    }

    /**
     * returns the associated Rule for this Transition,
     * configured with a 'reference' (stateful) object
     *
     * @param Context $object the associated stateful object for a our statemachine
     * @param string $event optional in case the transition was triggered by an event code (mealy machine)
     * @return \izzum\rules\IRule
     * @throws Exception
     */
    public function getRule(Context $object, $event = null)
    {

        //if no rule is defined, just allow the transition by default
        if($this->rule === '') {
            return new True();
        }
        
        //this reference is cached, so we can be sure it is always the same instance
        $reference = $object->getEntity();
        
        
        //rule is defined, check if it is valid
        if(class_exists($this->rule)) {
            try {
                $rule = new $this->rule($reference);
            	//a mealy machine might still want to check the event to see if it is the expected event.
            	//therefore, see if the rule expects an event to be set that it can use in it's 'applies' method.
                if(method_exists($rule, 'setEvent'))
                {
                	$rule->setEvent($event);
                }
            } catch (\Exception $e) {
                $e = new Exception(
                        sprintf("failed rule creation, class objects to construction with reference: (%s) for Context (%s). message: %s", $this->rule, $object->toString(), $e->getMessage()),
                        Exception::RULE_CREATION_FAILURE);
                throw $e;
            }
        }else {
            //misconfiguration
            $e = new Exception(
                       sprintf("failed rule creation, class does not exist: (%s) for Context (%s).", $this->rule, $object->toString()),
                       Exception::RULE_CREATION_FAILURE);
            throw $e;
        }
        return $rule;
    }

    
    /**
     * returns the associated Command for this Transition.
     * the Command will be configured with the 'reference' of the stateful object
     *
     * @param Context $object the associated stateful object for a our statemachine
     * @param string $event optional in case the transition was triggered by an event code (mealy machine)
     * @return izzum\command\ICommand
     * @throws Exception
     */
    public function getCommand(Context $object, $event = null)
    {
        //this reference is cached, so we can be sure it is always the same instance
        $reference = $object->getEntity();
            
        //it's oke to have no command, as there might be 'marker' states, where
        //we just need to transition something to a next state (according to a rule)
        //where useful  work can be done (eg: from the 'initial' type state to
        //a 'shortcut' state for special cases.
        if($this->command === '') {
            //return a command without side effects
            return new Null();
        }
        
        if(class_exists($this->command)) {
            try {
                $command = new $this->command($reference);
                //a mealy machine might want to use the event in it's transition logic.
                //therefore, see if the command expects an event to be set that it can use in it's 'execute' method.
                if(method_exists($command, 'setEvent'))
                {
                	$command->setEvent($event);
                }
            } catch (\Exception $e) {
                $e = new Exception(
                           sprintf("Command objects to construction with reference: (%s) for Context (%s). message: %s",
                               $this->command, $object->toString(), $e->getMessage()),
                           Exception::COMMAND_CREATION_FAILURE);
                throw $e;
            }
        } else {
            //misconfiguration
            $e = new Exception(
                       sprintf("failed command creation, class does not exist: (%s) for Context (%s)", 
                               $this->command, $object->toString()),
                       Exception::COMMAND_CREATION_FAILURE);
            throw $e;
        }
        
        return $command;
    }

    
    /**
     * @return string
     */
    public function toString()
    {
        //includes the namespace
        return get_class($this) . 
                " '" . $this->getName() . "'" . 
                " [rule]: '" . $this->rule . "' [command]: '" .  $this->command . "'";
    }
    

    /**
     * get the state this transition points from
     * @return State
     */
    public function getStateFrom()
    {
        return $this->state_from;
    }

    
    /**
     * get the state this transition points to
     * @return State
     */
    public function getStateTo(){
        return $this->state_to;
    }


    /**
     * get the transition name
     * @return string
     */
    public function getName()
    {
    	$name = Utils::getTransitionName(
                    $this->getStateFrom()->getName(),
                    $this->getStateTo()->getName());
        return  $name;
    }
    
    public function getCommandName()
    {
        return $this->command;
    }
    
    public function getRuleName()
    {
        return $this->rule;
    }
    
    /**
     * set the description of the transition (for uml generation for example)
     * @param string $description
     */
    public function setDescription($description)
    {
    	$this->description = $description;
    }
    
    /**
     * get the description for this transition (if any)
     * @return string
     */
    public function getDescription()
    {
    	return $this->description;
    }
    
    /**
     * set the event name by which this transition can be triggered
     * @param string $event
     */
    public function setEvent($event)
    {
    	$this->event = $event;
    }
    
    /**
     * get the event name by which this transition can be triggered
     * 
     * @return string
     */
    public function getEvent()
    {
    	return $this->event;
    }

    
    /**
     * @return string
     */
    public function __toString()
    {
        return $this->getName();
    }
}