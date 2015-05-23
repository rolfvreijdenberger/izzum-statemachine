<?php
namespace izzum\command;
use izzum\command\Exception;
use izzum\command\ICommand;

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
abstract class Command implements ICommand {

    /**
     * (non-PHPdoc)
     * 
     * @see \izzum\command\ICommand::execute()
     * @throws Exception https://en.wikipedia.org/wiki/Template_method_pattern
     */
    public final function execute()
    {
        try {
            $this->_execute();
        } catch(Exception $e) {
            $this->handleException($e);
            throw $e;
        } catch(\Exception $e) {
            // make sure we always throw the right type
            $e = new Exception($e->getMessage(), $e->getCode(), $e);
            $this->handleException($e);
            throw ($e);
        }
    }

    /**
     * hook method for logging etc.
     * 
     * @param Exception $e            
     */
    protected function handleException($e)
    {
        // implement in subclass if needed
    }

    /**
     *
     * @throws \Exception
     */
    abstract protected function _execute();

    /**
     *
     * @return string
     */
    public function toString()
    {
        // includes the namespace
        return get_class($this);
    }

    public function __toString()
    {
        return $this->toString();
    }
}