<?php 
namespace izzum\command;
use izzum\command\Command;

use izzum\command\ICommand;
use izzum\command\IComposite;
use izzum\command\Exception;

/**
 * Command Pattern [GoF] implementation
 * CompositeCommand (aka MacroCommand) can be used to insert multiple ICommand instances which will be handled in the execute() method
 * @author Rolf Vreijdenberger
 * @link https://en.wikipedia.org/wiki/Command_pattern
 */
class Composite extends Command implements IComposite {
    /**
     * an array of commands
     * @var \izzum\ICommand[]
     */
    private $commands;

    public function __construct() 
    {
        $this->commands = array();
    }
    
    
    /**
     * this method will call all commands added to this class in order of addition
     * @throws Exception
     */
    protected function _execute(){
        foreach($this->commands as $command){
            $command->execute();
        }
    }
    
    /**
     * this method can be used to add multiple commands that will be used in the execute() method
     * @param command a concrete command that implements the ICommand interface
     */
    public function add(ICommand $command){
        $this->commands[] = $command;
    }
    

    /**
     * Removes a command if it is part of the composite (based on identity ===)
     * @param ICommand $command
     * @return boolean
     */
    public function remove(ICommand $command)
    {
        $total = count($this->commands);
        $removed = false;
        for($i=($total-1);$i>=0;$i--){
            $current = $this->commands[$i];
            if($current === $command)
            {
                 array_splice($this->commands, $i, 1);
                 $removed = true;   
            }
        }
        return $removed;
    }
    
    /**
     * does this contain a command (based on identity ===)
     * @param ICommand $command
     * @return boolean
     */
    public function contains(ICommand $command)
    {
        $contains = false;
        foreach($this->commands as $current) {
             if($current === $command)
             {
                 $contains = true;
                 break;
             }   
        }
        return $contains;
    }
    
    /**
     * (non-PHPdoc)
     * @see \izzum\IComposite::count()
     */
    public function count()
    {
        return count($this->commands);
    }
    
    public function toString() {
        $composition = array();
        $children = '';
        foreach ($this->commands as $command) {
            $composition[] = $command->toString();
        }
        if(count($composition) != 0) {
            $children = " consisting of: [" . implode(", ", $composition) . "]";
        }
        return get_class($this) . $children;
    }
}