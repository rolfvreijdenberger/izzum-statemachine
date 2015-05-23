<?php
namespace izzum\rules;

/**
 * A Closure rule allows us to easily add logic to a rule by means of an
 * anonymous
 * function.
 *
 * This provides flexibility to clients that do not want to subclass a Rule but
 * still want to work with the rules library.
 *
 * $rule = new Closure(function ($a, $b) { return $a === $b; }), array(1,2));
 * $rule->applies();//returns false: 1 is not equal to 2
 */
class Closure extends Rule {
    /**
     *
     * @var \Closure
     */
    private $closure;
    
    /**
     * an array of arguments to pass as parameters to the closure
     * 
     * @var mixed[]
     */
    private $arguments;

    /**
     *
     * @param Closure $closure            
     * @param mixed[] $arguments
     *            an optional array of arguments to pass to the closure
     */
    public function __construct(\Closure $closure, $arguments = array())
    {
        $this->closure = $closure;
        $this->arguments = $arguments;
    }

    protected function _applies()
    {
        return (boolean) call_user_func_array($this->closure, $this->arguments);
    }
}