<?php
namespace izzum\statemachine\persistence;
use izzum\statemachine\Context;
/**
 * Simple Helper class for storing data. A value object
 * It does not have to be used by subclasses of Adapter, but it is used for
 * both the Session and Memory adapter classes.
 *
 * @author rolf
 */
class StorageData {

    /**
     * the entity id
     * @var string
     */
    public $id;
    /**
     * the state the transition was made to (the current state)
     * @var string
     */
    public $state;
    /**
     * the state the transition was made from if any.
     * A null value implies that the entity was just added to the statemachine
     * storage facility.
     * @var string
     */
    public $state_from;
    /**
     * the statemachine name
     * @var string
     */
    public $machine;
    /**
     * the timestamp when the storagedata was created, ideally at storage time.
     * @var int
     */
    public $timestamp;
    
    /**
     * 
     * @param string $machine
     * @param string $id
     * @param string $state
     * @param string $state_from optional
     */
    public function __construct($machine, $id, $state, $state_from = null) {
        $this->id = $id;
        $this->machine = $machine;
        $this->state = $state;
        $this->state_from = $state_from;
        $this->timestamp = time();
    }
    
    /**
     * factory method
     * @param Context $object this holds the current 'old' state (state_from)
     * @param string $state
     * @return StorageData
     */
    public static function get(Context $object, $state = null) {
        return new static(
                $object->getMachine(), 
                $object->getEntityId(), 
                $state, 
                $object->getState());
    }
}