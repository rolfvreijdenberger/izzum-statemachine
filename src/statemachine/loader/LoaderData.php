<?php
namespace izzum\statemachine\loader;
use izzum\statemachine\State;
use izzum\statemachine\utils\Utils;
use izzum\statemachine\Transition;

/**
 * LoaderData is used to represent the configuration of a State and a Transition
 * and 1 or more of them are created by an implementation of Loader. 
 * for example: LoaderPostgresDatabase,  LoaderYaml, LoaderJson etc.
 * 
 * It contains all (simple) data related to one transition for a statemachine:
 * names and types of the origin and destination state and the guard (rule) name 
 * and transition logic (command) name.
 * 
 * It acts like a simple value object to pass around and for clients (Loaders like LoaderArray etc)
 * to use.
 * 
 * 
 * @author Rolf Vreijdenberger
 * @see LoaderArray
 */
class LoaderData {
    /**
     * @var string
     */
    protected $state_from;
    /**
     * @var string
     */
    protected $state_to;
    /**
     * @var string
     */
    protected $state_type_to;
    /**
     * @var string
     */
    protected $state_type_from;
    /**
     * @var string
     */
    protected $rule;
    /**
     * @var string
     */
    protected $command;
    
    
    /**
     * Constructor.
     * 
     * @param string $state_from
     * @param string $state_to
     * @param string $rule fully qualified class name
     * @param string $command fully qualified class name
     * @param string $state_type_from
     * @param string $state_type_to
     */
    public function __construct($state_from, $state_to, 
        $rule = Transition::RULE_FALSE, $command = Transition::COMMAND_NULL, 
        $state_type_from = State::TYPE_NORMAL, $state_type_to = State::TYPE_NORMAL) 
    {
        $this->state_from           = $state_from;
        $this->state_to             = $state_to;
        $this->rule                 = $rule;
        $this->command              = $command; 
        $this->state_type_from      = $state_type_from;
        $this->state_type_to        = $state_type_to;
       
    }
    
    /**
     * the state to transition from
     * @return State
     */
    public function getStateFrom()
    {
       return $this->state_from;
    }
    
    /**
     * the state to transition to
     * @return State
     */
    public function getStateTo()
    {
        return $this->state_to;
    }
    
    /**
     * the type of the state (origin/from)
     * @return string
     */
    public function getStateTypeFrom()
    {
        return $this->state_type_from;
    }
    
    /**
     * the type of the state (destination/to)
     * @return string
     */
    public function getStateTypeTo()
    {
        return $this->state_type_to;
    }
    
    /**
     * the fully qualified name of the Command class
     * @return string
     */
    public function getCommand()
    {
        return $this->command;
    }
    
    /**
     * the fully qualified name of the Rule class
     * @return string
     */
    public function getRule()
    {
        return $this->rule;
    }
    
    /**
     * get the transition name
     * @return string
     */
    public function getTransition()
    {
        return Utils::getTransitionName($this->state_from, $this->state_to);
    }
    
    /**
     * Factory method.
     * 
     * @param string $state_from
     * @param string $state_to
     * @param string $rule fully qualified class name
     * @param string $command fully qualified class name
     * @return LoaderData or a subclass
     */
    public static function get($state_from, $state_to, 
        $rule = Transition::RULE_FALSE, $command = Transition::COMMAND_NULL,  
        $state_type_from = State::TYPE_NORMAL, $state_type_to = State::TYPE_NORMAL)
    {
        //use static so we can subclass.
        return new static($state_from, $state_to, 
                $rule, $command, 
                $state_type_from, $state_type_to);
    }
    
}