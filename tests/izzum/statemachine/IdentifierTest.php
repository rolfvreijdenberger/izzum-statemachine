<?php
namespace izzum\statemachine;
use izzum\statemachine\persistence\Memory;

/**
 * @group statemachine
 * @group Context
 * 
 * @author rolf
 *        
 */
class IdentifierTest extends \PHPUnit_Framework_TestCase {

    /**
     * @test
     */
    public function shouldBehave()
    {
        $entity_id = "123";
        $machine = "test";
        $identifier = new Identifier($entity_id, $machine);
        $this->assertEquals($entity_id, $identifier->getEntityId());
        $this->assertEquals($machine, $identifier->getMachine());
        
        //getId
        $this->assertContains('test', $identifier->getId(true));
        $this->assertContains('test', $identifier->getId(false));
        $this->assertContains($entity_id, $identifier->getId(false));
        $this->assertContains($entity_id, $identifier->getId(true));
        $this->assertContains('machine', $identifier->getId(true));
        $this->assertContains('id', $identifier->getId(true));
        $this->assertNotContains('machine', $identifier->getId(false));
        $this->assertNotContains('id', $identifier->getId(false));

        //string representation
        $this->assertContains($entity_id, $identifier->toString());
        $this->assertContains($machine, $identifier->toString());
        $this->assertContains('Identifier', $identifier->toString());
        //__toString
        $this->assertContains($entity_id, $identifier . "");
        $this->assertContains($machine, $identifier . "");
        $this->assertContains('Identifier', $identifier . "");
        
        
        $identifier->setEntityId('321');
        $this->assertEquals('321', $identifier->getEntityId());
        
        
    }
}