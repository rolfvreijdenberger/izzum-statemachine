<?php
namespace izzum\rules;

/**
 * When a rule is chained using the 'XOR' operator one of the rules given in the
 * constructor should apply.
 *
 * @author romuald villetet
 */
class XorRule extends Rule {
    /**
     *
     * @var Rule
     */
    private $original;
    /**
     *
     * @var Rule
     */
    private $other;

    /**
     *
     * @param Rule $original            
     * @param Rule $other            
     */
    public function __construct(Rule $original, Rule $other)
    {
        $this->original = $original;
        $this->other = $other;
    }

    public function _applies()
    {
        return (boolean) ($this->original->applies() ^ $this->other->applies());
    }

    /**
     *
     * @return string
     */
    public function toString()
    {
        // includes the namespace
        $original = $this->original->toString();
        $other = $this->other->toString();
        return "($original xor $other)";
    }

    /**
     * Merge results
     *
     * @return array
     */
    public function getResults()
    {
        return array_merge($this->other->getResults(), $this->original->getResults());
    }
}