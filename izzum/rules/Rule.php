<?php
namespace izzum\rules;
use izzum\rules\Exception;

/**
 * Rules are used to encapsulate business logic of the type where you ask a 
 * question: 'does this piece of code conform to a specific business rule?'
 * Rules should never have side effects and should only return true or false.
 * 
 * This Rule serves as a base class for all your business logic, encapsulating
 * and centralizing the logic in a class and making it reusable through it's interface.
 * 
 * This is a way of seperating mechanism and policy of code.
 * In other words: how it is done vs what should be done. The mechanism is a rule
 * library, the policy is what is defined in the rule.
 * https://en.wikipedia.org/wiki/Separation_of_mechanism_and_policy
 * 
 * The rules package aims to prevent code like this:
 * if ($name == 'blabla' && $password == 'lala') {
 *  //do something here. the statement above might be duplicated in a lot of places
 * }
 * and substitute it by this:
 * $rule = new AccesRule($name, $password);
 * if($rule->applies()) {
 *  //do something
 * }
 * 
 * 
 * usage: 
 * Clients should subclass this Rule and implement the 
 * protected '_applies' method and let that return a boolean value.
 * 
 * A concrete Rule (a subclass) can (and should be) injected with contextual data via
 * dependency injection in the constructor
 *
 * 
 * 
 * @author Rolf Vreijdenberger
 * @author Richard Ruiter
 * @link https://en.wikipedia.org/wiki/Separation_of_mechanism_and_policy
 */
abstract class Rule
{
    /**
     * contains results that a concrete Rule can set.
     * This allows clients of the Rule to check if certain conditions in 
     * a rule have been met. The subclassed rule should add a result itself.
     * This will happen in case of multiple conditions being checked for a rule
     * to evaluate to true. If one of the conditions is not met, you can set a
     * result there.
     * @var RuleResult[] 
     */
    private $result = array();

    /**
     * should we cache the result or not?
     * TRICKY: this might be very dangerous for non-deterministic rules but a 
     * great speed optimizer for rules that are evaluated multiple times and are
     * deterministic
     * @var boolean
     */
    private $use_caching = false;
    
    /**
     * if the result is cached, it will be put in this variable
     * after the applies method has run
     * @var boolean
     */
    private $cache;
    
    
     /**
     * A concrete rule should at least implement the _applies method and return a 
     * boolean. When the rule logic cannot determine if it applies then an exception should
     * be thrown instead of a boolean value
     * 
     * @return boolean
     * @throws \Exception
     */
    abstract protected function _applies();
    
    
    /**
     * The applies method is the only point where the rule can be validated. 
     * Internally each rule implements the _applies() method to do the actual
     * validation. 
     * 
     * There are only two types of outcome for each rule.
     * Either the rule applies (true) or doesn't (false).
     * Any other outcome should always be thrown as an exception so no false
     * assumptions can be made by the caller. For example when a rule returns a
     * NULL value the caller may asume that false is meant. The rule cannot
     * trust that the caller checks the boolean type so we will.
     * 
     * @return boolean
     * @throws \izzum\rules\Exception
     */
    public final function applies()
    {
        try
        {
            if($this->getCacheEnabled())
            {
                if($this->cache !== null) {
                    return $this->cache;
                }
            }
            $this->clearResult();
            $this->clearCache();
            $result = $this->_applies();
            if($this->getCacheEnabled()) {
                $this->cache = $result;
            }
            if (is_bool($result)) {
                return $result;
            } else {
                $error = 'A rule must return a boolean.';
                throw new Exception($error, Exception::CODE_NONBOOLEAN);
            }
        } catch (Exception $e)
        {
            $this->handleException($e);
            throw $e;
        } catch (\Exception $e)
        {
            $e = new Exception($e->getMessage(), $e->getCode(), $e);
            $this->handleException($e);
            throw $e;
        }
    }
    
    /**
     * hook method for logging etc.
     * @param Exception $e
     */
    protected function handleException($e) {
        //implement in subclass if needed
    }

    /**
     * Chain a 'OR' rule. This means one of the rules should apply.
     * 
     * @param \izzum\rules\Rule $other
     * @return \izzum\rules\Rule
     */
    public final function orRule(Rule $other)
    {
        return new OrRule($this, $other);
    }

    /**
     * Chain a 'XOR' rule. This means one of the rules should apply but not both.
     *
     * @param \izzum\rules\Rule $other
     * @return \izzum\rules\Rule
     */
    public final function xorRule(Rule $other)
    {
    	return new XorRule($this, $other);
    }
    
    
    /**
     * Chain a 'AND' rule. This means both rules should apply.
     * 
     * @param \izzum\rules\Rule $other
     * @return \izzum\rules\Rule
     */
    public final function andRule(Rule $other)
    {
        return new AndRule($this, $other);
    }

    /**
     * Inverse current rule
     * 
     * @return \izzum\rules\Rule
     */
    public final function not()
    {
        return new NotRule($this);
    }


    
    /**
     * @return string
     */
    public function toString()
    {
        //includes the namespace 
        return get_class($this);
    }
    
    /**
     * Gets an array of RuleResult to check if a rule has set a certain result
     * for the client of the rule.
     * This will mostly be useful to check what actually happened when a rule
     * has failed.
     * 
     * @return RuleResult[]
     */
    public function getResult() 
    {
        return $this->result;
    }
    
    /**
     * Add a result to this rule. 
     * 
     * this might be useful if a client wants to check if a rule
     * has executed certain steps during the logic of rule execution. 
     * @param string $result
     */
    protected final function addResult($result)
    {
        $this->result[] = new RuleResult($this, $result);
    }
    
    /**
     * Check if this rule contains a certain expected result.
     * This is only matched on the string, not on the class that generated
     * the result
     * In case you want to also know the class or classname, use getResult()
     * @see Rule::getResult()
     * @param string $expected
     */
    public final function containsResult($expected) 
    {
        $output = false;
        $results = $this->getResult();
        foreach ($results as $result) {
            if($result->getResult() === $expected) 
            {
                $output = true;
            }
        }
        return $output;
    }
    
    /**
     * Clear the results
     */
    private final function clearResult()
    {
        $this->result = array();
    }
    
    private final function clearCache()
    {
        $this->cache = null;
    }
    
    /**
     * Has any result?
     * 
     * @return boolean
     */
    public final function hasResult()
    {
        return count($this->getResult()) !== 0;
    }
    
    /**
     * should we cache the result if the rule is applied more than once?
     * @param boolean $cache
     */
    public function setCacheEnabled($cached = true) {
        $cached = (bool) $cached;
        $this->use_caching = $cached;
        $this->clearCache();
    }
    
    /**
     * 
     * @return boolean
     */
    public function getCacheEnabled() {
        return $this->use_caching;
    }
}
