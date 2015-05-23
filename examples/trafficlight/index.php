<?php
namespace izzum\examples\trafficlight;
use izzum\statemachine\utils\PlantUml;
require_once('../autoload.php');
/**
 * run this script from the command line:
 * php -f index.php
 * and stop it with ctrl+c
 */
 

//create the factory
$factory = new TrafficLightFactory();
//get the machine from the factory, for traffic light 1
$machine = $factory->getStateMachine(1);

//generate the uml diagram
$uml = new PlantUml();
$output = $uml->createStateDiagram($machine);
echo PHP_EOL . PHP_EOL. PHP_EOL;
echo $output;
echo PHP_EOL . PHP_EOL. PHP_EOL;

//loop the machine
while(true) {
    $machine->run();
    sleep(1);  
}
