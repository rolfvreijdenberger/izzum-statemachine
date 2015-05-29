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
use izzum\statemachine\loader\JSON;
/**
 * Redis is an open source advanced key-value (nosql database) cache and store using
 * datastructures.
 * 
 * Since redis has no schemas (as a nosql db), it should store data based on the way you want to retrieve data.
 * There will be multiple views on the data which should make sense for a lot of use cases (counters, 
 * sets, sorted sets, lists, aggregates etc) to retrieve the data.
 * 
 * An instance uses a redis key prefix of 'izzum:' by default. but this can be set to whatever you like.
 * 
 * You can even set the prefix key to something else temporarily for loading the configuration data and
 * then set it to another prefix for writing state data. This allows you to store multiple machine 
 * configurations under different prefixes so you can load different machines from different places, 
 * which facilitates multiple dev teams working on configuration of different machines without them
 * overwriting other teams' definitions.
 * 
 * The configuration of statemachines is a JSON string. The specification of the JSON string can 
 * be found in izzum\statemachine\loader\JSON::getJSONSchema. see asset/json/json.schema
 * 
 * Internally, this class uses the JSON loader to load the configuration. It can be set up to 
 * store multiple configurations under multiple keys.
 * 
 * You can use the normal redis connection settings to connect to redis.
 *
 * @link http://redis.io
 * @link https://github.com/nicolasff/phpredis a php module for redis you'll need to use this class.
 * you need to install the php module:
 * - debian/ubuntu: via the apt package manager: apt-get install php5-redis
 * - osx: use homebrew
 *      - install homebrew php https://github.com/Homebrew/homebrew-php
 *      - install php5-redis: brew install php55-redis
 *
 *
 * @author rolf
 *
 */
class Redis extends Adapter implements Loader {

    const DATABASE_DEFAULT = 0;//default database to use
    const KEY_PREFIX_IZZUM = 'izzum:';//default key prefix
    const KEY_CONFIGURATION = 'configuration';//default key for configuration of machines
    const KEY_ENTITYIDS = '%sentities:%s:ids';//set of entities in a machine: <prefix>, <machine>
    const KEY_CURRENT_STATES = '%sentities:%s:state:%s';//set of entities per state: <prefix>, <machine>, <state>
    const KEY_STATES = '%sstates:%s:%s';//state of an entity: <prefix>, <machine>, <id>

    //TODO: implement all these counters
    const KEY_COUNTER_TRANSITIONS_ALL = '%scount:transitions:all';//prefix
    const KEY_COUNTER_TRANSITIONS_ERROR_ALL = '%scount:transitions:errors:all';//prefix,
    const KEY_COUNTER_TRANSITIONS_STATES = '%scount:transitions:states:%s:%s';//prefix, <machine>, <state>
    const KEY_COUNTER_TRANSITIONS_ERROR_STATES = '%scount:transitions:errors:states:%s:%s';//prefix, <machine>, <state>
    const KEY_COUNTER_TRANSITIONS_MACHINES = '%scount:transitions:machines:%s';//prefix, <machine>
    const KEY_COUNTER_TRANSITIONS_ERROR_MACHINES = '%scount:transitions:errors:machines:%s';//prefix, <machine>>
    const KEY_COUNTER_TRANSITIONS_ENTITIES = '%scount:transitions:entities:%s:%s';//prefix, <machine>, <entity>
    const KEY_COUNTER_TRANSITIONS_ERROR_ENTITIES = '%scount:transitions:errors:entities:%s:%s';//prefix, <machine>, <entity>
    
    //TODO: store in sorted set (state, id, time, machine in json)
    const KEY_TRANSITIONS_FAILED = '%stransitions:failed';
    const KEY_TRANSITIONS_ALL = '%stransitions:all';
    const KEY_TRANSITIONS_MACHINE = '%stransitions:machines:%s:%s';//sorted set history of entity transitions <prefix>, <machine>, <id>
    
    private $host;
    private $port;
    private $timeout;
    private $reserved;
    private $retry;
    private $socket;
    private $password;
    private $database;
    private $prefix;
    private $configuration_key;

    /**
     * connected and optionally authenticated redis connection.
     * @var \Redis
     */
    private $redis;


    /**
     * The constructor accepts default connection parameters.
     *
     * You can also use an existing \Redis instance used by your application. 
     * Just construct without parameters and call 'setConnection($instance)' before doing anything else.
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
        $this->setPrefix(self::KEY_PREFIX_IZZUM);
        $this->setConfigurationKey(self::KEY_CONFIGURATION);
        $this->setDatabase(self::DATABASE_DEFAULT);


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

    /**
     * set password to authenticate to the redis server
     * @param string $password
     */
    public function setPassword($password) {
        $this->password = $password;
    }
    
    /**
     * set the redis database. in case there is an active connection, it switches the database.
     * @param int $database a redis database is an integer starting from 0 (the default)
     */
    public function setDatabase($database) {
        if($this->redis) {
            $this->redis->select($database);
        }
        $this->database = $database;
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
     * set the configuration key to be used for storing a json string of machine configurations.
     * @param string $key
     */
    public final function setConfigurationKey($key) {
        $this->configuration_key = $key;
    }
    
    /**
     * get the configuration key used for storing a json string of machine configurations.
     * @return string $key
     */
    public final function getConfigurationKey() {
        return $this->configuration_key;
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
                } else { /* default connection with different parameters */
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
                //set the database
                $this->redis->select($this->database);
                //hook for subclass
                $this->onConnect();
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
     * you can do you initial setup here if you like.
     */
    protected function onConnect() {
        //override if necessary
    }

    /**
     * implementation of the hook in the Adapter::getState() template method
     * @param Identifier $identifier
     * @param string $state
     */
    public function processGetState(Identifier $identifier) {
        $redis = $this->getConnection();
        try {
            //get state from key
            $key = sprintf(self::KEY_STATES, $this->getPrefix(), $identifier->getMachine(), $identifier->getEntityId());
            $state = $redis->get($key);
        } catch (\Exception $e) {
            throw new Exception(sprintf('getting current state failed: [%s]',
                    $e->getMessage()), Exception::PERSISTENCE_LAYER_EXCEPTION);
        }
        if(!$state) {
            throw new Exception(sprintf('no state found for [%s]. '
                    . 'Did you $machine->add() it to the persistence layer?',
                    $identifier->getId(true)),
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
        $this->insertState($identifier, $state);
        return true;
    }

    /**
     * is the state already persisted?
     * @param Identifier $identifier
     * @return boolean
     * @throws Exception
     */
    public function isPersisted(Identifier $identifier) {
        try {
            $redis = $this->getConnection();
            //get key from known entity ids set
            $key = sprintf(self::KEY_ENTITYIDS, $this->getPrefix(), $identifier->getMachine());
            return $redis->sismember($key, $identifier->getEntityId());
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
        try {
            $redis = $this->getConnection();
            //add a history record
            $this->addHistory($identifier, $state);
            //add to set of known id's
            $key = sprintf(self::KEY_ENTITYIDS, $this->getPrefix(), $identifier->getMachine());
            $redis->sadd($key, $identifier->getEntityId());
            //set the state
            $key = sprintf(self::KEY_STATES, $this->getPrefix(), $identifier->getMachine(), $identifier->getEntityId());
            $redis->set($key, $state);
            //set on current states set
            $key = sprintf(self::KEY_CURRENT_STATES, $this->getPrefix(), $identifier->getMachine(), $state);
            $redis->sadd($key, $identifier->getEntityId());
            
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

        try {
            //add a history record
            $this->addHistory($identifier, $state);
            $redis = $this->getConnection();
            //remove from current state set
            $key = sprintf(self::KEY_STATES, $this->getPrefix(), $identifier->getMachine(), $identifier->getEntityId());
            $current = $redis->get($key);
            $key = sprintf(self::KEY_CURRENT_STATES, $this->getPrefix(), $identifier->getMachine(), $current);
            $redis->srem($key, $identifier->getEntityId());
            //set the new state
            $key = sprintf(self::KEY_STATES, $this->getPrefix(), $identifier->getMachine(), $identifier->getEntityId());
            $redis->set($key, $state);
            //set on current states set
            $key = sprintf(self::KEY_CURRENT_STATES, $this->getPrefix(), $identifier->getMachine(), $state);
            $redis->sadd($key, $identifier->getEntityId());
            
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
        try {
            $redis = $this->getConnection();
            $prefix = $this->getPrefix();
            
            
            //create the record for the transition
            $timestamp = time();
            $record = new \stdClass();
            $record->state = $state;
            $record->machine = $identifier->getMachine();
            $record->entity_id = $identifier->getEntityId();
            $record->timestamp = $timestamp;
            $record->message = $message;//TODO: check it is not double encoded
            $record = json_encode($record);
            
            //add to lists for specific machines
            $key = sprintf(self::KEY_TRANSITIONS_MACHINE, $prefix, $identifier->getMachine(), $identifier->getEntityId());
            $redis->rpush($key, $record);
            //add to list of all transitions
            $key = sprintf(self::KEY_TRANSITIONS_ALL, $prefix);
            $redis->rpush($key, $record);
            if($message != null ) {
                //add failed message to list
                $key = sprintf(self::KEY_TRANSITIONS_FAILED, $prefix);
                $redis->rpush($key, $record);
            }
            
            //TODO: counters (also for errors)
            
        } catch (\Exception $e) {
            throw new Exception(sprintf('adding history failed: [%s]',
                    $e->getMessage()),
                    Exception::PERSISTENCE_LAYER_EXCEPTION);
        }
    }


    
    /**
     * Stores a failed transition in the storage facility.
     *
     * @param Identifier $identifier
     * @param Transition $transition
     * @param \Exception $e
     */
    public function setFailedTransition(Identifier $identifier, Transition $transition, \Exception $e)
    {
        // check if it is persisted, otherwise we cannot get the current state
        if ($this->isPersisted($identifier)) {
            $message = new \stdClass();
            $message->code = $e->getCode();
            $message->transition = $transition->getName();
            $message->message = $e->getMessage();
            $message->file = $e->getFile();
            $message->line = $e->getLine();
            $state = $this->getState($identifier);
            $message->state = $state;
            $this->addHistory($identifier, $state, $message, true);
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
        $redis = $this->getConnection();
        $prefix = $this->getPrefix();
        try {
            if($state) {
                //get from set of entities per state
                $key = sprintf(self::KEY_CURRENT_STATES, $this->getPrefix(), $machine, $state);
                return $redis->smembers($key);
            } else {
                //get state directly
                $key = sprintf(self::KEY_ENTITYIDS, $this->getPrefix(), $machine);
                return $redis->smembers($key);
            }
        } catch (\Exception $e) {
            throw new Exception($e->getMessage(),
                    Exception::PERSISTENCE_LAYER_EXCEPTION, $e);
        }
        return $output;
    }

    /**
     * Load the statemachine with data.
     * @param StateMachine $statemachine
     */
    public function load(StateMachine $statemachine) {
        //use the JSON loader to load the configuration (see the json schema we expect in JSON::getJSONSchema)
        $key = $this->getPrefix() . $this->getConfigurationKey();
        $loader = new JSON($this->redis->get($key));
        $count = $loader->load($statemachine);
        return $count;
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
     * very very dumb proxy to redis connection. should only be used for testing.
     * The assumption is that the first of the arguments is a call to redis
     * that accepts a KEY as it's first argument in $arguments.
     * 
     * This makes it useful to test most of the datastructure commands but definitely
     * not all of them (eg: hset, zadd etc. are ok. but: migrate, scan, object etc. are not ok).
     * 
     * Also, it makes use of the prefix you set on the adapter
     * @param string $name name of the method to route to the active redis connection
     * @param mixed $arguments
     * @return mixed
     */
    public function __call($name, $arguments)
    {
        if($arguments) {
            //prefix with the currently chosen prefix
            $arguments[0] = $this->getPrefix() . $arguments[0];
        }
        return call_user_func_array(array($this->getConnection(), $name), $arguments);
    }
    
    public function toString()
    {
        return get_class($this) . 'redis://'. $this->host . ':' . $this->port . '/' . $this->database;
    }
    
    public function __toString()
    {
        return $this->toString();
    }

}
