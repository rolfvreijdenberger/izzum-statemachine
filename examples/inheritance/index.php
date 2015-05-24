<?php
namespace izzum\examples\inheritance;
use izzum\statemachine\Context;
use izzum\statemachine\Identifier;
use izzum\statemachine\State;
use izzum\statemachine\Transition;
use izzum\statemachine\StateMachine;
/**
 * Example script that uses the 'inheritance mode' as one of the four usage models for the statemachine.
 * The other three usage models being standalone, composition and delegation.
 * 
 * run this script from the (bash) command line:
 * php -f index.php
 * and stop it with ctrl+c
 */
 
require_once('../autoload.php');

//add transitions to the machine, with event names
$hero = new SuperHero();
$hero->start();
echo $hero->getCurrentState();
$hero->canHandle("normal_to_jo");
$hero->canTransition("normal_to_jo");

$hero->setState($hero->getState('flying'));


