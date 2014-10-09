<?php
namespace izzum\statemachine\persistence;
use izzum\statemachine\loader\Loader;
use izzum\statemachine\StateMachine;
use izzum\statemachine\Context;
use izzum\statemachine\loader\LoaderArray;
use izzum\statemachine\loader\LoaderData;
use izzum\statemachine\Exception;
/**
 * A persistence adapter/loader specifically for the PHP Data Objects (PDO) 
 * extension.
 * PDO use database drivers for different backend implementations (postgres, mysql, 
 * sqlite, MSSQL, oracle etc). 
 * By providing a DSN connection string, you can connect to different backends.
 * 
 * This specific adapter uses the schema as defined in /assets/sql/postgres.sql
 * or /assets/sql/sqlite.sql
 * but can be used on tables for a different database vendor as long as
 * the table names, fields and constraints are the same.
 * 
 * TRICKY: this Adapter does double duty as a Loader since both use the 
 * same backend. You could use two seperate classes for this, but you can 
 * implement both in one class.
 * Loader::load(), Adapter::getEntityIds(), Adapter::processGetState()
 * Adapter::processSetState(), Adapter::add()
 * 
 * This is not a highly optimized adapter, but serves as something you can use 
 * out of the box. If you need an adapter more specialized to your 
 * needs/framework/system you can easily write one yourself.
 * 
 * @link http://php.net/manual/en/pdo.drivers.php
 * @link http://php.net/manual/en/book.pdo.php
 *
 * @author rolf
 */
class PDO extends Adapter implements  Loader {
    
    /**
     * the pdo connection string
     * @var string
     */
    private $dsn;
    
    /**
     * @var string
     */
    private $user;
     /**
     * @var string
     */
    private $password;
     /**
     * pdo options
     * @var array
     */
    private $options;
    
    /**
     * the locally cached connections
     * @var \PDO
     */
    private $connection;
    
    /**
     * 
     * @param string $dsn a PDO data source name
     *      example: 'pgsql:host=localhost;port=5432;dbname=izzum'
     * @param string $user optional, defaults to null
     * @param string $password optional, defaults to null
     * @param array $options optional, defaults to empty array
     * @link http://php.net/manual/en/pdo.connections.php
     */
    public function __construct($dsn, $user = null, $password = null, $options = array()) {
        $this->dsn = $dsn;
        $this->user = $user;
        $this->password= $password;
        $this->options = $options;
    }
    
    /**
     * get the connection to a database via the PDO adapter.
     * The connection retrieved will be reused if one already exists.
     * @return \PDO
     * @throws Exception
     */
    protected function getConnection() {
        try {
            if($this->connection === null) {
                $this->connection = new \PDO($this->dsn, $this->user, $this->password, $this->options);
                $this->setupConnection($this->connection);
            }
            return $this->connection;
        } catch (\Exception $e) {
            throw new Exception(
                    sprintf("error creating PDO [%s], message: [%s]", 
                            $this->dsn, $e->getMessage()), 
                    Exception::PERSISTENCE_FAILED_TO_CONNECT);
        }
    }
    
    protected function setupConnection(\PDO $connection) {
        /**
         * hook, override to:
         * - set schema on postgresql
         * - set PRAGMA on sqlite
         * - etc.. whatever is the need to do a setup on, the first time 
         * you create a connection
         */
        
    }
    
    /**
     * implementation of the hook in the Adapter::getState() template method
     * @param Context $context
     * @param string $state
     */
    protected function processGetState(Context $context) {
        $connection = $this->getConnection();
        try {
            $query = 'SELECT state FROM statemachine_entities WHERE machine = '
                    . ':machine AND entity_id = :entity_id';
            $statement = $connection->prepare($query);
            $statement->bindParam(":machine", $context->getMachine());
            $statement->bindParam(":entity_id", $context->getEntityId());
            $result = $statement->execute();
            if($result === false) {
                throw new Exception($this->getErrorInfo($statement));
            }
        } catch (\Exception $e) {
            throw new Exception(sprintf('query for getting current state failed: [%s]', 
                    $e->getMessage()), Exception::PERSISTENCE_LAYER_EXCEPTION);
        } 
        $row = $statement->fetch();
        if($row === false) {
             throw new Exception(sprintf('no state found for [%s]. '
                     . 'Did you add it to the persistence layer?', 
                    $context->getId(true)), 
                    Exception::PERSISTENCE_LAYER_EXCEPTION);   
        }
        return $row['state'];
    }

    /**
     * implementation of the hook in the Adapter::setState() template method
     * @param Context $context
     * @param string $state
     * @return boolean true if not already present, false if stored before
     */
    protected function processSetState(Context $context, $state) {
        if($this->isPersisted($context)) {
            $this->updateState($context, $state);
            return false;
        } else {
            $this->insertState($context, $state);      
            return true;
        }
    }

    /**
     * adds Context info to the persistance layer.
     * Thereby marking the time when the object was created.
     * @param Context $context
     * @return boolean
     */
    public function add(Context $context) {
        if($this->isPersisted($context)) {
            return false;
        } 
        $this->insertState($context, $this->getInitialState($context));
        return true;
    }
    
    /**
     * is the context already persisted?
     * @param Context $context
     * @return boolean
     * @throws Exception
     */
    public function isPersisted(Context $context) {
        $connection = $this->getConnection();
        try {
            $query = 'SELECT entity_id FROM statemachine_entities WHERE '
                    . 'machine = :machine AND entity_id = :entity_id';
            $statement = $connection->prepare($query);
            $statement->bindParam(":machine", $context->getMachine());
            $statement->bindParam(":entity_id", $context->getEntityId());
            $result = $statement->execute();
            if($result === false) {
                throw new Exception($this->getErrorInfo($statement));
            }
 
            $row = $statement->fetch();
            
            if($row === false) {
                return false;
            }
            return ($row['entity_id'] == $context->getEntityId());
        } catch (\Exception $e) {
            throw new Exception(
                    sprintf('query for getting persistence info failed: [%s]', 
                    $e->getMessage()), Exception::PERSISTENCE_LAYER_EXCEPTION);
        }          
    }
    
    
    /**
     * insert state for context into persistance layer.
     * This method is public for testing purposes
     * @param Context $context
     * @param string $state
     */
    public function insertState(Context $context, $state)
    {

        //add a history record
        $this->addHistory($context, $state);
        
        $connection = $this->getConnection();
        try {
            $query = 'INSERT INTO statemachine_entities
                (machine, entity_id, state, changetime)
                    VALUES
                (:machine, :entity_id, :state, :timestamp)';
            $statement = $connection->prepare($query);
            $statement->bindParam(":machine", $context->getMachine());
            $statement->bindParam(":entity_id", $context->getEntityId());
            $statement->bindParam(":state", $state);
            $statement->bindParam(":timestamp", $this->getTimestampForDriver());
            $result = $statement->execute();
            if($result === false) {
              throw new Exception($this->getErrorInfo($statement));  
            }
        } catch (\Exception $e) {
            throw new Exception(sprintf('query for inserting state failed: [%s]', 
                    $e->getMessage()), 
                    Exception::PERSISTENCE_LAYER_EXCEPTION);
        } 
    }
    
    protected function getErrorInfo(\PDOStatement $statement) {
        $info = $statement->errorInfo();
        $output = sprintf("%s - message: '%s'", $info[0], $info[2]);
        return $output;
    } 
    
    protected function getTimestampForDriver()
    {
        //yuk, seems postgres and sqlite need some different input.
        //maybe other drivers too. so therefore this hook method.
        if(strstr($this->dsn, 'sqlite:')) {
            return time();//"CURRENT_TIMESTAMP";//"DateTime('now')";
        }
        //might have to be overriden for certain drivers.
        return 'now';//now, CURRENT_TIMESTAMP
        
    }
    
    /**
     * update state for context into persistance layer
     * This method is public for testing purposes
     * @param Context $context
     * @param string $state
     * @throws Exception
     */
    public function updateState(Context $context, $state)
    {
        //add a history record
        $this->addHistory($context, $state);
        
        $connection = $this->getConnection();
        try {
            $query = 'UPDATE statemachine_entities SET state = :state, 
                changetime = :timestamp WHERE entity_id = :entity_id 
                AND machine = :machine';
            $statement = $connection->prepare($query);
            $statement->bindParam(":machine", $context->getMachine());
            $statement->bindParam(":entity_id", $context->getEntityId());
            $statement->bindParam(":state", $state);
            $statement->bindParam(":timestamp", $this->getTimestampForDriver());
            $result = $statement->execute();
            if($result === false) {
              throw new Exception($this->getErrorInfo($statement));  
            }
        } catch (\Exception $e) {
            throw new Exception(sprintf('query for updating state failed: [%s]', 
                    $e->getMessage()), 
                    Exception::PERSISTENCE_LAYER_EXCEPTION);
        } 
    }
    
     /**
      * Adds a history record for a transition
      * @param Context $context
      * @param string $state
      * @param string $message an optional message. which would imply an error.
      * @throws Exception
      */
    public function addHistory(Context $context, $state, $message = null)
    {
        $connection = $this->getConnection();
        try {
            $query = 'INSERT INTO statemachine_history
                    (machine, entity_id, state, message, changetime)
                        VALUES
                    (:machine, :entity_id, :state, :message, :timestamp)';
            $statement = $connection->prepare($query);
            $statement->bindParam(":machine", $context->getMachine());
            $statement->bindParam(":entity_id", $context->getEntityId());
            $statement->bindParam(":state", $state);
            $statement->bindParam(":message", $message);
            $statement->bindParam(":timestamp", $this->getTimestampForDriver());
            $result = $statement->execute();
            if($result === false) {
              throw new Exception($this->getErrorInfo($statement));  
            }
        } catch (\Exception $e) {
            throw new Exception(sprintf('query for updating state failed: [%s]', 
                    $e->getMessage()), 
                    Exception::PERSISTENCE_LAYER_EXCEPTION);
        } 
    }
    
    
       /**
     * Stores a failed transition in the storage facility.
     * @param Context $context
     * @param Exception $e
     * @param string $transition_name
     */
    public function setFailedTransition(Context $context, Exception $e, 
            $transition_name)
    {
        //check if it is persisted, otherwise we cannot get the current state
        if($this->isPersisted($context)) {
            $message = new \stdClass();
            $message->code = $e->getCode();
            $message->transition = $transition_name;
            $message->message = $e->getMessage();          
            $message->file = $e->getFile();
            $message->line = $e->getLine();
            //convert to json for storage
            $json = json_encode($message);
            $state = $context->getState();
            $this->addHistory($context, $state, $json);
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
        $query = 'SELECT se.entity_id FROM statemachine_entities AS se
                JOIN statemachine_states AS ss ON (se.state = ss.state AND 
                se.machine = ss.machine) WHERE se.machine = :machine';
        $output = array();
        try {
            if($state != null) {
                $query .= ' AND se.state = :state';
            }
            $statement = $connection->prepare($query);
            $statement->bindParam(":machine", $machine);
            if($state != null) {
                $statement->bindParam(":state", $state);
            }
            

            $result = $statement->execute();
            if($result === false) {
              throw new Exception($this->getErrorInfo($statement));  
            }

            $rows = $statement->fetchAll(); 
            if($rows === false) {
                throw new Exception("failed getting rows: " . 
                        $this->getErrorInfo($statement));
            }

            foreach($rows as $row) {
                $output[] = $row['entity_id'];
            }   
 
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
        $data = $this->getLoaderData($statemachine->getMachine());
        //delegate to LoaderArray
        $loader = new LoaderArray($data);
        $loader->load($statemachine);
    }
    
    /**
     * get all the ordered transition information for a specific machine.
     * This method is made public for testing purposes
     * @param string $machine
     * @return [][] resultset from postgres
     * @throws Exception
     */
    public function getTransitions($machine)
    {
        $connection = $this->getConnection();
        $query = "SELECT st.machine, 
                        st.state_from AS state_from, st.state_to AS state_to, 
                        st.rule, st.command,
                        ss_to.type AS state_type_to,ss.type AS state_type_from,
                        st.priority, 
                        ss.description AS description_state_from,
                        ss_to.description AS description_state_to,
                        st.description AS description_transition
                    FROM  statemachine_transitions AS st
                    LEFT JOIN
                        statemachine_states AS ss
                        ON (st.state_from = ss.state AND st.machine = ss.machine)
                    LEFT JOIN
                        statemachine_states AS ss_to
                        ON (st.state_to = ss_to.state AND st.machine = ss_to.machine)
                    WHERE
                        st.machine = :machine
                    ORDER BY st.state_from ASC, st.priority ASC";
        try {
            $statement = $connection->prepare($query);
            $statement->bindParam(":machine", $machine);
            $result = $statement->execute();
            
            if($result === false) {
                throw new Exception($this->getErrorInfo($statement));
            }
            $rows = $statement->fetchAll();
        } catch (\Exception $e) {
            throw new Exception($e->getMessage(), 
                    Exception::PERSISTENCE_LAYER_EXCEPTION, $e);
        }
                
        return $rows;
    }
    
    /**
     * gets all data for transitions.
     * This method is made public for testing purposes
     * @param string $machine the machine name
     * @return LoaderData[]
     */
    public function getLoaderData($machine){
        $rows = $this->getTransitions($machine);
        $output = array();
        foreach($rows as $row) {
            $output[] = LoaderData::get($row['state_from'], $row['state_to'], 
                            $row['rule'], $row['command'], 
                            $row['state_type_from'], $row['state_type_to']);
        }
        return $output;
    }
    
    /**
     * do some cleanup
     */
    public function __destruct() {
        $this->connection = null;
    }
}