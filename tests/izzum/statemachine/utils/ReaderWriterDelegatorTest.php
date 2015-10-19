<?php
namespace izzum\statemachine\utils;
use izzum\statemachine\persistence\Memory;
use izzum\statemachine\Transition;
use izzum\statemachine\State;
use izzum\statemachine\StateMachine;
use izzum\statemachine\Context;
use izzum\statemachine\Identifier;
use izzum\statemachine\Entity;
use izzum\statemachine\Exception;
use izzum\statemachine\loader\Loader;
use izzum\statemachine\loader\XML;
use izzum\statemachine\utils\Utils;

/**
 * @group statemachine
 * @group loader
 * @group xml
 * 
 * @author rolf
 *        
 */
class ReaderWriterDelegatorTest extends \PHPUnit_Framework_TestCase {

    /**
     * @test
     */
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
    
    /**
     * @test
     */
    public function shouldBehave()
    {
        $loader = XML::createFromFile(__DIR__ . '/../../../../assets/xml/example.xml');
        $writer = new Memory();
        Memory::clear();
        $delegator = new ReaderWriterDelegator($loader, $writer);
        
        $this->assertSame($loader, $delegator->getReader());
        $this->assertSame($writer, $delegator->getWriter());
        $this->assertContains('Memory', $delegator->toString());
        $this->assertContains('XML', $delegator->toString());
        $this->assertContains('Memory', $delegator . '');
        $this->assertContains('XML', $delegator . '');
        $this->assertCount(0, $delegator->getEntityIds('test'));
        $this->assertFalse($delegator->isPersisted(new Identifier('123', 'bogus')));
        $delegator->setFailedTransition(new Identifier('foo', 'bar'), new Transition(new State('foo'), new State('bar')), new \Exception('bogus'));
       
    }
}