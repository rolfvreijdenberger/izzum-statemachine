<?php

namespace izzum\rules;
/**
 * specifies an interface for a generic Rule
 * 
 * @author Rolf Vreijdenberger
 */
interface IRule {
    /**
     * The applies method which is executed somewhere at runtime when the 
     * rule is invoked.
     * Context for a rule can be injected via dependency injection, so a 
     * Rule can use that dependent on object (or mock) in the method.
     * 
     * Any context should be provided at rule creation time via dependency injection. 
     * The context provided might know how to get additional information at 
     * execution time from objects it knows about. 
     * 
     * Context provided can also be a mock (for unittesting)
     * 
     * @return bool
     */
    public function applies();
    
    /**
     * gives a string representation of the instance
     */
    public function toString();
}