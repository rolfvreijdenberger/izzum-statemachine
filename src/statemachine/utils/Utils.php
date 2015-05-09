<?php
namespace izzum\statemachine\utils;
use izzum\command\Null;
use izzum\command\Composite;
use izzum\statemachine\Exception;
use izzum\statemachine\Context;
/**
 * utils class that has some helper methods for diverse purposes
 * @author Rolf Vreijdenberger
 *
 */
class Utils {

	const STATE_CONCATENATOR = '_to_';

    /**
     * gets the transition name by two state names, using the default convention
     * for a transition name (which is concatenating state-from to state-to with '_to_'
     * @param string $from the state from which the transition is made
     * @param string $to the state to which the transition will be made
     * @return string <state_from>_to_<state_to>
     */
    public static function getTransitionName($from, $to)
    {
        return $from . self::STATE_CONCATENATOR . $to;
    }
    
    
    /**
     * returns the associated Command for the entry/exit/transition action on a State or a Transition.
     * the Command will be configured with the 'reference' of the stateful object
     *
     * @param string $command_name entry~,exit~ or transition command name.
     * 		multiplecommands can be split by a ',' in which case a composite command will be returned.
     * @param Context $context
     * @param string $event optional in case the transition was triggered by an event code (mealy machine)
     * @return ICommand
     * @throws Exception
     */
    public static function getCommand($command_name, Context $context, $event = null)
    {
    	//it's oke to have no command, as there might be 'marker' states, where
    	//we just need to transition something to a next state (according to a rule)
    	//where useful  work can be done (eg: from the 'initial' type state to
    	//a 'shortcut' state for special cases.
    	if($command_name === '' || $command_name === null) {
    		//return a command without side effects
    		return new Null();
    	}
    
    	$output = new Composite();
    
    	//a command string can be made up of multiple commands seperated by a comma
    	$all_commands = explode(',', $command_name);
    
    	//get the correct object reference to inject in the command(s)
    	$reference = $context->getEntity();
    
    	foreach ($all_commands as $single_command) {
    			
    		if(class_exists($single_command)) {
    			try {
    				$command = new $single_command($reference);
    				//a mealy machine might want to use the event/trigger in it's transition logic.
    				//therefore, see if the command expects an event/trigger to be set that it can use in it's 'execute' method.
    				if(method_exists($command, 'setEvent'))
    				{
    					$command->setEvent($event);
    				}
    				//add it to the composite
    				$output->add($command);
    			} catch (\Exception $e) {
	    			$e = new Exception(
	    			sprintf("command (%s) objects to construction for Context (%s). message: '%s'",
	    			$single_command, $context->toString(), $e->getMessage()),
		                           Exception::COMMAND_CREATION_FAILURE);
	    	                           throw $e;
	    		}
    		} else {
	    		//misconfiguration
	   			$e = new Exception(
	   					sprintf("failed command creation, class does not exist: (%s) for Context (%s)",
	   			$single_command, $context->toString()),
	   				Exception::COMMAND_CREATION_FAILURE);
    				throw $e;
    	    }
    	}
    	return $output;
    }
}
