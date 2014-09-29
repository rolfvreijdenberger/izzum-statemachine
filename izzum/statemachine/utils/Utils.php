<?php
namespace izzum\statemachine\utils;
/**
 * utils class that has some helper methods for diverse purposes
 * @author Rolf Vreijdenberger
 *
 */
class Utils {


    /**
     * gets the transition name by two state names, using the default convention
     * for a transition name (which is concatenating state-from to state-to with '_to_'
     * @param string $from the state from which the transition is made
     * @param string $to the state to which the transition will be made
     * @return string
     */
    public static function getTransitionName($from, $to)
    {
        return $from . "_to_" . $to;
    }
}
