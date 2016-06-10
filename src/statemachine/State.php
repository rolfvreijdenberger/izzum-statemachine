<?php
namespace izzum\statemachine;
use izzum\command\ICommand;
use izzum\command\NullCommand;
use izzum\statemachine\Exception;
use izzum\command\Composite;
use izzum\statemachine\utils\Utils;

/**
 * This class holds the finite state data:
 * - the name of the state
 * - the type of the state (initial/normal/final)
 * - what outgoing transitions this state has (bidirectional association
 * initiated by a Transition)
 * - class names for the entry and exit commands (if any)
 * - callables for entry and exit logic (if any)
 *
 * A State instance can (and should) be shared by multiple Transition
 * objects when it is the same State for their origin/from State.
 * 
 * A State can be a regex state (or negated regex). 
 * A regex state can be used in a transition and when added to a
 * statemachine the regular expression will be matched on all currently
 * known states on that statemachine and new Transitions will be added
 * to the statemachine that match the from/to state regexes. This is very 
 * useful to build a lot of transitions very quickly.
 * 
 * to build a full mesh of transitions (all states to all states):
 * $a = new State('a');
 * $b = new State('b');
 * $c = new State('c');
 * $machine->addState($a);
 * $machine->addState($b);
 * $machine->addState($c);
 * $state_regex_all = new State('regex:|.*|');
 * $machine->addTransition(new Transition($state_regex_all, $state_regex_all));
 *
 * @author Rolf Vreijdenberger
 * @link https://php.net/manual/en/language.types.callable.php
 * @link https://en.wikipedia.org/wiki/Command_pattern    
 * @link https://php.net/manual/en/function.preg-match.php
 * @link http://regexr.com/ for trying out regular expressions    
 */
class State {
    
    /**
     * state name if it is unknown (not configured)
     * @var string
     */
    const STATE_UNKNOWN = 'unknown';
    
    /**
     * default name for the first/only initial state (but you can specify whatever you want for your initial state)
     * @var string
     */
    const STATE_NEW = 'new';
    
    /**
     * default name for a normal final state
     * @var string
     */
    const STATE_DONE = 'done';
    
    /**
     * default exit/entry command
     * @var string
     */
    const COMMAND_NULL = '\izzum\command\NullCommand';
    
    /**
     * default exit/entry command for constructor
     * @var string
     */
    const COMMAND_EMPTY = '';
    const CALLABLE_NULL = null;
    const REGEX_PREFIX = 'regex:';
    const REGEX_PREFIX_NEGATED = 'not-regex:';
    
    /**
     * the state types:
     * - 'initial':     a statemachine has exactly 1 initial type, this is always the only
     *                  entrance into the statemachine.
     * - 'normal':      a statemachine can have 0-n normal types.
     * - 'done':        a statemachine should have at least 1 final type where it has no
     *                  further transitions.
     * - 'regex':       a statemachine configuration could have regex states, which serve a purpose to create transitions
     *                  from or to multiple other states
     * 
     * @var string
     */
    const TYPE_INITIAL = 'initial', TYPE_NORMAL = 'normal', TYPE_FINAL = 'final', TYPE_REGEX = 'regex';
    
    /**
     * The state type:
     * - State::TYPE_INITIAL
     * - State::TYPE_NORMAL
     * - State::TYPE_FINAL
     * - State::TYPE_REGEX
     * @var string
     */
    protected $type;
    
    /**
     * an array of transitions that are outgoing for this state.
     * These will be set by Transition objects (they provide the association)
     *
     * this is not a hashmap, so the order of Transitions *might* be important.
     * whenever a State is asked for it's transitions, the first transition
     * might
     * be tried first. this might have performance and configuration benefits
     *
     * @var Transition[]
     */
    protected $transitions;
    
    /**
     * The name of the state
     * 
     * @var string
     */
    protected $name;
    
    /**
     * fully qualified command name for the command to be executed
     * when entering a state as part of a transition.
     * This can actually be a ',' seperated string of multiple commands that
     * will be executed as a composite.
     * 
     * @var string
     */
    protected $command_entry_name;
    
    /**
     * fully qualified command name for the command to be executed
     * when exiting a state as part of a transition.
     * This can actually be a ',' seperated string of multiple commands that
     * will be executed as a composite.
     * 
     * @var string
     */
    protected $command_exit_name;
    
    /**
     *  the entry callable method
     * @var callable
     */
    protected $callable_entry;
    
    /**
     *  the exit callable method
     * @var callable
     */
    protected $callable_exit;
    
    /**
     * a description for the state
     * 
     * @var string
     */
    protected $description;

    /**
     *
     * @param string $name
     *            the name of the state (can also be a regex in format: [not-]regex:/<regex-specification-here>/)
     * @param string $type
     *            the type of the state (on of self::TYPE_<*>)
     * @param $command_entry_name optional:
     *            a command to be executed when a transition enters this state
     *            One or more fully qualified command (sub)class name(s) to
     *            execute when entering this state.
     *            This can actually be a ',' seperated string of multiple
     *            commands that will be executed as a composite.
     * @param $command_exit_name optional:
     *            a command to be executed when a transition leaves this state
     *            One or more fully qualified command (sub)class name(s) to
     *            execute when exiting this state.
     *            This can actually be a ',' seperated string of multiple
     *            commands that will be executed as a composite.
     * @param callable $callable_entry
     *            optional: a php callable to call. eg: "function(){echo 'closure called';};"
     * @param callable $callable_exit
     *            optional: a php callable to call. eg: "izzum\MyClass::myStaticMethod"
     */
    public function __construct($name, $type = self::TYPE_NORMAL, $command_entry_name = self::COMMAND_EMPTY, $command_exit_name = self::COMMAND_EMPTY, $callable_entry = self::CALLABLE_NULL, $callable_exit = self::CALLABLE_NULL)
    {
        $this->setName($name);
        $this->setType($type);
        $this->setEntryCommandName($command_entry_name);
        $this->setExitCommandName($command_exit_name);
        $this->setEntryCallable($callable_entry);
        $this->setExitCallable($callable_exit);
        $this->transitions = array();
    }

    /**
     * get the entry callable, the callable to be called when entering this state
     * @return callable 
     */
    public function getEntryCallable()
    {
        return $this->callable_entry;
    }

    /**
     * set the entry callable, the callable to be called when entering this state
     * @param callable $callable
     */
    public function setEntryCallable($callable)
    {
        $this->callable_entry = $callable;
        return $this;
    }

    /**
     * get the exit callable, the callable to be called when exiting this state
     * @return callable
     */
    public function getExitCallable()
    {
        return $this->callable_exit;
        
    }

    /**
     * set the exit callable, the callable to be called when exiting this state
     * @param callable $callable
     */
    public function setExitCallable($callable)
    {
        $this->callable_exit = $callable;
        return $this;
    }

    /**
     * is it an initial state
     * 
     * @return boolean
     */
    public function isInitial()
    {
        return $this->type === self::TYPE_INITIAL;
    }

    /**
     * is it a normal state
     * 
     * @return boolean
     */
    public function isNormal()
    {
        return $this->type === self::TYPE_NORMAL;
    }

    /**
     * is it a final state
     * 
     * @return boolean
     */
    public function isFinal()
    {
        return $this->type === self::TYPE_FINAL;
    }

    /**
     * is this state a regex type of state?
     * formats:
     *      "regex:<regular-expression-here>"
     *      "not-regex:<regular-expression-here>"
     *
     * @return boolean
     * @link https://php.net/manual/en/function.preg-match.php
     * @link http://regexr.com/ for trying out regular expressions
     */
    public function isRegex()
    {
        //check the type (and check the state name for regex matches)
        return $this->type === self::TYPE_REGEX || $this->isNormalRegex() || $this->isNegatedRegex();
    }

    /**
     * is this state a normal regex type of state?
     * "regex:<regular-expression-here>"
     *
     * @return boolean
     */
    public function isNormalRegex()
    {
        return strpos($this->getName(), self::REGEX_PREFIX) === 0;
    }

    /**
     * is this state a negated regex type of state?
     * "not-regex:<regular-expression-here>"
     *
     * @return boolean
     */
    public function isNegatedRegex()
    {
        return strpos($this, self::REGEX_PREFIX_NEGATED) === 0;
    }

    /**
     * get the state type
     * 
     * @return string
     */
    public function getType()
    {
        
        $this->isRegex();
        return $this->type;
    }

    /**
     * set the state type
     *
     * @param string $type
     */
    protected function setType($type)
    {
        //if a client mistakenly creates a regex State (a name of [not-]<regex:>), but with a non-regex type, 
        //we will set it to a regex state.
        if($this->isRegex()) {
            $type = self::TYPE_REGEX;
        }
        $this->type = trim($type);
        return $this;
    }

    /**
     * add an outgoing transition from this state.
     *
     * TRICKY: this method should be package visibility only,
     * so don't use directly. it is used to set the bidirectional association
     * for State and Transition from a Transition instance on the state the transition will be allowed to 
     * run from ('state from').
     *
     * @param Transition $transition            
     * @return boolan yes in case the transition was not on the State already or in case of an invalid transition
     */
    public function addTransition(Transition $transition)
    {
        $output = false;
        // check all existing transitions.
        if (!$this->hasTransition($transition->getName()) 
                && $transition->getStateFrom()->getName() == $this->getName() 
                && !$this->isFinal()
                 && !$this->isRegex()) {
            $output = true;
            $this->transitions [] = $transition;
        }
        
        return $output;
    }

    /**
     * get all outgoing transitions
     * 
     * @return Transition[] an array of transitions
     */
    public function getTransitions()
    {
        // a subclass might return an ordered/prioritized array
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
     * sets the name of this state
     * @param string $name
     */
    protected function setName($name)
    {
        $this->name = trim($name);
        return $this;
    }

    /**
     *
     * @return string
     */
    public function __toString()
    {
        return $this->getName();
    }

    /**
     * Do we have a transition from this state with a certain name?
     * 
     * @param string $transition_name            
     * @return boolean
     */
    public function hasTransition($transition_name)
    {
        $has = false;
        foreach ($this->transitions as $transition) {
            if ($transition_name === $transition->getName()) {
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
     * @throws Exception
     */
    public function entryAction(Context $context)
    {
        $command = $this->getCommand($this->getEntryCommandName(), $context);
        $this->execute($command);
        $this->callCallable($this->getEntryCallable(), $context);
    }

    /**
     * calls a $callable if it exists, with the arguments $context->getEntity()
     * @param callable $callable
     * @param Context $context
     */
    protected function callCallable($callable, Context $context)
    {
        if ($callable != self::CALLABLE_NULL && is_callable($callable)) {
            call_user_func($callable, $context->getEntity());
        }
    }

    /**
     * An action executed every time a state is exited.
     * An exit action will not be executed for a 'final' state since a machine
     * will not leave a 'final' state.
     *
     * @param Context $context            
     * @throws Exception
     */
    public function exitAction(Context $context)
    {
        $command = $this->getCommand($this->getExitCommandName(), $context);
        $this->execute($command);
        $this->callCallable($this->getExitCallable(), $context);
    }

    /**
     * helper method
     * 
     * @param ICommand $command            
     * @throws Exception
     */
    protected function execute(ICommand $command)
    {
        try {
            $command->execute();
        } catch(\Exception $e) {
            // command failure
            $e = new Exception($e->getMessage(), Exception::COMMAND_EXECUTION_FAILURE, $e);
            throw $e;
        }
    }

    /**
     * returns the associated Command for the entry/exit action.
     * the Command will be configured with the domain model via dependency injection
     *
     * @param string $command_name
     *            entry or exit command name
     * @param Context $context            
     * @return ICommand
     * @throws Exception
     */
    protected function getCommand($command_name, Context $context)
    {
        return Utils::getCommand($command_name, $context);
    }

    /**
     * get the transition for this state that can be triggered by an event code.
     * 
     * @param string $event
     *            the event code that can trigger a transition (mealy machine)
     * @return Transition[]
     */
    public function getTransitionsTriggeredByEvent($event)
    {
        $output = array();
        foreach ($this->getTransitions() as $transition) {
            if ($transition->isTriggeredBy($event)) {
                $output [] = $transition;
            }
        }
        return $output;
    }

    /**
     * get the fully qualified command name for entry of the state
     * 
     * @return string
     */
    public function getEntryCommandName()
    {
        return $this->command_entry_name;
    }

    /**
     * get the fully qualified command name for exit of the state
     * 
     * @return string
     */
    public function getExitCommandName()
    {
        return $this->command_exit_name;
    }

    /**
     * set the exit command name
     * @param string $name a fully qualified command name
     */
    public function setExitCommandName($name)
    {
        $this->command_exit_name = trim($name);
        return $this;
    }

    /**
     * set the entry command name
     * @param string $name a fully qualified command name
     */
    public function setEntryCommandName($name)
    {
        $this->command_entry_name = trim($name);
        return $this;
    }

    /**
     * set the description of the state (for uml generation for example)
     * 
     * @param string $description            
     */
    public function setDescription($description)
    {
        $this->description = $description;
        return $this;
    }

    /**
     * get the description for this state (if any)
     * 
     * @return string
     */
    public function getDescription()
    {
        return $this->description;
    }
}