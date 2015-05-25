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

//there once were two heroes.
$wolfie = new SuperHero("logan", "wolverine");
$spidey = new SuperHero("peter parker" , "spiderman");
$wolfie->wakeup();
$spidey->wakeup();
foreach ($spidey->getTransitions() as $t) {
   // echo $t->getName() . PHP_EOL;
}

$wolfie->beSuper();
$wolfie->pose();
$wolfie->resque();
$wolfie->fight();
$wolfie->standDown();


$spidey->beSuper();
$spidey->fight();
$spidey->resque();
$spidey->pose();
$spidey->resque();
$spidey->fight();
$spidey->pose();
$spidey->resque();
$spidey->pose();
$spidey->standDown();

