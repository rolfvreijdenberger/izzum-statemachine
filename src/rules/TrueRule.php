<?php
namespace izzum\rules;

/**
 * A Rule that always returns true
 *
 * useful for testing or to be used in a statemachine guard clause that should
 * always pass.
 *
 * @author Rolf Vreijdenberger
 */
class TrueRule extends Rule {

    protected function _applies()
    {
        return true;
    }
}