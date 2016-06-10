<?php
namespace izzum\statemachine;
use izzum\command\NullCommand;
use izzum\rules\TrueRule;
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
 * transition between states.
 *
 * It has functionality to accept a Rule (guard logic) and a Command (transition
 * logic) as well as callables for the guard logic and transition logic .
 * callables are: closures, anonymous functions, user defined functions,
 * instance methods, static methods etc. see the php manual.
 *
 * The guards are used to check whether a transition can take place (Rule and callable)
 * The logic parts are used to execute the transition logic (Command and callable)
 *
 * Rules and commands should be able to be found/autoloaded by the application
 *
 * If transitions share the same states (both to and from) then they should
 * point to the same object reference (same states should share the exact same state
 * configuration).
 *
 * @link https://php.net/manual/en/language.types.callable.php
 * @link https://en.wikipedia.org/wiki/Command_pattern
 * @author Rolf Vreijdenberger
 *
 */
class Transition {
    const RULE_TRUE = '\izzum\rules\TrueRule';
    const RULE_FALSE = '\izzum\rules\FalseRule';
    const RULE_EMPTY = '';
    const COMMAND_NULL = '\izzum\command\NullCommand';
    const COMMAND_EMPTY = '';
    const CALLABLE_NULL = null;

    /**
     * the state this transition starts from
     *
     * @var State
     */
    protected $state_from;

    /**
     * the state this transition points to
     *
     * @var State
     */
    protected $state_to;

    /**
     * an event code that can trigger this transitions
     *
     * @var string
     */
    protected $event;

    /**
     * The fully qualified Rule class name of the
     * Rule to be applied to check if we can transition.
     * This can actually be a ',' seperated string of multiple rules.
     *
     * @var string
     */
    protected $rule;

    /**
     * the fully qualified Command class name of the Command to be
     * executed as part of the transition logic.
     * This can actually be a ',' seperated string of multiple commands.
     *
     * @var string
     */
    protected $command;

    /**
     * the callable to call as part of the transition logic
     * @var callable
     */
    protected $callable_transition;

    /**
     * the callable to call as part of the transition guard (should return a boolean)
     * @var callable
     */
    protected $callable_guard;

    /**
     * a description for the state
     *
     * @var string
     */
    protected $description;

    /**
     *
     * @param State $state_from
     * @param State $state_to
     * @param string $event
     *            optional: an event name by which this transition can be
     *            triggered
     * @param string $rule
     *            optional: one or more fully qualified Rule (sub)class name(s)
     *            to check to see if we are allowed to transition.
     *            This can actually be a ',' seperated string of multiple rules
     *            that will be applied as a chained 'and' rule.
     * @param string $command
     *            optional: one or more fully qualified Command (sub)class
     *            name(s) to execute for a transition.
     *            This can actually be a ',' seperated string of multiple
     *            commands that will be executed as a composite.
     * @param callable $callable_guard
     *            optional: a php callable to call. eg: "function(){echo 'closure called';};"
     * @param callable $callable_transition
     *            optional: a php callable to call. eg: "izzum\MyClass::myStaticMethod"
     */
    public function __construct(State $state_from, State $state_to, $event = null, $rule = self::RULE_EMPTY, $command = self::COMMAND_EMPTY, $callable_guard = self::CALLABLE_NULL, $callable_transition = self::CALLABLE_NULL)
    {
        $this->state_from = $state_from;
        $this->state_to = $state_to;
        $this->setRuleName($rule);
        $this->setCommandName($command);
        $this->setGuardCallable($callable_guard);
        $this->setTransitionCallable($callable_transition);
        // setup bidirectional relationship with state this transition
        // originates from. only if it's not a regex or final state type
        if (!$state_from->isRegex() && !$state_from->isFinal()) {
            $state_from->addTransition($this);
        }
        // set and sanitize event name
        $this->setEvent($event);
    }

    /**
     * the callable to call as part of the transition logic
     * @param callable $callable
     */
    public function setTransitionCallable($callable) {
        $this->callable_transition = $callable;
        return $this;
    }

    /**
     * returns the callable for the transition logic.
     * @return callable or null
     */
    public function getTransitionCallable()
    {
        return $this->callable_transition;
    }

    /**
     * the callable to call as part of the transition guard
     * @param callable $callable
     */
    public function setGuardCallable($callable) {
        $this->callable_guard = $callable;
        return $this;
    }

    /**
     * returns the callable for the guard logic.
     * @return callable or null
     */
    public function getGuardCallable()
    {
        return $this->callable_guard;
    }

    /**
     * Can this transition be triggered by a certain event?
     * This also matches on the transition name.
     *
     * @param string $event
     * @return boolean
     */
    public function isTriggeredBy($event)
    {
        return ($this->event === $event || $this->getName() === $event) && $event !== null && $event !== '';
    }

    /**
     * is a transition possible? Check the guard Rule with the domain object
     * injected.
     *
     * @param Context $context
     * @return boolean
     */
    public function can(Context $context)
    {
        try {
            if(!$this->getRule($context)->applies()) {
                return false;
            }
            return $this->callCallable($this->getGuardCallable(), $context);
        } catch(\Exception $e) {
            $e = new Exception($this->toString() . ' '. $e->getMessage(), Exception::RULE_APPLY_FAILURE, $e);
            throw $e;
        }
    }

    /**
     * Process the transition for the statemachine and execute the associated
     * Command with the domain object injected.
     *
     * @param Context $context
     * @return void
     */
    public function process(Context $context)
    {
        // execute, we do not need to check if we 'can' since this is done
        // by the statemachine itself
        try {
            $this->getCommand($context)->execute();
            $this->callCallable($this->getTransitionCallable(), $context);
        } catch(\Exception $e) {
            // command failure
            $e = new Exception($e->getMessage(), Exception::COMMAND_EXECUTION_FAILURE, $e);
            throw $e;
        }
    }

    /**
     * calls the $callable as part of the transition
     * @param callable $callable
     * @param Context $context
     */
    protected function callCallable($callable, Context $context) {
        //in case it is a guard callable we need to return true/false
        if($callable != self::CALLABLE_NULL && is_callable($callable)){
            return (boolean) call_user_func($callable, $context->getEntity());
        }
        return true;
    }

    /**
     * returns the associated Rule for this Transition,
     * configured with a 'reference' (stateful) object
     *
     * @param Context $context
     *            the associated Context for a our statemachine
     * @return IRule a Rule or chained AndRule if the rule input was a ','
     *         seperated string of rules.
     * @throws Exception
     */
    public function getRule(Context $context)
    {
        // if no rule is defined, just allow the transition by default
        if ($this->rule === '' || $this->rule === null) {
            return new TrueRule();
        }

        $entity = $context->getEntity();

        // a rule string can be made up of multiple rules seperated by a comma
        $all_rules = explode(',', $this->rule);
        $rule = new TrueRule();
        foreach ($all_rules as $single_rule) {

            // guard clause to check if rule exists
            if (!class_exists($single_rule)) {
                $e = new Exception(sprintf("failed rule creation, class does not exist: (%s) for Context (%s).", $this->rule, $context->toString()), Exception::RULE_CREATION_FAILURE);
                throw $e;
            }

            try {
                $and_rule = new $single_rule($entity);
                // create a chain of rules that need to be true
                $rule = new AndRule($rule, $and_rule);
            } catch(\Exception $e) {
                $e = new Exception(sprintf("failed rule creation, class objects to construction with entity: (%s) for Context (%s). message: %s", $this->rule, $context->toString(), $e->getMessage()), Exception::RULE_CREATION_FAILURE);
                throw $e;
            }
        }
        return $rule;
    }


    /**
     * returns the associated Command for this Transition.
     * the Command will be configured with the 'reference' of the stateful
     * object.
     * In case there have been multiple commands as input (',' seperated), this
     * method will return a Composite command.
     *
     * @param Context $context
     * @return izzum\command\ICommand
     * @throws Exception
     */
    public function getCommand(Context $context)
    {
        return Utils::getCommand($this->command, $context);
    }

    /**
     *
     * @return string
     */
    public function toString()
    {
        return get_class($this) . " '" . $this->getName() . "' [event]: '" . $this->event . "'" . " [rule]: '" . $this->rule . "' [command]: '" . $this->command . "'";
    }

    /**
     * get the state this transition points from
     *
     * @return State
     */
    public function getStateFrom()
    {
        return $this->state_from;
    }

    /**
     * get the state this transition points to
     *
     * @return State
     */
    public function getStateTo()
    {
        return $this->state_to;
    }

    /**
     * get the transition name.
     * the transition name is always unique for a statemachine
     * since it constists of <state_from>_to_<state_to>
     *
     * @return string
     */
    public function getName()
    {
        $name = Utils::getTransitionName($this->getStateFrom()->getName(), $this->getStateTo()->getName());
        return $name;
    }

    /**
     * return the command name(s).
     * one or more fully qualified command (sub)class name(s) to execute for a
     * transition.
     * This can actually be a ',' seperated string of multiple commands that
     * will be executed as a composite.
     *
     * @return string
     */
    public function getCommandName()
    {
        return $this->command;
    }

    public function setCommandName($command)
    {
        $this->command = trim($command);
        return $this;
    }

    public function getRuleName()
    {
        return $this->rule;
    }

    public function setRuleName($rule)
    {
        $this->rule = trim($rule);
        return $this;
    }

    /**
     * set the description of the transition (for uml generation for example)
     *
     * @param string $description
     */
    public function setDescription($description)
    {
        $this->description = $description;
        return $this;
    }

    /**
     * get the description for this transition (if any)
     *
     * @return string
     */
    public function getDescription()
    {
        return $this->description;
    }

    /**
     * set the event name by which this transition can be triggered.
     * In case the event name is null or an empty string, it defaults to the
     * transition name.
     *
     * @param string $event
     */
    public function setEvent($event)
    {
        if ($event === null || $event === '') {
            $event = $this->getName();
        }
        $this->event = $event;
        return $this;
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
     * for transitions that contain regex states, we need to be able to copy an
     * existing (subclass of this) transition with all it's fields.
     * We need to instantiate it with a different from and to state since either
     * one of those states can be the regex states. All other fields need to be
     * copied.
     *
     * Override this method in a subclass to add other fields. By using 'new
     * static' we are already instantiating a possible subclass.
     *
     *
     * @param State $from
     * @param State $to
     * @return Transition
     */
    public function getCopy(State $from, State $to)
    {
        $copy = new static($from, $to, $this->getEvent(), $this->getRuleName(), $this->getCommandName(), $this->getGuardCallable(), $this->getTransitionCallable());
        $copy->setDescription($this->getDescription());
        return $copy;
    }

    /**
     *
     * @return string
     */
    public function __toString()
    {
        return $this->getName();
    }
}