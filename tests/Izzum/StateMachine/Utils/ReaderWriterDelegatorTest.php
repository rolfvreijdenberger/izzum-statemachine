<?php

namespace Izzum\StateMachine\Utils;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use Codeception\Attribute\Group;
use Izzum\StateMachine\Persistence\Memory;
use Izzum\StateMachine\Transition;
use Izzum\StateMachine\State;
use Izzum\StateMachine\StateMachine;
use Izzum\StateMachine\Context;
use Izzum\StateMachine\Identifier;
use Izzum\StateMachine\Exception;
use Izzum\StateMachine\Loader\XML;

/**
 *
 * @author rolf
 *
 */
#[Group('statemachine', 'loader', 'xml')]
class ReaderWriterDelegatorTest extends TestCase
{
    #[Test]
    public function shouldLoadAndWriteViaDelegator()
    {
        $loader = XML::createFromFile(__DIR__ . '/../loader/fixture-example.xml');
        $writer = new Memory();
        $identifier = new Identifier('readerwriter-test', 'test-machine');
        $delegator = new ReaderWriterDelegator($loader, $writer);
        $context = new Context($identifier, null, $delegator);
        $machine = new StateMachine($context);
        $this->assertCount(0, $machine->getTransitions());
        $count = $delegator->load($machine);
        //add to the backend
        $this->assertTrue($context->add('a'));

        $this->assertCount(4, $machine->getTransitions(), 'there is a regex transition that adds 2 transitions (a-c and b-c)');
        $this->assertEquals(4, $count);
        $this->assertTrue($machine->ab());

        //get the data from the memory storage facility
        $data = $writer->getStorageFromRegistry($machine->getContext()->getIdentifier());
        $this->assertEquals('b', $data->state);
        $this->assertEquals('b', $machine->getCurrentState()->getName());
        $this->assertTrue($machine->bdone());
        $data = $writer->getStorageFromRegistry($machine->getContext()->getIdentifier());
        $this->assertEquals('done', $data->state);

    }

    #[Test]
    public function shouldBehave()
    {
        $loader = XML::createFromFile(__DIR__ . '/../../../../assets/xml/example.xml');
        $writer = new Memory();
        Memory::clear();
        $delegator = new ReaderWriterDelegator($loader, $writer);

        $this->assertSame($loader, $delegator->getReader());
        $this->assertSame($writer, $delegator->getWriter());
        $this->assertStringContainsString('Memory', $delegator->toString());
        $this->assertStringContainsString('XML', $delegator->toString());
        $this->assertStringContainsString('Memory', $delegator . '');
        $this->assertStringContainsString('XML', $delegator . '');
        $this->assertCount(0, $delegator->getEntityIds('test'));
        $this->assertFalse($delegator->isPersisted(new Identifier('123', 'bogus')));
        $delegator->setFailedTransition(new Identifier('foo', 'bar'), new Transition(new State('foo'), new State('bar')), new \Exception('bogus'));

    }
}
