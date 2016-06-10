<?php
namespace izzum\statemachine\persistence;
use izzum\statemachine\StateMachine;
use izzum\statemachine\Context;
use izzum\statemachine\Exception;
use izzum\statemachine\Identifier;
use izzum\statemachine\State;
use izzum\statemachine\Transition;
/**
 * this test makes use of an active redis instance on the localhost listening
 * on port 6379 (the defaults) and database 15 (which will be flused on each test)
 *
 * this test used the redis module for php. therefore it can only be run on
 * a system that has been setup properly with that module and with an instance of
 * the redis server running
 *
 * @group persistence
 * @group loader
 * @group redis
 * @group not-on-production
 * @author rolf
 *
 */
class RedisTest extends \PHPUnit_Framework_TestCase {

    /**
     * @test
     */
    public function shouldBeAbleToLoadConfigurationAndTestSomeGettersAndSetters()
    {
        $redis = new Redis();
        $redis->setDatabase(15);
        //clear the redis database for testing
        $redis->flushdb();
        $machine = new StateMachine(new Context(new Identifier(1, 'test-machine'), null, $redis));
        //create the loader
        //get the configuration from the json file
        $configuration = file_get_contents(__DIR__ .'/../loader/fixture-example.json');
        //set it. normally, this would be done by a seperate process that has already loaded the configuration
        $redis->set(Redis::KEY_CONFIGURATION, $configuration);
        //load the machine
        $count = $redis->load($machine);
        $this->assertEquals(4, $count, 'expect 4 transitions to be loaded');
        $this->assertCount(4, $machine->getTransitions(), 'there is a regex transition that adds 2 transitions (a-c and b-c)');
        $this->assertCount(4, $machine->getStates());
        $this->assertNotNull($redis->toString());
        $this->assertNotNull($redis . '');

        $redis->setConfigurationKey("bogus");
        $this->assertEquals("bogus", $redis->getConfigurationKey());
        $redis->setPrefix("foobar");
        $this->assertEquals("foobar", $redis->getPrefix());

    }

    /**
     * @test
     */
    public function shouldBeAbleToLoadConfigurationFromSpecificConfigurationKey()
    {
        $redis = new Redis();
        $redis->setDatabase(15);
        //clear the redis database for testing
        $redis->flushdb();
        $identifier = new Identifier(1, 'test-machine');
        $machine = new StateMachine(new Context($identifier, null, $redis));
        //create the loader
        //get the configuration from the json file
        $configuration = file_get_contents(__DIR__ .'/../loader/fixture-example.json');
        //set it. normally, this would be done by a seperate process that has already loaded the configuration
        $redis->set(sprintf(Redis::KEY_CONFIGURATION_SPECIFIC, $redis->getConfigurationKey(), 'test-machine'), $configuration);
        //load the machine
        $count = $redis->load($machine);
        $this->assertEquals(4, $count, 'expect 4 transitions to be loaded');
        $this->assertCount(4, $machine->getTransitions(), 'there is a regex transition that adds 2 transitions (a-c and b-c)');
        $this->assertCount(4, $machine->getStates());
        $this->assertNotNull($redis->toString());
        $this->assertNotNull($redis . '');
        $this->assertFalse($redis->isPersisted($identifier));
        $this->assertTrue($machine->add('add to the backend'));
        $this->assertFalse($machine->add('add to the backend'), 'already added');
        $this->assertEquals('a', $machine->getCurrentState());
        $this->assertTrue($redis->isPersisted($identifier));
        $this->assertTrue($machine->run('run from a to b'));
        $this->assertEquals('b', $machine->getCurrentState());
        $this->assertTrue($redis->isPersisted($identifier));
        $this->assertTrue($machine->run('some message here to store'));
        $this->assertEquals('done', $machine->getCurrentState());
        $ids = $redis->getEntityIds('test-machine');
        $this->assertEquals(1, count($ids));

        //destroy
        $redis = null;

    }

    /**
     * @test
     */
    public function shouldBeAbleToStoreAndRetrieveData()
    {
        $redis = new Redis();
        $redis->setDatabase(15);
        //clear the redis database for testing
        $redis->flushdb();
        $machine = new StateMachine(new Context(new Identifier('1', 'test-machine'), null, $redis));
        //create the loader
        //get the configuration from the json file
        $configuration = file_get_contents(__DIR__ .'/../loader/fixture-example.json');
        //set it. normally, this would be done by a seperate process that has already loaded the configuration
        $redis->set(Redis::KEY_CONFIGURATION, $configuration);
        //load the machine
        $count = $redis->load($machine);
        //add the machine to the backend system
        $this->assertTrue($machine->add('this is the first addition'));
        $this->assertFalse($machine->add(), 'returns false, already added');

        $this->assertTrue($machine->run('this is a test run message'), 'succesful transitions so it returns true');
        $this->assertEquals('b', $machine->getCurrentState());
        $this->assertContains('1', $redis->getEntityIds('test-machine'));
        $this->assertTrue($machine->hasEvent('goToC'));
        try {
            $machine->goToC();
            $this->fail('should not come here');
        }catch (Exception $e) {
            $this->assertEquals(Exception::RULE_APPLY_FAILURE, $e->getCode());
        }
        $this->assertEquals('b', $machine->getCurrentState());

        //create new instance of same machine
        $machine2 = new StateMachine(new Context(new Identifier('1', 'test-machine'), null, $redis));
        $this->assertNotSame($machine2, $machine);
        $redis->load($machine2);
        $this->assertEquals('b', $machine2->getCurrentState(), 'should retrieve the same value');

        //create new instance of other machine
        $machine3 = new StateMachine(new Context(new Identifier('2', 'test-machine'), null, $redis));
        $this->assertNotSame($machine2, $machine3);
        $redis->load($machine3);
        $this->assertTrue($machine3->add());
        $this->assertNotEquals('b', $machine3->getCurrentState()->getName(), 'should not retrieve the same value as the other machine');
        $this->assertEquals('a', $machine3->getCurrentState()->getName(), 'initial state');
        //echo $machine3->toString(true);
        $this->assertEquals(2, $machine3->runToCompletion("go to the final state"));
        $this->assertEquals('done', $machine3->getCurrentState()->getName(), 'final state');


        $machine4 = new StateMachine(new Context(new Identifier('3', 'another-machine'), null, $redis));
        $a = new State('begin', State::TYPE_INITIAL);
        $b = new State('enter', State::TYPE_NORMAL);
        $c = new State('leave', State::TYPE_FINAL);
        $machine4->addTransition(new Transition($a, $b));
        $machine4->addTransition(new Transition($b, $c));
        $machine4->add('creating another machine to see that all goes well storing the data for multiple machines in redis');
        $this->assertEquals(2, $machine4->runToCompletion('running the machine to completion'));

        $ids = $redis->getEntityIds('test-machine');
        $this->assertEquals(array('1', '2'), $ids);
        $ids = $redis->getEntityIds('another-machine');
        $this->assertEquals(array('3'), $ids);
        $ids = $redis->getEntityIds('test-machine', 'done');
        $this->assertEquals(array('2'), $ids, 'only 2 was run to completion and in state done');
        $ids = $redis->getEntityIds('another-machine', 'leave');
        $this->assertEquals(array('3'), $ids, 'only 3 was run to completion and in state leave');

        //$redis->hmset("key" , array("name1" => "value1", "name2" => "value2"));

    }

    /**
     * @test
     */
    public function shouldDoMoreTests()
    {
        $this->markTestIncomplete('need more tests for actual database contents');
    }
}