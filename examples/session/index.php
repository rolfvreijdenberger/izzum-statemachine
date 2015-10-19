<?php
namespace izzum\examples\inheritance;
use izzum\statemachine\Context;
use izzum\statemachine\Identifier;
use izzum\statemachine\State;
use izzum\statemachine\Transition;
use izzum\statemachine\StateMachine;
use izzum\statemachine\persistence\Session;
use izzum\statemachine\utils\Utils;

/**
 * Example script that uses the 'standalone mode' as one of the four usage models for the statemachine.
 * The other three usage models being inheritance, composition and delegation.
 * 
 * This demonstrates the use of a sesssion adapter to store the state in a php session
 * to display in a browser over page refreshes.
 * 
 * run this script from the (bash) command line:
 * php -S localhost:2468 -t <docroot: the path to this index.php file which you
 * can get via the shell command 'pwd'>
 * go to the browser and open localhost:2468 and refresh a couple of times
 * and stop the webserver when you're done with ctrl+c
 */
require_once ('../autoload.php');

//all states, the color of the rainbow
$new = new State('white', State::TYPE_INITIAL);
$red = new State('red');
$orange = new State('orange');
$yellow = new State('yellow');
$green = new State('green');
$blue = new State('blue');
$indigo = new State('indigo');
$violet = new State('violet');

// create the machine with the correct session adapter to store the state accross page refreshes
$adapter = new Session();
$machine = new StateMachine(new Context(new Identifier('session-example', 'rainbow-machine'), null, $adapter));

//add the transitions, going from one color to the next and back to the first
$machine->addTransition(new Transition($new, $red));
$machine->addTransition(new Transition($red, $orange));
$machine->addTransition(new Transition($orange, $yellow));
$machine->addTransition(new Transition($yellow, $green));
$machine->addTransition(new Transition($green, $blue));
$machine->addTransition(new Transition($blue, $indigo));
$machine->addTransition(new Transition($indigo, $violet));
$machine->addTransition(new Transition($violet, $red));
//initialize the first time to 'red' and then cycle through the 
//colors for each page refresh
$machine->run();

//get some data to put in the output
$current = $machine->getCurrentState();
$next_transitions = implode(',', $current->getTransitions());
$next = $current->getTransitions()[0]->getStateTo();
//generate the ouput
$output = <<<EOT
<html>
    <header>
        <title>rainbows all over the place: gimme some more izzum jo!</title>
        <style>
        body {
            background-color: $current; 
            color: black;
            padding:10px;
            margin:10px;
            font-family: "Verdana";
            font-size: 0.8em;
        }
        #content {
            background-color: white; 
            color: black; 
        	height:100%;
        	padding:10px;
        	margin:10px;    		
        }
        h3 {
            color: black;
            border: 2px dotted $current;
            padding: 2px;
        }
        </style>
    </header>
    <body>
        <div id="content">
            <h3>welcome at the rainbow example for the izzum statemachine with a session backend adapter that holds the state</h3>
            The current state for the machine is <span style="color:$current">'$current'</span>
            <br />
            <br />
            The next transition is '$next_transitions' and the next color of the rainbow will be 
            <a href="http://en.wikipedia.org/wiki/$next" title="see wikipedia for $next" style="color: $next">
            $next. Click to see wikipedia info for the color $next.
            </a>
        </div>
    </body>
</html>
EOT;
//and echo the output
echo $output;




