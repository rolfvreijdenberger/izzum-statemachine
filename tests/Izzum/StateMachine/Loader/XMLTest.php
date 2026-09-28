<?php

namespace Izzum\StateMachine\Loader;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use Codeception\Attribute\Group;
use Izzum\StateMachine\StateMachine;
use Izzum\StateMachine\Context;
use Izzum\StateMachine\Identifier;
use Izzum\StateMachine\Exception;

/**
 *
 * @author rolf
 *
 */
#[Group('statemachine', 'loader', 'xml')]
class XMLTest extends TestCase
{
    #[Test]
    public function shouldBehave()
    {
        $machine = new StateMachine(new Context(new Identifier('xml-test', 'test-machine')));
        $loader = XML::createFromFile(__DIR__ . '/../../../../assets/xml/example.xml');
        $count = $loader->load($machine);
        $this->assertStringContainsString('xml', $loader->getXSD());
        $this->assertStringContainsString('bdone', $loader->getXML());
        $this->assertStringContainsString('XML', $loader->toString());
        $this->assertStringContainsString('XML', $loader . '', '_toString()');
    }

    #[Test]
    public function shouldLoadTransitionsFromFile()
    {
        $machine = new StateMachine(new Context(new Identifier('xml-test', 'test-machine')));
        $this->assertCount(0, $machine->getTransitions());
        //this is a symbolic link to the asset/xml/example.xml file
        $loader = XML::createFromFile(__DIR__ . '/fixture-example.xml');
        $count = $loader->load($machine);
        $this->assertCount(4, $machine->getTransitions(), 'there is a regex transition that adds 2 transitions (a-c and b-c)');
        $this->assertEquals(4, $count);
        $this->assertEquals(0, MyStatic::$guard);
        $this->assertTrue($machine->ab());
        $this->assertEquals(1, MyStatic::$guard, 'guard callable specified in xml should be called');
        $this->assertTrue($machine->bdone());
        $this->assertEquals(2, MyStatic::$entry, '2 entry state callables in config');
        $this->assertEquals(1, MyStatic::$exit, '1 exit state callable in config');
    }

    #[Test]
    public function shouldThrowExceptionForNonExistentFileLoading()
    {
        $machine = new StateMachine(new Context(new Identifier('xml-test', 'xml-machine')));
        try {
            $loader = XML::createFromFile(__DIR__ . '/bogus.xml');
            $this->fail('should not come here');
        } catch (Exception $e) {
            $this->assertEquals(Exception::BAD_LOADERDATA, $e->getCode());
            $this->assertStringContainsString('bogus', $e->getMessage());
            $this->assertStringContainsString('does not exist', $e->getMessage());
        }
    }


    /**
     * this has been tested locally with a file with permissions of 220 (no read permissions) and it passes.
     * github/travis builds do not play well with this so if you want to run this, create the file with those permissions
     */
    #[Group('not-on-production', 'filepermissions')]
    #[Test]
    public function shouldThrowExceptionForNoReadPermissions()
    {
        $machine = new StateMachine(new Context(new Identifier('xml-test', 'xml-machine')));
        try {
            $loader = XML::createFromFile(__DIR__ . '/fixture-no-permission.xml');
            $this->fail('should not come here');
        } catch (Exception $e) {
            $this->assertEquals(Exception::BAD_LOADERDATA, $e->getCode());
            $this->assertStringContainsString('Failed to read', $e->getMessage());
        }
    }

    #[Test]
    public function shouldThrowExceptionForBadXMLData()
    {
        $machine = new StateMachine(new Context(new Identifier('xml-test', 'xml-machine')));
        $loader = XML::createFromFile(__DIR__ . '/fixture-bad-xml.xml');
        try {
            $loader->load($machine);
            $this->fail('should not come here');
        } catch (Exception $e) {
            $this->assertEquals(Exception::BAD_LOADERDATA, $e->getCode());
            $this->assertStringContainsString('could not load', $e->getMessage());
        }
    }

    #[Test]
    public function shouldThrowExceptionForNoMachineData()
    {
        $machine = new StateMachine(new Context(new Identifier('xml-test', 'xml-machine')));
        $loader = XML::createFromFile(__DIR__ . '/fixture-no-machines.xml');
        try {
            $loader->load($machine);
            $this->fail('should not come here');
        } catch (Exception $e) {
            $this->assertEquals(Exception::BAD_LOADERDATA, $e->getCode());
            $this->assertStringContainsString('no machine data', $e->getMessage());
        }
    }
}

/**
 * Static class. this can be called as a callable. configured in the
 * configuration loaded by loaders
 */
class MyStatic
{
    public static $guard = 0;
    public static $transition = 0;
    public static $entry = 0;
    public static $exit = 0;

    public static function guardMethod($entity)
    {
        self::$guard += 1;
        return true;
    }

    public static function transitionMethod($entity)
    {
        self::$transition += 1;
    }

    public static function entryMethod($entity)
    {
        self::$entry += 1;
    }

    public static function exitMethod($entity)
    {
        self::$exit += 1;
    }
}
