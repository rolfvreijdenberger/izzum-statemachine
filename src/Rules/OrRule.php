<?php

namespace Izzum\Rules;

/**
 * When a rule is chained using the 'OR' operator only one of the rules given in
 * the
 * constructor needs to apply.
 *
 * $rule = new TrueRule();
 * $chained = $rule->orRule(new FalseRule());
 * $chained->applies();//true, since true or false is true.
 *
 * @author Rolf Vreijdenberger
 * @author Richard Ruiter
 */
class OrRule extends Rule
{
    public function __construct(private readonly Rule $original, private readonly Rule $other) {}

    public function _applies()
    {
        return (bool) $this->original->applies() || $this->other->applies();
    }

    #[\Override]
    public function toString(): string
    {
        // includes the namespace
        $original = $this->original->toString();
        $other = $this->other->toString();
        return "($original or $other)";
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
