<?php
namespace izzum\rules;

/**
 * Exception layer to handle exceptions for rules.
 *
 * @author Rolf Vreijdenberger
 * @author Richard Ruiter
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
