<?php

namespace Izzum\StateMachine\Persistence;

use Izzum\StateMachine\Identifier;

/**
 * Simple Helper class for storing data.
 * It does not have to be used by subclasses of Adapter, but it is used for
 * both the Session and Memory adapter classes.
 *
 * @author Rolf Vreijdenberger
 */
class StorageData
{
    /**
     * the entity id
     *
     * @var string
     */
    public string $id;

    /**
     * the statemachine name
     *
     * @var string
     */
    public string $machine;
    /**
     * the timestamp when the storagedata was created, ideally at storage time.
     *
     * @var int
     */
    public int $timestamp;

    /**
     * @param string $state the state the transition was made to (the current state)
     */
    public function __construct(Identifier $identifier, public string $state, public $message = null)
    {
        $this->id = $identifier->getEntityId();
        $this->machine = $identifier->getMachine();
        $this->timestamp = time();
    }
}
