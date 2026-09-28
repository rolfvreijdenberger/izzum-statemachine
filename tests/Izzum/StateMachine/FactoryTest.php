<?php

namespace Izzum\StateMachine;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use Codeception\Attribute\Group;

/**
 * @author rolf
 *
 */
#[Group('statemachine', 'factory')]
class FactoryTest extends TestCase
{
    #[Test]
    public function shouldCreateAndUseSimpleTestFactory()
    {
        $machineName = 'factory-test';

        //scenario: testing instantiation and some checks
        $factory = new SimpleTestFactory();
        //instantation oke!
        $machine = $factory->getStateMachine(1);
        //test the machine
        $context = $machine->getContext();
        $this->assertCount(0, $context->getPersistenceAdapter()->getEntityIds($machineName));
        $machine->add();
        $this->assertCount(1, $context->getPersistenceAdapter()->getEntityIds($machineName));
        $context->getPersistenceAdapter()->getEntityIds($machineName);
        $this->assertEquals($machineName, $context->getMachine(), 'name as provided by factory');
        $this->assertEquals($machineName, $machine->getContext()->getMachine(), 'name as provided by factory');
        $this->assertEquals($machine, $context->getStateMachine(), 'bidirectional association check');
        $this->assertCount(5, $machine->getStates());
        $this->assertCount(6, $machine->getTransitions());

        $this->assertTrue(is_a($context->getPersistenceAdapter(), 'Izzum\StateMachine\Persistence\Memory'));
        $this->assertTrue(is_a($context->getBuilder(), 'Izzum\StateMachine\EntityBuilder'));
        //echo $machine->toString();


    }


}

namespace Izzum\StateMachine;

use Izzum\StateMachine\Persistence\Memory;
use Izzum\StateMachine\Persistence\Adapter;
use Izzum\StateMachine\Loader\Loader;
use Izzum\StateMachine\Loader\LoaderArray;

class SimpleTestFactory extends AbstractFactory
{
    protected function createLoader(): Loader
    {
        //this is only for the tests.
        //normally you'd create a specific loader, which would get the data
        //from a backend somewhere.

        // 6 transitions, 5 states
        $transitions = [];
        $new = new State('new', \Izzum\StateMachine\State::TYPE_INITIAL);
        $a = new State('a');
        $b = new State('b');
        $c = new State('c');
        $done = new State('done', \Izzum\StateMachine\State::TYPE_FINAL);
        $transitions[] = new Transition($new, $a, null, 'Izzum\Rules\TrueRule', 'Izzum\Command\NullCommand');
        //can never go, a false rule
        $transitions[] = new Transition($a, $done, null, 'Izzum\Rules\FalseRule', 'Izzum\Command\NullCommand');
        $transitions[] = new Transition($a, $b, null, 'Izzum\Rules\TrueRule', 'Izzum\Command\NullCommand');
        //can never go, a false rule
        $transitions[] = new Transition($b, $c, null, 'Izzum\Rules\FalseRule', 'Izzum\Command\NullCommand');
        $transitions[] = new Transition($c, $done, null, 'Izzum\Rules\TrueRule', 'Izzum\Command\NullCommand');
        $transitions[] = new Transition($b, $done, null, 'Izzum\Rules\TrueRule', 'Izzum\Command\NullCommand');
        return new LoaderArray($transitions);


    }

    protected function getMachineName(): string
    {
        return 'factory-test';
    }

    protected function createAdapter(): Adapter
    {
        $io = new Memory();
        $io->clear();
        return $io;
    }


    protected function createBuilder(): EntityBuilder
    {
        return new EntityBuilder();
    }

}
