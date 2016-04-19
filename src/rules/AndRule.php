<?php
namespace izzum\rules;

/**
 * When a rule is chained using the 'AND' operator both rules given in the
 * constructor should apply.
 *
 * @author Rolf Vreijdenberger
 * @author Richard Ruiter
 */
class AndRule extends Rule {
    /**
     *
     * @var izzum\rules\Rule
     */
    private $original;
    /**
     *
     * @var izzum\rules\Rule
     */
    private $other;

    /**
     *
     * @param \izzum\rules\Rule $original            
     * @param \izzum\rules\Rule $other            
     */
    public function __construct(Rule $original, Rule $other)
    {
        $this->original = $original;
        $this->other = $other;
    }

    protected function _applies()
    {
        return (boolean) $this->original->applies() && $this->other->applies();
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
        return "($original and $other)";
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
