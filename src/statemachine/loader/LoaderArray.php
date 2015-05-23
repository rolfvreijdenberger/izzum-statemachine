<?php
namespace izzum\statemachine\loader;
use izzum\statemachine\StateMachine;
use izzum\statemachine\Exception;
use izzum\statemachine\State;
use izzum\statemachine\utils\Utils;
use izzum\statemachine\Transition;
use izzum\statemachine\persistence\PDO;

/**
 * LoaderArray supports configuration of a StateMachine by loading it from
 * an array of Transition instances.
 *
 * The LoaderArray should always be the object that is used by other Loader
 * implementations.
 * This means other LoaderInterface implementations should delegate the loading
 * to this class. You could implement the Loader interface and
 * use object composition for the LoaderArray.
 *
 *
 * A transition will be unique per StateMachine and is uniquely defined by the
 * tuple of a starting state name and a destination state name.
 * The data of the rule, command or the identity (===) of the states does not
 * matter when a Transition is added to a StateMachine. The machine will react
 * on a first come first serve basis. In short, just make sure your
 * configuration data is ok.
 *
 * TRICKY: if multiple transitions share the same State object (for example as
 * their origin/from state or their destination/to state) then at least make
 * sure that those states share the exact same data.
 * Ideally, they should point to the same State instance.
 *
 *
 *
 * @see Transitions
 * @see State
 * @see PDO
 * @author Rolf Vreijdenberger
 *        
 */
class LoaderArray implements Loader {
    /**
     *
     * @var Transition[]
     */
    protected $transitions;

    /**
     *
     * @param Transition[] $transitions
     *            the transitions to be loaded
     */
    public function __construct($transitions = array())
    {
        $this->transitions = array();
        foreach ($transitions as $transition) {
            if (!is_a($transition, 'izzum\statemachine\Transition')) {
                throw new Exception('Expected Transition (or a subclass), found something else: ' . get_class($transition), Exception::BAD_LOADERDATA);
            }
            $this->add($transition);
        }
    }

    /**
     * Load a statemachine with transition data
     *
     * @param StateMachine $stateMachine            
     */
    public function load(StateMachine $stateMachine)
    {
        $transitions = $this->getTransitions();
        // add the transitions. the transitions added will set the
        // states (from/to) on the statemachine
        foreach ($transitions as $transition) {
            // when using transitions with 'regex' states, the statemachine will
            // handle this for you.
            $stateMachine->addTransition($transition);
        }
    }

    /**
     * add/overwrite a transition
     *
     * @param Transition $transition            
     */
    public function add(Transition $transition)
    {
        $this->transitions [$transition->getName()] = $transition;
    }

    /**
     * This method will return the transitions instances with the correct
     * references to each other.
     *
     * @return Transition[]
     */
    public function getTransitions()
    {
        return $this->transitions;
    }

    /**
     * counts the number of contained transitions.
     *
     * @return int
     */
    public function count()
    {
        return (int) count($this->transitions);
    }
}