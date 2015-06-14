<?php
namespace izzum\statemachine\persistence;
use izzum\statemachine\Identifier;
use izzum\statemachine\State;

/**
 * In memory storage adapter that stores statemachine data, best used in a
 * runtime environment.
 * This is the default persistence adapter for a Context.
 *
 * TRICKY: This memory adapter only functions during the execution of
 * one (1) php process. Therefore, it is best used in a runtime environment such
 * as a php daemon program or an interactive command line php script.
 *
 * @author Rolf Vreijdenberger
 */
class Memory extends Adapter {
    
    /**
     * hashmap.
     * the key is Identifier->getId()
     *
     * @var StorageData[]
     */
    private static $registry = array();

    /**
     * {@inheritDoc}
     */
    public function processGetState(Identifier $identifier)
    {
        return $this->getStateFromRegistry($identifier);
    }

    /**
     * {@inheritDoc}
     */
    protected function insertState(Identifier $identifier, $state, $message = null)
    {
        $this->setStateInRegistry($identifier, $state, $message);
    }
    
    /**
     * {@inheritDoc}
     */
    protected function updateState(Identifier $identifier, $state, $message = null)
    {
        $this->setStateInRegistry($identifier, $state, $message);
    }
    
    /**
     *
     * @param Identifier $identifier
     * @param string $state
     */
    protected function setStateInRegistry(Identifier $identifier, $state, $message = null)
    {
        $data = new StorageData($identifier, $state, $message);
        $this->writeRegistry($identifier->getId(), $data);
    }
    
    /**
     * {@inheritDoc}
     */
    public function isPersisted(Identifier $identifier)
    {
        $persisted = false;
        $storage = $this->getStorageFromRegistry($identifier);
        if ($storage != null) {
            $persisted = true;
        }
        return $persisted;
    }

    /**
     * {@inheritDoc}
     */
    public function getEntityIds($machine, $state = null)
    {
        $ids = array();
        foreach ($this->getRegistry() as $key => $storage) {
            if (strstr($key, $machine)) {
                if ($state) {
                    if ($storage->state === $state) {
                        $ids [] = $storage->id;
                    }
                } else {
                    $ids [] = $storage->id;
                }
            }
        }
        return $ids;
    }

    protected function getStateFromRegistry(Identifier $identifier)
    {
        $storage = $this->getStorageFromRegistry($identifier);
        if (!$storage) {
            $state = State::STATE_UNKNOWN;
        } else {
            $state = $storage->state;
        }
        return $state;
    }
    
    /**
     * {@inheritDoc}
     */
    protected function addHistory(Identifier $identifier, $state, $message = null, $is_exception = false)
    {
        //don't store history in memory, this is a simple adapter and we don't want a memory increase
        //for a long running process
    }

    /**
     *
     * @param string $key            
     * @param StorageData $value            
     */
    protected function writeRegistry($key, $value)
    {
        self::$registry [$key] = $value;
    }

    /**
     *
     * @return StorageData[]
     */
    protected function getRegistry()
    {
        return self::$registry;
    }

    public function getStorageFromRegistry(Identifier $identifier)
    {
        $registry = $this->getRegistry();
        if (!isset($registry [$identifier->getId()])) {
            $storage = null;
        } else {
            $storage = $registry [$identifier->getId()];
        }
        return $storage;
    }

    /**
     * clears the storage facility.
     * Not a method we want to have on the Adapter interface.
     * this method is useful for testing.
     */
    public static function clear()
    {
        self::$registry = array();
    }

    public static function get()
    {
        return self::$registry;
    }
   
}