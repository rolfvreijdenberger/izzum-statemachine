<?php
namespace izzum\examples\interactive;
use izzum\statemachine\Context;
use izzum\statemachine\Identifier;
use izzum\statemachine\State;
use izzum\statemachine\Transition;
use izzum\statemachine\StateMachine;
/**
 * run this script from the (bash) command line:
 * php -f index.php
 * and stop it with ctrl+c
 */
 
/**
 * add the composer autoloader, we assume this is the normal composer setup:
 * <root>/vendor/rolfvreijdenberger/izzum/
 * with the autoloader in:
 * <root>/vendor/autoload.php
 */
$loader = require_once __DIR__ . '/../../../../../vendor/autoload.php';
$loader->addPsr4('izzum\examples\\', __DIR__ . '/../');
$loader->register();

//create machine. context defaults to in-memory state handling
$context = new Context(new Identifier("interactive-example", "interactive-machine"));
$machine = new StateMachine($context);

//define the states and the state types
$new = new State('new', State::TYPE_INITIAL);
$eating = new State('eating');
$drinking = new State('drinking');
$sleep = new State('sleep');
$hungry = new State('hungry');
$drunk = new State('drunk');
$smoking = new State('smoking');
$high = new State('high');
$dead = new State('dead', State::TYPE_FINAL);

//add transitions to the machine, with event names
$machine->addTransition(new Transition($new, $hungry, 'wakeup'));
$machine->addTransition(new Transition($hungry, $sleep, 'sleep'));
$machine->addTransition(new Transition($hungry, $eating, 'eat'));
$machine->addTransition(new Transition($hungry, $drinking, 'drink'));
$machine->addTransition(new Transition($hungry, $drunk, 'drinkalot'));
$machine->addTransition(new Transition($hungry, $smoking, 'izzumJo'));//http://www.urbandictionary.com/define.php?term=Izzum
$machine->addTransition(new Transition($eating, $eating, 'eatmore'));
$machine->addTransition(new Transition($eating, $drinking, 'party'));
$machine->addTransition(new Transition($drinking, $drunk, 'morebooze'));
$machine->addTransition(new Transition($drinking, $sleep, 'sleep'));
$machine->addTransition(new Transition($drunk, $sleep, 'crash'));
$machine->addTransition(new Transition($drunk, $high, 'weedz'));
$machine->addTransition(new Transition($sleep, $hungry, 'wakeup'));
$machine->addTransition(new Transition($drunk, $dead, 'moreboozzz'));
$machine->addTransition(new Transition($smoking, $hungry, 'munchies'));
$machine->addTransition(new Transition($eating, $smoking, 'izzum'));//http://www.urbandictionary.com/define.php?term=Izzum
$machine->addTransition(new Transition($smoking, $high, 'foshizzle'));
$machine->addTransition(new Transition($high, $dead, 'moreweedzzz'));
$machine->addTransition(new Transition($high, $sleep, 'pzzah'));


//start the interactive demo
//with some coloring that works in the bash shell
echo PHP_EOL ."\033[01;32mIzzum statemachine interactive demo. press ctrl+c to stop it.\033[0m" . PHP_EOL . PHP_EOL;
//loop the machine
while(true) {
	$state = $machine->getCurrentState();
	echo "current state: $state" . PHP_EOL;
	echo "possible transitions from $state: " . PHP_EOL;
	if($state->isFinal()) {
		//too much good times
		echo "\033[01;35mAhw man...! Try not to drink/smoke as much next time, it's bad for you ;)\033[0m" . PHP_EOL . PHP_EOL;
		exit;
	}
	foreach ($state->getTransitions() as $transition) {
		echo "'" . $transition->getName() . "' aka event '" . $transition->getEvent() . "'" . PHP_EOL;
	}
	echo PHP_EOL;
    //get input from the user
	$event = readline("\033[01;32mEnter an \033[01;36mevent \033[01;32mor \033[01;34mtransition \033[01;32mname: \033[0m");
    
    try {
    	$status = 0;
    	$transitioned = false;
    	//we allow transitions by name or by event
    	if(strstr($event, '_to_')) {
    		$transitioned = $machine->transition($event);
    		if($transitioned) $status = 1;
    	} else {
    		$transitioned = $machine->handle($event); 
    		if($transitioned) $status = 2;
    	}
    } catch (\Exception $e) {
    	//for instance, when providing a bad transition name.
    	echo "\033[31mAn exception occured: " . $e->getMessage(). PHP_EOL;
    }
    
    //check what happened
    switch ($status) {
    	case 1:
    		echo "\033[01;34m--- transition by transition name '$event' succesful";
    		break;
    	case 2:
    		echo "\033[01;36m--- transition by event name '$event' succesful";
    		break;
    	default:
	    	echo "\033[01;31m--- transition for '$event' not succesful";
	    	break;
    }
    echo "\033[0m" . PHP_EOL . PHP_EOL;
    		
}
