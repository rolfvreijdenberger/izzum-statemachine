<?php
namespace izzum\command;
use izzum\command\Exception as CommandException;
use izzum\command\ICommand;
/**
 * Serves as base class for all other concrete commands.
 * Concrete commands must implement the _execute() method
 * @author Rolf Vreijdenberger
 * @link https://en.wikipedia.org/wiki/Command_pattern
 *
 */
abstract class Command implements ICommand {
    
    /**
     * (non-PHPdoc)
     * @see \izzum\ICommand::execute()
     * @throws izzum\Exception
     */
    public final function execute()
    {
        try {
            $this->_execute();
        } catch (CommandException $e)
        {
            throw $e;
        } catch (\Exception $e)
        {
            //make sure we always throw the right type
            $e = new CommandException($e->getMessage(), $e->getCode(), $e);
            throw($e);
        }
    }
    
    /**
     * @throws \Exception
     */
    abstract protected function _execute();

    /**
     * @return string
     */
    public function toString()
    {
        //includes the namespace
        return get_class($this);
    }
    
}