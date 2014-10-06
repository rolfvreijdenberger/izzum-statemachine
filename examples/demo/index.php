<?php
namespace izzum\examples\demo;
/**
 * run this script from the command line:
 * php -f index.php
 * and stop it with ctrl+c
 * make sure you include the right scripts via an autoloader
 */
//use composer autoloader
require_once __DIR__ . '/../../vendor/autoload.php';

//create the factory
$factory = new TrafficLightFactory();
//get the machine from the factory, for traffic light 1
$machine = $factory->getStateMachine(1);

//loop the machine
while(true) {
    $machine->run();
    sleep(1);  
}
