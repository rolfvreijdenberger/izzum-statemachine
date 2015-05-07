<?php
namespace izzum\statemachine;
use izzum\statemachine\persistence\Adapter;
use izzum\statemachine\persistence\Memory;
use izzum\statemachine\Exception;
/**
 * Context is an object that holds all the contextual information for the statemachine
 * to do it's work with the help of the relevant dependencies.
 * A Context is created by your application to provide the right dependencies ('context')
 * for the statemachine to work with.
 * 
 * Important are:
 * - the stateful entity, which is  an application domain specific  object 
 * like 'Order' or 'Customer' that goes through some finite states in it's lifecycle.
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

 * This class delegates reading and writing states to specific implementations
 * of the Adapter classes. this is useful for 
 * testing and creating specific behaviour for statemachines that need extra 
 * functionality to get and set the correct states.
 * 
 * @author Rolf Vreijdenberger
 *
 */
class Context {
	
    
    /**
     * the Identifier that uniquely identifies the statemachine
     * @var Identifier
     */
    protected $identifier;
    
    /**
     * the builder to get the reference to the entity.
     * @var EntityBuilder
     */
    protected $entity_builder;
    
    /**
     * the instance for getting to the persistence layer
     * @var Adapter
     */
    protected $persistence_adapter;

    
    /**
     * an associated statemachine, if one is set. Only a statemachine that
     * uses this Context should set itself on the Context, providing a 
     * bidirectional association
     * @var StateMachine
     */
    protected $statemachine;
    
    
    /**
     * Constructor
     * @param Identifier $identifier the identifier for the statemachine
     * @param EntityBuilder $entity_builder optional: A specific builder class to create a reference to the entity we wish to manipulate.
     * @param Adapter $persistance_adapter optional: A specific reader/writer class can be used to generate different 'read/write' behaviour
     */
    public function __construct(Identifier $identifier, $entity_builder = null, $persistance_adapter = null)
    {
        $this->identifier           = $identifier;
        $this->entity_builder       = $entity_builder;
        $this->persistance_adapter  = $persistance_adapter;
    }
    
    /**
     * Provides a bidirectional association with the statemachine.
     * This method should be called only by the StateMachine itself.
     * @param StateMachine $statemachine
     */
    public function setStateMachine(StateMachine $statemachine)
    {
        $this->statemachine = $statemachine;
    }
    
    /**
     * gets the associated statemachine (if a statemachine is associated)
     * 
     * @return StateMachine
     */
    public function getStateMachine()
    {
        return $this->statemachine;
    }
    
    /**
     * Gets a (cached) reference to the application domain specific model, 
     * for example an 'Order' or 'Customer' that transitions through states in
     * it's lifecycle.
     * 
     * 
     * @param boolean $create_fresh_entity optional
     * @return mixed
     */
    public function getEntity($create_fresh_entity = false)
    {
        //use a specialized builder object to create the (cached) reference.
        return $this->getBuilder()->getEntity($this->getIdentifier(), $create_fresh_entity);
    }
    
    
    /**
     * gets the state.
     *
     * @return string
     */
    public function getState()
    {
        //get the state by delegating to a specific reader
        return $this->getPersistenceAdapter()->getState($this->getIdentifier());
    }
    
    /**
     * Sets the state
     *
     * @param string $state
     * @return boolan true is state was never added before (just added for the
     *      first time), false otherwise
    */
    public function setState($state)
    {
        //set the state by delegating to a specific writer
        return $this->getPersistenceAdapter()->setState($this->getIdentifier(), $state);
    }

    /**
     * returns the builder used to get the application domain specific model.
     * @return EntityBuilder
     */
    public function getBuilder()
    {
        if($this->entity_builder === null || !is_a($this->entity_builder, 
                'izzum\statemachine\EntityBuilder')) {
            // the default builder returns the Identifier as the entity
            $this->entity_builder = new EntityBuilder();
        }
        return $this->entity_builder;
    }
    
    
    /**
     * gets the Context state reader/writer.
     * 
     * @return Adapter a concrete persistance adapter
     */
    public function getPersistenceAdapter()
    {
        if($this->persistance_adapter === null || !is_a($this->persistance_adapter, 
                'izzum\statemachine\persistence\Adapter')) {
            //the default
            $this->persistance_adapter = new Memory();
        } 
        return $this->persistance_adapter;
    }

     
    /**
     * gets the entity id that represents the unique identifier for the 
     * application domain specific model.
     * 
     * @return string
     */
    public function getEntityId() 
    {
        return $this->getIdentifier()->getEntityId();
    }
    
    /**
     * get the Identifier
     * @return Identifier
     */
    public function getIdentifier()
    {
    	return $this->identifier;
    }
    
   
    /**
     * gets the statemachine name that handles the entity
     * @return string
     */
    public function getMachine()
    {
        return $this->getIdentifier()->getMachine();
    }

    public static function get($identifier, $entity_builder = null, $persistence_adapter = null)
    {
        //return a new instance of this (sub)class
        return new static($identifier, $entity_builder, $persistence_adapter);
    }
    
    /**
     * get the toString representation
     * @return string
     */
    public function toString(){
        return get_class($this) . "(" . $this->getId(true) . ")";
    }
    
    /**
     * get the unique identifier for an Context, which consists of the machine
     * name and the entity_id in parseable form, with an optional state
     * 
     * @param boolean $readable human readable or not. defaults to false
     * @param boolean $with_state append current state. defaults to false
     * @return string
     */
    public function getId($readable = false, $with_state = false)
    {
        $output = $this->getIdentifier()->getId($readable);
        if($readable) {
            if($with_state) {
                $output .= ", state: '" . $this->getState() ."'";
            }
        } else {
            if($with_state) {
                $output .=  "_" . $this->getState();
            }
        }
        
        return $output;
    }
    
    public function __toString()
    {
        return $this->toString();
    }
    
    /**
     * adds the contextual data to the persistence layer.
     * @return boolean true if it was added, false if it was already there
     */
    public function add()
    {
       return $this->getPersistenceAdapter()->add($this->getIdentifier());
    }
    
    /**
     * stores a failed transition, called by the statemachine
     * This is a transition that has failed since it:
     * - was not allowed
     * - where an exception was thrown from a rule or command 
     * - etc. any general transition failure
     * @param Exception $e
     * @param string $transition_name
     */
    public function setFailedTransition(Exception $e, $transition_name)
    {
        $this->getPersistenceAdapter()->setFailedTransition($this->getIdentifier(), $e, $transition_name);
    }
}
