<?php
namespace izzum\statemachine\persistence;
use izzum\statemachine\Identifier;

/**
 * Simple Helper class for storing data.
 * It does not have to be used by subclasses of Adapter, but it is used for
 * both the Session and Memory adapter classes.
 *
 * @author rolf
 */
class StorageData {
    
    /**
     * the entity id
     * 
     * @var string
     */
    public $id;
    /**
     * the state the transition was made to (the current state)
     * 
     * @var string
     */
    public $state;
    
    /**
     * the statemachine name
     * 
     * @var string
     */
    public $machine;
    /**
     * the timestamp when the storagedata was created, ideally at storage time.
     * 
     * @var int
     */
    public $timestamp;

    /**
     *
     * @param Identifier $identifier          
     * @param string $state            
     */
    public function __construct(Identifier $identifier, $state)
    {
        $this->id = $identifier->getEntityId();
        $this->machine = $identifier->getMachine();
        $this->state = $state;
        $this->timestamp = time();
    }
}