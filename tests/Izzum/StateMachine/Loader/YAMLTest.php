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
 * the YAML test used the yaml module for php. therefore it can only be run on
 * a system that has been setup properly with that module
 *
 * @author rolf
 *
 */
#[Group('statemachine', 'loader', 'yaml', 'not-on-production')]
class YAMLTest extends TestCase
{
    #[Test]
    public function shouldLoadTransitionsFromFile()
    {
        $machine = new StateMachine(new Context(new Identifier('yaml-test', 'test-machine')));
        $this->assertCount(0, $machine->getTransitions());
        //this is a symbolic link to the assets/yaml/example.yaml file
        $loader = YAML::createFromFile(__DIR__ . '/fixture-example.yaml');
        $count = $loader->load($machine);
        $this->assertCount(4, $machine->getTransitions(), 'there is a regex transition that adds 2 transitions (a-c and b-c)');
        $this->assertEquals(4, $count);
        //echo $machine->toString(true);
    }

    #[Test]
    public function shouldBehave()
    {
        $machine = new StateMachine(new Context(new Identifier('yaml-test', 'test-machine')));
        $loader = YAML::createFromFile(__DIR__ . '/../../../../assets/yaml/example.yaml');
        $count = $loader->load($machine);
        $this->assertStringContainsString('bdone', $loader->getYAML());
        $this->assertStringContainsString('YAML', $loader->toString());
        $this->assertStringContainsString('YAML', $loader . '', '__toString()');
    }

    #[Test]
    public function shouldThrowExceptionForNonExistentFileLoading()
    {
        $machine = new StateMachine(new Context(new Identifier('yaml-test', 'yaml-machine')));
        try {
            $loader = YAML::createFromFile(__DIR__ . '/bogus.yaml');
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
        $machine = new StateMachine(new Context(new Identifier('yaml-test', 'yaml-machine')));
        try {
            $loader = YAML::createFromFile(__DIR__ . '/fixture-no-permission.yaml');
            $this->fail('should not come here');
        } catch (Exception $e) {
            $this->assertEquals(Exception::BAD_LOADERDATA, $e->getCode());
            $this->assertStringContainsString('Failed to read', $e->getMessage());
        }
    }

    #[Test]
    public function shouldThrowExceptionForBadYamlData()
    {
        $machine = new StateMachine(new Context(new Identifier('yaml-test', 'yaml-machine')));
        $loader = YAML::createFromFile(__DIR__ . '/fixture-bad-yaml.yaml');
        try {
            $loader->load($machine);
            $this->fail('should not come here');
        } catch (Exception $e) {
            $this->assertEquals(Exception::BAD_LOADERDATA, $e->getCode());
            $this->assertStringContainsString('no machine data', $e->getMessage());
        }
    }

    #[Test]
    public function shouldThrowExceptionForNoMachineData()
    {
        $machine = new StateMachine(new Context(new Identifier('yaml-test', 'yaml-machine')));
        $loader = YAML::createFromFile(__DIR__ . '/fixture-no-machines.yaml');
        try {
            $loader->load($machine);
            $this->fail('should not come here');
        } catch (Exception $e) {
            $this->assertEquals(Exception::BAD_LOADERDATA, $e->getCode());
            $this->assertStringContainsString('no machine data', $e->getMessage());
        }
    }


    #[Test]
    public function shouldLoadTransitionsFromYAMLString()
    {
        $machine = new StateMachine(new Context(new Identifier('yaml-test', 'yaml-machine')));
        $this->assertCount(0, $machine->getTransitions());
        $yaml = $this->getYAML();
        $loader = new YAML($yaml);
        $this->assertEquals($this->getYAML(), $loader->getYAML());
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

    protected function getYAML()
    {
        //the string below is a json string.
        //since yaml 1.2, json is a valid subset o yaml.
        //most yaml parsers should parse json just fine.

        //heredoc syntax
        $yaml = '{
  "machines": [
    {
      "name": "yaml-machine",
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
        return $yaml;
    }

}
