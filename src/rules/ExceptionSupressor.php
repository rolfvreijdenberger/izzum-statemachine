<?php
namespace izzum\rules;

/**
 * Supresses an exception and returns either true or false in case a concrete
 * rule
 * throws an exception.
 * In case the original rule applies succesfully (a true or
 * false result) the result is passed back to the client.
 *
 * This rule is an implementation of the Decorator pattern and allows a client
 * to use rules with a consistent behaviour for exceptions.
 * 
 * @author Rolf Vreijdenberger
 * @link https://en.wikipedia.org/wiki/Decorator_pattern
 *      
 */
class ExceptionSupressor extends Rule {
    
    /**
     *
     * @var Rule
     */
    private $decoree;
    
    /**
     *
     * @var boolean
     */
    private $supressed_result;

    /**
     *
     * @param Rule $decoree            
     * @param boolean $supressed_result
     *            what to return in case the decorated rule
     *            throws an error
     */
    public function __construct(Rule $decoree, $supressed_result = false)
    {
        $this->decoree = $decoree;
        $this->supressed_result = $supressed_result;
    }

    public function _applies()
    {
        try {
            $output = (boolean) $this->decoree->applies();
        } catch(Exception $e) {
            $output = $this->supressed_result;
        }
        return $output;
    }
}
