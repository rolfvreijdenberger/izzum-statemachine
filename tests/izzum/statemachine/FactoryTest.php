<?php
namespace izzum\statemachine;
use izzum\statemachine\utils\Utils;


/**
 * @group statemachine
 * @group factory
 * @author rolf
 *
 */
class FactoryTest extends \PHPUnit_Framework_TestCase {
    
    /**
     * @test
     */
    public function shouldCreateAndUseSimpleTestFactory() {
        $machine_name = 'factory-test';
            
        //scenario: testing instantiation and some checks
        $factory = new SimpleTestFactory();
        //instantation oke!
        $machine = $factory->getStateMachine(1);
        //test the machine
        $context = $machine->getContext();
        $this->assertCount(0,$context->getPersistenceAdapter()->getEntityIds($machine_name));
        $machine->add();
        $this->assertCount(1, $context->getPersistenceAdapter()->getEntityIds($machine_name));
        $context->getPersistenceAdapter()->getEntityIds($machine_name);
        $this->assertEquals($machine_name, $context->getMachine(),'name as provided by factory');
        $this->assertEquals($machine_name, $machine->getContext()->getMachine(),'name as provided by factory');
        $this->assertEquals($machine, $context->getStateMachine(),'bidirectional association check');
        $this->assertCount(5, $machine->getStates());
        $this->assertCount(6, $machine->getTransitions());
        
        $this->assertTrue(is_a($context->getPersistenceAdapter(), 'izzum\statemachine\persistence\Memory'));
        $this->assertTrue(is_a($context->getBuilder(), 'izzum\statemachine\EntityBuilder'));   
        //echo $machine->toString();
   
        
    }
  
    
}

namespace izzum\statemachine;
use izzum\statemachine\persistence\Memory;
class SimpleTestFactory extends AbstractFactory{
    protected function createLoader() {
            //this is only for the tests.
            //normally you'd create a specific loader, which would get the data
            //from a backend somewhere.
        
            // 6 transitions, 5 states
            $transitions = array();
            $new = new State('new', \izzum\statemachine\State::TYPE_INITIAL);
            $a = new State('a');
            $b = new State('b');
            $c = new State('c');
            $done = new State('done', \izzum\statemachine\State::TYPE_FINAL);
            $transitions[] = new Transition($new, $a, null,'izzum\rules\TrueRule', 'izzum\command\NullCommand');
            //can never go, a false rule
            $transitions[] = new Transition($a, $done, null, 'izzum\rules\FalseRule', 'izzum\command\NullCommand');
            $transitions[] = new Transition($a, $b, null, 'izzum\rules\TrueRule', 'izzum\command\NullCommand');
            //can never go, a false rule
            $transitions[] = new Transition($b, $c, null, 'izzum\rules\FalseRule', 'izzum\command\NullCommand');
            $transitions[] = new Transition($c, $done, null, 'izzum\rules\TrueRule', 'izzum\command\NullCommand');
            $transitions[] = new Transition($b, $done, null, 'izzum\rules\TrueRule', 'izzum\command\NullCommand');
            return new loader\LoaderArray($transitions);
            
        
    }

    protected function getMachineName() {
       return 'factory-test';
    }

    protected function createAdapter() {
        $io = new Memory();
        $io->clear();
        return $io;
    }


    protected function createBuilder() {
        return new EntityBuilder();
    }

}