<?php
namespace izzum\statemachine;
use izzum\statemachine\persistence\Adapter;
use izzum\statemachine\persistence\Memory;
/**
 * Context is an object that holds information about a stateful entity, which is
 * basically an application domain specific object like 'Order' or 'Customer' that
 * goes through some finite states in it's lifecycle.
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
     * an entity id that represents the unique identifier for an application
     * domain specific object like 'Order', 'Customer' etc.
     * @var string
     */
    protected $entity_id;
    
    /**
     * the statemachine that governs the state behaviour for this entity (eg 'order').
     * this is the name of the statemachine itself and is used in conjunction
     * with the entity_id to define what a statemachine is about.
     * @var string
     */
    protected $machine;
    
    /**
     * the builder to get the reference to the entity.
     * @var EntityBuilder
     */
    protected $builder;
    
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
     * @param mixed $id the id of the domain specific entity (it will internally be converted to a string)
     * @param string $machine the name of the statemachine (eg: 'order')
     * @param EntityBuilder $builder A specific builder class to create a reference to the entity we wish to manipulate.
     * @param persistance\Adapter $persistance_adapter A specific reader/writer class can be used to generate different 'read/write' behaviour
     */
    public function __construct($id, $machine, $builder = null, $persistance_adapter = null)
    {
        //convert id to string (it will likely be an int but a string gives more flexibility)
        $this->entity_id            = "$id";
        $this->machine              = $machine;
        $this->builder              = $builder;
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
     * @return mixed
     */
    public function getEntity()
    {
        //use a specialized builder object to create the (cached) reference.
        return $this->getBuilder()->getEntity($this);
    }
    
    
    /**
     * gets the state.
     *
     * @return string
     */
    public function getState()
    {
        //get the state by delegating to a specific reader
        return $this->getPersistenceAdapter()->getState($this);
    }
    
    /**
     * Sets the state
     *
     * @param string $state
     * @return string
    */
    public function setState($state)
    {
        //set the state by delegating to a specific writer
        return $this->getPersistenceAdapter()->setState($this, $state);
    }

    /**
     * returns the builder used to get the application domain specific model.
     * @return EntityBuilder
     */
    public function getBuilder()
    {
        if($this->builder === null || !is_a($this->builder, 
                'izzum\statemachine\EntityBuilder')) {
            //the default is the default builder.
            $this->builder = new EntityBuilder();
        }
        return $this->builder;
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
        return $this->entity_id;
    }
    
   
    /**
     * gets the statemachine name that handles the entity
     * @return string
     */
    public function getMachine()
    {
        return $this->machine;
    }

    public static function get($id, $machine, $builder = null, $persistence_adapter = null)
    {
        //return a new instance of this (sub)class
        return new static($id, $machine, $builder, $persistence_adapter);
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
     * name and the entity_id in parseable form.
     * 
     * @param boolean $readable human readable or not. defaults to false
     * @param boolean $with_state append current state. defaults to false
     * @return string
     */
    public function getId($readable = false, $with_state = false)
    {
        $output = '';
        if($readable) {
            $output =  "machine: '" . $this->getMachine() . "', id: '". $this->getEntityId()  . "'";
            if($with_state) {
                $output .= ", state: : '" . $this->getState();
            }
        } else {
            $output =   $this->getMachine() . "_" . $this->getEntityId() ;
            if($with_state) {
                $output .=  "_" . $this->getState();
            }
        }
        
        return $output;
    }
    
    public function __toString()
    {
        return $this->getState();
    }
    
    /**
     * adds the contextual data to the persistence layer.
     * @return boolean true if it was added, false if it was already there
     */
    public function add()
    {
       return $this->getPersistenceAdapter()->add($this);
    }
}