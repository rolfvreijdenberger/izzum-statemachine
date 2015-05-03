<?php
namespace izzum\examples\trafficlight;
use izzum\statemachine\utils\PlantUml;
/**
 * run this script from the command line:
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

//create the factory
$factory = new TrafficLightFactory();
//get the machine from the factory, for traffic light 1
$machine = $factory->getStateMachine(1);

//generate the uml diagram
//$uml = new PlantUml();
//$output = $uml->createStateDiagram($machine);
//echo $output . PHP_EOL;

//loop the machine
while(true) {
    $machine->run();
    sleep(1);  
}
