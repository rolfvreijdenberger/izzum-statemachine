<?php

namespace Izzum\StateMachine;

use Izzum\Command\ICommand;
use Izzum\StateMachine\Utils\Utils;

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
class State implements \Stringable
{
    /**
     * state name if it is unknown (not configured)
     * @var string
     */
    public const STATE_UNKNOWN = 'unknown';

    /**
     * default name for the first/only initial state (but you can specify whatever you want for your initial state)
     * @var string
     */
    public const STATE_NEW = 'new';

    /**
     * default name for a normal final state
     * @var string
     */
    public const STATE_DONE = 'done';

    /**
     * default exit/entry command
     * @var string
     */
    public const COMMAND_NULL = '\Izzum\Command\NullCommand';

    /**
     * default exit/entry command for constructor
     * @var string
     */
    public const COMMAND_EMPTY = '';
    public const CALLABLE_NULL = null;
    public const REGEX_PREFIX = 'regex:';
    public const REGEX_PREFIX_NEGATED = 'not-regex:';

    public const CALLABLE_ENTRY = 'state entry';
    public const CALLABLE_EXIT = 'state exit';

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
    public const TYPE_INITIAL = 'initial', TYPE_NORMAL = 'normal', TYPE_FINAL = 'final', TYPE_REGEX = 'regex';

    /**
     * The state type:
     * - State::TYPE_INITIAL
     * - State::TYPE_NORMAL
     * - State::TYPE_FINAL
     * - State::TYPE_REGEX
     * @var string
     */
    protected string $type = '';

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
    protected array $transitions;

    /**
     * The name of the state
     *
     * @var string
     */
    protected string $name;

    /**
     * fully qualified command name for the command to be executed
     * when entering a state as part of a transition.
     * This can actually be a ',' seperated string of multiple commands that
     * will be executed as a composite.
     *
     * @var string
     */
    protected string $commandEntryName;

    /**
     * fully qualified command name for the command to be executed
     * when exiting a state as part of a transition.
     * This can actually be a ',' seperated string of multiple commands that
     * will be executed as a composite.
     *
     * @var string
     */
    protected string $commandExitName;

    /**
     *  the entry callable method
     * @var callable
     */
    protected $callableEntry;

    /**
     *  the exit callable method
     * @var callable
     */
    protected $callableExit;

    /**
     * a description for the state
     *
     * @var string|null
     */
    protected $description;

    /**
     *
     * @param string $name
     *            the name of the state (can also be a regex in format: [not-]regex:/<regex-specification-here>/)
     * @param string $type
     *            the type of the state (on of self::TYPE_<*>)
     * @param $commandEntryName optional:
     *            a command to be executed when a transition enters this state
     *            One or more fully qualified command (sub)class name(s) to
     *            execute when entering this state.
     *            This can actually be a ',' seperated string of multiple
     *            commands that will be executed as a composite.
     * @param $commandExitName optional:
     *            a command to be executed when a transition leaves this state
     *            One or more fully qualified command (sub)class name(s) to
     *            execute when exiting this state.
     *            This can actually be a ',' seperated string of multiple
     *            commands that will be executed as a composite.
     * @param callable $callableEntry
     *            optional: a php callable to call. eg: "function(){echo 'closure called';};"
     * @param callable $callableExit
     *            optional: a php callable to call. eg: "Izzum\MyClass::myStaticMethod"
     */
    public function __construct(string $name, string $type = self::TYPE_NORMAL, ?string $commandEntryName = self::COMMAND_EMPTY, ?string $commandExitName = self::COMMAND_EMPTY, $callableEntry = self::CALLABLE_NULL, $callableExit = self::CALLABLE_NULL)
    {
        $this->setName($name);
        $this->setType($type);
        $this->setEntryCommandName($commandEntryName);
        $this->setExitCommandName($commandExitName);
        $this->setEntryCallable($callableEntry);
        $this->setExitCallable($callableExit);
        $this->transitions = [];
    }

    /**
     * get the entry callable, the callable to be called when entering this state
     * @return callable
     */
    public function getEntryCallable()
    {
        return $this->callableEntry;
    }

    /**
     * set the entry callable, the callable to be called when entering this state
     * @param callable $callable
     */
    public function setEntryCallable($callable): static
    {
        $this->callableEntry = $callable;
        return $this;
    }

    /**
     * get the exit callable, the callable to be called when exiting this state
     * @return callable
     */
    public function getExitCallable()
    {
        return $this->callableExit;

    }

    /**
     * set the exit callable, the callable to be called when exiting this state
     * @param callable $callable
     */
    public function setExitCallable($callable): static
    {
        $this->callableExit = $callable;
        return $this;
    }

    public function isInitial(): bool
    {
        return $this->type === self::TYPE_INITIAL;
    }

    public function isNormal(): bool
    {
        return $this->type === self::TYPE_NORMAL;
    }

    public function isFinal(): bool
    {
        return $this->type === self::TYPE_FINAL;
    }

    /**
     * is this state a regex type of state?
     * formats:
     *      "regex:<regular-expression-here>"
     *      "not-regex:<regular-expression-here>"
     *
     * @link https://php.net/manual/en/function.preg-match.php
     * @link http://regexr.com/ for trying out regular expressions
     */
    public function isRegex(): bool
    {
        //check the type (and check the state name for regex matches)
        return $this->type === self::TYPE_REGEX || $this->isNormalRegex() || $this->isNegatedRegex();
    }

    /**
     * is this state a normal regex type of state?
     * "regex:<regular-expression-here>"
     */
    public function isNormalRegex(): bool
    {
        return str_starts_with($this->getName(), self::REGEX_PREFIX);
    }

    /**
     * is this state a negated regex type of state?
     * "not-regex:<regular-expression-here>"
     */
    public function isNegatedRegex(): bool
    {
        return str_starts_with($this, self::REGEX_PREFIX_NEGATED);
    }

    public function getType(): string
    {

        $this->isRegex();
        return $this->type;
    }

    protected function setType(string $type): static
    {
        //if a client mistakenly creates a regex State (a name of [not-]<regex:>), but with a non-regex type,
        //we will set it to a regex state.
        if ($this->isRegex()) {
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
     * @return bool yes in case the transition was not on the State already or in case of an invalid transition
     */
    public function addTransition(Transition $transition): bool
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
     * @return Transition[]
     */
    public function getTransitions(): array
    {
        // a subclass might return an ordered/prioritized array
        return $this->transitions;
    }

    public function getName(): string
    {
        return $this->name;
    }

    protected function setName(string $name): static
    {
        $this->name = trim($name);
        return $this;
    }

    public function __toString(): string
    {
        return (string) $this->getName();
    }

    public function hasTransition(string $transitionName): bool
    {
        $has = false;
        foreach ($this->transitions as $transition) {
            if ($transitionName === $transition->getName()) {
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
     * @throws Exception
     */
    public function entryAction(Context $context): void
    {
        $command = $this->getCommand($this->getEntryCommandName(), $context);
        $this->execute($command);
        $this->callCallable($this->getEntryCallable(), $context, self::CALLABLE_ENTRY);
    }

    /**
     * calls a $callable if it exists, with the arguments $context->getEntity()
     * @param callable $callable
     * @param string $type the type of callable (self::CALLABLE_ENTRY | self::CALLABLE_EXIT)
     */
    protected function callCallable($callable, Context $context, string $type = 'n/a'): void
    {
        if ($callable != self::CALLABLE_NULL) {
            Utils::checkCallable($callable, $type, $this, $context);
            call_user_func($callable, $context->getEntity());
        }
    }

    /**
     * An action executed every time a state is exited.
     * An exit action will not be executed for a 'final' state since a machine
     * will not leave a 'final' state.
     *
     * @throws Exception
     */
    public function exitAction(Context $context): void
    {
        $command = $this->getCommand($this->getExitCommandName(), $context);
        $this->execute($command);
        $this->callCallable($this->getExitCallable(), $context, self::CALLABLE_EXIT);
    }

    /**
     * @throws Exception
     */
    protected function execute(ICommand $command): void
    {
        try {
            $command->execute();
        } catch (\Exception $e) {
            // command failure
            $e = new Exception($e->getMessage(), Exception::COMMAND_EXECUTION_FAILURE, $e);
            throw $e;
        }
    }

    /**
     * returns the associated Command for the entry/exit action.
     * the Command will be configured with the domain model via dependency injection
     *
     * @throws Exception
     */
    protected function getCommand(string $commandName, Context $context): ICommand
    {
        return Utils::getCommand($commandName, $context);
    }

    /**
     * get the transition for this state that can be triggered by an event code.
     *
     * @param string $event
     *            the event code that can trigger a transition (mealy machine)
     * @return Transition[]
     */
    public function getTransitionsTriggeredByEvent(?string $event): array
    {
        $output = [];
        foreach ($this->getTransitions() as $transition) {
            if ($transition->isTriggeredBy($event)) {
                $output [] = $transition;
            }
        }
        return $output;
    }

    /**
     * get the fully qualified command name for entry of the state
     */
    public function getEntryCommandName(): string
    {
        return $this->commandEntryName;
    }

    /**
     * get the fully qualified command name for exit of the state
     */
    public function getExitCommandName(): string
    {
        return $this->commandExitName;
    }

    /**
     * @param string|null $name a fully qualified command name
     */
    public function setExitCommandName(?string $name): static
    {
        $this->commandExitName = trim($name ?? self::COMMAND_EMPTY);
        return $this;
    }

    /**
     * @param string|null $name a fully qualified command name
     */
    public function setEntryCommandName(?string $name): static
    {
        $this->commandEntryName = trim($name ?? self::COMMAND_EMPTY);
        return $this;
    }

    /**
     * set the description of the state (for uml generation for example)
     */
    public function setDescription(?string $description): static
    {
        $this->description = $description;
        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }
}
