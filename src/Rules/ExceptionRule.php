<?php

namespace Izzum\Rules;

/**
 * A Rule that always throws an exception
 *
 * useful for testing.
 *
 * @author Rolf Vreijdenberger
 */
class ExceptionRule extends Rule
{
    protected function _applies(): never
    {
        throw new Exception('this rule always throws an exception', Exception::CODE_GENERAL);
    }
}
