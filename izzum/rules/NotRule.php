<?php
namespace izzum\rules;

/**
 * When a rule is chained using the 'NOT' operator the rule given in the 
 * constructor should not apply. The rule is effectively negated.
 *
 * @author Rolf Vreijdenberger
 */
class NotRule extends Rule
{

    /**
     *
     * @var izzum\rules\Rule
     */
    private $original;

    /**
     * 
     * @param \izzum\rules\Rule $original
     */
    public function __construct(Rule $original)
    {
        $this->original = $original;
    }

    public function _applies()
    {
        return (boolean) !$this->original->applies();
    }
    
    /**
     * Return original results
     * 
     * @return array
     */
    public function getResult() 
    {
        return $this->original->getResult();
    }
}
