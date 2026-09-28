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
#[Group('statemachine', 'loader', 'json')]
class JSONTest extends TestCase
{
    #[Test]
    public function shouldLoadTransitionsFromFile()
    {
        $machine = new StateMachine(new Context(new Identifier('json-test', 'test-machine')));
        $this->assertCount(0, $machine->getTransitions());
        //this is a symbolic link to the assets/json/example.json file
        $loader = JSON::createFromFile(__DIR__ . '/fixture-example.json');
        $count = $loader->load($machine);
        $this->assertCount(4, $machine->getTransitions(), 'there is a regex transition that adds 2 transitions (a-c and b-c)');
        $this->assertEquals(4, $count);
    }

    #[Test]
    public function shouldBehave()
    {
        $machine = new StateMachine(new Context(new Identifier('json-test', 'test-machine')));
        $loader = JSON::createFromFile(__DIR__ . '/../../../../assets/json/example.json');
        $count = $loader->load($machine);
        $this->assertStringContainsString('bdone', $loader->getJSON());
        $this->assertStringContainsString('json-schema', $loader->getJSONSchema());
        $this->assertStringContainsString('JSON', $loader->toString());
        $this->assertStringContainsString('JSON', $loader . '', '__toString()');
    }

    #[Test]
    public function shouldThrowExceptionForNonExistentFileLoading()
    {
        $machine = new StateMachine(new Context(new Identifier('json-test', 'json-machine')));
        try {
            $loader = JSON::createFromFile(__DIR__ . '/bogus.json');
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
        $machine = new StateMachine(new Context(new Identifier('json-test', 'json-machine')));
        try {
            $loader = JSON::createFromFile(__DIR__ . '/fixture-no-permission.json');
            $this->fail('should not come here');
        } catch (Exception $e) {
            $this->assertEquals(Exception::BAD_LOADERDATA, $e->getCode());
            $this->assertStringContainsString('Failed to read', $e->getMessage());
        }
    }

    #[Test]
    public function shouldThrowExceptionForBadJsonData()
    {
        $machine = new StateMachine(new Context(new Identifier('json-test', 'json-machine')));
        $loader = JSON::createFromFile(__DIR__ . '/fixture-bad-json.json');
        try {
            $loader->load($machine);
            $this->fail('should not come here');
        } catch (Exception $e) {
            $this->assertEquals(Exception::BAD_LOADERDATA, $e->getCode());
            $this->assertStringContainsString('decode', $e->getMessage());
        }
    }

    #[Test]
    public function shouldThrowExceptionForNoMachineData()
    {
        $machine = new StateMachine(new Context(new Identifier('json-test', 'json-machine')));
        $loader = JSON::createFromFile(__DIR__ . '/fixture-no-machines.json');
        try {
            $loader->load($machine);
            $this->fail('should not come here');
        } catch (Exception $e) {
            $this->assertEquals(Exception::BAD_LOADERDATA, $e->getCode());
            $this->assertStringContainsString('no machine data', $e->getMessage());
        }
    }


    #[Test]
    public function shouldLoadTransitionsFromJSONString()
    {
        $machine = new StateMachine(new Context(new Identifier('json-test', 'json-machine')));
        $this->assertCount(0, $machine->getTransitions());
        $json = $this->getJSON();
        $loader = new JSON($json);
        $this->assertEquals($this->getJSON(), $loader->getJSON());
        $count = $loader->load($machine);
        $this->assertCount(2, $machine->getTransitions());
        $this->assertEquals(2, $count);
        $tbd = $machine->getTransition('b_to_done');
        $b = $tbd->getStateFrom();
        $d = $tbd->getStateTo();
        $tab = $machine->getTransition('a_to_b');
        $a = $tab->getStateFrom();
        $this->assertEquals($b, $tab->getStateTo());
        $this->assertSame($b, $tab->getStateTo());
        $this->assertTrue($a->isInitial());
        $this->assertTrue($b->isNormal());
        $this->assertTrue($d->isFinal());
    }

    protected function getJSON()
    {
        //heredoc syntax
        $json = '{
  "machines": [
    {
      "name": "json-machine",
      "factory": "fully\\\\qualified\\\\factory-for-test-machine",
      "description": "my test-machine description",
      "states": [
        {
          "name": "a",
          "type": "initial",
          "entry_command": "",
          "exit_command": null,
          "entry_callable": null,
          "exit_callable": null,
          "description": "state a description"
        },
        {
          "name": "b",
          "type": "normal",
          "entry_command": "Izzum\\\\Command\\\\NullCommand",
          "exit_command": "Izzum\\\\Command\\\\NullCommand",
          "entry_callable": "Static::method",
          "exit_callable": "Static::method",
          "description": "state b description"
        },
        {
          "name": "done",
          "type": "final",
          "entry_command": "Izzum\\\\Command\\\\NullCommand",
          "exit_command": "Izzum\\\\Command\\\\NullCommand",
          "entry_callable": "Static::method",
          "exit_callable": null,
          "description": "state done description"
        }
      ],
      "transitions": [
        {
          "state_from": "a",
          "state_to": "b",
          "rule": "Izzum\\\\Rules\\\\TrueRule",
          "command": "Izzum\\\\Command\\\\NullCommand",
          "guard_callable": "Static::guard",
          "transition_callable": "Static::method",
          "event": "ab",
          "description": "my description for a_to_b"
        },
        {
          "state_from": "b",
          "state_to": "done",
          "rule": null,
          "command": null,
          "guard_callable": null,
          "transition_callable": null,
          "event": "bdone",
          "description": "my description for b_to_done"
        }
      ]
    }
  ]
}';
        return $json;
    }

}
