<?php

namespace Izzum\StateMachine;

use Izzum\StateMachine\Persistence\Adapter;
use Izzum\StateMachine\Persistence\Memory;

/**
 * Context is an object that holds all the contextual information for the
 * statemachine to do it's work with the help of the relevant dependencies.
 * A Context is created by your application to provide the right dependencies
 * ('context') for the statemachine to work with.
 *
 * It seperates the concerns for the statemachine of how you are reading/writing
 * state data and of how you access your domain models.
 *
 * Important are:
 * - the entity id, which references an application domain specific object
 * like 'Order' or 'Customer' that goes through some finite states in it's
 * lifecycle.
 * - the machine name, which is the type identifier for the machine and related
 * to the entity (eg: 'order-machine')
 * - persistence adapter, which reads/writes to/from a storage facility
 * - entity_builder, which constructs the stateful entity.
 *
 * The entity is the object that will be acted upon by the
 * Statemachine. This stateful object will be uniquely identified by it's id,
 * which will mostly be some sort of primary key for that object that is defined
 * by the application specific implementation.
 *
 * A reference to the stateful object can be obtained via the factory method
 * getEntity().
 *
 * This class delegates reading and writing states to specific implementations
 * of the Adapter classes. this is useful for
 * testing and creating specific behaviour for statemachines that need extra
 * functionality to get and set the correct states.
 *
 *
 * @author Rolf Vreijdenberger
 *
 */
class Context implements \Stringable
{
    /**
     * the Identifier that uniquely identifies the statemachine
     *
     * @var Identifier
     */
    protected Identifier $identifier;

    /**
     * an associated statemachine, if one is set.
     * Only a statemachine that uses this Context should set itself on the
     * Context, providing a bidirectional association.
     *
     * @var StateMachine|null
     */
    protected ?StateMachine $statemachine = null;

    /**
     * @param EntityBuilder $entityBuilder
     *            optional: A specific builder class to create a reference to
     *            the entity we wish to manipulate/have access to.
     * @param Adapter $persistenceAdapter
     *            optional: A specific reader/writer class can be used to
     *            generate different 'read/write' behaviour
     */
    public function __construct(Identifier $identifier, protected ?EntityBuilder $entityBuilder = null, protected ?Adapter $persistenceAdapter = null)
    {
        $this->identifier = $identifier;
    }

    /**
     * Provides a bidirectional association with the statemachine.
     * This method should be called only by the StateMachine itself.
     */
    public function setStateMachine(StateMachine $statemachine): void
    {
        $this->statemachine = $statemachine;
    }

    public function getStateMachine(): ?StateMachine
    {
        return $this->statemachine;
    }

    /**
     * Gets a (cached) reference to the application domain specific model,
     * for example an 'Order' or 'Customer' that transitions through states in
     * it's lifecycle.
     *
     * @return mixed
     */
    public function getEntity(bool $createFreshEntity = false)
    {
        // use a specialized builder object to create the (cached) reference.
        return $this->getBuilder()->getEntity($this->getIdentifier(), $createFreshEntity);
    }

    /**
     * gets the state.
     * first try the backend storage facility. If not found, then try the
     * configured statemachine itself for the initial state.
     */
    public function getState(): string
    {
        // get the state by delegating to a specific reader
        $state = $this->getPersistenceAdapter()->getState($this->getIdentifier());
        // if found
        if ($state !== State::STATE_UNKNOWN) {
            return $state;
        }
        // not found, try to get it from the states loaded on the statemachine
        if (!$this->getStateMachine()) {
            // reference to statemachine does not exist (possible standalone context object)
            return $state;
        }
        // reference to machine exists, just try to get the initial state
        $state = $this->getStateMachine()->getInitialState(true);
        if ($state === null) {
            return State::STATE_UNKNOWN;
        }
        // we have a State instance, get the name
        $state = $state->getName();
        return $state;
    }

    /**
     * @param string $message optional message. this can be used by the persistence adapter
     *          to be part of the transition history to provide extra information about the transition.
     * @return bool true if there was never any state persisted for this
     *         machine before (just added for the
     *         first time), false otherwise
     */
    public function setState(string $state, ?string $message = null): bool
    {
        // set the state by delegating to a specific writer
        return $this->getPersistenceAdapter()->setState($this->getIdentifier(), $state, $message);
    }

    /**
     * adds the state data to the persistence layer if it is not there.
     * Used to mark the initial construction of a statemachine at a certain
     * point in time. subsequent calls to 'add' will not have any effect if it
     * has already been persisted.
     *
     * @param string $message optional message. this can be used by the persistence adapter
     *          to be part of the transition history to provide extra information about the transition.
     * @return boolean true if it was added, false if it was already there
     */
    public function add(string $state, ?string $message = null): bool
    {
        return $this->getPersistenceAdapter()->add($this->getIdentifier(), $state, $message);
    }

    public function getBuilder(): EntityBuilder
    {
        if ($this->entityBuilder === null) {
            // the default builder returns the Identifier as the entity
            $this->entityBuilder = new EntityBuilder();
        }
        return $this->entityBuilder;
    }

    /**
     * gets the Context state reader/writer.
     */
    public function getPersistenceAdapter(): Adapter
    {
        if ($this->persistenceAdapter === null) {
            // the default
            $this->persistenceAdapter = new Memory();
        }
        return $this->persistenceAdapter;
    }

    /**
     * gets the entity id that represents the unique identifier for the
     * application domain specific model.
     */
    public function getEntityId(): string
    {
        return $this->getIdentifier()->getEntityId();
    }

    public function getIdentifier(): Identifier
    {
        return $this->identifier;
    }

    /**
     * gets the statemachine name that handles the entity
     */
    public function getMachine(): string
    {
        return $this->getIdentifier()->getMachine();
    }

    public function toString(): string
    {
        return static::class . "(" . $this->getId(true) . ")";
    }

    /**
     * get the unique identifier for an Context, which consists of the machine
     * name and the entity_id in parseable form, with an optional state
     */
    public function getId(bool $readable = false, bool $withState = false): string
    {
        $output = $this->getIdentifier()->getId($readable);
        if ($readable) {
            if ($withState) {
                $output .= ", state: '" . $this->getState() . "'";
            }
        } else {
            if ($withState) {
                $output .= "_" . $this->getState();
            }
        }

        return $output;
    }

    public function __toString(): string
    {
        return $this->toString();
    }

    /**
     * stores a failed transition, called by the statemachine
     * This is a transition that has failed since it:
     * - was not allowed
     * - where an exception was thrown from a rule or command
     * - etc. any general transition failure
     */
    public function setFailedTransition(Transition $transition, Exception $e): void
    {
        $this->getPersistenceAdapter()->setFailedTransition($this->getIdentifier(), $transition, $e);
    }
}
