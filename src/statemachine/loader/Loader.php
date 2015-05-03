<?php
namespace izzum\statemachine\loader;
use izzum\statemachine\StateMachine;
/**
 * Loader is a marker interface for our package, so it always expects a Loader implementation.
 * 
 * An implementation of Loader might use subclasses of Transition and State to
 * provide alternative behaviour eg: an application performance optimized 
 * prioritized transition, so that when a state has 2 outgoing transitions, 
 * 1 is always tried first.
 * 
 * An implementation of Loader knows how to retrieve the transition and state 
 * data from it's source and it knows about the izzum\statemachine. Therefore, the 
 * Loader implementation is best suited to create all transitions and states.
 * 
 * A Loader implementation might create application specific subclasses of
 * Transition and State (in case you need extra functionality)
 * 
 * Ideally your  specific loader should:
 * - implement this interface: for use in a subclass of AbstractFactory
 * - create the transitions and states correctly (use LoaderArray via composition)
 * - work together with your persistence adapter (a concrete persistence adapter could implement this interface)
 *   to retrieve the data for the creation of the transitions and states
 * - act as a Decorator for the LoaderArray
 * 
 * 
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