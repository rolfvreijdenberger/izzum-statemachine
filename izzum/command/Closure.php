<?php
namespace izzum\command;
use izzum\command\Command;
/**
 * A Closure command allows the use of closure in a (composite) command.
 * 
 * This allows clients of a Command to inject logic by means of an anonymous
 * function.
 * 
 * This provides the flexibility to use custom logic in a command without
 * subclassing a command, while still allowing the use of the command library.
 * 
 * eg: $command = new Closure(function() {echo "hello world";});
 * $command->execute();//echoes 'hello world'
 * 
 * @author Rolf Vreijdenberger
 */
class Closure extends Command {
    /**
     * @var \Closure
     */
    private $closure;
    
    /**
     * an array of arguments to pass as parameters to the closure
     * @var mixed[]
     */
    private $arguments;
    
    /**
     * 
     * @param Closure $closure
     * @param array $arguments an optional array of arguments to pass to the closure
     */
    public function __construct(\Closure $closure, $arguments = array())
    {
        $this->closure = $closure;
        $this->arguments = $arguments;
    }
    
    protected function _execute(){
        call_user_func_array($this->closure, $this->arguments);
    }
}