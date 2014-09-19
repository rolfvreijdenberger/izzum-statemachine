<?php
namespace izzum\rules;

/**
 * A Rule that can return a boolean
 * 
 * useful for testing or to be used in a statemachine guard clause that should
 * always pass.
 *
 * @author Rolf Vreijdenberger
 */
class Boolean extends Rule
{
    private $boolean;

    public function __construct($boolean)
    {
        if (!is_bool($boolean))
        {
            $error = 'Boolean rule needs a boolean in the constructor';
            throw new Exception($error, Exception::CODE_NONBOOLEAN);
        }
        $this->boolean = $boolean;
    }

    protected function _applies()
    {
        return (bool) $this->boolean;
    }

}
