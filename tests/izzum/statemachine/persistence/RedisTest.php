<?php
namespace izzum\statemachine\persistence;
use izzum\statemachine\StateMachine;
use izzum\statemachine\Context;
use izzum\statemachine\Identifier;
/**
 * this test makes use of an active redis instance on the localhost listening
 * on port 6379 (the defaults) and database 15 (which will be flused on each test)
 * 
 * @group persistence
 * @group loader
 * @group redis
 * @author rolf
 *
 */
class RedisTest extends \PHPUnit_Framework_TestCase {
    
    /**
     * @test
     * @group not-on-production
     */
    public function shouldBeAbleToLoadConfiguration()
    {
        $redis = new Redis();
        $redis->setDatabase(15);
        //clear the redis database for testing
        $redis->flushdb();
        $machine = new StateMachine(new Context(new Identifier('redis', 'test-machine'), null, $redis));
        //create the loader
        //get the configuration from the json file
        $configuration = file_get_contents(__DIR__ .'/../loader/fixture-example.json');
        //set it. normally, this would be done by a seperate process that has already loaded the configuration
        $redis->set(Redis::KEY_CONFIGURATION, $configuration);
        //load the machine
        $count = $redis->load($machine);
        $this->assertEquals(2, $count, 'expect 2 transitions to be loaded');
        $this->assertCount(2, $machine->getTransitions());
        $this->assertCount(3, $machine->getStates());
        $machine->getContext()->add($machine->getInitialState()->getName());
        $this->assertTrue($machine->run());
        $this->assertContains('redis', $redis->getEntityIds('test-machine'));
    }
}