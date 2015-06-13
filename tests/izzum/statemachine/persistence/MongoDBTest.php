<?php
namespace izzum\statemachine\persistence;
use izzum\statemachine\StateMachine;
use izzum\statemachine\Context;
use izzum\statemachine\Exception;
use izzum\statemachine\Identifier;
use izzum\statemachine\State;
use izzum\statemachine\Transition;
/**
 * this test makes use of an active mongod server instance on the localhost listening
 * on port 27017 (the defaults) and database izzum (which will be flused on each test).
 * 
 * this test used the mongodb module for php. therefore it can only be run on 
 * a system that has been setup properly with that module and with an instance of 
 * the mongod server running
 * 
 * @group persistence
 * @group loader
 * @group mongodb
 * @group not-on-production
 * @author rolf
 *
 */
class MongoDBTest extends \PHPUnit_Framework_TestCase {
    
    /**
     * @test
     */
    public function shouldBeAbleToLoadConfigurationAndTestSomeGettersAndSetters()
    {

        // connect
        $m = new \MongoClient();
        $db = $m->izzum;
        // select a collection (analogous to a relational database's table)
        $collection = $db->cartoons;
        // add a record
        $document = array( "title" => "Calvin and Hobbes", "author" => "Bill Watterson" );
        $collection->insert($document);
        $cursor = $m->izzum->configuration->find();
        //var_dump( json_encode($m->izzum->configuration->findOne()));
        //echo "JO";
        foreach($cursor as $document) {
            //var_dump($document);
        }
    }
    
    /**
     * @test
     */
    public function shouldBeAbleToStoreAndRetrieveData()
    {
        
        
        
    }
}