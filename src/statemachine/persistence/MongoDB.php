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
 * TODO: think about indexes: how do we set them initially http://docs.mongodb.org/manual/core/index-single/
 * maybe use random check every 100 instantiations to see if there is a 'created index' flag?
 */
class MongoDB extends Adapter implements Loader {
    
    /**
     * constructor
     * @param string $dns the data source name. mongodb://[username:password@]host1[:port1][,host2[:port2:],...]/db
     * @param array $options server options
     * @param array $driver_options driver options
     * @link https://php.net/manual/en/mongoclient.construct.php
     */
    public function __construct($dns = 'mongodb://localhost:27017', $options = array("connect" => true), $driver_options = array()) {
        $this->dns = $dns;
        $this->options = $options;
        $this->driver_options = $driver_options;
    }

    
    /**
     * Gets a lazy loaded \MongoClient instance
     *
     * @throws Exception
     * @return \MongoClient
     */
    public function getClient() 
    {
        if(!isset($this->client)) {
            $client = new \MongoClient($this->dns, $this->options, $this->driver_options);
            $this->client = $client;
        }
        return $this->client;
    }
    
    public function setClient(\MongoClient $client)
    {
        $this->client = $client;
    }
    
    
    /**
     * Load the statemachine via a document in a mongodb collection.
     * 
     * The document, originally loaded as a json string (see JSON::getJSONSchema)
     * is stored at the mongodb collection 'configuration' by default.
     * multiple machine definitions can be provided in a single document, or in multiple documents in the collection.
     * The first document containing the 'machines.machine' key with the value matching 
     * the name of the $statemachine is used.
     *
     * You could use the ReaderWriterDelegator to use another source to load the configuration from.
     *
     * @param StateMachine $statemachine
     */
    public function load(StateMachine $statemachine) {
        //use the JSON loader to load the configuration (see the json schema we expect in JSON::getJSONSchema)
        //mongodb does not store JSON but documents and the mongodb library returns these documents as
        //php objects. therefore, we json_encode it again, so it can be json_decoded in the JSON class :-(
        //alternatively, we could write a PHP Loader, but the assumption is that the speed gain is not worth it.
        $loader = new JSON(
                    json_encode(
                        $this->getClient()->izzum->configuration->findOne(
                                array("machines.machine" => $statemachine->getContext()->getMachine())
                                )
                            )
                         );
        $count = $loader->load($statemachine);
        return $count;
    }
}
