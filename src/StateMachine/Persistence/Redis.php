<?php

namespace Izzum\StateMachine\Persistence;

use Izzum\StateMachine\Loader\Loader;
use Izzum\StateMachine\StateMachine;
use Izzum\StateMachine\Exception;
use Izzum\StateMachine\Identifier;
use Izzum\StateMachine\Loader\JSON;

/**
 * Redis is an open source advanced key-value (nosql database) cache and store using
 * datastructures.
 *
 * Since redis has no schemas (as a nosql db), it should store data based on the way you want to retrieve data.
 * There will be multiple views on the data which should make sense for a lot of use cases (counters,
 * sets, sorted sets, lists, hashes etc) to retrieve the data.
 * All keys that izzum uses in redis can be found in this classes' constants with a 'KEY_' prefix.
 *
 * This class uses the php redis module and your php build should be setup with this module loaded.
 *
 * An instance uses a redis key prefix of 'izzum:' by default. but this can be set to whatever you like.
 *
 * .
 * You can even set the prefix key to something else temporarily for loading the configuration data and
 * then set it to another prefix for writing state data. This allows you to store multiple machine
 * configurations under different prefixes so you can load different machines from different places,
 * which facilitates multiple dev teams working on configuration of different machines without them
 * overwriting other teams' definitions.
 *
 * The configuration of statemachines is a JSON string. The specification of the JSON string can
 * be found in Izzum\StateMachine\Loader\JSON::getJSONSchema. see asset/json/json.schema
 *
 * Internally, this class uses the JSON loader to load the configuration. It can be set up to
 * store multiple configurations under multiple keys.
 *
 * You can use the normal redis connection settings to connect to redis.
 *
 * @link http://redis.io
 * @link https://github.com/nicolasff/phpredis a php module for redis you'll need to use this class.
 * you need to install the php module and make it available to php:
 * - debian/ubuntu: via the apt package manager: apt-get install php5-redis
 * - osx: use homebrew
 *      - install homebrew php https://github.com/Homebrew/homebrew-php
 *      - install php5-redis: brew install php55-redis
 *
 * @link http://redis.io/commands/sort#using-hashes-in-codebycode-and-codegetcode to get information
 *      about how to query a set or list of referenced keys
 * @link https://stackoverflow.com/questions/10155398/getting-multiple-key-values-from-redis methods of retrieval of data in redis
 * @link https://github.com/antirez/lamernews redis implementation with interesting datastructures (by the redis author)
 *
 * @author Rolf Vreijdenberger
 *
 * @mixin \Redis __call() proxies any method directly to the underlying phpredis connection
 */
class Redis extends Adapter implements Loader
{
    public const DATABASE_DEFAULT = 0;//default database to use
    public const KEY_PREFIX_IZZUM = 'izzum:';//default key prefix

    /**
     * default key for configuration of machines
     */
    public const KEY_CONFIGURATION = 'configuration';

    /**
     * configuration:<machine-name>
     */
    public const KEY_CONFIGURATION_SPECIFIC = '%s:%s';
    /**
     * set of entities in a machine:  <machine>
     */
    public const KEY_ENTITYIDS = 'entities:%s:ids';
    /**
     * set of entities per state:  <machine>, <state>
     */
    public const KEY_CURRENT_STATES = 'entities:%s:states:%s';

    /**
     * state of an entity: <machine>, <id>
     */
    public const KEY_ENTITY_STATE = 'entities:%s:state:%s';


    /**
     * this key stores the count of all transitions and is used as the id that references the canonical form.
     * transitions are added by incrementing this counter and using that id as key to the entries of the canonical forms
     * of the transitions (stored as a redis hash)
     */
    public const KEY_COUNTERS_TRANSITIONS_ALL = 'counters:transitions:all';

    /**
     * the keys that stores the canonical form of the transitions, storing the transition data.
     * all other transitions will reference the id/counter of the canonical form
     */
    public const KEY_TRANSITIONS_CANONICAL = 'transitions:canonical:%s';//hash: $counter (KEY_COUNTERS_TRANSITIONS_ALL)

    /**
     * the next keys store id's in sorted sets, scored by timestamp. the id's reference the canonical form.
     * the keys provide different views on the data that can easily be retrieved by using:
     * - sort: the sort command allows you to reference multiple transitions as referenced in a list or set, in one command
     * - zcount: count the elements in a sorted set. this gives you an easy count on the number of transitions per
     *      machine, state, entity etc. since those are all stored.
     */
    public const KEY_TRANSITIONS_ALL = 'transitions:all:normal';//sorted set
    public const KEY_TRANSITIONS_MACHINES = 'transitions:machines:normal:%s';//sorted set: $machine
    public const KEY_TRANSITIONS_STATES = 'transitions:states:normal:%s:%s';//sorted set: $machine, $state
    public const KEY_TRANSITIONS_ENTITIES = 'transitions:entities:normal:%s:%s';//sorted set: $machine, $entityId
    public const KEY_TRANSITIONS_ALL_FAILED = 'transitions:all:failed';//sorted set
    public const KEY_TRANSITIONS_MACHINES_FAILED = 'transitions:machines:failed:%s';//sorted set:$machine
    public const KEY_TRANSITIONS_STATES_FAILED = 'transitions:states:failed:%s:%s';//sorted set: $machine, $state
    public const KEY_TRANSITIONS_ENTITIES_FAILED = 'transitions:entities:failed:%s:%s';//sorted set: $machine, $entityId



    private ?string $host;
    private ?int $port;
    private ?float $timeout;
    private ?string $reserved;
    private ?int $retry;
    private ?string $socket;
    private ?string $password = null;
    private int $database;
    private string $prefix;
    private string $configurationKey;

    /**
     * connected and optionally authenticated redis connection.
     * lazily instantiated, and reset to null on disconnect/failed connect.
     * @var \Redis|null
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
     * @param float $timeout value in seconds. default is 0 meaning unlimited
     * @param string $reserved should be NULL if $retry is specified
     * @param int $retry value in milliseconds
     */
    public function __construct(?string $host = '127.0.0.1', ?int $port = 6379, ?float $timeout = 0, ?string $reserved = null, ?int $retry = null)
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

    public function setUnixDomainSocket(string $socket): void
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
     */
    public function setPassword(string $password): void
    {
        $this->password = $password;
    }

    /**
     * set the redis database. in case there is an active connection, it switches the database.
     * @param int $database a redis database is an integer starting from 0 (the default)
     */
    public function setDatabase(int $database): void
    {
        if ($this->redis) {
            $this->redis->select($database);
        }
        $this->database = $database;
    }

    /**
     * set the redis connection explicitely, useful if you want to share the
     * redis instance when it is created outside this class.
     * @param \Redis $redis a connected (and authenticated) redis instance
     */
    public function setConnection(\Redis $redis): void
    {
        $this->redis = $redis;
    }

    /**
     * set the key prefix to be used for all redis keys
     */
    final public function setPrefix(string $prefix): void
    {
        if ($this->redis) {
            $this->redis->setOption(\Redis::OPT_PREFIX, $prefix);
        }
        $this->prefix = $prefix;
    }

    /**
     * set the configuration key to be used for storing a json string of machine configurations.
     */
    final public function setConfigurationKey(string $key): void
    {
        $this->configurationKey = $key;
    }

    final public function getConfigurationKey(): string
    {
        return $this->configurationKey;
    }

    final public function getPrefix(): string
    {
        return $this->prefix;
    }

    /**
     * Gets a lazy loaded \Redis instance that is connected and optionally authenticated.
     *
     * @throws Exception
     */
    public function getRedis(): \Redis
    {
        //lazy loaded connection
        try {
            if ($this->redis === null) {
                $this->redis = new \Redis();
                if ($this->socket) {
                    $connected = $this->redis->connect($this->socket);
                } else { /* default connection with different parameters */
                    if ($this->retry !== null) {
                        $connected = $this->redis->connect($this->host, $this->port, $this->timeout, null, $this->retry);
                    } else {
                        if ($this->reserved !== null) {
                            $connected = $this->redis->connect($this->host, $this->port, $this->timeout, $this->reserved);
                        } else {
                            $connected = $this->redis->connect($this->host, $this->port, $this->timeout);
                        }
                    }
                }
                if (!$connected) {
                    $this->redis = null;
                    throw new Exception('connection not made', Exception::PERSISTENCE_FAILED_TO_CONNECT);
                }
                if ($this->password) {
                    $authenticated = $this->redis->auth($this->password);
                    if (!$authenticated) {
                        throw new Exception('authentication failed', Exception::PERSISTENCE_FAILED_TO_CONNECT);
                    }
                }
                //set the database
                $this->redis->select($this->database);
                $this->redis->setOption(\Redis::OPT_PREFIX, $this->getPrefix());
                //hook for subclass
                $this->onConnect();
            }

            return $this->redis;

        } catch (\Exception $e) {
            throw new Exception(
                sprintf(
                    "error creating Redis connection: [%s]",
                    $e->getMessage(),
                ),
                Exception::PERSISTENCE_FAILED_TO_CONNECT,
            );
        }

    }

    /**
     * A hook to use in a subclass.
     * you can do you initial setup here if you like.
     */
    protected function onConnect(): void
    {
        //override if necessary
    }

    /**
     * {@inheritDoc}
     */
    public function processGetState(Identifier $identifier): string
    {
        $redis = $this->getRedis();
        try {
            //get state from key
            $key = sprintf(self::KEY_ENTITY_STATE, $identifier->getMachine(), $identifier->getEntityId());
            $state = $redis->get($key);
        } catch (\Exception $e) {
            throw new Exception(sprintf(
                'getting current state failed: [%s]',
                $e->getMessage(),
            ), Exception::PERSISTENCE_LAYER_EXCEPTION);
        }
        if (!$state) {
            throw new Exception(
                sprintf(
                    'no state found for [%s]. '
                    . 'Did you "$machine->add()" it to the persistence layer?',
                    $identifier->getId(true),
                ),
                Exception::PERSISTENCE_LAYER_EXCEPTION,
            );
        }
        return $state;
    }


    /**
     * {@inheritDoc}
     */
    public function isPersisted(Identifier $identifier): bool
    {
        try {
            $redis = $this->getRedis();
            //get key from known entity ids set
            $key = sprintf(self::KEY_ENTITYIDS, $identifier->getMachine());
            return $redis->sismember($key, $identifier->getEntityId());
        } catch (\Exception $e) {
            throw new Exception(
                sprintf(
                    'getting persistence info failed: [%s]',
                    $e->getMessage(),
                ),
                Exception::PERSISTENCE_LAYER_EXCEPTION,
            );
        }
    }


    /**
     * {@inheritDoc}
     */
    #[\Override]
    public function insertState(Identifier $identifier, string $state, $message = null): void
    {
        $redis = $this->getRedis();
        try {
            $redis->multi(\Redis::MULTI);
            //add to set of known id's
            $key = sprintf(self::KEY_ENTITYIDS, $identifier->getMachine());
            $redis->sadd($key, $identifier->getEntityId());
            //set the state
            $key = sprintf(self::KEY_ENTITY_STATE, $identifier->getMachine(), $identifier->getEntityId());
            $redis->set($key, $state);
            //set on current states set
            $key = sprintf(self::KEY_CURRENT_STATES, $identifier->getMachine(), $state);
            $redis->sadd($key, $identifier->getEntityId());
            $redis->exec();

        } catch (\Exception $e) {
            $redis->discard();
            throw new Exception(
                sprintf(
                    'query for inserting state failed: [%s]',
                    $e->getMessage(),
                ),
                Exception::PERSISTENCE_LAYER_EXCEPTION,
            );
        }
    }



    /**
     * {@inheritDoc}
     */
    #[\Override]
    public function updateState(Identifier $identifier, string $state, $message = null): void
    {

        $redis = $this->getRedis();
        try {
            //first, get the current state
            $key = sprintf(self::KEY_ENTITY_STATE, $identifier->getMachine(), $identifier->getEntityId());
            $current = $redis->get($key);


            //now that we have the current state, start multi in pipeline mode for faster execution (single server roundtrip)
            $redis->multi(\Redis::PIPELINE);
            //remove from current state set
            $key = sprintf(self::KEY_CURRENT_STATES, $identifier->getMachine(), $current);
            $redis->srem($key, $identifier->getEntityId());
            //set the new state
            $key = sprintf(self::KEY_ENTITY_STATE, $identifier->getMachine(), $identifier->getEntityId());
            $redis->set($key, $state);
            //set on current states set
            $key = sprintf(self::KEY_CURRENT_STATES, $identifier->getMachine(), $state);
            $redis->sadd($key, $identifier->getEntityId());
            //execute the sequence of redis commands
            $redis->exec();

        } catch (\Exception $e) {
            $redis->discard();
            throw new Exception(
                sprintf(
                    'updating state failed: [%s]',
                    $e->getMessage(),
                ),
                Exception::PERSISTENCE_LAYER_EXCEPTION,
            );
        }
    }

    /**
     * {@inheritDoc}
     */
    #[\Override]
    public function addHistory(Identifier $identifier, string $state, $message = null, bool $isException = false): void
    {
        $redis = $this->getRedis();
        try {
            $machine = $identifier->getMachine();
            $entityId = $identifier->getEntityId();

            //counter for the number of transitions.
            $key = self::KEY_COUNTERS_TRANSITIONS_ALL;
            //this will function as the id of the transition data, stored as a redis hash
            $counter = $redis->incr($key);

            //now that we have the counter, start a redis multi command in pipeline mode
            $redis->multi(\Redis::PIPELINE);

            //create the record for the transition to store in a redis hash
            $timestamp = time();
            $record = [];
            $record['state'] = $state;
            $record['machine'] = $machine;
            $record['entity_id'] = $entityId;
            if ($message) {
                if (is_string($message)) {
                    $info = new \stdClass();
                    $info->message = $message;
                    $message = $info;
                }
                //always json_encode so we can pass objects as messages
                $message = json_encode($message);
            }
            $record['message'] = $message;
            $record['timestamp'] = $timestamp;
            $record['datetime'] = date('Y-m-d H:i:s', $timestamp);//ISO_8601
            $record['exception'] = $isException ? 1 : 0;
            $record['id'] = $counter;

            /*
             * set the canonical form of the transition as a hash with the counter as the id.
             * This allows you to get data from the canonical from by storing a reference to the transaction id
             * in other lists or sets (memory optimization) via:
             * sort izzum:transitions:all:normal by nosort get izzum:transitions:canonical:*->message STORE mymessages
             * @see http://redis.io/commands/sort
             */

            $keyHashId = sprintf(self::KEY_TRANSITIONS_CANONICAL, $counter);
            $redis->hmset($keyHashId, $record);


            //store all transitions referencing the id of the canonical form in sorted sets (keyed by timestamp),
            //set manipulations are powerful in redis (diff, union, intersect etc with the exceptions for example)
            $key = self::KEY_TRANSITIONS_ALL;
            $redis->zadd($key, $timestamp, $counter);
            $key = sprintf(self::KEY_TRANSITIONS_MACHINES, $machine);
            $redis->zadd($key, $timestamp, $counter);
            $key = sprintf(self::KEY_TRANSITIONS_STATES, $machine, $state);
            $redis->zadd($key, $timestamp, $counter);
            $key = sprintf(self::KEY_TRANSITIONS_ENTITIES, $machine, $entityId);
            $redis->zadd($key, $timestamp, $counter);


            if ($isException) {
                //store all failed transitions referencing the id of the canonical form in sets
                $key = self::KEY_TRANSITIONS_ALL_FAILED;
                $redis->zadd($key, $timestamp, $counter);
                $key = sprintf(self::KEY_TRANSITIONS_MACHINES_FAILED, $machine);
                $redis->zadd($key, $timestamp, $counter);
                $key = sprintf(self::KEY_TRANSITIONS_STATES_FAILED, $machine, $state);
                $redis->zadd($key, $timestamp, $counter);
                $key = sprintf(self::KEY_TRANSITIONS_ENTITIES_FAILED, $machine, $entityId);
                $redis->zadd($key, $timestamp, $counter);
            }
            //execute the sequence of redis commands
            $redis->exec();

        } catch (\Exception $e) {
            $redis->discard();
            throw new Exception(
                sprintf(
                    'adding history failed: [%s]',
                    $e->getMessage(),
                ),
                Exception::PERSISTENCE_LAYER_EXCEPTION,
            );
        }
    }

    /**
     * {@inheritDoc}
     */
    public function getEntityIds(string $machine, ?string $state = null): array
    {
        $output = [];
        try {
            $redis = $this->getRedis();
            if ($state !== null) {
                //get from set of entities per state
                $key = sprintf(self::KEY_CURRENT_STATES, $machine, $state);
                $output = $redis->smembers($key);
            } else {
                //get state directly
                $key = sprintf(self::KEY_ENTITYIDS, $machine);
                $output = $redis->smembers($key);
            }
        } catch (\Exception $e) {
            throw new Exception(
                $e->getMessage(),
                Exception::PERSISTENCE_LAYER_EXCEPTION,
                $e,
            );
        }
        return $output;
    }

    /**
     * {@inheritDoc}
     * Load the statemachine with data from a JSON string.
     * the JSON string is stored at the redis key '<prefix:>configuration' by default.
     * you can alter the configuration key by using Redis::setPrefix() and Redis::setConfigurationKey()
     *
     * First, the key '<prefix>:configuration:<machine-name>' is checked for existence.
     * If it exists, take the configuration from that key, else take the configuration form
     * the '<prefix>:configuration' key.
     *
     * This method can be overriden in a subclass to use another loader when
     * the data is stored in redis in YAML or XML form for example.
     * You could use the ReaderWriterDelegator to use another source to load the configuration from.
     */
    public function load(StateMachine $statemachine): int
    {
        //use the JSON loader to load the configuration (see the json schema we expect in JSON::getJSONSchema)
        $key = $this->getConfigurationKey();
        $redis = $this->getRedis();
        $specificKey = sprintf(self::KEY_CONFIGURATION_SPECIFIC, $key, $statemachine->getContext()->getMachine());
        if ($redis->exists($specificKey)) {
            $key = $specificKey;
        }
        $loader = new JSON($this->getRedis()->get($key));
        $count = $loader->load($statemachine);
        return $count;
    }


    public function __destruct()
    {
        try {
            if ($this->redis) {
                $this->redis->close();
                $this->redis = null;
            }
        } catch (\Exception) {
            //nothing we can do about it...
        }
    }

    /**
     * very very dumb proxy to redis connection. should only be used for testing.
     * calls methods on a https://github.com/phpredis/phpredis instance.
     *
     * some method calls will only accept an array as the second argument (like hmset)
     *
     * This makes it useful to test the redis commands or just use this class as an interface to redis.
     *
     * @param string $name name of the method to route to the active redis connection
     * @return mixed
     */
    public function __call(string $name, array $arguments)
    {
        //call the method with $name on the \Redis instance
        return call_user_func_array([$this->getRedis(), $name], $arguments);
    }

    #[\Override]
    public function toString(): string
    {
        return static::class . ' redis://' . $this->host . ':' . $this->port . '/' . $this->database;
    }

    #[\Override]
    public function __toString(): string
    {
        return $this->toString();
    }

}
