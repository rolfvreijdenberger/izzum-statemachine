<?php
namespace izzum\command;
use izzum\command\Command;
/**
 * throws an exception
 * 
 * @author Rolf Vreijdenberger
 *
 */
class ExceptionCommand extends Command {
    
    private $exception;
    
    public function __construct($message, $code = 0, $previous = null)
    {
        $this->exception = new \Exception($message, $code, $previous);
    }
    
    protected function _execute()
    {
        throw $this->exception;
    }
}