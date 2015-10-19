<?php
namespace izzum\examples\trafficlight;
use izzum\statemachine\utils\PlantUml;

require_once ('../autoload.php');
/**
 * Example script that uses the 'delegation mode' as one of the four usage models for the statemachine.
 * The other three usage models being inheritance, composition and standalone.
 *
 * This example shows the usage of using a factory to get the machine and makes use of Rules and Command classes.
 * This is the most formal way to use the statemachine:
 * using domain models/rules/commands that are tested seperately and strong use of polymorphism and uniformity of code
 * by using these rules and commands.
 *
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
echo PHP_EOL . PHP_EOL . PHP_EOL;
echo $output;
echo PHP_EOL . PHP_EOL . PHP_EOL;

//loop the machine
while ( true ) {
    $machine->run();
    sleep(1);
}
