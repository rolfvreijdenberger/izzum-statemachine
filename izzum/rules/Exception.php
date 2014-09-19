<?php
namespace izzum\rules;

/**
 * Exception layer in between to handle exceptions for rules.
 *
 * @author Rolf Vreijdenberger
 */
class Exception extends \Exception
{

    /**
     * The error code for non boolean return value
     */
    const CODE_NONBOOLEAN = 1;
    
     /**
     * The error code for wrong object type
     */
    const CODE_WRONGTYPE = 2;
    
     /**
     * The error code for general errors, those that bubble up from inside a rule
     */
    const CODE_GENERAL = 999;
}
