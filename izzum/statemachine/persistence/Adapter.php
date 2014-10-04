<?php
namespace izzum\statemachine\persistence;
use izzum\statemachine\Context;
use izzum\statemachine\Exception;
use izzum\statemachine\State;
/**
 * This class serves as a base class for access to different type of persistence 
 * layers we might want to use to store the states for stateful entities.
 * for example: relational (postgres/mysql) databases, nosql databases, php sessions, 
 * files, memcache etc..
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
      * get the state of type 'initial' for a specific machine
      *
      * Get the only state for this machine that has a type of 'initial'.
      * 
      * In case it is not found return State::STATE_UNKNOWN (which would be a 
      * misconfiguration from the storage facility which dynamically retrieves 
      * data)
      *
      * @param Context $context
      * @return string
     */
    public function getInitialState(Context $context) {
        return State::STATE_NEW;
    }
    
    
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
     * This method adds a stateful object to the statemachine implementation so
     * it can be manipulated by the statemachine package or adds a record to the
      * underlying implementation about when the stateful object was first
      * generated/manipulated.
      * 
     * This method creates a record in the underlying statemachine tables where
     * it's initial state is set.
     * It can then be manipulated via other methods via this Adapter or via
     * the statemachine itself eg: via 'getEntityIds' etc.
      * 
     * @param Context $context
     * @boolean true if it was added, false if it was already there.
     * @throws Exception
     */
    abstract public function add(Context $context);
    
     /**
      * A hook to be able to precess the setting of the current state.
      * saves an object to a storage facility (either insert or update).
      * Implement this method for specifying how you want to set a state in the
      * storage facility.
      * 
      * A storage facility could store a timestamp and the state the transition
      * was made to, for extra statistical information.
      * 
      * @param Context $context
      * @param string $state
     */
    abstract protected function processSetState(Context $context, $state);
    
     /**
      * A hook to be able to process the getting of the current state.
      * Implement this method for specifying how you want to get a state from a
      * storage facility.
      * 
      * @param Context $context
      * @return string the current state of the entity represented in the context
     */
    abstract protected function processGetState(Context $context);
    
     /**
     * Get the current state for an Context
     * @param Context $context
     * @return string the state
     */
    public final function getState(Context $context)
    {
        try {
            //execute a hook that should be implemented in a subclass.
            //the subclass could check for STATE_UNKNOWN to see if it is already
            //added to the storage backend, or map a state to
            //something else that is used internally in legacy 
            //systems (eg: order.order_status)
            
            $state = $this->processGetState($context);
            return $state;
        } catch (Exception $e) {
            //already a statemachine exception, just rethrow
            throw $e;
        } catch (\Exception $e) {
            //a non statemachine type exception, wrap it so it is logged and throw
            $e = new Exception($e->getMessage(), Exception::IO_FAILURE_GET, $e);
            throw $e;
        }
    }
    
    /**
     * Sets the new state for an Context in the storage facility.
     * Will only be called by the statemachine.
     * 
     * @param Context $context Assume this object has the old state
     * @param string $state this is the new state
     * @return boolan true if already stored and overwritten, false if not stored before
     * @throws \izzum\statemachine\persistence\Exception
     */
    public final function setState(Context $context, $state){
        try {
            return $this->processSetState($context, $state);
        } catch (Exception $e) {
            //might be a database failure or network failure from a subclass
            //already a statemachine exception, just rethrow, it is logged
            throw $e;
        } catch (\Exception $e) {
            //a non statemachine type exception, wrap it so it is logged and throw
            $e = new Exception($e->getMessage(), Exception::IO_FAILURE_SET, $e);
            throw $e;
        }
    }
    
    public function toString()
    {
        return get_class($this);
    }

}
