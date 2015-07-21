<?php
namespace izzum\examples\webshopcheckout;
use izzum\statemachine\Context;
use izzum\statemachine\Identifier;
use izzum\statemachine\State;
use izzum\statemachine\Transition;
use izzum\statemachine\StateMachine;

require_once('../autoload.php');
/**
 * Example script that uses the 'standalone mode' as one of the four usage models for the statemachine.
 * The other three usage models being inheritance, composition and delegation.
 *
 * This script allows you to interact with the statemachine from the command line
 * and does not use anything fancy for guard or transition logic.
 * To see an example of guard and transition logic for the standalone mode you
 * can see 'examples/standalone'
 *
 *
 * run this script from the (bash) command line:
 * php -f index.php
 * and stop it with ctrl+c
 */


//create machine. context defaults to in-memory state handling
$context = new Context(new Identifier("webshopcheckout-example", "webshopcheckout-machine"));
$machine = new StateMachine($context);

//define the states and the state types
$basket = new State('basket', State::TYPE_INITIAL);
$customerdata = new State('customerdata');
$shipping = new State('shipping');
$payment = new State('payment');
$complete = new State('complete', State::TYPE_FINAL);

//add transitions to the machine, with event names
$machine->addTransition(new Transition($basket, $customerdata, 'Checkout'));
$machine->addTransition(new Transition($customerdata, $shipping, 'ChooseHowToShip'));
$machine->addTransition(new Transition($shipping, $payment, 'ChooseHowToPay'));
$machine->addTransition(new Transition($payment, $complete, 'ready'));

//start the interactive demo
//with some coloring that works in the bash shell
echo PHP_EOL ."\033[01;32mIzzum statemachine webshopcheckout demo. press ctrl+c to stop it.\033[0m" . PHP_EOL . PHP_EOL;
//loop the machine
while(true) {
    $state = $machine->getCurrentState();
    echo "current state: $state" . PHP_EOL;
    echo "possible transitions from $state: " . PHP_EOL;
    if($state->isFinal()) {
        //Sold! Thanks and hope to see you again soon.
        echo "\033[01;35mSold! Thanks and hope to see you again soon. ;)\033[0m" . PHP_EOL . PHP_EOL;
        exit;
    }
    foreach ($state->getTransitions() as $transition) {
        echo "'" . $transition->getName() . "' aka event '" . $transition->getEvent() . "'" . PHP_EOL;
    }
    echo PHP_EOL;
    //get input from the user
    if (PHP_OS == 'WINNT') {
        echo '$ ';
        $event = stream_get_line(STDIN, 1024, PHP_EOL);
    } else {
        $event = readline("\033[01;32mEnter an \033[01;36mevent \033[01;32mor \033[01;34mtransition \033[01;32mname: \033[0m");
    };

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
