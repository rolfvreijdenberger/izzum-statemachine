<?php
namespace izzum\rules;

/**
 * A Rule that always returns false
 *
 * useful for testing or to be used in a statemachine guard clause that should
 * always fail.
 *
 * @author Rolf Vreijdenberger
 */
class FalseRule extends Rule {

    protected function _applies()
    {
        return false;
    }
}