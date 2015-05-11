<?php
namespace izzum\statemachine;
use izzum\command\Null;
use izzum\rules\True;
use izzum\statemachine\Exception;
use izzum\statemachine\utils\Utils;
use izzum\statemachine\Context;
use izzum\rules\Rule;
use izzum\rules\AndRule;
use izzum\rules\izzum\rules;
use izzum\rules\IRule;
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
    const RULE_EMPTY = '';
    const COMMAND_NULL = 'izzum\command\Null';
    const COMMAND_EMPTY = '';
    
    
    /**
     * the state this transition starts from
     * @var State 
     */
    protected $state_from;
    
    /**
     * the state this transition points to
     * @var State
     */
    protected $state_to;
    
    /**
     * an event code that can trigger this transitions
     * @var string
     */
    protected $event;
    
    /**
     * The fully qualified Rule class name of the
     * Rule to be applied to check if we can transition.
     * This can actually be a ',' seperated string of multiple rules.
     * @var string
     */
    protected $rule;

    /**
     * the fully qualified Command class name of the Command to be 
     * executed as part of the transition logic.
     * This can actually be a ',' seperated string of multiple commands.
     * @var string 
     */
    protected $command;
    
    /**
     * a description for the state
     * @var string
     */
    protected $description;
    

    /**
     * @param State $state_from
     * @param State $state_to
     * @param string $event optional: an event name by which this transition can be triggered
     * @param string $rule optional: one or more fully qualified Rule (sub)class name(s) to check to see if we are allowed to transition.
     * 		This can actually be a ',' seperated string of multiple rules that will be applied as a chained 'and' rule.
     * @param string $command optional: one or more fully qualified Command (sub)class name(s) to execute for a transition.
     * 		This can actually be a ',' seperated string of multiple commands that will be executed as a composite.
     */
    public function __construct(State $state_from, State $state_to, $event = null, $rule = self::RULE_EMPTY, $command = self::COMMAND_EMPTY)
    {
       $this->state_from 	= $state_from;
       $this->state_to 		= $state_to;
       $this->rule 			= $rule;
       $this->command 		= $command;
       //setup bidirectional relationship with state this transition originates from
       $state_from->addTransition($this);
       //sanitize event name
       $this->setEvent($event);
    }
    
    /**
     * Can this transition be triggered by a certain event?
     * This also matches on the transition name.
     * @param string $event
     * @return boolean
     */
    public function isTriggeredBy($event) 
    {
    	return ($this->event === $event || $this->getName() === $event) && $event !== null && $event !== '';
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
     * @param Context $context the associated Context for a our statemachine
     * @param string $event optional in case the transition was triggered by an event code (mealy machine)
     * @return IRule a Rule or chained AndRule if the rule input was a ',' seperated string of rules.
     * @throws Exception
     */
    public function getRule(Context $context, $event = null)
    {
        //if no rule is defined, just allow the transition by default
        if($this->rule === '' || $this->rule === null) {
            return new True();
        }
        
        $entity = $context->getEntity();
        
        //a rule string can be made up of multiple rules seperated by a comma
        $all_rules = explode(',', $this->rule);
        $rule = new True();
        foreach($all_rules as $single_rule) {
        	
	        //guard clause to check if rule exists
	        if(!class_exists($single_rule)) {
	            $e = new Exception(
	                       sprintf("failed rule creation, class does not exist: (%s) for Context (%s).", 
	                       		$this->rule, $context->toString()),
	                       		Exception::RULE_CREATION_FAILURE);
	            throw $e;
	        }
	        
	        
	        try {
	        	$and_rule = new $single_rule($entity);
	        	$this->tryToSetEventOnRule($and_rule, $event);
	        	//create a chain of rules that need to be true
	        	$rule = new AndRule($rule, $and_rule);
	        } catch (\Exception $e) {
	        	$e = new Exception(
	        			sprintf("failed rule creation, class objects to construction with entity: (%s) for Context (%s). message: %s",
	        					$this->rule, $context->toString(), $e->getMessage()),
	        			Exception::RULE_CREATION_FAILURE);
	        	throw $e;
	        }
        
        }
        return $rule;
    }
    
    /**
     * Try to set an event string on a rule instance, if the method 'setEvent' exists.
     * @param IRule $rule
     * @param string $event
     */
    private function tryToSetEventOnRule(IRule $rule, $event)
    {
    	//a mealy machine might still want to check the event to see if it is the expected event.
    	//therefore, see if the rule expects an event to be set that it can use in it's 'applies' method.
    	if(method_exists($rule, 'setEvent'))
    	{
    		$and_rule->setEvent($event);
    	}
    }

    
    /**
     * returns the associated Command for this Transition.
     * the Command will be configured with the 'reference' of the stateful object.
     * In case there have been multiple commands as input (',' seperated), this method will return
     * a Composite command.
     *
     * @param Context $object the associated stateful object for a our statemachine
     * @param string $event optional in case the transition was triggered by an event code (mealy machine)
     * @return izzum\command\ICommand
     * @throws Exception
     */
    public function getCommand(Context $context, $event = null)
    {
		return Utils::getCommand($this->command, $context, $event);
    }

    
    /**
     * @return string
     */
    public function toString()
    {
        return get_class($this) . 
                " '" . $this->getName() . "' [event]: '" . $this->event . "'" . 
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
     * get the transition name. the transition name is always unique for a statemachine
     * since it constists of <state_from>_to_<state_to>
     * @return string
     */
    public function getName()
    {
    	$name = Utils::getTransitionName(
                    $this->getStateFrom()->getName(),
                    $this->getStateTo()->getName());
        return  $name;
    }
    
    /**
     * return the command name(s).
     * 		one or more fully qualified command (sub)class name(s) to execute for a transition.
     * 		This can actually be a ',' seperated string of multiple commands that will be executed as a composite.
     * @return string
     */
    public function getCommandName()
    {
        return $this->command;
    }
    
    public function setCommandName($command)
    {
    	$this->command = $command;
    }
    
    public function getRuleName()
    {
        return $this->rule;
    }
    
    public function setRuleName($rule)
    {
    	$this->rule = $rule;
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
     * set the event name by which this transition can be triggered.
     * In case the event name is null or an empty string, it defaults to the transition name.
     * @param string $event
     */
    public function setEvent($event)
    {
    	if($event === null || $event === '') {
    		$event = $this->getName();
    	}
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
        return $this->toString();
    }
}