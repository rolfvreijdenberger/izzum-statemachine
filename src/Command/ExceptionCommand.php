<?php

namespace Izzum\Command;

/**
 * throws an exception
 *
 * @author Rolf Vreijdenberger
 *
 */
class ExceptionCommand extends Command
{
    private \Exception $exception;
    public const NULL_MESSAGE = 'null exception';
    public const NULL_CODE = 1234567890;

    public function __construct(string $message = self::NULL_MESSAGE, int $code = self::NULL_CODE, ?\Throwable $previous = null)
    {
        $this->exception = new \Exception($message, $code, $previous);
    }

    protected function _execute(): never
    {
        throw $this->exception;
    }
}
