<?php
namespace izzum\statemachine\persistence;
use izzum\statemachine\Identifier;
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
    public function __construct($machine, $id, $state) {
        $this->id = $id;
        $this->machine = $machine;
        $this->state = $state;
        $this->timestamp = time();
    }
    
    /**
     * factory method
     * @param Identifier $identifier 
     * @param string $state
     * @return StorageData
     */
    public static function get(Identifier $identifier, $state = null) {
        return new static(
                $identifier->getMachine(), 
                $identifier->getEntityId(), 
                $state);
    }
}