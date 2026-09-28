<?php

namespace Izzum\StateMachine;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use Codeception\Attribute\Group;

/**
 * Tests the public methods of builders.
 *
 * @author rolf
 *
 */
#[Group('statemachine', 'EntityBuilder')]
class EntityBuilderTest extends TestCase
{
    public function testDefaultBuilder()
    {
        //create Entity in default state. this is enough to pass it
        //to the builder
        $object1 = new Identifier(-1, 'order');
        $object2 = new Identifier(-2, 'order');
        $this->assertNotEquals($object1, $object2);



        //scenario: call it twice with same object
        $builder = new EntityBuilder();
        $result1 = $builder->getEntity($object1);
        $this->assertEquals($object1, $result1);
        //same result when we call it again (should be cached, but we can only test
        //this when we override the protected build() method of the builder).
        $result2 = $builder->getEntity($object1);
        $this->assertEquals($object1, $result2);
        $this->assertEquals($result1, $result2, 'obviously');
        $this->assertEquals('Izzum\StateMachine\EntityBuilder', $builder->toString());

        //scenario: call it with different objects
        $builder = new EntityBuilder();
        $result1 = $builder->getEntity($object1);
        $this->assertEquals($object1, $result1);
        //different result when we call it again
        $result2 = $builder->getEntity($object2);
        $this->assertEquals($object2, $result2);

        $this->assertStringContainsString('EntityBuilder', $builder . '', '__toString()');
    }

    /**
     * tests the overriden function for building/.
     * this is also used to check if the caching works
     */
    public function testBuilderOverride()
    {
        //create Entity in default state. this is enough to pass it
        //to the builder
        $object1 = new Identifier(-1, 'order');
        $object2 = new Identifier(-2, 'order');
        $this->assertNotEquals($object1, $object2);


        //scenario: call it with same objects to check CACHING! on the differently
        //returned references
        $builder = new EntityBuilderStdClss();
        $result1 = $builder->getEntity($object1);
        $this->assertNotEquals($object1, $result1, 'returns something different than input');
        //check values
        $this->assertEquals($object1->getEntityId(), $result1->entity_id);
        $this->assertEquals($object1->getMachine(), $result1->machine);
        //expect same result when we call it again
        $result2 = $builder->getEntity($object1);
        $this->assertEquals($object1->getEntityId(), $result2->entity_id);
        $this->assertEquals($object1->getMachine(), $result2->machine);
        //identity is exactly the same (same cached object)
        $this->assertEquals($result1, $result2, 'same identity because cached');
        $this->assertEquals('Izzum\StateMachine\EntityBuilderStdClss', $builder->toString());

        //scenario: call it twice with different object
        $builder = new EntityBuilderStdClss();
        $result1 = $builder->getEntity($object1);
        //different result when we call it again
        $result2 = $builder->getEntity($object2);
        $this->assertNotEquals($result1, $result2);

    }

    #[Test]
    public function shouldThrowException()
    {
        $identifier = new Identifier(-1, 'order');
        $builder = new EntityBuilderException(true);
        try {
            $builder->getEntity($identifier);
            $this->fail('should  throw exception');
        } catch (Exception $e) {
            $this->assertEquals(0, $e->getCode());
        }

        $builder = new EntityBuilderException(false);
        try {
            $builder->getEntity($identifier);
            $this->fail('should  throw exception');
        } catch (Exception $e) {
            $this->assertEquals(Exception::BUILDER_FAILURE, $e->getCode());
        }
    }
}

/**
 * helper class. this reference builder builds a stdClss.
 */
class EntityBuilderStdClss extends EntityBuilder
{
    #[\Override]
    protected function build(Identifier $identifier)
    {
        $output = new \stdClass();
        $output->entity_id = $identifier->getEntityId();
        $output->machine = $identifier->getMachine();
        return $output;
    }
}

/**
 * helper class. this reference builder builds a stdClss.
 */
class EntityBuilderException extends EntityBuilder
{
    public function __construct(private $bool) {}
    #[\Override]
    protected function build(Identifier $identifier)
    {
        if ($this->bool) {
            throw new Exception('oops', 0);
        } else {
            throw new \Exception('ooops');
        }
    }
}
