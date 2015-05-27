<?php
namespace izzum\statemachine\persistence;
use izzum\statemachine\loader\Loader;
use izzum\statemachine\StateMachine;
use izzum\statemachine\Context;
use izzum\statemachine\loader\LoaderArray;
use izzum\statemachine\loader\LoaderData;
use izzum\statemachine\Exception;
use izzum\statemachine\Identifier;
use izzum\statemachine\Transition;
/**
 * Redis is an open source advanced key-value (nosql database) cache and store using
 * datastructures.
 *
 * @link http://redis.io
 * @link https://github.com/nicolasff/phpredis a php module for redis you'll need to use this class.
 * you need to install the php module:
 * - debian/ubuntu: via the apt package manager: apt-get install php5-redis
 *
 *
 * @author rolf
 *
 */
class Redis extends Adapter implements Loader {

    private $host;
    private $port;
    private $timeout;
    private $reserved;
    private $retry;
    private $socket;
    private $password;
    private $prefix;

    /**
     * connected and optionally authenticated redis connection.
     * @var \Redis
     */
    private $redis;


    /**
     * The constructor accepts default connection parameters.
     *
     * You can also use an existing Redis instance. Just construct without parameters
     * and call 'setConnection($instance)' before doing anything else
     *
     * You can also use a unix domain socket. Just construct without parameters
     * and call 'setUnixDomainSocket' before doing anything else.
     *
     * @param string $host optional
     * @param int $port optional
     * @param float $timeout value in seconds. default is 0 meaning unlimited
     * @param string $reserved should be NULL if $retry is specified
     * @param int $retry value in milliseconds
     */
    public function __construct($host = '127.0.0.1', $port = 6379, $timeout = 0, $reserved = null, $retry = null)
    {

        $this->host = $host;
        $this->port = $port;
        $this->timeout = $timeout;
        $this->reserved = $reserved;
        $this->retry = $retry;
        $this->socket = null;


    }

    public function setUnixDomainSocket($socket)
    {
        $this->socket = $socket;
        $this->host = null;
        $this->port = null;
        $this->timeout = null;
        $this->reserved = null;
        $this->retry = null;
        $this->socket = null;
    }

    public function setPassword($password) {
        $this->password = $password;
    }

    /**
     * set the redis connection explicitely, useful if you want to share the
     * redis instance when it is created outside this class.
     * @param \Redis $redis a connected (and authenticated) redis instance
     */
    public function setConnection(\Redis $redis)
    {
        $this->redis = $redis;
    }

    /**
     * set the key prefix to be used for all redis keys
     * @param string $prefix
     */
    public final function setPrefix($prefix) {
        $this->prefix = $prefix;
    }

    /**
     * get the prefix for all keys used
     * @return string
     */
    public final function getPrefix()
    {
        return $this->prefix;
    }

    /**
     * Gets a lazy loaded \Redis instance that is connected and optionally authenticated.
     *
     * @throws Exception
     * @return \Redis
     */
    public function getConnection() {
        //lazy loaded connection

        try {
            if($this->redis === null) {
                $this->redis = new \Redis();
                if($this->socket) {
                    $connected = $this->redis->connect($this->socket);
                } else {
                    if($this->retry) {
                        $connected = $this->redis->connect($this->host, $this->port, $this->timeout, null, $this->retry);
                    } else {
                        if($this->reserved) {
                            $connected = $this->redis->connect($this->host, $this->port, $this->timeout, $this->reserved);
                        } else {
                            $connected = $this->redis->connect($this->host, $this->port, $this->timeout);
                        }
                    }
                }
                if(!$connected) {
                    $this->redis = null;
                    throw new Exception('connection not made', Exception::PERSISTENCE_FAILED_TO_CONNECT);
                }
                if($this->password) {
                    $authenticated = $this->redis->auth($this->password);
                    if(!$authenticated) {
                        throw new Exception('authentication failed', Exception::PERSISTENCE_FAILED_TO_CONNECT);
                    }
                }
                //hook for subclass
                $this->onConnect($this->redis);
            }

            return $this->redis;

        } catch (\Exception $e) {
            throw new Exception(
                    sprintf("error creating Redis connection: [%s]",
                             $e->getMessage()),
                    Exception::PERSISTENCE_FAILED_TO_CONNECT);
        }

    }

    /**
     * A hook to use in a subclass.
     * @param \Redis $redis
     */
    protected function onConnect(\Redis $redis) {
        //override if necessary
    }

    /**
     * implementation of the hook in the Adapter::getState() template method
     * @param Identifier $identifier
     * @param string $state
     */
    public function processGetState(Identifier $identifier) {
        $connection = $this->getConnection();
        $prefix = $this->getPrefix();
        try {
            //TODO
            $state;
        } catch (\Exception $e) {
            throw new Exception(sprintf('getting current state failed: [%s]',
                    $e->getMessage()), Exception::PERSISTENCE_LAYER_EXCEPTION);
        }
        if(!$state) {
            throw new Exception(sprintf('no state found for [%s]. '
                    . 'Did you add it to the persistence layer?',
                    $context->getId(true)),
                    Exception::PERSISTENCE_LAYER_EXCEPTION);
        }
        return $state;
    }

    /**
     * implementation of the hook in the Adapter::setState() template method
     * @param Identifier $identifier
     * @param string $state
     * @return boolean true if not already present, false if stored before
     */
    public function processSetState(Identifier $identifier, $state) {
        if($this->isPersisted($identifier)) {
            $this->updateState($identifier, $state);
            return false;
        } else {
            $this->insertState($identifier, $state);
            return true;
        }
    }

    /**
     * adds Context info to the persistance layer.
     * Thereby marking the time when the object was created.
     * @param Identifier $identifier
     * @return boolean
     */
    public function add(Identifier $identifier, $state) {
        if($this->isPersisted($identifier)) {
            return false;
        }
        $this->insertState($identifier, $this->getInitialState($identifier));
        return true;
    }

    /**
     * is the state already persisted?
     * @param Identifier $identifier
     * @return boolean
     * @throws Exception
     */
    public function isPersisted(Identifier $identifier) {
        $connection = $this->getConnection();
        $prefix = $this->getPrefix();
        try {
            //TODO
            $id;
            return ($id == $identifier->getEntityId());
        } catch (\Exception $e) {
            throw new Exception(
                    sprintf('getting persistence info failed: [%s]',
                            $e->getMessage()), Exception::PERSISTENCE_LAYER_EXCEPTION);
        }
    }


    /**
     * insert state for statemachine/entity into persistance layer.
     * This method is public for testing purposes
     * @param Context $context
     * @param string $state
     */
    public function insertState(Identifier $identifier, $state)
    {

        //add a history record
        $this->addHistory($identifier, $state);

        $connection = $this->getConnection();
        $prefix = $this->getPrefix();
        try {
            //TODO
        } catch (\Exception $e) {
            throw new Exception(sprintf('query for inserting state failed: [%s]',
                    $e->getMessage()),
                    Exception::PERSISTENCE_LAYER_EXCEPTION);
        }
    }



    /**
     * update state for statemachine/entity into persistance layer
     * This method is public for testing purposes
     * @param Identifier $identifier
     * @param string $state
     * @throws Exception
     */
    public function updateState(Identifier $identifier, $state)
    {
        //add a history record
        $this->addHistory($identifier, $state);

        $connection = $this->getConnection();
        $prefix = $this->getPrefix();
        try {
            //TODO
        } catch (\Exception $e) {
            throw new Exception(sprintf('updating state failed: [%s]',
                    $e->getMessage()),
                    Exception::PERSISTENCE_LAYER_EXCEPTION);
        }
    }

    /**
     * Adds a history record for a transition
     * @param Identifier $identifier
     * @param string $state
     * @param string $message an optional message. which would imply an error.
     * @throws Exception
     */
    public function addHistory(Identifier $identifier, $state, $message = null)
    {
        $connection = $this->getConnection();
        $prefix = $this->getPrefix();
        try {
            //TODO
        } catch (\Exception $e) {
            throw new Exception(sprintf('adding history failed: [%s]',
                    $e->getMessage()),
                    Exception::PERSISTENCE_LAYER_EXCEPTION);
        }
    }


    /**
     * Stores a failed transition in the storage facility.
     * @param Context $context
     * @param Transition $transition
     * @param Exception $e
     */
    public function setFailedTransition(Identifier $identifier, Transition $transition, Exception $e)
    {
        //check if it is persisted, otherwise we cannot get the current state
        if($this->isPersisted($identifier)) {
            $message = new \stdClass();
            $message->code = $e->getCode();
            $message->transition = $transition_name;
            $message->message = $e->getMessage();
            $message->file = $e->getFile();
            $message->line = $e->getLine();
            //convert to json for storage
            $json = json_encode($message);
            $state = $this->getState($identifier);
            $this->addHistory($identifier, $state, $json);
        }
    }

    /**
     *
     * @param string $machine the machine to get the names for
     * @param string $state
     * @return string[] an array of entity ids
     * @throws Exception
     */
    public function getEntityIds($machine, $state = null) {
        $connection = $this->getConnection();
        $prefix = $this->getPrefix();
        try {
            //TODO
            $output = array();

        } catch (\Exception $e) {
            throw new Exception($e->getMessage(),
                    Exception::PERSISTENCE_LAYER_EXCEPTION, $e);
        }
        return $output;
    }

    /**
     * Load the statemachine with data.
     * This is an implemented method from the Loader interface.
     * All other methods are actually implemented methods from the Adapter class.
     * @param StateMachine $statemachine
     */
    public function load(StateMachine $statemachine) {
        $data = $this->getTransitions($statemachine->getMachine());
        //delegate to LoaderArray
        $loader = new LoaderArray($data);
        $loader->load($statemachine);
    }

    /**
     * get all the ordered transition information for a specific machine.
     * This method is made public for testing purposes
     * @param string $machine
     * @return [][] something
     * @throws Exception
     */
    public function getTransitions($machine)
    {
        $connection = $this->getConnection();
        $prefix = $this->getPrefix();

        try {
            //TODO
            $transitions;
        } catch (\Exception $e) {
            throw new Exception($e->getMessage(),
                    Exception::PERSISTENCE_LAYER_EXCEPTION, $e);
        }

        return $transitions;
    }


    /**
     * do some cleanup
     */
    public function __destruct()
    {
        try {
            if($this->redis) {
                $this->redis->close();
                $this->redis = null;
            }
        } catch (\Exception $e) {

        }
    }

    /**
     * very very dumb proxy to redis connection. only used during testing.
     * The assumption is that the first of the arguments is a call to redis
     * that accepts a KEY as it's first argument.
     * This makes it useful to test most of the datastructure commands but definitely
     * not all of them (eg: hset, zadd etc. are ok. but: migrate, scan, object etc. are not ok).
     * @param string $name name of the method to route to the active redis connection
     * @param mixed $arguments
     * @return mixed
     */
    public function __call($name, $arguments)
    {
        if($arguments) {
            $arguments[0] = $this->getPrefix() . $arguments[0];
        }
        return call_user_func_array(array($this->getConnection(), $name), $arguments);
    }

}
