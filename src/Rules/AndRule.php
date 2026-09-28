<?php

namespace Izzum\Rules;

/**
 * When a rule is chained using the 'AND' operator both rules given in the
 * constructor should apply.
 *
 * @author Rolf Vreijdenberger
 * @author Richard Ruiter
 */
class AndRule extends Rule
{
    public function __construct(private readonly Rule $original, private readonly Rule $other) {}

    protected function _applies()
    {
        return (bool) $this->original->applies() && $this->other->applies();
    }

    #[\Override]
    public function toString(): string
    {
        // includes the namespace
        $original = $this->original->toString();
        $other = $this->other->toString();
        return "($original and $other)";
    }

    /**
     * Merge results
     */
    #[\Override]
    public function getResults(): array
    {
        return array_merge($this->other->getResults(), $this->original->getResults());
    }
}
