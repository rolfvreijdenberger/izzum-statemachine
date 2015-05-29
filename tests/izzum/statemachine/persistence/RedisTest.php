<?php
namespace izzum\statemachine\persistence;
use izzum\statemachine\StateMachine;
use izzum\statemachine\Context;
use izzum\statemachine\Exception;
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
        $this->assertEquals(4, $count, 'expect 4 transitions to be loaded');
        $this->assertCount(4, $machine->getTransitions(), 'there is a regex transition that adds 2 transitions (a-c and b-c)');
        $this->assertCount(4, $machine->getStates());
 
    }
    
    /**
     * @test
     * @group not-on-production
     */
    public function shouldBeAbleToStoreData()
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
        //add the machine to the backend system
        $this->assertTrue($machine->add());
        $this->assertFalse($machine->add(), 'returns false, already added');

        $this->assertTrue($machine->run(), 'succesful transitions so it returns true');
        $this->assertEquals('b', $machine->getCurrentState());
        $this->assertContains('redis', $redis->getEntityIds('test-machine'));
        $this->assertTrue($machine->hasEvent('goToC'));
        try {
            $machine->goToC();
            $this->fail('should not come here');
        }catch (Exception $e) {
            $this->assertEquals(Exception::RULE_APPLY_FAILURE, $e->getCode());
        }
        $this->assertEquals('b', $machine->getCurrentState());
        
        //create new instance of same machine
        $machine2 = new StateMachine(new Context(new Identifier('redis', 'test-machine'), null, $redis));
        $this->assertNotSame($machine2, $machine);
        $redis->load($machine2);
        $this->assertEquals('b', $machine2->getCurrentState(), 'should retrieve the same value');
        
        //create new instance of other machine
        $machine3 = new StateMachine(new Context(new Identifier('my-id', 'test-machine'), null, $redis));
        $this->assertNotSame($machine2, $machine3);
        $redis->load($machine3);
        $this->assertTrue($machine3->add());
        $this->assertNotEquals('b', $machine3->getCurrentState()->getName(), 'should not retrieve the same value as the other machine');
        $this->assertEquals('a', $machine3->getCurrentState()->getName(), 'initial state');
        //echo $machine3->toString(true);
        $this->assertEquals(2, $machine3->runToCompletion());
        $this->assertEquals('done', $machine3->getCurrentState()->getName(), 'final state');
        
        $this->markTestIncomplete('should check the actual redis database for contents. instead, I did this manually. see the png file in assets/redis');
        
        
    }
}