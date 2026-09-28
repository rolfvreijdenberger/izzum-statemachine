<?php

namespace izzum\examples\interactive;

use Izzum\StateMachine\Context;
use Izzum\StateMachine\Identifier;
use Izzum\StateMachine\State;
use Izzum\StateMachine\Transition;
use Izzum\StateMachine\StateMachine;

/**
 * Example script that uses the 'standalon mode' as one of the four usage models for the statemachine.
 * The other three usage models being inheritance, composition and delegation.
 *
 *
 * This example demonstrates the use of callables to interact with the statemachine, for
 * both transition guards and transition logic
 *
 * run this script from the (bash) command line:
 * php -f index.php
 * and stop it with ctrl+c
 */

require_once('../autoload.php');

//TODO
