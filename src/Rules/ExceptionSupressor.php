<?php

namespace Izzum\Rules;

/**
 * Supresses an exception and returns either true or false in case a concrete
 * rule
 * throws an exception.
 * In case the original rule applies succesfully (a true or
 * false result) the result is passed back to the client.
 *
 * This rule is an implementation of the Decorator pattern and allows a client
 * to use rules with a consistent behaviour for exceptions.
 *
 * @author Rolf Vreijdenberger
 * @link https://en.wikipedia.org/wiki/Decorator_pattern
 *
 */
class ExceptionSupressor extends Rule
{
    /**
     * @param boolean $supressedResult
     *            what to return in case the decorated rule
     *            throws an error
     */
    public function __construct(private readonly Rule $decoree, private bool $supressedResult = false) {}

    public function _applies()
    {
        try {
            $output = (bool) $this->decoree->applies();
        } catch (Exception) {
            $output = $this->supressedResult;
        }
        return $output;
    }
}
