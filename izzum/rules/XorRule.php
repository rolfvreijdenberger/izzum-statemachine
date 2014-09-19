<?php

namespace izzum\rules;

/**
 * When a rule is chained using the 'XOR' operator one of the rules given in the 
 * constructor should apply.
 *
 * @author romuald
 */
class XorRule extends Rule
{
    /**
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
    
    public function _applies() 
    {
        return (boolean) ($this->original->applies() ^ $this->other->applies());
    }
    
    /**
     * @return string
     */
    public function toString()
    {
        //includes the namespace 
        $original = $this->original->toString();
        $other = $this->other->toString();
        return "($original xor $other)";
    }
    
    /**
     * Merge results
     * 
     * @return array
     */
    public function getResult() {
        return array_merge($this->other->getResult(), $this->original->getResult());
    }
}