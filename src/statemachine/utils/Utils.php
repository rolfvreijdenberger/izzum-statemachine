<?php
namespace izzum\statemachine\utils;
use izzum\command\NullCommand;
use izzum\command\Composite;
use izzum\statemachine\Exception;
use izzum\statemachine\Context;
use izzum\command\ICommand;
use izzum\statemachine\State;
use izzum\statemachine\StateMachine;

/**
 * utils class that has some helper methods for diverse purposes
 *
 * @author Rolf Vreijdenberger
 *
 */
class Utils {
    const STATE_CONCATENATOR = '_to_';

    /**
     * gets the transition name by two state names, using the default convention
     * for a transition name (which is concatenating state-from to state-to with
     * '_to_')
     *
     * @param string $from
     *            the state from which the transition is made
     * @param string $to
     *            the state to which the transition will be made
     * @return string <state_from>_to_<state_to>
     */
    public static function getTransitionName($from, $to)
    {
        return $from . self::STATE_CONCATENATOR . $to;
    }

    /**
     * returns the associated Command for the entry/exit/transition action on a
     * State or a Transition.
     * the Command will be configured with the 'reference' of the stateful
     * object
     *
     * @param string $command_name
     *            entry~,exit~ or transition command name.
     *            multiple commands can be split by a ',' in which case a
     *            composite command will be returned.
     * @param Context $context
     *            to be able to get the entity
     * @return ICommand
     * @throws Exception
     */
    public static function getCommand($command_name, Context $context)
    {
        // it's oke to have no command, as there might be 'marker' states, where
        // we just need to transition something to a next state (according to a
        // rule)
        // where useful work can be done (eg: from the 'initial' type state to
        // a 'shortcut' state for special cases.
        if ($command_name === '' || $command_name === null) {
            // return a command without side effects
            return new NullCommand();
        }

        $output = new Composite();

        // a command string can be made up of multiple commands seperated by a
        // comma
        $all_commands = explode(',', $command_name);

        // get the correct object to inject in the command(s)
        $entity = $context->getEntity();

        foreach ($all_commands as $single_command) {
            if (!class_exists($single_command)) {
                $e = new Exception(sprintf("failed command creation, class does not exist: (%s) for Context (%s)", $single_command, $context->toString()), Exception::COMMAND_CREATION_FAILURE);
                throw $e;
            }

            try {
                $command = new $single_command($entity);
                $output->add($command);
            } catch(\Exception $e) {
                $e = new Exception(sprintf("command (%s) objects to construction for Context (%s). message: '%s'", $single_command, $context->toString(), $e->getMessage()), Exception::COMMAND_CREATION_FAILURE);
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
     * @param \Exception $e
     * @param int $code
     * @return Exception
     * @throws Exception
     */
    public static function wrapToStateMachineException(\Exception $e, $code, $throw = false)
    {
        if (!is_a($e, 'izzum\statemachine\Exception')) {
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
    public static function getAllRegexMatchingStates(State $regex, $targets)
    {
        $all = array();
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
     * @return boolean
     * @link https://php.net/manual/en/function.preg-match.php
     * @link http://regexr.com/ for trying out regular expressions
     */
    public static function matchesRegex(State $regex, State $target)
    {
        $matches = false;
        if ($regex->isNormalRegex()) {
            $expression = str_replace(State::REGEX_PREFIX, '', $regex->getName());
            $matches = preg_match($expression, $target->getName()) === 1;
        }
        if($regex->isNegatedRegex()) {
            $expression = str_replace(State::REGEX_PREFIX_NEGATED, '', $regex->getName());
            $matches = preg_match($expression, $target->getName()) !== 1;
        }
        return $matches;
    }
}