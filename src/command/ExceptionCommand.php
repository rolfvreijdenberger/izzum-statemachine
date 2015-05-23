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
    const NULL_MESSAGE = 'null exception';
    const NULL_CODE = '1234567890';

    public function __construct($message = self::NULL_MESSAGE, $code = self::NULL_CODE, $previous = null)
    {
        $this->exception = new \Exception($message, $code, $previous);
    }

    protected function _execute()
    {
        throw $this->exception;
    }
}