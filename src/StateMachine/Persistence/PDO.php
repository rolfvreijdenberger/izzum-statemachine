<?php

namespace Izzum\StateMachine\Persistence;

use Izzum\StateMachine\Loader\Loader;
use Izzum\StateMachine\StateMachine;
use Izzum\StateMachine\Identifier;
use Izzum\StateMachine\Loader\LoaderArray;
use Izzum\StateMachine\Exception;
use Izzum\StateMachine\Transition;
use Izzum\StateMachine\State;

/**
 * A persistence adapter/loader specifically for the PHP Data Objects (PDO)
 * extension.
 * PDO use database drivers for different backend implementations (postgres,
 * mysql, sqlite, MSSQL, oracle etc).
 * By providing a DSN connection string, you can connect to different backends.
 *
 * This specific adapter uses the schema as defined in
 * - /assets/sql/postgres.sql
 * - /assets/sql/sqlite.sql
 * - /assets/sql/mysql.sql
 * but can be used on tables for a different database vendor as long as
 * the table names, fields and constraints are the same. Optionally,
 * a prefix can be set.
 *
 * This Adapter does double duty as a Loader since both use the
 * same backend. You could use two seperate classes for this, but you can
 * implement both in one class.
 * You could use the ReaderWriterDelegator to seperate
 * the reading of configuration and the persisting of state and transition data.
 *
 *
 * If you need an adapter more specialized to your
 * needs/framework/system you can easily write one yourself.
 *
 * More functionality related to a backend can be implemented on this class eg:
 * - get the history of transitions from the history table
 * - get the factories for machines from the machine table
 *
 * @link http://php.net/manual/en/pdo.drivers.php
 * @link http://php.net/manual/en/book.pdo.php
 *
 * @author Rolf Vreijdenberger
 */
class PDO extends Adapter implements Loader
{
    /**
     * the locally cached connections
     *
     * lazily instantiated, and reset to null on destruction.
     * @var \PDO|null
     */
    private $connection;

    /**
     * table prefix
     *
     * @var string
     */
    private $prefix = '';

    /**
     * @param string $dsn
     *            a PDO data source name
     *            example: 'pgsql:host=localhost;port=5432;dbname=izzum'
     * @link http://php.net/manual/en/pdo.connections.php
     */
    public function __construct(
        private string $dsn,
        private ?string $user = null,
        private ?string $password = null,
        private array $options = [],
    ) {}

    /**
     * get the connection to a database via the PDO adapter.
     * The connection retrieved will be reused if one already exists.
     *
     * @throws Exception
     */
    public function getConnection(): \PDO
    {
        try {
            if ($this->connection === null) {
                $this->connection = new \PDO($this->dsn, $this->user, $this->password, $this->options);
                $this->setupConnection($this->connection);
            }
            return $this->connection;
        } catch (\Exception $e) {
            throw new Exception(sprintf("error creating PDO [%s], message: [%s]", $this->dsn, $e->getMessage()), Exception::PERSISTENCE_FAILED_TO_CONNECT);
        }
    }

    /**
     * set the PDO connection explicitely, useful if you want to share the
     * PDO instance when it is created outside this class.
     */
    public function setConnection(\PDO $connection): void
    {
        $this->connection = $connection;
    }

    protected function setupConnection(\PDO $connection): void
    {
        /**
         * hook, override to:
         * - set schema on postgresql
         * - set PRAGMA on sqlite
         * - etc..
         * whatever is the need to do a setup on, the first time
         * you create a connection
         * - SET UTF-8 on mysql can be done with an option in the $options
         * constructor argument
         */
    }

    /**
     *
     * @return string the type of backend we connect to
     */
    public function getType(): string
    {
        $index = strpos($this->dsn, ":");
        return $index ? substr($this->dsn, 0, $index) : $this->dsn;
    }

    /**
     * set the table prefix to be used
     */
    final public function setPrefix(string $prefix): void
    {
        $this->prefix = $prefix;
    }

    final public function getPrefix(): string
    {
        return $this->prefix;
    }

    /**
     * {@inheritDoc}
     * This is an implemented method from the Loader interface.
     * All other methods are actually implemented methods from the Adapter
     * class.
     */
    public function load(StateMachine $statemachine): int
    {
        $data = $this->getLoaderData($statemachine->getContext()->getMachine());
        // delegate to LoaderArray
        $loader = new LoaderArray($data);
        return $loader->load($statemachine);
    }

    /**
     * {@inheritDoc}
     */
    public function processGetState(Identifier $identifier): string
    {
        $connection = $this->getConnection();
        $prefix = $this->getPrefix();
        try {
            $query = 'SELECT state FROM ' . $prefix . 'statemachine_entities WHERE machine = ' . ':machine AND entity_id = :entity_id';
            $machine = $identifier->getMachine();
            $entityId = $identifier->getEntityId();
            $statement = $connection->prepare($query);
            $statement->bindParam(":machine", $machine);
            $statement->bindParam(":entity_id", $entityId);
            $result = $statement->execute();
            if ($result === false) {
                throw new Exception($this->getErrorInfo($statement));
            }
        } catch (\Exception $e) {
            throw new Exception(sprintf('query for getting current state failed: [%s]', $e->getMessage()), Exception::PERSISTENCE_LAYER_EXCEPTION);
        }
        $row = $statement->fetch();
        if ($row === false) {
            throw new Exception(sprintf('no state found for [%s]. Did you "$machine->add()" it to the persistence layer?', $identifier->toString()), Exception::PERSISTENCE_LAYER_EXCEPTION);
        }
        return $row ['state'];
    }




    /**
     * {@inheritDoc}
     */
    public function getEntityIds(string $machine, ?string $state = null): array
    {
        $connection = $this->getConnection();
        $prefix = $this->getPrefix();
        $query = 'SELECT se.entity_id FROM ' . $prefix . 'statemachine_entities AS se
                JOIN ' . $prefix . 'statemachine_states AS ss ON (se.state = ss.state AND
                se.machine = ss.machine) WHERE se.machine = :machine';
        $output = [];
        try {
            if ($state != null) {
                $query .= ' AND se.state = :state';
            }
            $statement = $connection->prepare($query);
            $statement->bindParam(":machine", $machine);
            if ($state != null) {
                $statement->bindParam(":state", $state);
            }

            $result = $statement->execute();
            if ($result === false) {
                throw new Exception($this->getErrorInfo($statement));
            }

            $rows = $statement->fetchAll();
            // @phpstan-ignore identical.alwaysFalse (PHPStan's PDO stub omits the false-on-failure case that the real PDOStatement::fetchAll() can return)
            if ($rows === false) {
                throw new Exception("failed getting rows: " . $this->getErrorInfo($statement));
            }

            foreach ($rows as $row) {
                $output [] = $row ['entity_id'];
            }
        } catch (\Exception $e) {
            throw new Exception($e->getMessage(), Exception::PERSISTENCE_LAYER_EXCEPTION, $e);
        }
        return $output;
    }

    /**
     * {@inheritDoc}
     */
    public function isPersisted(Identifier $identifier): bool
    {
        $connection = $this->getConnection();
        $prefix = $this->getPrefix();
        try {
            $query = 'SELECT entity_id FROM ' . $prefix . 'statemachine_entities WHERE ' . 'machine = :machine AND entity_id = :entity_id';
            $statement = $connection->prepare($query);
            $machine = $identifier->getMachine();
            $entityId = $identifier->getEntityId();
            $statement->bindParam(":machine", $machine);
            $statement->bindParam(":entity_id", $entityId);
            $result = $statement->execute();
            if ($result === false) {
                throw new Exception($this->getErrorInfo($statement));
            }

            $row = $statement->fetch();

            if ($row === false) {
                return false;
            }
            return ($row ['entity_id'] == $identifier->getEntityId());
        } catch (\Exception $e) {
            throw new Exception(sprintf('query for getting persistence info failed: [%s]', $e->getMessage()), Exception::PERSISTENCE_LAYER_EXCEPTION);
        }
    }

    /**
     * {@inheritDoc}
     */
    #[\Override]
    protected function insertState(Identifier $identifier, string $state, $message = null): void
    {

        $connection = $this->getConnection();
        $prefix = $this->getPrefix();
        try {
            $query = 'INSERT INTO ' . $prefix . 'statemachine_entities
                (machine, entity_id, state, changetime)
                    VALUES
                (:machine, :entity_id, :state, :timestamp)';
            $statement = $connection->prepare($query);
            $machine = $identifier->getMachine();
            $entityId = $identifier->getEntityId();
            $timestamp =  $this->getTimestampForDriver();
            $statement->bindParam(":machine", $machine);
            $statement->bindParam(":entity_id", $entityId);
            $statement->bindParam(":state", $state);
            $statement->bindParam(":timestamp", $timestamp);
            $result = $statement->execute();
            if ($result === false) {
                throw new Exception($this->getErrorInfo($statement));
            }
        } catch (\Exception $e) {
            throw new Exception(sprintf('query for inserting state failed: [%s]', $e->getMessage()), Exception::PERSISTENCE_LAYER_EXCEPTION);
        }
    }

    /**
     * collects error information ready to be used as output.
     */
    protected function getErrorInfo(\PDOStatement $statement): string
    {
        $info = $statement->errorInfo();
        $output = sprintf("%s - message: '%s'", $info [0], $info [2]);
        return $output;
    }

    /**
     * hook method
     */
    protected function getTimestampForDriver(): int|string
    {
        // yuk, seems postgres and sqlite need some different input.
        // maybe other drivers too. so therefore this hook method.
        if (strstr($this->dsn, 'sqlite:')) {
            return time(); // "CURRENT_TIMESTAMP";//"DateTime('now')";
        }
        // might have to be overriden for certain drivers.
        return date("Y-m-d H:i:s", time()); // since prepared statements do not support now as binding parameter we need the time from php
    }

    /**
     * hook method.
     * not all drivers have the same boolean datatype. convert here.
     */
    protected function getBooleanForDriver(bool $boolean): bool|int
    {
        if (strstr($this->dsn, 'sqlite:') || strstr($this->dsn, 'mysql:')) {
            return $boolean ? 1 : 0;
        }
        // might have to be overriden for certain drivers.
        return $boolean;
    }

    /**
     * {@inheritDoc}
     */
    #[\Override]
    protected function updateState(Identifier $identifier, string $state, $message = null): void
    {

        $connection = $this->getConnection();
        $prefix = $this->getPrefix();
        try {
            $query = 'UPDATE ' . $prefix . 'statemachine_entities SET state = :state, 
                changetime = :timestamp WHERE entity_id = :entity_id 
                AND machine = :machine';
            $statement = $connection->prepare($query);
            $machine = $identifier->getMachine();
            $entityId = $identifier->getEntityId();
            $timestamp =  $this->getTimestampForDriver();
            $statement->bindParam(":machine", $machine);
            $statement->bindParam(":entity_id", $entityId);
            $statement->bindParam(":state", $state);
            $statement->bindParam(":timestamp", $timestamp);
            $result = $statement->execute();
            if ($result === false) {
                throw new Exception($this->getErrorInfo($statement));
            }
        } catch (\Exception $e) {
            throw new Exception(sprintf('query for updating state failed: [%s]', $e->getMessage()), Exception::PERSISTENCE_LAYER_EXCEPTION);
        }
    }

    /**
     *{@inheritDoc}
     */
    #[\Override]
    public function addHistory(Identifier $identifier, string $state, $message = null, bool $isException = false): void
    {
        $connection = $this->getConnection();
        $prefix = $this->getPrefix();
        try {
            $query = 'INSERT INTO ' . $prefix . 'statemachine_history
                    (machine, entity_id, state, message, changetime, exception)
                        VALUES
                    (:machine, :entity_id, :state, :message, :timestamp, :exception)';
            $statement = $connection->prepare($query);
            $machine = $identifier->getMachine();
            $entityId = $identifier->getEntityId();
            $timestamp =  $this->getTimestampForDriver();
            $isException = $this->getBooleanForDriver($isException);
            $statement->bindParam(":machine", $machine);
            $statement->bindParam(":entity_id", $entityId);
            $statement->bindParam(":state", $state);
            if ($message) {
                if (is_string($message)) {
                    $info = new \stdClass();
                    $info->message = $message;
                    $message = $info;
                }
                //always json encode it so we can pass objects as the message and store it
                $message = json_encode($message);
            }
            $statement->bindParam(":message", $message);
            $statement->bindParam(":timestamp", $timestamp);
            $statement->bindParam(":exception", $isException);
            $result = $statement->execute();
            if ($result === false) {
                throw new Exception($this->getErrorInfo($statement));
            }
        } catch (\Exception $e) {
            throw new Exception(sprintf('query for updating state failed: [%s]', $e->getMessage()), Exception::PERSISTENCE_LAYER_EXCEPTION);
        }
    }

    /**
     * get all the ordered transition and state information for a specific
     * machine.
     * This method is public for testing purposes
     *
     * @return mixed[][] resultset from postgres
     * @throws Exception
     */
    public function getTransitions(string $machine): array
    {
        $connection = $this->getConnection();
        $prefix = $this->getPrefix();
        $query = 'SELECT st.machine, 
                        st.state_from AS state_from, st.state_to AS state_to, 
                        st.rule, st.command,
                        ss_to.type AS state_to_type, 
        				ss_to.exit_command as state_to_exit_command,
        				ss_to.entry_command as state_to_entry_command, 
        				ss.type AS state_from_type, 
        				ss.exit_command as state_from_exit_command,
        				ss.entry_command as state_from_entry_command,
                        st.priority, 
                        ss.description AS state_from_description,
                        ss_to.description AS state_to_description,
                        st.description AS transition_description,
        				st.event
                    FROM  ' . $prefix . 'statemachine_transitions AS st
                    LEFT JOIN
                        ' . $prefix . 'statemachine_states AS ss
                        ON (st.state_from = ss.state AND st.machine = ss.machine)
                    LEFT JOIN
                        ' . $prefix . 'statemachine_states AS ss_to
                        ON (st.state_to = ss_to.state AND st.machine = ss_to.machine)
                    WHERE
                        st.machine = :machine
                    ORDER BY 
                        st.state_from ASC, st.priority ASC, st.state_to ASC';
        try {
            $statement = $connection->prepare($query);
            $statement->bindParam(":machine", $machine);
            $result = $statement->execute();

            if ($result === false) {
                throw new Exception($this->getErrorInfo($statement));
            }
            $rows = $statement->fetchAll();
        } catch (\Exception $e) {
            throw new Exception($e->getMessage(), Exception::PERSISTENCE_LAYER_EXCEPTION, $e);
        }

        return $rows;
    }

    /**
     * gets all data for transitions.
     * This method is public for testing purposes
     *
     * @return Transition[]
     */
    public function getLoaderData(string $machine): array
    {
        $rows = $this->getTransitions($machine);

        $output = [];
        // array for local caching of states
        $states = [];

        foreach ($rows as $row) {
            $stateFrom = $row ['state_from'];
            $stateTo = $row ['state_to'];

            // create the 'from' state
            if (isset($states [$stateFrom])) {
                $from = $states [$stateFrom];
            } else {
                $from = new State($row ['state_from'], $row ['state_from_type'], $row ['state_from_entry_command'], $row ['state_from_exit_command']);
                $from->setDescription($row ['state_from_description']);
            }
            // cache the 'from' state for the next iterations
            $states [$from->getName()] = $from;

            // create the 'to' state
            if (isset($states [$stateTo])) {
                $to = $states [$stateTo];
            } else {
                $to = new State($row ['state_to'], $row ['state_to_type'], $row ['state_to_entry_command'], $row ['state_to_exit_command']);
                $to->setDescription($row ['state_to_description']);
            }
            // cache to 'to' state for the next iterations
            $states [$to->getName()] = $to;

            // build the transition
            $transition = new Transition($from, $to, $row ['event'], $row ['rule'], $row ['command']);
            $transition->setDescription($row ['transition_description']);

            $output [] = $transition;
        }

        return $output;
    }

    public function __destruct()
    {
        $this->connection = null;
    }
}
