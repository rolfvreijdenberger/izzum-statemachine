<?php
namespace izzum\rules;

/**
 * RuleResult contains a result for a certain rule, indicating that the
 * rule wants to store some extra information for a client to consume.
 * 
 * This could be when there are multiple steps or paths in rule execution and
 * the client wants to know which one of those were executed after a rule->applies()
 * call has been made.
 * 
 * This will most probably happen in a rule where multipe business rules are
 * combined.
 *
 * @author Rolf Vreijdenberger
 * @author Richard Ruiter
 */
class RuleResult
{
    /**
     *
     * @var Rule
     */
    private $rule;
    
    /**
     *
     * @var string
     */
    private $result;
    
    /**
     * 
     * @param \izzum\rules\Rule $rule
     * @param string $result
     */
    public function __construct(Rule $rule, $result) {
        $this->rule = $rule;
        $this->result = $result;
    }
    /**
     * get the rule for which this result applies
     * @return Rule
     */
    public function getRule() {
        return $this->rule;
    }
    
    /**
     * get the result
     * @return string
     */
    public function getResult() {
        return $this->result;
    }
}
