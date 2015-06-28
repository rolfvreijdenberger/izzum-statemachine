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
use izzum\statemachine\State;
/**
 * MongoDB (from humongous) is a cross-platform document-oriented database.
 * MongoDB is an open-source document database that provides high performance, high availability, and automatic scaling. 
 * MongoDB obviates the need for an Object Relational Mapping (ORM) to facilitate development.
 * 
 * start macosx: mongod --config /usr/local/etc/mongod.conf
 * mongo shell: mongo
 * 
 * 
 * @link https://www.mongodb.org/
 * @link https://www.mongodb.org/downloads for the downloads for your system
 * @link http://docs.mongodb.org/ecosystem/drivers/php/ for the php driver you need to install
 * @link https://github.com/mongodb/mongo-php-driver for the source code of the php driver
 * @link https://php.net/mongo the official documentation for the driver on php.net
 * @link http://docs.mongodb.org/manual/reference/program/mongo/#bin.mongo the mongo client
 * @link https://en.wikipedia.org/wiki/MongoDB
 * 
 */
class MongoDB extends Adapter implements Loader {
    
    /**
     * the data source name for a mongo connection
     * @var string
     */
    protected $dns;
    /**
     * connection options
     * @var array
     */
    protected $options;
    /**
     * the php driver specific options
     * @var array
     */
    protected $driver_options;
    
    /**
     * the (settable) mongo client
     * @var \MongoClient
     */
    private $client;
    
    /**
     * constructor. a connection to mongodb via the mongoclient will be lazily created. 
     * an existing mongoclient can also be set on this class (reuse a client accross your application)
     * @param string $dns the data source name. mongodb://[username:password@]host1[:port1][,host2[:port2:],...]
     * @param array $options server options (usable for authentication, replicasets etc)
     * @param array $driver_options php specifif driver options
     * @link https://php.net/manual/en/mongoclient.construct.php
     */
    public function __construct($dns = 'mongodb://localhost:27017', $options = array("connect" => true), $driver_options = array()) {
        $this->dns = $dns;
        $this->options = $options;
        $this->driver_options = $driver_options;
    }
    
    /**
     * A hook to use in a subclass.
     * you can do you initial setup here if you like.
     */
    protected function onConnect() {
        //override if necessary
    }

    
    /**
     * Gets a lazy loaded \MongoClient instance.
     *
     * @throws Exception
     * @return \MongoClient
     */
    public function getClient() 
    {
        if(!isset($this->client)) {
            $client = new \MongoClient($this->dns, $this->options, $this->driver_options);
            $this->client = $client;
            $this->onConnect();
        }
        return $this->client;
    }
    
    /**
     * since we do not want to burden a client of this code with the responsiblity of
     * creating indexes, we take a statistical approach to check if we need to 
     * create an index in the background. This will only be done once.
     * @param number $check_index_once_in check for index creation. on average, every <x> times
     *      the index should be created if it is not there already
     */
    protected function checkAndCreateIndexesIfNecessary($check_index_once_in = 1000)
    {
        //statistical approach to building the index on average once every x times
        $check_index_once_in = min(1000, $check_index_once_in);
        if(rand(1, $check_index_once_in) % $check_index_once_in === 0) {
            $this->createIndexes();
        }
    }
    
    /**
     * create indexes in the background for the collections used. this will only be done by 
     * mongo if they do not exist already.
     */
    protected function createIndexes()
    {
        //http://docs.mongodb.org/manual/tutorial/create-a-compound-index/
        
        //querying the history could use different indexes, depending on what you want to know
        //db.history.createIndex({entity_id: 1, machine: 1}, {background: true});
        $index = array("entity_id" => 1, "machine" => 1);
        $options = array ("background" => true);
        $this->getClient()->izzum->history->createIndex($index, $options);
        //getting the state for an entity_id/machine should be fast
        //db.states.createIndex({entity_id: 1, machine: 1}, {background: true});
        $index = array("entity_id" => 1, "machine" => 1);
        $options = array ("background" => true);
        $this->getClient()->izzum->states->createIndex($index, $options);
        
        //show the existing indexes
        //db.system.indexes.find()
    }
    
    /**
     * sets a mongoclient. this can be useful if your application already has 
     * a mongoclient instantiated and you want to reuse it.
     * @param \MongoClient $client
     */
    public function setClient(\MongoClient $client)
    {
        $this->client = $client;
    }
    
    
    /**
     * {@inheritDoc}
     */
    protected function addHistory(Identifier $identifier, $state, $message = null, $is_exception = false)
    {
        //find in history from mongo shell: db.history.find({"machine" : "test-machine", "state": "done"},{entity_id: 1, state: 1, datetime: 1})
        try {
            $data = new \stdClass();
            $data->entity_id = $identifier->getEntityId();
            $data->machine = $identifier->getMachine();
            $data->state = $state;
            if($message) {
                if(is_string($message)) {
                    $info = new \stdClass();
                    $info->message = $message;
                    $message = $info;
                }
            }
            $data->message = $message;
            $timestamp = time();
            $data->timestamp = $timestamp;
            $data->datetime = date('Y-m-d H:i:s', $timestamp);//ISO_8601
            $data->is_exception = $is_exception;
            //insert into the 'history' collection
            $this->getClient()->izzum->history->insert($data);
        } catch (\Exception $e) {
            throw new Exception(sprintf('adding history failed: [%s]',
                    $e->getMessage()),
                    Exception::PERSISTENCE_LAYER_EXCEPTION);
        }
    }
    
    /**
     * {@inheritDoc}
     */
    protected function insertState(Identifier $identifier, $state, $message = null)
    {
        try {
            $data = new \stdClass();
            $data->timestamp = time();
            $data->state = $state;
            $data->entity_id = $identifier->getEntityId();
            $data->machine = $identifier->getMachine();
            //insert into the 'states' collection
            //https://php.net/manual/en/mongocollection.insert.php
            $this->getClient()->izzum->states->insert($data);
        } catch (\Exception $e) {
            throw new Exception(sprintf('query for inserting state failed: [%s]',
                    $e->getMessage()),
                    Exception::PERSISTENCE_LAYER_EXCEPTION);
        }
    }
    
    /**
     * {@inheritDoc}
     */
    protected function updateState(Identifier $identifier, $state, $message = null)
    {
        try {
            $client = $this->getClient();
            //find the state
            //https://php.net/manual/en/mongocollection.findone.php
            $query = array("machine" => $identifier->getMachine(), "entity_id" => $identifier->getEntityId());
            $data = $client->izzum->states->findOne($query);
            if($data) {
                //update the state and timestamp
                //$_id = $data['_id'];
                $data['timestamp'] = time();
                $data['state'] = $state;
                //save into the 'states' collection
                //https://php.net/manual/en/mongocollection.save.php
                $client->izzum->states->save($data);
            } else {
                throw new Exception(sprintf('no state found for [%s]. Did you "$machine->add()" it to the persistence layer?',
                        $identifier->getId(true)),
                        Exception::PERSISTENCE_LAYER_EXCEPTION);
            }
         } catch (\Exception $e) {
             throw new Exception(sprintf('updating state failed: [%s]',
                    $e->getMessage()),
                    Exception::PERSISTENCE_LAYER_EXCEPTION);
         }
    }
    
    /**
     * {@inheritDoc}
     */
     public function processGetState(Identifier $identifier) 
     {
         $state = null;
         try {
             //check if indexes exists every x times this method is called
             $this->checkAndCreateIndexesIfNecessary(500);
             
             //find the state
             //https://php.net/manual/en/mongocollection.findone.php
             $query = array("entity_id" => $identifier->getEntityId(), "machine" => $identifier->getMachine());
             $data = $this->getClient()->izzum->states->findOne($query);
             if($data) {
                $state = $data['state'];
             }
         } catch (\Exception $e) {
             throw new Exception(sprintf('getting current state failed: [%s]',
                     $e->getMessage()), Exception::PERSISTENCE_LAYER_EXCEPTION);
         }
         if(!$state) {
            throw new Exception(sprintf('no state found for [%s]. Did you "$machine->add()" it to the persistence layer?',
                    $identifier->getId(true)),
                    Exception::PERSISTENCE_LAYER_EXCEPTION);
        }
        return $state;
     }
    
    /**
     * {@inheritDoc}
    */
    public function isPersisted(Identifier $identifier)
    {
        $is_persisted = false;
        try {
            //https://php.net/manual/en/mongocollection.findone.php
            $query = array("entity_id" => $identifier->getEntityId(), "machine" => $identifier->getMachine());
            $data = $this->getClient()->izzum->states->findOne($query);
            if($data) {
                $is_persisted = true;
            }
        } catch (\Exception $e) {
            throw new Exception(
                    sprintf('getting persistence info failed: [%s]',
                            $e->getMessage()), Exception::PERSISTENCE_LAYER_EXCEPTION);
        }
        return $is_persisted;
    }
    
    /**
     * {@inheritDoc}
    */
    public function getEntityIds($machine, $state = null) 
    {
        $output = array();
        try {
            $client = $this->getClient();
            $query = array("machine" => $machine);
            if($state) {
                $query["state"] = $state;
            }
            $projection = array("entity_id" => 1);
            //find all in the 'states' collection
            $found = $client->izzum->states->find($query, $projection);
            foreach($found as $data) {
                $output[] = $data['entity_id'];
            }
        } catch (\Exception $e) {
            throw new Exception($e->getMessage(),
                    Exception::PERSISTENCE_LAYER_EXCEPTION, $e);
        }
        return $output;
    }
    
    
    
    /**
     * {@inheritDoc}
     * Load the statemachine via a document in a mongodb collection.
     * 
     * The document, originally loaded as a json string (see JSON::getJSONSchema)
     * is stored at the mongodb collection 'configuration' by default.
     * multiple machine definitions can be provided in a single document, or in multiple documents in the collection.
     * The first document containing the 'machines.name' key with the value matching 
     * the name of the $statemachine is used.
     *
     * You could use the ReaderWriterDelegator to use another source to load the configuration from.
     */
    public function load(StateMachine $statemachine) {
        //use the JSON loader to load the configuration (see the json schema we expect in JSON::getJSONSchema)
        //mongodb does not store JSON but documents (converts the json structure) and the mongodb 
        //php library returns these documents as php objects. 
        //therefore, we json_encode it again, so it can be json_decoded in the JSON class :-(
        //alternatively, we could write a PHP Loader, but the assumption is that the speed gain is not worth it.
        $loader = new JSON(
                    json_encode(
                        $this->getClient()->izzum->configuration->findOne(
                                array("machines.name" => $statemachine->getContext()->getMachine())
                                )
                            )
                         );
        $count = $loader->load($statemachine);
        return $count;
    }
    
    public function toString()
    {
        return get_class($this) . ' ' . $this->dns;;
    }
    
    public function __toString()
    {
        return $this->toString();
    }
}
