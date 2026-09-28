<?php

namespace Izzum\StateMachine\Utils;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use Codeception\Attribute\Group;
use Izzum\Command\Command;
use Izzum\StateMachine\Builder\ModelBuilder;
use Izzum\StateMachine\Transition;
use Izzum\StateMachine\State;
use Izzum\StateMachine\StateMachine;
use Izzum\StateMachine\Context;
use Izzum\StateMachine\Identifier;
use Izzum\StateMachine\Exception;
use Izzum\StateMachine\Loader\LoaderArray;

/**
 * @author rolf
 *
 */
#[Group('statemachine', 'state')]
class UtilsTest extends TestCase
{
    #[Test]
    public function shouldGetCommandWithEntity()
    {

        $commandName = 'Izzum\StateMachine\Utils\IncreaseId';
        $entity = new \stdClass();
        $entity->id = 0;
        $entity->event = null;
        //modelbuilder always returns the model we give it in the constructor
        $context = new Context(new Identifier('1', 'test'), new ModelBuilder($entity));
        $event = null;

        $command = Utils::getCommand($commandName, $context);
        $this->assertTrue(is_a($command, 'Izzum\Command\Composite'));
        $this->assertStringContainsString('IncreaseId', $command->toString());
        $this->assertEquals(0, $entity->id);
        $command->execute();
        $this->assertEquals(1, $entity->id);
    }

    /**
     * https://github.com/rolfvreijdenberger/izzum-statemachine/issues/7
     */
    #[Test]
    public function shouldPassConfigurationCheckForBasicMachine()
    {
        $transitions = [];
        $s1 = new State("1");
        $s2 = new State("2");
        $s3 = new State("3");
        $transitions[] = new Transition($s1, $s2);
        $transitions[] = new Transition($s2, $s3);
        $loader = new LoaderArray($transitions);
        $context = new Context(new Identifier(Identifier::NULL_ENTITY_ID, Identifier::NULL_STATEMACHINE));
        $machine = new StateMachine($context);
        $loader->load($machine);
        $exceptions = Utils::checkConfiguration($machine);
        $this->assertCount(0, $exceptions, 'basic machine will be configured correctly');

    }

    /**
     * https://github.com/rolfvreijdenberger/izzum-statemachine/issues/7
     */
    #[Test]
    public function shouldPassConfigurationCheckForMachineWithGoodCallables()
    {
        $transitions = [];
        $s1 = new State("1");
        $s1->setEntryCallable('phpinfo');
        $s2 = new State("2");
        $s2->setEntryCallable('phpinfo');
        $s3 = new State("3");
        $s3->setEntryCallable('phpinfo');
        $transitions[] = new Transition($s1, $s2);
        $transitions[] = new Transition($s2, $s3);
        foreach ($transitions as $transition) {
            $transition->setGuardCallable('phpinfo');
            $transition->setTransitionCallable('phpinfo');
        }
        $loader = new LoaderArray($transitions);
        $context = new Context(new Identifier(Identifier::NULL_ENTITY_ID, Identifier::NULL_STATEMACHINE));
        $machine = new StateMachine($context);
        $loader->load($machine);
        $exceptions = Utils::checkConfiguration($machine);
        $this->assertCount(0, $exceptions, 'basic machine with good callables will be configured correctly');

    }


    /**
     * https://github.com/rolfvreijdenberger/izzum-statemachine/issues/7
     */
    #[Test]
    public function shouldFailConfigurationCheckForMachineWithBadCallables()
    {
        $transitions = [];
        $s1 = new State("1");
        $s1->setEntryCallable('foobar');
        $s2 = new State("2");
        $s2->setEntryCallable('foobar');
        $s3 = new State("3");
        $s3->setEntryCallable('foobar');
        $transitions[] = new Transition($s1, $s2);
        $transitions[] = new Transition($s2, $s3);
        //3 states, 3 bad callables
        //2 transitions, 4 bad callables
        //total of 7
        foreach ($transitions as $transition) {
            $transition->setGuardCallable('foobar');
            $transition->setTransitionCallable('foobar');
        }
        $loader = new LoaderArray($transitions);
        $context = new Context(new Identifier(Identifier::NULL_ENTITY_ID, Identifier::NULL_STATEMACHINE));
        $machine = new StateMachine($context);
        $loader->load($machine);
        $exceptions = Utils::checkConfiguration($machine);
        $this->assertEquals(7, count($exceptions));
    }


    #[Test]
    public function shouldGetCompositeCommand()
    {

        //id should be increased three times
        $commandName = 'Izzum\StateMachine\Utils\IncreaseId,Izzum\StateMachine\Utils\IncreaseId,Izzum\StateMachine\Utils\IncreaseId';
        $entity = new \stdClass();
        $entity->id = 0;
        $entity->event = null;
        //modelbuilder always returns the model we give it in the constructor
        $context = new Context(new Identifier('1', 'test'), new ModelBuilder($entity));
        $event = 'event';

        $command = Utils::getCommand($commandName, $context);
        $this->assertTrue(is_a($command, 'Izzum\Command\Composite'));
        $this->assertStringContainsString('IncreaseId', $command->toString());
        $this->assertEquals(0, $entity->id);
        $command->execute();
        $this->assertEquals(3, $entity->id);
        $command->execute();
        $this->assertEquals(6, $entity->id);
    }

    #[Test]
    public function shouldGetNullCommand()
    {

        $commandName = '';
        $context = new Context(new Identifier('1', 'test'));

        $command = Utils::getCommand($commandName, $context);
        $this->assertTrue(is_a($command, 'Izzum\Command\NullCommand'));

        $commandName = null;
        $context = new Context(new Identifier('1', 'test'));

        $command = Utils::getCommand($commandName, $context);
        $this->assertTrue(is_a($command, 'Izzum\Command\NullCommand'));

    }

    #[Test]
    public function shouldGetExceptionForInvalidCommand()
    {
        $commandName = 'Izzum\StateMachine\Utils\CannotCreate';
        $context = new Context(new Identifier('1', 'test'));

        try {
            $command = Utils::getCommand($commandName, $context);
            $this->fail('should not come here, command should throw exception on failure');
        } catch (Exception $e) {
            $this->assertEquals(Exception::COMMAND_CREATION_FAILURE, $e->getCode());
            $this->assertStringContainsString('cannot create', $e->getMessage());
            $this->assertStringContainsString('objects to construction', $e->getMessage());
            //echo $e->getMessage() . PHP_EOL;
        }
    }

    #[Test]
    public function shouldGetExceptionForNonExistingCommand()
    {

        $commandName = 'bogus';
        $context = new Context(new Identifier('1', 'test'));

        try {
            $command = Utils::getCommand($commandName, $context);
            $this->fail('should not come here, command does not exist');
        } catch (Exception $e) {
            $this->assertEquals(Exception::COMMAND_CREATION_FAILURE, $e->getCode());
            $this->assertStringContainsString('class does not exist', $e->getMessage());
            //echo $e->getMessage() . PHP_EOL;
        }
    }

    #[Test]
    public function shouldWrapException()
    {
        $e = new \Exception('test', 0);
        try {
            Utils::wrapToStateMachineException($e, 1, true);
            $this->fail('should not come here');
        } catch (\Exception $e) {
            $this->assertEquals(1, $e->getCode());
            $this->assertEquals('test', $e->getMessage());
            $this->assertTrue(is_a($e, '\Izzum\StateMachine\Exception'));
        }

    }

    #[Test]
    public function shouldReturnCorrectTransitionName()
    {
        $from = 'state-from';
        $to = 'state-to';
        $this->assertEquals($from . Utils::STATE_CONCATENATOR . $to, Utils::getTransitionName($from, $to));
    }


    #[Group('regex')]
    #[Test]
    public function shouldMatchValidRegexAndNegatedRegex()
    {
        $name = 'regex:/.*/';//only allow regexes between regex begin and end markers
        $regex = new State($name);
        $target = new State('aa');
        $this->assertTrue(Utils::matchesRegex($regex, $target), 'only allow regexes between regex begin and end markers');


        $name = 'regex:/a|b/';//allow regexes without regex begin and end markers
        $regex = new State($name);
        $target = new State('b');
        $this->assertTrue(Utils::matchesRegex($regex, $target));


        $name = 'regex:/c|a|aa/';
        $regex = new State($name);
        $target = new State('aa');
        $this->assertTrue(Utils::matchesRegex($regex, $target));


        $name = 'regex:/action-.*/';
        $regex = new State($name);
        $target = new State('action-hero');
        $bad = new State('action_hero');
        $this->assertTrue(Utils::matchesRegex($regex, $target));
        $this->assertFalse(Utils::matchesRegex($regex, $bad));


        $name = 'regex:/go[o,l]d/';
        $regex = new State($name);
        $target = new State('gold');
        $bad = new State('golld');
        $this->assertTrue(Utils::matchesRegex($regex, $target));
        $this->assertFalse(Utils::matchesRegex($regex, $bad));

        //NOT matching a regex
        $name = 'not-regex:/go[o,l]d/';
        $regex = new State($name);
        $target = new State('goad');
        $bad = new State('gold');
        $this->assertTrue(Utils::matchesRegex($regex, $target));
        $this->assertFalse(Utils::matchesRegex($regex, $bad));
    }

    #[Group('regex')]
    #[Test]
    public function shouldReturnArrayOfMatchedStates()
    {

        $a = new State('a');
        $b = new State('ab');
        $c = new State('ba');
        $d = new State('abracadabra');
        $e = new State('action-hero');
        $f = new State('action-bad-guy');
        $g = new State('ac');
        $targets = [$a, $b, $c, $d, $e, $f, $g];

        $regex = new State('regex:/.*/');
        $this->assertEquals($targets, Utils::getAllRegexMatchingStates($regex, $targets));


        $regex = new State('regex:/^a.*/');
        $this->assertEquals([$a, $b, $d, $e, $f, $g], Utils::getAllRegexMatchingStates($regex, $targets));

        $regex = new State('regex:/^a.+/');
        $this->assertEquals([$b, $d, $e, $f, $g], Utils::getAllRegexMatchingStates($regex, $targets));

        $regex = new State('regex:/^a.*a.+$/');
        $this->assertEquals([$d, $f], Utils::getAllRegexMatchingStates($regex, $targets));

        $regex = new State('regex:/^ac.*-.+$/');
        $this->assertEquals([$e, $f], Utils::getAllRegexMatchingStates($regex, $targets));

        $regex = new State('ac');
        $this->assertFalse($regex->isRegex());
        $this->assertEquals([$g], Utils::getAllRegexMatchingStates($regex, $targets), 'non regex state');

    }





}

//helper class, increases the id on an entity when executed.
class IncreaseId extends Command
{
    public function __construct(private $entity) {}


    protected function _execute(): void
    {
        //proof that we can manipulate the entity
        $this->entity->id += 1;
    }
}

//helper class, throws exception on execution
class CannotCreate extends Command
{
    public function __construct($entity)
    {
        throw new Exception("cannot create");
    }

    protected function _execute(): void {}
}
