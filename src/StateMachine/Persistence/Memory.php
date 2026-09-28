<?php

namespace Izzum\StateMachine\Persistence;

use Izzum\StateMachine\Identifier;
use Izzum\StateMachine\State;

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
class Memory extends Adapter
{
    /**
     * hashmap.
     * the key is Identifier->getId()
     *
     * @var StorageData[]
     */
    private static array $registry = [];

    /**
     * {@inheritDoc}
     */
    public function processGetState(Identifier $identifier): string
    {
        return $this->getStateFromRegistry($identifier);
    }

    /**
     * {@inheritDoc}
     */
    #[\Override]
    protected function insertState(Identifier $identifier, string $state, $message = null): void
    {
        $this->setStateInRegistry($identifier, $state, $message);
    }

    /**
     * {@inheritDoc}
     */
    #[\Override]
    protected function updateState(Identifier $identifier, string $state, $message = null): void
    {
        $this->setStateInRegistry($identifier, $state, $message);
    }

    protected function setStateInRegistry(Identifier $identifier, string $state, $message = null): void
    {
        $data = new StorageData($identifier, $state, $message);
        $this->writeRegistry($identifier->getId(), $data);
    }

    /**
     * {@inheritDoc}
     */
    public function isPersisted(Identifier $identifier): bool
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
    public function getEntityIds(string $machine, ?string $state = null): array
    {
        $ids = [];
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

    protected function getStateFromRegistry(Identifier $identifier): string
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
    #[\Override]
    protected function addHistory(Identifier $identifier, $state, $message = null, $isException = false): void
    {
        //don't store history in memory, this is a simple adapter and we don't want a memory increase
        //for a long running process
    }

    protected function writeRegistry(string $key, StorageData $value): void
    {
        self::$registry [$key] = $value;
    }

    /**
     *
     * @return StorageData[]
     */
    protected function getRegistry(): array
    {
        return self::$registry;
    }

    public function getStorageFromRegistry(Identifier $identifier): ?StorageData
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
    public static function clear(): void
    {
        self::$registry = [];
    }

    public static function get(): array
    {
        return self::$registry;
    }

}
