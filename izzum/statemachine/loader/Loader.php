<?php
namespace izzum\statemachine\loader;
use izzum\statemachine\StateMachine;
/**
 * Loader is a marker interface for our package, so it always expects a Loader implementation.
 * 
 * An implementation of Loader might use subclasses of Transition and State to
 * provide alternative behaviour eg: an application performance optimized 
 * prioritized transition, so that when a state has 2 outgoing transitions, 
 * 1 is always tried first
 * 
 * 
 * @see LoaderObject
 * @see LoaderArray
 * @author Rolf Vreijdenberger
 *
 */
 interface Loader {
 
     /**
     * Loads a state machine with the correct transitions and state data
     *
     * @param StateMachine $stateMachine
     */
    public function load(StateMachine $stateMachine);
}