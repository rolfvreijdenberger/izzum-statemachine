<?php
namespace izzum\statemachine\persistence;
/**
 * this Tooling interface can be implemented by creating a subclass of Adapter or by 
 * creating a subclass from one of the existing Adapter subclasses.
 * 
 * The Adapter subclass (a data access object) will then allow you to gather
 * data related to the statemachine so you can build and use tooling to manipulate
 * your statemachine.
 * 
 * Your application specific Adapter subclass can ofcourse implement a lot more methods
 * more tailored to your information/manipulation needs.
 * 
 * @author Rolf Vreijdenberger
 *
 */
Interface Tooling {
    
    /**
     * returns machine information for machines.
     * 
     * This method allows you to get the factory name(s) for machines which in turn
     * allows you to polymorphically build all associated objects for your application.
     * This is very powerful since it lets you build tooling that acts generically on 
     * all statemachines in your application.
     * 
     * @param string $machine optional the machine to get the info for
     * @return array an array containing machine names and fully qualifed factory classes and description
     */
    public function getMachineInformation($machine = null);
    
    /**
     * returns state information for machines.
     * 
     * This method allows you to get all state information. This allows you to build 
     * tooling where you can access all exit and entry logic and execute that (commands)
     * on an domain object (which you can build via a factory and a custom entity builder).
     * 
     * This is highly useful if you have failed transitions (eg: because a 3d party service
     * was temporarily down) and only want to execute a specific piece of logic independently.
     * Just instantiate the command associated with the state logic with the domain object
     * injected in the constructor.
     * 
     * @param string $machine optional the machine to get the info for
     * @return array an array containing state names, exit and entry logic, machine name etc)
     */
    public function getStateInformation($machine = null);
    
    /**
     * returns transition information for machines.
     * 
     * This method allows you to get all transition information. This allows you to build 
     * tooling where you can access all guard and transition logic and check that (via a rule)
     * or execute that (via commands) on an domain object (which you can build via a factory and a custom entity builder).
     * 
     * This is highly useful if you have failed transitions (eg: because a 3d party service
     * was temporarily down) and only want to execute a specific piece of logic independently.
     * Just instantiate the rule or command associated with the state logic with the domain object
     * injected in the constructor. You can then check if a domain object/statemachine is allowed
     * to transition (via the rule, with optional output from the rule via Rule::addResult) or execute a command
     * associated with that transition.
     * 
     * @param string $machine optional the machine to get the info for
     * @return array an array containing transition info (guards, logic, source and sink states, event names etc)
     */
    public function getTransitionInformation($machine = null);
    
    /**
     * returns transition history information.
     * 
     * Useful to build textual or visual (plantuml) output related to transitions
     * for a specific machine or entity in a machine.
     * 
     * @param string $machine optional the machine to get the info for
     * @param $entity_id optional the entity id to get the history info for
     * @return array an array containing transition history info (machine, entity_id, timestamp, message etc)
     */
    public function getTransitionHistoryInformation($machine = null, $entity_id = null);
    
}