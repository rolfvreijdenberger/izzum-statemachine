<?php
namespace izzum\statemachine\persistence;
use izzum\statemachine\Identifier;
use izzum\statemachine\Exception;
use izzum\statemachine\State;
use izzum\statemachine\Transition;
use izzum\statemachine\utils\Utils;
/**
 * This class serves as a base class for access to different type of persistence 
 * layers we might want to use to store the states for stateful entities.
 * for example: relational (postgres/mysql) databases, nosql databases, php sessions, 
 * files, memcache, redis etc..
 * 
 * It also acts as a central place to store logic related to this persistance layer,
 * which might be useful when you want to get statistics from the persistence  layer.
 * all access logic could be centralized in a subclass.
 * logic such as:
 *  - history of all transitions (when did an entity for a statemachine transition?)
 *  - the retrieval of the definition of transitions
 * 
 * A specific type of persistence adapter can be coupled (via the AbstractFactory)
 * to a specific implementation of a statemachine.
 * 
 * This abstract Adapter class has no side effects (like database/file/session/memory writes etc). 
 * Subclasses will probably have side effects.
 * 
 * Adapter is a base class that defines the algorithm outline for reading and writing a state.
 * It implements hooks to allow subclasses to alter the base implementation.
 * 
 * This class is a helper class for Context.
 * Context delegates reading and writing states to this class and it's subclasses.
 * 
 * @author rolf
 */
abstract class Adapter {
    
    
     /**
     * Get all the entity id's for a specific
     * statemachine. All entity id's in all states are returned unless a specific
     * state is given via the optional parameter.
     *
     * This method will be highly useful when you want to select a batch of
     * entities to feed to the statemachine, for example in a cron job or a message queue.
     *
     * @param string $machine the name of the machine
     * @param string $state optional
     * @return string[] an array of entity_id's
     */
    abstract public function getEntityIds($machine, $state = null);
    
    
     /**
      * A hook to be able to process the setting of the current state.
      * saves an object to a storage facility (either insert or update).
      * Implement this method for specifying how you want to set a state in the
      * storage facility.
      * 
      * A storage facility could store a timestamp and the state the transition
      * was made to, for extra statistical information.
      * 
      * @param Identifier $identifier
      * @param string $state
     * @return boolean true if just added to storage, false if stored before
     */
    abstract protected function processSetState(Identifier $identifier, $state);
    
     /**
      * A hook to be able to process the getting of the current state.
      * Implement this method for specifying how you want to get a state from a
      * storage facility.
      * 
      * @param Identifier $identifier
      * @return string the current state of the entity represented in the context
     */
    abstract protected function processGetState(Identifier $identifier);
    
    /**
     * This method adds state information to the persistence layer so
     * it can be manipulated by the statemachine package. It adds a record to the
     * underlying implementation about when the stateful object was first
     * generated/manipulated. 
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
     * @param string $state the initial state to set, which should be known to 
     * 		the client of the statemachine the first time a machine is created.
     * 		this can also be retrieved via a loaded statemachine: $machine->getInitialState()->getName()
     * @boolean true if it was added, false if it was already there.
     * @throws Exception
     */
    abstract public function add(Identifier $identifier, $state);
    
     /**
     * Get the current state for an Identifier
     * @param Identifier $identifier
     * @return string the state
     */
    public final function getState(Identifier $identifier)
    {
        try {
            //execute a hook that should be implemented in a subclass.
            //the subclass could return STATE_UNKNOWN  it is not already
            //added to the storage backend, or map a state to
            //something else that is used internally in legacy 
            //systems (eg: order.order_status)
            
            $state = $this->processGetState($identifier);
            return $state;
        } catch (\Exception $e) {
            $e = Utils::wrapToStateMachineException($e,Exception::IO_FAILURE_GET);
            throw $e;
        }
    }
    
    /**
     * Sets the new state for an Identifier in the storage facility.
     * Will only be called by the statemachine.
     * 
     * @param Identifier $identifier (old state can be retrieved via the identifier and this class)
     * @param string $state this is the new state
     * @return boolan false if already stored before, true if just added
     * @throws Exception
     */
    public final function setState(Identifier $identifier, $state){
        try {
            return $this->processSetState($identifier, $state);
        } catch (\Exception $e) {
            //a possible low level non statemachine type exception, wrap it and throw
            $e = Utils::wrapToStateMachineException($e, Exception::IO_FAILURE_SET);
            throw $e;
        }
    }
    
    /**
     * Stores a failed transition in the storage facility for historical/analytical purposes.
     * @param Identifier $identifier
     * @param Transition $transition
     * @param \Exception $e
     */
    public function setFailedTransition(Identifier $identifier, Transition $transition, \Exception $e)
    {
        //override in subclasses if necessary
    }
    
    public function toString()
    {
        return get_class($this);
    }
    
    public function __toString(){
    	return $this->toString();
    }

}
