<?php

namespace Izzum\StateMachine\Loader;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use Codeception\Attribute\Group;
use Izzum\StateMachine\Transition;
use Izzum\StateMachine\State;
use Izzum\StateMachine\StateMachine;
use Izzum\StateMachine\Context;
use Izzum\StateMachine\Identifier;
use Izzum\StateMachine\Exception;
use Izzum\StateMachine\Loader\LoaderArray;

/**
 * Tests the loading mechanisms objects
 * @author rolf
 *
 */
#[Group('statemachine')]
class ArrayLoaderTest extends TestCase
{
    public function testLoaderArray()
    {
        //scenario: test loader supported stuff
        $loader = new LoaderArray();
        $this->assertStringContainsString('LoaderArray', $loader->toString());
        $this->assertStringContainsString('LoaderArray', $loader . '', '__toString');
        $this->assertEquals(0, $loader->count());


        //scenario: configure loader
        $transitions = [];
        $s1 = new State("1");
        $s2 = new State("2");
        $s3 = new State("3");
        $transitions[] = new Transition($s1, $s2);
        $transitions[] = new Transition($s2, $s3);
        $loader = new LoaderArray($transitions);
        $this->assertEquals(count($transitions), $loader->count());


        //scenario: configure loader with bad object types
        $transitions = [];
        $transitions[] = new Transition($s2, $s3);
        $transitions[] =  new \stdClass();
        try {
            $loader = new LoaderArray($transitions);
            $this->fail('fails cause not the right type');
        } catch (Exception $e) {
            $this->assertEquals(Exception::BAD_LOADERDATA, $e->getCode());
        }
    }

    #[Test]
    public function shouldLoadStateMachine()
    {
        $transitions = [];
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

    #[Test]
    public function shouldAddToLoader()
    {
        $transitions = [];
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

    #[Test]
    public function shouldAddRegexesLoaderOnlyWhenStatesAreSet()
    {

        $context = new Context(new Identifier(Identifier::NULL_ENTITY_ID, Identifier::NULL_STATEMACHINE));
        $machine = new StateMachine($context);

        $transitions = [];
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
