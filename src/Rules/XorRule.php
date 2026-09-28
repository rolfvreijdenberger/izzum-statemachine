<?php

namespace Izzum\Rules;

/**
 * When a rule is chained using the 'XOR' operator one of the rules given in the
 * constructor should apply.
 *
 * @author romuald villetet
 */
class XorRule extends Rule
{
    public function __construct(private readonly Rule $original, private readonly Rule $other) {}

    public function _applies()
    {
        return (bool) ($this->original->applies() ^ $this->other->applies());
    }

    #[\Override]
    public function toString(): string
    {
        // includes the namespace
        $original = $this->original->toString();
        $other = $this->other->toString();
        return "($original xor $other)";
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
