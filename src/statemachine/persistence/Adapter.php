<?php
namespace izzum\statemachine\persistence;
use izzum\statemachine\Identifier;
use izzum\statemachine\Exception;
use izzum\statemachine\State;
use izzum\statemachine\Transition;
use izzum\statemachine\utils\Utils;

/**
 * The abstract class Adapter is responsible for adapting any code written to access
 * different persistence backends to the targeted php extensions and vendor databases. 
 * 
 * This class serves as a base class for access to different type of persistence
 * layers we might want to use to store the states for stateful entities.
 * for example: relational (postgres/mysql) databases, nosql databases, php
 * sessions, files, memcache, redis etc..
 *
 * It also acts as a central place to store logic related to this persistance
 * layer, which might be useful when you want to get statistics from the
 * persistence layer.
 *
 * all access logic could be centralized in a subclass.
 * logic such as:
 * - history of all transitions (when did an entity for a statemachine
 * transition?)
 * - the retrieval of the definition of transitions
 *
 * A specific type of persistence adapter can be coupled (via the
 * AbstractFactory) to a specific implementation of a statemachine.
 *
 * This abstract Adapter class has no side effects (like
 * database/file/session/memory writes etc). Subclasses will probably have side
 * effects.
 *
 * Adapter is a base class that defines the algorithm outline for reading and
 * writing a state. It implements hooks to allow subclasses to alter the base
 * implementation.
 *
 * This class is a helper class for Context. Context delegates reading and
 * writing states to this class and it's subclasses.
 * 
 * @author rolf
 */
abstract class Adapter {

    /**
     * Get all the entity id's for a specific statemachine that have been persisted
     * All entity id's in all states are returned unless a specific
     * state is given via the optional parameter.
     *
     * This method will be highly useful when you want to select a batch of
     * entities to feed to the statemachine, for example in a cron job or a
     * message queue.
     *
     * @param string $machine
     *            the name of the machine
     * @param string $state
     *            optional: if provided, only those entities in the specific state
     * @return string[] an array of entity_id's
     */
    abstract public function getEntityIds($machine, $state = null);

    /**
     * A template method to be able to process the setting of the current state.
     * saves an object to a storage facility (either insert or update).
     * Implement this method for specifying how you want to set a state in the
     * storage facility.
     *
     * A storage facility could store a timestamp and the state the transition
     * was made to, for extra statistical information.
     * 
     * this method is public to be able to call it via the ReaderWriterDelegator
     *
     * @param Identifier $identifier            
     * @param string $state            
     * @return boolean true if just added to storage, false if stored before
     */
    public function processSetState(Identifier $identifier, $state, $message = null) 
    {
        if ($this->isPersisted($identifier)) {
            $this->addHistory($identifier, $state, $message);
            $this->updateState($identifier, $state, $message);
            return false;
        } else {
            $this->addHistory($identifier, $state, $message);
            $this->insertState($identifier, $state, $message);
            return true;
        }
    }
    
    /**
     * Adds a history record for a transition
     *
     * @param Identifier $identifier
     * @param string $state
     * @param mixed $message
     *            an optional message (which might be exception data or not).
     * @param boolean $is_exception
     *            an optional value, specifying if there was something
     *            exceptional or not.
     *            this can be used to signify an exception for storage in the
     *            backend so we can analyze the history
     *            for regular transitions and failed transitions
     * @throws Exception
     */
    protected function addHistory(Identifier $identifier, $state, $message = null, $is_exception = false)
    {
        //override in subclasses if needed
    }
    
    /**
     * insert state for Identifier into persistance layer.
     * 
     * @param Identifier $identifier            
     * @param string $state            
     */
    protected function insertState(Identifier $identifier, $state, $message = null)
    {
        //override in subclasses
    }
    
    /**
     * update state for statemachine/entity into persistance layer
     * @param Identifier $identifier
     * @param string $state
     * @throws Exception
     */
     protected function updateState(Identifier $identifier, $state, $message = null)
     {
         //override in subclasses
     }

    /**
     * A hook to be able to process the getting of the current state.
     * Implement this method for specifying how you want to get a state from a
     * storage facility.
     * 
     * this method is public to be able to call it via the ReaderWriterDelegator
     *
     * @param Identifier $identifier            
     * @return string the current state of the entity represented in the context
     */
    abstract public function processGetState(Identifier $identifier);
    
    /**
     * is the state information already persisted?
     * 
     * @param Identifier $identifier            
     * @return boolean
     * @throws Exception
     */
    abstract public function isPersisted(Identifier $identifier);

    /**
     * A template method method that adds state information to the persistence layer so
     * it can be manipulated by the statemachine package.
     * It adds a record to the underlying implementation about when the stateful
     * object was first generated/manipulated.
     *
     * This method can safely be called multiple times. It will only add data
     * when there is no state information already present.
     *
     * This method creates a record in the underlying statemachine tables where
     * it's initial state is set.
     * It can then be manipulated via other methods via this Adapter or via
     * the statemachine itself eg: via 'getEntityIds' etc.
     *
     * @param Identifier $identifier            
     * @param string $state
     *            the initial state to set, which should be known to
     *            the client of the statemachine the first time a machine is
     *            created.
     *            this can also be retrieved via a loaded statemachine:
     *            $machine->getInitialState()->getName()
     * @param string $message optional message. this can be used by the persistence adapter
     *          to be part of the transition history to provide extra information about the transition.
     * @return boolean true if it was added, false if it was already there.
     * @throws Exception
     */
    public function add(Identifier $identifier, $state, $message = null)
    {
        if ($this->isPersisted($identifier)) {
            return false;
        }
        $this->addHistory($identifier, $state, $message);
        $this->insertState($identifier, $state, $message);
        return true;
    }

    /**
     * A template method to get the current state for an Identifier
     *
     * @param Identifier $identifier            
     * @return string the state
     */
    public function getState(Identifier $identifier)
    {
        try {
            // execute a hook that should be implemented in a subclass.
            // the subclass could return STATE_UNKNOWN if it is not already
            // added to the storage backend.
            $state = $this->processGetState($identifier);
            return $state;
        } catch(\Exception $e) {
            $e = Utils::wrapToStateMachineException($e, Exception::IO_FAILURE_GET);
            throw $e;
        }
    }

    /**
     * A template method that sets the new state for an Identifier in the storage facility.
     * Will only be called by the statemachine.
     *
     * @param Identifier $identifier
     *            (old state can be retrieved via the identifier and this class)
     * @param string $state
     *            this is the new state
     * @param string $message optional message. this can be used by the persistence adapter
     *          to be part of the transition history to provide extra information about the transition.
     * @return boolan false if already stored before, true if just added
     * @throws Exception
     */
    public function setState(Identifier $identifier, $state, $message = null)
    {
        try {
            // a subclass could map a state to
            // something else that is used internally in legacy
            // systems (eg: order.order_status)
            return $this->processSetState($identifier, $state, $message);
        } catch(\Exception $e) {
            // a possible lowlevel nonstatemachine exception, wrap it and throw
            $e = Utils::wrapToStateMachineException($e, Exception::IO_FAILURE_SET);
            throw $e;
        }
    }

    /**
     * A template method that Stores a failed transition in the storage facility for
     * historical/analytical purposes.
     *
     * @param Identifier $identifier            
     * @param Transition $transition            
     * @param \Exception $e            
     */
    public function setFailedTransition(Identifier $identifier, Transition $transition, \Exception $e)
    {
        // check if it is persisted, otherwise we cannot get the current state
        $message = new \stdClass();
        $message->code = $e->getCode();
        $message->transition = $transition->getName();
        $message->message = $e->getMessage();
        $message->file = $e->getFile();
        $message->line = $e->getLine();
        if ($this->isPersisted($identifier)) {
            /*
            a transition can fail even after a state has been set in the transition process,
            for example when executing the code in the entry action of the new state,
            making the transition partly failed.
            the history will then show a succesful transition to the new state first,
            and here we will then add the failure of the transition with the current state (which is the 'to' state of the transition)
            and with the failure message.
            In case that the transition failed before the state has been set
            then this will be put in the history of transitions with the 'from' state as the current state.
            */
            $state = $this->getState($identifier);
        } else {
            //no current state available in persistence layer.
            //this is exceptional and should not happen when configured correctly and
            //if the machine has been 'added' or if a transition has been (partly) mande.
            //therefore, it must be the from state.. 
            $state = $transition->getStateFrom()->getName();
        }
        $message->state = $state;
        $this->addHistory($identifier, $state, $message, true);
    }

    public function toString()
    {
        return get_class($this);
    }

    public function __toString()
    {
        return $this->toString();
    }
}
