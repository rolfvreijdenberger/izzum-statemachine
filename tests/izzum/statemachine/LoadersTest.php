<?php
namespace izzum\statemachine;
use izzum\statemachine\Transition;
use izzum\statemachine\State;
use izzum\statemachine\Entity;
use izzum\statemachine\loader\Loader;
use izzum\statemachine\loader\LoaderArray;
use izzum\statemachine\loader\LoaderData;
use izzum\statemachine\utils\Utils;
/**
 * Tests the loading mechanisms objects
 * @group statemachine
 * @author rolf
 *
 */
class LoadersTest extends \PHPUnit_Framework_TestCase {
    
    /**
     * tests the use of the factory method with only default parameters
     */
    public function testLoaderDataFactoryDefault()
    {

        $from = 'new';
        $to = 'end';
        
        $object = LoaderData::get($from, $to);
        //sanity check the public methods;
        $this->assertEquals($to, $object->getStateTo());
        $this->assertEquals($from, $object->getStateFrom());
        $this->assertEquals(Utils::getTransitionName($from, $to), $object->getTransition());
        $this->assertEquals(State::TYPE_NORMAL, $object->getStateTypeFrom());
        $this->assertEquals(State::TYPE_NORMAL, $object->getStateTypeTo());
        $this->assertEquals(Transition::COMMAND_NULL, $object->getCommand());
        $this->assertEquals(Transition::RULE_FALSE, $object->getRule());
    }
    
    /**
     * @test
     */
    public function testLoaderDataTransitionRulesAndCommands()
    {
        $from = 'new';
        $to = 'end';
        
        //scenario: rule configured correctly
        $rule = 'izzum\rules\Bogus';
        $object = LoaderData::get($from, $to, $rule);
        $this->assertEquals($to, $object->getStateTo());
        $this->assertEquals($from, $object->getStateFrom());
        $this->assertEquals(Utils::getTransitionName($from, $to), $object->getTransition());
        $this->assertEquals(State::TYPE_NORMAL, $object->getStateTypeFrom());
        $this->assertEquals(State::TYPE_NORMAL, $object->getStateTypeTo());
        $this->assertEquals(Transition::COMMAND_NULL, $object->getCommand());
        $this->assertEquals($rule, $object->getRule());
        
        //scenario: rule configured correctly (True rule)
        $rule = 'izzum\rules\True';
        $object = LoaderData::get($from, $to, $rule);
        $this->assertEquals($to, $object->getStateTo());
        $this->assertEquals($from, $object->getStateFrom());
        $this->assertEquals(Utils::getTransitionName($from, $to), $object->getTransition());
        $this->assertEquals(State::TYPE_NORMAL, $object->getStateTypeFrom());
        $this->assertEquals(State::TYPE_NORMAL, $object->getStateTypeTo());
        $this->assertEquals(Transition::COMMAND_NULL, $object->getCommand());
        $this->assertEquals(Transition::RULE_TRUE, $object->getRule());
        
        //scenario: command configured correctly (Null
        $command = 'izzum\command\Bogus';
        $rule = 'izzum\rules\True';
        $object = LoaderData::get($from, $to, $rule, $command);
        $this->assertEquals($to, $object->getStateTo());
        $this->assertEquals($from, $object->getStateFrom());
        $this->assertEquals(Utils::getTransitionName($from, $to), $object->getTransition());
        $this->assertEquals(State::TYPE_NORMAL, $object->getStateTypeFrom());
        $this->assertEquals(State::TYPE_NORMAL, $object->getStateTypeTo());
        $this->assertEquals($command, $object->getCommand());
        $this->assertEquals(Transition::RULE_TRUE, $object->getRule());
        
    }
    
    /**
     * tests the properly configured state objects that are retreived from a loaderObject
     */
    public function testLoaderDataStatesReturned (){
 
        $from = 'new';
        $to = 'end';
        $data = new LoaderData($from, $to, '', '', State::TYPE_INITIAL, State::TYPE_FINAL);
        $this->assertEquals(State::TYPE_INITIAL, $data->getStateTypeFrom());
        $this->assertEquals(State::TYPE_FINAL, $data->getStateTypeTo());

    }
    
    
    public function testLoaderArray()
    {
        //scenario: test loader supported stuff
        $loader = new LoaderArray();
        $this->assertEquals(0, $loader->count());

        
        //scenario: configure loader with loader objects
        $objects = array();
        $objects[] =  LoaderData::get("1", "2");
        $objects[] =  LoaderData::get("2", "3");
        $loader = new LoaderArray($objects);
        $this->assertEquals(count($objects), $loader->count());
        
        
        //scenario: configure loader with bad object types
        $objects = array();
        $objects[] =  LoaderData::get("2", "3");
        $objects[] =  new \stdClass();
        try {
            $loader = new LoaderArray($objects);
            $this->fail('fails cause not the right type');
        }catch (Exception $e) {
            $this->assertContains("Expected LoaderData", $e->getMessage());
            $this->assertEquals(Exception::BAD_LOADERDATA, $e->getCode());
        }
    }
    
    /**
     * @test
     */
    public function shouldLoadStateMachine()
    {
        $objects = array();
        $objects[] =  LoaderData::get("1", "2");
        $objects[] =  LoaderData::get("2", "3");
        $loader = new LoaderArray($objects);
        $this->assertEquals(count($objects), $loader->count());
        $context = Context::get(new Identifier(Identifier::NULL_ENTITY_ID, Identifier::NULL_STATEMACHINE));
        $machine = new StateMachine($context);
        $loader->load($machine);
        $this->assertCount(2, $machine->getTransitions());
        $this->assertCount(3, $machine->getStates());
    }
    

}