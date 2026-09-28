<?php

namespace Izzum\Rules;

/**
 * When a rule is chained using the 'NOT' operator the rule given in the
 * constructor should not apply.
 * The rule is effectively negated.
 *
 * @author Rolf Vreijdenberger
 * @author Richard Ruiter
 */
class NotRule extends Rule
{
    public function __construct(private readonly Rule $original) {}

    public function _applies()
    {
        return (bool) !$this->original->applies();
    }

    /**
     * Return original results
     */
    #[\Override]
    public function getResults(): array
    {
        return $this->original->getResults();
    }
}
