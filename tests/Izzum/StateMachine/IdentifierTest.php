<?php

namespace Izzum\StateMachine;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use Codeception\Attribute\Group;

/**
 *
 * @author rolf
 *
 */
#[Group('statemachine', 'Context')]
class IdentifierTest extends TestCase
{
    #[Test]
    public function shouldBehave()
    {
        $entityId = "123";
        $machine = "test";
        $identifier = new Identifier($entityId, $machine);
        $this->assertEquals($entityId, $identifier->getEntityId());
        $this->assertEquals($machine, $identifier->getMachine());

        //getId
        $this->assertStringContainsString('test', $identifier->getId(true));
        $this->assertStringContainsString('test', $identifier->getId(false));
        $this->assertStringContainsString($entityId, $identifier->getId(false));
        $this->assertStringContainsString($entityId, $identifier->getId(true));
        $this->assertStringContainsString('machine', $identifier->getId(true));
        $this->assertStringContainsString('id', $identifier->getId(true));
        $this->assertStringNotContainsString('machine', $identifier->getId(false));
        $this->assertStringNotContainsString('id', $identifier->getId(false));

        //string representation
        $this->assertStringContainsString($entityId, $identifier->toString());
        $this->assertStringContainsString($machine, $identifier->toString());
        $this->assertStringContainsString('Identifier', $identifier->toString());
        //__toString
        $this->assertStringContainsString($entityId, $identifier . "");
        $this->assertStringContainsString($machine, $identifier . "");
        $this->assertStringContainsString('Identifier', $identifier . "");


        $identifier->setEntityId('321');
        $this->assertEquals('321', $identifier->getEntityId());


    }
}
