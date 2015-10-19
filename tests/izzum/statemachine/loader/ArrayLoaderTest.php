<?php
namespace izzum\statemachine\loader;
use izzum\statemachine\Transition;
use izzum\statemachine\State;
use izzum\statemachine\StateMachine;
use izzum\statemachine\Context;
use izzum\statemachine\Identifier;
use izzum\statemachine\Entity;
use izzum\statemachine\Exception;
use izzum\statemachine\loader\Loader;
use izzum\statemachine\loader\LoaderArray;
use izzum\statemachine\utils\Utils;
/**
 * Tests the loading mechanisms objects
 * @group statemachine
 * @author rolf
 *
 */
class ArrayLoaderTest extends \PHPUnit_Framework_TestCase {
    
    
    
    public function testLoaderArray()
    {
        //scenario: test loader supported stuff
        $loader = new LoaderArray();
        $this->assertContains('LoaderArray', $loader->toString());
        $this->assertContains('LoaderArray', $loader .'', '__toString');
        $this->assertEquals(0, $loader->count());

        
        //scenario: configure loader
        $transitions = array();
        $s1 = new State("1");
        $s2 = new State("2");
        $s3 = new State("3");
        $transitions[] = new Transition($s1, $s2);
        $transitions[] = new Transition($s2, $s3);
        $loader = new LoaderArray($transitions);
        $this->assertEquals(count($transitions), $loader->count());
        
        
        //scenario: configure loader with bad object types
        $transitions = array();
        $transitions[] = new Transition($s2, $s3);
        $transitions[] =  new \stdClass();
        try {
            $loader = new LoaderArray($transitions);
            $this->fail('fails cause not the right type');
        }catch (Exception $e) {
            $this->assertEquals(Exception::BAD_LOADERDATA, $e->getCode());
        }
    }
    
    /**
     * @test
     */
    public function shouldLoadStateMachine()
    {
        $transitions = array();
        $s1 = new State("1");
        $s2 = new State("2");
        $s3 = new State("3");
        $transitions[] = new Transition($s1, $s2);
        $transitions[] = new Transition($s2, $s3);
        $loader = new LoaderArray($transitions);
        $this->assertEquals(count($transitions), $loader->count());
        $this->assertEquals(count($loader->getTransitions()), $loader->count());
        $context = new Context(new Identifier(Identifier::NULL_ENTITY_ID, Identifier::NULL_STATEMACHINE));
        $machine = new StateMachine($context);
        $count = $loader->load($machine);
        $this->assertEquals(2, $count);
        $this->assertCount(2, $machine->getTransitions());
        $this->assertCount(3, $machine->getStates());
    }
    
    /**
     * @test
     */
    public function shouldAddToLoader()
    {
    	$transitions = array();
    	$s1 = new State("1");
    	$s2 = new State("2");
    	$s3 = new State("3");
    	$transitions[] = new Transition($s1, $s2);
    	$transitions[] = new Transition($s2, $s3);
    	$loader = new LoaderArray($transitions);
    	$this->assertEquals(count($transitions), $loader->count());
    	$this->assertEquals(2, $loader->count());
    	
    	//add existing transition (not the same instance, but same name)
    	$loader->add(new Transition($s1, $s2));
    	$this->assertEquals(count($transitions), $loader->count());
    	$this->assertEquals(2, $loader->count());
    	
    	//add new transition
    	$loader->add(new Transition($s2, $s1));
    	$this->assertEquals(count($loader->getTransitions()), $loader->count());
    	$this->assertEquals(3, $loader->count());
    }
    
    /**
     * @test
     */
    public function shouldAddRegexesLoaderOnlyWhenStatesAreSet()
    {
        
        $context = new Context(new Identifier(Identifier::NULL_ENTITY_ID, Identifier::NULL_STATEMACHINE));
        $machine = new StateMachine($context);
        
        $transitions = array();
        $s1 = new State("1");
        $s2 = new State("2");
        $s3 = new State("3");
        
        //many to many
        $transitions[] = new Transition(new State('regex:/.*/'), new State('regex:/.*/'));
        $loader = new LoaderArray($transitions);
        $this->assertEquals(count($transitions), $loader->count());
        $this->assertEquals(1, $loader->count());
        $this->assertEquals(0, count($machine->getStates()));
        $this->assertEquals(0, count($machine->getTransitions()));
        
        $count = $loader->load($machine);
        $this->assertEquals(0, $count, 'nothing because there are no known states');
        
        $this->assertTrue($machine->addState($s1));
        $this->assertTrue($machine->addState($s2));
        $this->assertTrue($machine->addState($s3));
        $this->assertEquals(3, count($machine->getStates()));
        $this->assertFalse($machine->addState($s1));
        $this->assertFalse($machine->addState($s2));
        $this->assertFalse($machine->addState($s3));
        $this->assertEquals(3, count($machine->getStates()));
        
        $count = $loader->load($machine);
        $this->assertEquals(6, count($machine->getTransitions()));
        $this->assertEquals(6, $count, 'regexes have matched all states and created a mesh');
        $count = $loader->load($machine);
        $this->assertEquals(0, $count, 'transitions are not added since they have already been added');
        $this->assertEquals(3, count($machine->getStates()));
        $this->assertEquals(6, count($machine->getTransitions()));
         

    }

}