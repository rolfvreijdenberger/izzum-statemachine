<?php

namespace Izzum\StateMachine\Loader;

use Izzum\StateMachine\StateMachine;
use Izzum\StateMachine\Exception;
use Izzum\StateMachine\State;
use Izzum\StateMachine\Transition;
use Izzum\StateMachine\Persistence\PDO;

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
 * Otherwise, transitions and states are actually stored on the statemachine
 * on a first wins basis (the later transition/state instance is not stored).
 *
 * Transitions will be sorted before they are added to the machine based on
 * if they contain a regex or not. All regex transitions will be added to
 * the machine after the non-regex transitions have been added.
 *
 *
 *
 * @see Transitions
 * @see State
 * @see PDO
 * @author Rolf Vreijdenberger
 *
 */
class LoaderArray implements Loader, \Stringable
{
    /**
     *
     * @var Transition[]
     */
    protected array $transitions;

    /**
     *
     * @param mixed[] $transitions
     *            the transitions to be loaded; each element is validated to be
     *            a Transition (or subclass) at runtime, since PHP has no native
     *            way to declare an array's element type
     */
    public function __construct(array $transitions = [])
    {
        $this->transitions = [];
        foreach ($transitions as $transition) {
            if (!is_a($transition, 'Izzum\StateMachine\Transition')) {
                throw new Exception('Expected Transition (or a subclass), found something else: ' . $transition::class, Exception::BAD_LOADERDATA);
            }
            $this->add($transition);
        }
    }

    /**
     * {@inheritDoc}
     */
    public function load(StateMachine $stateMachine): int
    {
        $count = 0;
        $unsorted = $this->getTransitions();
        $hasRegex = [];
        $hasNoRegex = [];
        foreach ($unsorted as $transition) {
            $to = $transition->getStateTo();
            $from = $transition->getStateFrom();
            //sort on regexes. they should come last in an automated loader like this
            if ($from->isRegex() || $to->isRegex()) {
                $hasRegex [] = $transition;
            } else {
                $hasNoRegex [] = $transition;
            }
        }
        $sorted = array_merge($hasNoRegex, $hasRegex);

        // add the sorted transitions. the transitions added will set the
        // states (from/to) on the statemachine
        foreach ($sorted as $transition) {
            // when using transitions with 'regex' states, the statemachine will handle this for you.
            $count += $stateMachine->addTransition($transition);
        }
        return $count;
    }

    /**
     * add/overwrite a transition
     */
    public function add(Transition $transition): void
    {
        $this->transitions [$transition->getName()] = $transition;
    }

    /**
     * This method will return the transitions instances with the correct
     * references to each other.
     *
     * @return Transition[]
     */
    public function getTransitions(): array
    {
        return $this->transitions;
    }

    /**
     * counts the number of contained transitions.
     * this is not the same as the possible amount of transitions added (because of regex transitions)
     */
    public function count(): int
    {
        return (int) count($this->transitions);
    }

    public function toString(): string
    {
        return static::class;
    }

    public function __toString(): string
    {
        return $this->toString();
    }
}
