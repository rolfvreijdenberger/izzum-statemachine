<?php
namespace izzum\statemachine\persistence;
use izzum\statemachine\Identifier;
use izzum\statemachine\State;

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
class Session extends Adapter {
    /**
     * the namespace of the session
     *
     * @var string
     */
    private $namespace;

    /**
     *
     * @param string $namespace
     *            optional, defaults to izzum
     * @param string $session_id
     *            optional force a session id, used for testing purposes
     */
    public function __construct($namespace = 'izzum', $session_id = null)
    {
        if (session_status() === PHP_SESSION_NONE) {
            if ($session_id) {
                session_id($session_id);
            }
            session_start();
        }
        $this->namespace = $namespace;
        if (!isset($_SESSION)) {
            $_SESSION = array();
        }
        if (!isset($_SESSION [$this->namespace])) {
            $_SESSION [$this->namespace] = array();
        }
    }
    

    public function processGetState(Identifier $identifier)
    {
        $key = $identifier->getId();
        if (isset($_SESSION [$this->namespace] [$key])) {
            $state = $_SESSION [$this->namespace] [$key]->state;
        } else {
            $state = State::STATE_UNKNOWN;
        }
        return $state;
    }

    
    protected function insertState(Identifier $identifier, $state, $message = null)
    {
        // set object on the session
        $data = new StorageData($identifier, $state, $message);
        $key = $identifier->getId();
        $_SESSION [$this->namespace] [$key] = $data;
    }
    
    protected function updateState(Identifier $identifier, $state, $message = null)
    {
        // set object on the session
        $data = new StorageData($identifier, $state, $message);
        $key = $identifier->getId();
        $_SESSION [$this->namespace] [$key] = $data;
    }

    public function isPersisted(Identifier $identifier)
    {
        $key = $identifier->getId();
        if (!isset($_SESSION [$this->namespace] [$key])) {
            return false;
        }
        return true;
    }

    public function getEntityIds($machine, $state = null)
    {
        $ids = array();
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
