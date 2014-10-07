<?php
namespace izzum\statemachine\persistence;
use izzum\statemachine\loader\Loader;
use izzum\statemachine\StateMachine;
use izzum\statemachine\Context;
use izzum\statemachine\loader\LoaderArray;
use izzum\statemachine\loader\LoaderData;
use izzum\statemachine\Exception;
/**
 * A persistence adapter/loader specifically for a postgresql backend as defined in the
 * file /assets/sql/postgresql.sql
 * 
 * TRICKY: this Adapter does double duty as a Loader since they both use the 
 * same backend. You could use two seperate classes for this, but since they
 * both use the same backend you can implement both in one class.
 * Loader::load(), Adapter::getEntityIds(), Adapter::processGetState()
 * Adapter::processSetState(), Adapter::add()
 * 
 * This is not a highly optimized adapter, but serves as something you can use 
 * out of the box. If you need a postgres adapter more specialized to your 
 * needs/framework/system you can easily write one yourself.
 * 
 * A more optimized loader would/could:
 * - reuse connections
 * - create locally cached results
 * - be able to cater to more specific configuration needs
 * - etc.
 * 
 * dependencies: the postgres module for php (sudo apt-get install php5-pgsql)
 * 
 * 
 * @link http://php.net/manual/en/function.pg-connect.php
 *
 * @author rolf
 */
class Postgres extends Adapter implements  Loader {
    
    /**
     * the pg connection string
     * @var string
     */
    private $pg_connection;
    
    /**
     *
     * @var string
     */
    private $schema;
    
    /**
     * 
     * @param string $pg_connection a postgresql connection string as specified
     *      on php.net
     * @param string an optional schema prefix
     * @link http://php.net/manual/en/function.pg-connect.php
     */
    public function __construct($pg_connection = 'host=localhost port=5432 dbname=izzum', $schema = null) {
        $this->pg_connection = $pg_connection;
        $this->schema = $schema;
    }
    
    /**
     * get the connection to a postgres database.
     * The connection retrieved will be reused if one already exists.
     * @return resource a connection to a postgres database
     * @throws Exception
     */
    protected function getConnection()
    {
        if(!function_exists('pg_connect')) {
            throw new Exception('postgres module not available in your php setup', 
                    Exception::PERSISTENCE_FAILED_TO_CONNECT);
        }
        
        try {
            $connection = pg_connect($this->pg_connection);
            //optionally set a different schema than 'public'
            if($this->schema) {
                pg_query($connection, 'SET search_path TO ' . $this->schema);
            }
            return $connection;
        } catch (\Exception $e) {
            throw new Exception(
                    sprintf("error connecting to postgres backend[%s]", 
                            $this->pg_connection), 
                    Exception::PERSISTENCE_FAILED_TO_CONNECT);
        }
    }
    
    /**
     * implementation of the hook in the Adapter::getState() template method
     * @param Context $context
     * @param string $state
     */
    protected function processGetState(Context $context) {
        $connection = $this->getConnection();
        try {
            $query = 'SELECT state FROM statemachine_entities WHERE machine = $1 AND entity_id = $2';
            $result = pg_query_params($connection, $query, array($context->getMachine(), $context->getEntityId()));
            $row = pg_fetch_object($result);
            
        } catch (\Exception $e) {
            $error = pg_last_error($connection);
            throw new Exception(sprintf('query for getting current state failed: [%s]', 
                    $error), 
                    Exception::PERSISTENCE_LAYER_EXCEPTION);
        } 
        if($row === false) {
             throw new Exception(sprintf('no state found for [%s]. Did you add it to the persistence layer?', 
                    $context->getId(true)), 
                    Exception::PERSISTENCE_LAYER_EXCEPTION);   
        }
        return $row->state;
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
            $query = 'SELECT entity_id FROM statemachine_entities WHERE machine = $1 AND entity_id = $2';
            $result = pg_query_params($connection, $query, array($context->getMachine(), $context->getEntityId()));
            $row = pg_fetch_object($result);
            return $row!== false && $row->entity_id == $context->getEntityId();
        } catch (\Exception $e) {
            $error = pg_last_error($connection);
            throw new Exception(sprintf('query for getting persistence info failed: [%s]', 
                    $error), 
                    Exception::PERSISTENCE_LAYER_EXCEPTION);
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
                (machine, entity_id,state, changetime)
                    VALUES
                ($1, $2, $3, now())';
            $result = pg_query_params($connection, $query, 
                    array($context->getMachine(), $context->getEntityId(), $state));
            return $result !== false;
        } catch (\Exception $e) {
            $error = pg_last_error($connection);
            throw new Exception(sprintf('query for inserting state failed: [%s]', 
                    $error), 
                    Exception::PERSISTENCE_LAYER_EXCEPTION);
        } 
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
            $query = 'UPDATE statemachine_entities SET state = $3, changetime = now()
                WHERE entity_id = $2 AND machine = $1';
            $result = pg_query_params($connection, $query, 
                    array($context->getMachine(), $context->getEntityId(), $state));
        } catch (\Exception $e) {
            $error = pg_last_error($connection);
            throw new Exception(sprintf('query for updating state failed: [%s]', 
                    $error), 
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
                    (machine, entity_id, state, changetime, message)
                        VALUES
                    ($1, $2, $3, now(), $4)';
            $params = array($context->getMachine(), $context->getEntityId(), $state, $message);
            $result = pg_query_params($connection, $query, $params);
        } catch (\Exception $e) {
            $error = pg_last_error($connection);
            throw new Exception(sprintf('query for updating state failed: [%s]', 
                    $error), 
                    Exception::PERSISTENCE_LAYER_EXCEPTION);
        } 
    }
    
    
       /**
     * Stores a failed transition in the storage facility.
     * @param Context $context
     * @param Exception $e
     * @param string $transition_name
     */
    public function setFailedTransition(Context $context, Exception $e, $transition_name)
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
                se.machine = ss.machine) WHERE se.machine = $1';
        $output = array();
        try {
            if($state != null) {
                $query .= ' AND se.state = $2';
                $result = pg_query_params($connection, $query, array($machine, $state));
            } else {
                $result = pg_query_params($connection, $query, array($machine));
            }
            $rows = pg_fetch_all($result);
            $error = pg_last_error($connection);
            if($rows === false && $error != '') {
                throw new Exception(sprintf('query for getting ids failed: [%s]', 
                        $error), 
                        Exception::PERSISTENCE_LAYER_EXCEPTION);
            }
            if(is_array($rows )) {
                foreach($rows as $row) {
                    $output[] = $row['entity_id'];
                }   
            }   
        } catch (\Exception $e) {
            throw new Exception($e->getMessage(), Exception::PERSISTENCE_LAYER_EXCEPTION, $e);
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
        //this public method will only work on an already instantiated statemachine.
        //Since an instantiated statemachine has access to it's name, we use that
        //name here to get the correct data.
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
                        st.machine = $1
                    ORDER BY st.state_from ASC, st.priority ASC";
        try {
            $result = pg_query_params($connection, $query, array($machine));
            $rows = pg_fetch_all($result);
            if($rows === false) {
                $error = pg_last_error($connection);
                throw new Exception(sprintf('query failed: [%s]', $error), Exception::PERSISTENCE_LAYER_EXCEPTION, $e);
            }
        } catch (\Exception $e) {
            throw new Exception($e->getMessage(), Exception::PERSISTENCE_LAYER_EXCEPTION, $e);
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
        try {
            if(function_exists('pg_close')) {
                pg_close($this->getConnection());
            }
        } catch(\Exception $e) {
            //fail silenty to prevent race conditions
        }
    }

}
