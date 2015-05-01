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
 * Ideally your  specific loader should:
 * - implement this interface: for use in a subclass of AbstractFactory
 * - act as a Decorator for the LoaderArray: for the logic of building the transitions and
 *      states correctly (use LoaderArray via composition)
 * - work together with your persistence adapter (a concrete persistence adapter could implement this interface)
 * 
 * 
 * @see LoaderObject
 * @see LoaderArray
 * @see izzum\statemachine\persistence\PDO
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