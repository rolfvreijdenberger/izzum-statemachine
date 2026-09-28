<?php

namespace Izzum\Command;

/**
 * Serves as base class for all other concrete commands.
 * Concrete commands must implement the _execute() method.
 *
 * Intent: Encapsulate a request as an object, thereby letting you parameterize
 * clients with different requests, queue or log requests.
 *
 * A concrete Command (a subclass) can (and should be) injected with contextual
 * data via
 * dependency injection in the constructor
 *
 * @author Rolf Vreijdenberger
 * @link https://en.wikipedia.org/wiki/Command_pattern
 *
 */
abstract class Command implements ICommand, \Stringable
{
    final public function execute(): void
    {
        try {
            $this->_execute();
        } catch (Exception $e) {
            $this->handleException($e);
            throw $e;
        } catch (\Exception $e) {
            // make sure we always throw the right type
            $e = new Exception($e->getMessage(), $e->getCode(), $e);
            $this->handleException($e);
            throw ($e);
        }
    }

    protected function handleException(Exception $e): void
    {
        // implement in subclass if needed
    }

    /**
     * @throws \Exception
     */
    abstract protected function _execute(): void;

    public function toString(): string
    {
        // includes the namespace
        return static::class;
    }

    public function __toString(): string
    {
        return $this->toString();
    }
}
