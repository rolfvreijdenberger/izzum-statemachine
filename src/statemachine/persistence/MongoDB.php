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
 * @link http://blog.scottlogic.com/2014/08/04/mongodb-vs-couchdb.html
 * @link https://en.wikipedia.org/wiki/MongoDB
 */
class MongoDB extends Adapter implements Loader {
    
    /**
     * constructor
     * @param string $dns the data source name. mongodb://[username:password@]host1[:port1][,host2[:port2:],...]/db
     * @param array $options server options
     * @param array $driver_options driver options
     * @link https://php.net/manual/en/mongoclient.construct.php
     */
    public function __construct($dns = 'mongodb://localhost:27017/izzum', $options = array("connect" => true), $driver_options = array()) {
        
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
}
