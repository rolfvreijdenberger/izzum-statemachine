<?php

namespace Izzum\StateMachine\Utils;

use Izzum\Command\NullCommand;
use Izzum\Command\Composite;
use Izzum\StateMachine\Exception;
use Izzum\StateMachine\Context;
use Izzum\Command\ICommand;
use Izzum\StateMachine\State;
use Izzum\StateMachine\StateMachine;
use Izzum\StateMachine\Transition;

/**
 * utils class that has some helper methods for diverse purposes
 *
 * @author Rolf Vreijdenberger
 *
 */
class Utils
{
    public const STATE_CONCATENATOR = '_to_';


    /**
     * Checks a fully loaded statemachine for a valid configuration.
     *
     * This is useful in a debug situation so you can check for the validity of
     * rules, commands and callables in guard logic, transition logic and state entry/exit logic
     * before they hit a
     *
     * This does not instantiate any objects. It just checks if callables can be found
     * and if classes for rules and commands can be found
     *
     * @return Exception[] an array of exceptions if anything is wrong with the configuration
     */
    public static function checkConfiguration(StateMachine $machine): array
    {

        //TODO: also check the rules and commands

        $exceptions = [];
        $output = [];
        //check state callables
        foreach ($machine->getStates() as $state) {
            $exceptions[] = self::getExceptionForCheckingCallable($state->getExitCallable(), State::CALLABLE_ENTRY, $state);
            $exceptions[] = self::getExceptionForCheckingCallable($state->getEntryCallable(), State::CALLABLE_ENTRY, $state);
        }

        //check transition callables
        foreach ($machine->getTransitions() as $transition) {
            $exceptions[] = self::getExceptionForCheckingCallable($transition->getGuardCallable(), Transition::CALLABLE_GUARD, $transition);
            $exceptions[] = self::getExceptionForCheckingCallable($transition->getTransitionCallable(), Transition::CALLABLE_TRANSITION, $transition);
        }
        //get the exceptions
        foreach ($exceptions as $e) {
            if (is_a($e, '\Exception')) {
                $output[] = $e;
            }
        }
        return $output;
    }

    /**
     * @param mixed $callable the value being checked for callability; not
     *        statically guaranteed to be callable, since that is exactly what
     *        this method validates
     * @param string $type a type description of the callable eg: State::CALLABLE_ENTRY
     * @param string|\Stringable $info extra info for exception message purposes
     * @param Context $context optional: the context the callable is called in
     * @return Exception|null the exception if the callable does not pass the check, null otherwise
     */
    private static function getExceptionForCheckingCallable($callable, string $type, string|\Stringable $info, ?Context $context = null): ?Exception
    {
        try {
            self::checkCallable($callable, $type, $info, $context);
        } catch (Exception $e) {
            return $e;
        }
        return null;
    }


    /**
     * @param mixed $callable the value being checked for callability; not
     *        statically guaranteed to be callable, since that is exactly what
     *        this method validates
     * @param string $type a type description of the callable eg: State::CALLABLE_ENTRY
     * @param string|\Stringable $info extra info for exception message purposes
     * @param Context $context optional: the context the callable is called in
     * @return bool
     * @throws Exception if the callable does not pass the check
     */
    public static function checkCallable($callable, string $type, string|\Stringable $info, ?Context $context = null): bool
    {
        if ($callable !== null && !is_callable($callable)) {
            throw new Exception(
                sprintf(
                    "not a valid '%s' callable for '%s'. %s",
                    $type,
                    $info,
                    $context,
                ),
                Exception::CALLABLE_FAILURE,
            );
        }
        return true;
    }


    /**
     * gets the transition name by two state names, using the default convention
     * for a transition name (which is concatenating state-from to state-to with
     * '_to_')
     *
     * @return string a string formatted as "state_from_to_state_to"
     */
    public static function getTransitionName(string $from, string $to): string
    {
        return $from . self::STATE_CONCATENATOR . $to;
    }

    /**
     * returns the associated Command for the entry/exit/transition action on a
     * State or a Transition.
     * the Command will be configured with the 'reference' of the stateful
     * object
     *
     * @param string|null $commandName
     *            entry~,exit~ or transition command name.
     *            multiple commands can be split by a ',' in which case a
     *            composite command will be returned.
     * @param Context $context
     *            to be able to get the entity
     * @throws Exception
     */
    public static function getCommand(?string $commandName, Context $context): ICommand
    {
        // it's oke to have no command, as there might be 'marker' states, where
        // we just need to transition something to a next state (according to a
        // rule)
        // where useful work can be done (eg: from the 'initial' type state to
        // a 'shortcut' state for special cases.
        if ($commandName === '' || $commandName === null) {
            // return a command without side effects
            return new NullCommand();
        }

        $output = new Composite();

        // a command string can be made up of multiple commands seperated by a
        // comma
        $allCommands = explode(',', $commandName);

        // get the correct object to inject in the command(s)
        $entity = $context->getEntity();

        foreach ($allCommands as $singleCommand) {
            if (!class_exists($singleCommand)) {
                $e = new Exception(sprintf("failed command creation, class does not exist: (%s) for Context (%s)", $singleCommand, $context->toString()), Exception::COMMAND_CREATION_FAILURE);
                throw $e;
            }

            try {
                $command = new $singleCommand($entity);
                $output->add($command);
            } catch (\Exception $e) {
                $e = new Exception(sprintf("command (%s) objects to construction for Context (%s). message: '%s'", $singleCommand, $context->toString(), $e->getMessage()), Exception::COMMAND_CREATION_FAILURE);
                throw $e;
            }
        }
        return $output;
    }

    /**
     * Always returns an izzum exception (converts a non-izzum exception to an
     * izzum exception).
     * optionally throws it.
     *
     * @throws Exception
     */
    public static function wrapToStateMachineException(\Exception $e, int $code, bool $throw = false): Exception
    {
        if (!is_a($e, 'Izzum\StateMachine\Exception')) {
            // wrap the exception and use the code provided.
            $e = new Exception($e->getMessage(), $code, $e);
        }
        if ($throw) {
            throw $e;
        }
        return $e;
    }

    /**
     * get all states that match a possible regex state from the set of states
     * provided
     *
     * @param State $regex
     *            a possible regex state.
     * @param State[] $targets
     *            all target State instances that we check the regex against.
     * @return State[] an array of State instances from the $targets State
     *         instances that matched the (negated) regex, or the $regex State if it was
     *         not a regex State after all.
     * @link https://php.net/manual/en/function.preg-match.php
     * @link http://regexr.com/ for trying out regular expressions
     */
    public static function getAllRegexMatchingStates(State $regex, array $targets): array
    {
        $all = [];
        if ($regex->isRegex()) {
            // lookup all from states that conform to this rgex
            foreach ($targets as $target) {
                if (!$target->isRegex() && self::matchesRegex($regex, $target)) {
                    $all [] = $target;
                }
            }
        } else {
            $all [] = $regex;
        }
        return $all;
    }

    /**
     * does an input regex state match a target states' name?
     *
     * @param State $regex
     *            the regex state
     * @param State $target
     *            the state to match the regular expression to
     * @link https://php.net/manual/en/function.preg-match.php
     * @link http://regexr.com/ for trying out regular expressions
     */
    public static function matchesRegex(State $regex, State $target): bool
    {
        $matches = false;
        if ($regex->isNormalRegex()) {
            $expression = str_replace(State::REGEX_PREFIX, '', $regex->getName());
            $matches = preg_match($expression, $target->getName()) === 1;
        }
        if ($regex->isNegatedRegex()) {
            $expression = str_replace(State::REGEX_PREFIX_NEGATED, '', $regex->getName());
            $matches = preg_match($expression, $target->getName()) !== 1;
        }
        return $matches;
    }
}
