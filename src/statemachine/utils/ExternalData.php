<?php
namespace izzum\statemachine\utils;

/**
 * This serves as a very generic store/registry that is only
 * valid during one (1) single php process.
 *
 * It's main use it for it to be used so an external application
 * can set data that can be used in a Rule or Command to
 * check/execute a transition.
 *
 * This allows us to nicely encapsulate the data so we do not need to do
 * stuff like checking the $_REQUEST/$_SESSION etc in Rules/Commands during a
 * statemachine transition.
 *
 * example:
 * state A
 * state B
 * transition A->B (rule: t>7)
 * State C
 * transition A->C (rule: can always take place to 'force' a transition from A
 * to B
 * with step C in between. For example when we need to do
 * a manual transition in the context of a Controller Action)
 * We want this to be know via the HISTORY of the statemachine and
 * therefore we have a seperate transition for this
 * transition C->B (rule: can safely take place)
 *
 * Now when we use a cronjob where we loop over items the code would be like
 * this:
 * //pseudocode
 * $all = $service->getEntityIdsForState('A');
 * for ($all as $id) do $statemachine->run($id)
 * //end pseudocode
 *
 * the problem with the code above is that there are two OUTGOING transitions
 * for state A and that transition A->B might fail because the rule
 * is not satisfied (for t=4), but then A->C would run since that is
 * essentially an empty rule.
 *
 * Note: this is only a problem when we use the 'run' method on the
 * statemachine. It is not a problem when we use the 'transition' method on the
 * sm since we provide a transition name to that method, forcing the sm to
 * execute only that transition if it can. But keep in mind that there are
 * situations where you would just want the sm to decide what it does by calling
 * 'run' multiple times in a row.
 *
 * What we need is to be able to set data that is checked in the Rule for
 * the transition from A->C. This data can be set by the client of the
 * state machine in exceptional circumstances, to PREVENT the use of empty
 * rules and to PREVENT unwanted state transitions and to PREVENT difficult
 * mechanisms for setting data in the client where the rule has to do lots
 * of work to retrieve it (eg: controller->form->database->rule)
 *
 * for exmample: \a cron job would not set data and the rule for transition A->B
 * would
 * be checked against $t > 7. A controller would set data and the Rule for
 * transition A->C would be able to run in the context of a controller
 * that sets data but NOT in the context of the cron
 * that does not set the contextual data.
 *
 *
 * example:
 * controllercode where the statemachine is used:
 * //set context as a flag variable
 * ExternalData::set(Data::CONTEXT_SOME_VALUE);
 * $sm->transition('a_to_c');
 *
 * rule code where the context is checked:
 * protected function _applies()
 * {
 * //check external context and act upon it
 * return ExternalData::get() === Data::CONTEXT_SOME_VALUE;
 * }
 *
 *
 * @author Rolf Vreijdenberger
 *        
 */
class ExternalData {
    
    /**
     * a simple holder for any data we want, strings, domain models etc.
     *
     * @var mixed
     */
    private static $data;

    /**
     * is there any external data set?
     *
     * @return boolean
     */
    static public function has()
    {
        return self::$data !== null;
    }

    /**
     * clear the external context
     */
    static public function clear()
    {
        self::set(null);
    }

    /**
     * set the external data
     *
     * @param mixed $data            
     */
    static public function set($data = null)
    {
        self::$data = $data;
    }

    /**
     * get the external data
     *
     * @return mixed
     */
    static public function get()
    {
        return self::$data;
    }
}