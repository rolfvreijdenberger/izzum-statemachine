<?php

namespace Izzum\StateMachine\Persistence;

use Izzum\StateMachine\Identifier;
use Izzum\StateMachine\State;

/**
 * the Session persistence adapter uses php sessions for persistence.
 *
 * Keep in mind that:
 * - php sessions have a limited lifetime.
 * - php sessions itself can have a different backend adapter, configurable.
 * - php sessions are only valid for one user's session (unless a session id is
 * forced)
 *
 * A simple adapter for a proof of concept and an example.
 * it's possible to use this adapter for for instance gui wizards.
 *
 * see also the /examples/session for how to use this adapter.
 *
 * TRICKY: make sure output is not already sent when instantiating this adapter.
 *
 * @author Rolf Vreijdenberger
 */
class Session extends Adapter
{
    /**
     * @param string $namespace the namespace of the session
     * @param string $sessionId
     *            optional force a session id, used for testing purposes
     */
    public function __construct(private string $namespace = 'izzum', ?string $sessionId = null)
    {
        if (session_status() === PHP_SESSION_NONE) {
            if ($sessionId !== null) {
                session_id($sessionId);
            }
            session_start();
        }
        if (!isset($_SESSION)) {
            $_SESSION = [];
        }
        if (!isset($_SESSION [$this->namespace])) {
            $_SESSION [$this->namespace] = [];
        }
    }


    /**
     * {@inheritDoc}
     */
    public function processGetState(Identifier $identifier): string
    {
        $key = $identifier->getId();
        if (isset($_SESSION [$this->namespace] [$key])) {
            $state = $_SESSION [$this->namespace] [$key]->state;
        } else {
            $state = State::STATE_UNKNOWN;
        }
        return $state;
    }


    /**
     * {@inheritDoc}
     */
    #[\Override]
    protected function insertState(Identifier $identifier, string $state, $message = null): void
    {
        // set object on the session
        $data = new StorageData($identifier, $state, $message);
        $key = $identifier->getId();
        $_SESSION [$this->namespace] [$key] = $data;
    }

    /**
     * {@inheritDoc}
     */
    #[\Override]
    protected function updateState(Identifier $identifier, string $state, $message = null): void
    {
        // set object on the session
        $data = new StorageData($identifier, $state, $message);
        $key = $identifier->getId();
        $_SESSION [$this->namespace] [$key] = $data;
    }

    /**
     * {@inheritDoc}
     */
    public function isPersisted(Identifier $identifier): bool
    {
        $key = $identifier->getId();
        if (!isset($_SESSION [$this->namespace] [$key])) {
            return false;
        }
        return true;
    }

    /**
     * {@inheritDoc}
     */
    public function getEntityIds(string $machine, ?string $state = null): array
    {
        $ids = [];
        foreach ($_SESSION [$this->namespace] as $key => $storage) {
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
}
