<?php
namespace izzum\statemachine\loader;
use izzum\statemachine\persistence\Memory;
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
 * @group statemachine
 * @group loader
 * @group xml
 * 
 * @author rolf
 *        
 */
class XMLTest extends \PHPUnit_Framework_TestCase {

    /**
     * @test
     */
    public function shouldLoadTransitionsFromFile()
    {
        $machine = new StateMachine(new Context(new Identifier('xml-test', 'test-machine')));
        $this->assertCount(0, $machine->getTransitions());
        $loader = XML::createFromFile(__DIR__ . '/../../../../assets/xml/example.xml');
        $count = $loader->load($machine);
        $this->assertCount(2, $machine->getTransitions());
        $this->assertEquals(2, $count);
        $this->assertEquals(0, MyStatic::$guard);
        $this->assertTrue($machine->ab());
        $this->assertEquals(1, MyStatic::$guard, 'guard callable specified in xml should be called');
        $this->assertTrue($machine->bdone());
        $this->assertEquals(2, MyStatic::$entry, '2 entry state callables in config');
        $this->assertEquals(1, MyStatic::$exit, '1 exit state callable in config');
    }
    
    /**
     * @test
     */
    public function shouldBehave()
    {
        $machine = new StateMachine(new Context(new Identifier('xml-test', 'test-machine')));
        $loader = XML::createFromFile(__DIR__ . '/../../../../assets/xml/example.xml');
        $count = $loader->load($machine);
        $this->assertContains('xml', $loader->getXSD());
        $this->assertContains('bdone', $loader->getXML());
        $this->assertContains('XML', $loader->toString());
    }
    
}
class MyStatic {
    public static $guard = 0;
    public static $transition = 0;
    public static $entry = 0;
    public static $exit = 0;
    public static function guardMethod($entity, $event) {
        self::$guard += 1;
        return true;
    }
    public static function transitionMethod($entity, $event) {
        self::$transition += 1;
    }
    public static function entryMethod($entity, $event) {
        self::$entry += 1;
    }
    public static function exitMethod($entity, $event) {
        self::$exit += 1;
    }
}