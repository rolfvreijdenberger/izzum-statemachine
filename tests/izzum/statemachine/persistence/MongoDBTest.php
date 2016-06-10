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
 * @group mongodb_less_than_php7
 * @group not-on-production
 * @author rolf
 *
 */
class MongoDBTest extends \PHPUnit_Framework_TestCase {

    /**
     * @test
     */
    public function shouldBeAbleToStoreAndRetrieveData()
    {

        $adapter = new MongoDB("mongodb://localhost:27017");

        //fixture
        $adapter->getClient()->izzum->states->drop();
        $adapter->getClient()->izzum->configuration->drop();
        $adapter->getClient()->izzum->history->drop();
        $configuration = file_get_contents(__DIR__ .'/../loader/fixture-example.json');
        //via the mongo shell, you could directly enter the json.
        //via php, we first need to decode the json to input it via the php mongo driver as an array
        $configuration = json_decode($configuration, true);
        //var_dump( $configuration);
        $adapter->getClient()->izzum->configuration->insert($configuration);
        //end fixture

        $machine = new StateMachine(new Context(new Identifier('mongo', 'test-machine'), null, $adapter));
        $adapter->load($machine);
        $machine->add("adding for " . __FUNCTION__);
        $machine->runToCompletion("testing 213");
        $this->assertEquals(array("mongo"), $adapter->getEntityIds('test-machine'));
        $this->assertEquals(array("mongo"), $adapter->getEntityIds('test-machine', 'done'));
        $this->assertEquals(array(), $adapter->getEntityIds('test-machine', 'a'));
        $this->assertEquals(array(), $adapter->getEntityIds('test-machine', 'b'));
        $this->assertEquals(array(), $adapter->getEntityIds('test-machine', 'c'));

        //another
        $identifier = new Identifier('another-mongo', 'test-machine');
        $machine = new StateMachine(new Context($identifier, null, $adapter));
        $adapter->load($machine);
        $this->assertFalse($adapter->isPersisted($identifier));
        $this->assertTrue($machine->add("adding for " . __FUNCTION__));
        $this->assertFalse($machine->add("adding for " . __FUNCTION__), 'already added');
        $this->assertTrue($adapter->isPersisted($identifier));
        $machine->runToCompletion("testing 213");

        //some other stuff
        $machine = new StateMachine(new Context(new Identifier('foobar', 'non-used-machine'), null, $adapter));
        $adapter->load($machine);
        $machine->add("adding for " . __FUNCTION__);
        $machine->runToCompletion("testing 213");

        $index = array("entity_id" => 1, "machine" => 1);
        $options = array ("background" => true);
        $adapter->getClient()->izzum->history->createIndex($index, $options);
        //getting the state for an entity_id/machine should be fast
        //db.states.createIndex({entity_id: 1, machine: 1}, {background: true});
        $index = array("entity_id" => 1, "machine" => 1);
        $options = array ("background" => true);
        $adapter->getClient()->izzum->states->createIndex($index, $options);


        //recreate the existing statemachine
        $identifier = new Identifier('another-mongo', 'test-machine');
        $machine = new StateMachine(new Context($identifier, null, $adapter));
        $adapter->load($machine);
        $this->assertTrue($adapter->isPersisted($identifier));
        $this->assertFalse($machine->add(), 'already added');
        $this->assertEquals('done', $machine->getCurrentState()->getName(), 'state persisted');
        $this->assertEquals(0, $machine->runToCompletion(), 'alread in a final state, no transitions');

        $ids = $adapter->getEntityIds('test-machine');
        $this->assertEquals(2, count($ids));

        $ids = $adapter->getEntityIds('non-used-machine');
        $this->assertEquals(1, count($ids));

        $this->assertNotNull($adapter->toString());
        $this->assertNotNull($adapter . '');
        $this->assertRegexp('|mongodb://localhost:27017|', $adapter->toString());
        $this->assertRegexp('|mongodb://localhost:27017|', $adapter . '');

    }

    /**
     * @test
     */
    public function shouldDoMoreTests()
    {
        $this->markTestIncomplete('need more tests for actual database contents');
    }
}