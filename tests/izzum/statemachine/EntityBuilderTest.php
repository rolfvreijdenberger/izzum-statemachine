<?php
namespace izzum\statemachine;
use izzum\statemachine\builder\ModelBuilder;
/**
 * Tests the public methods of builders.
 * 
 * @group statemachine
 * @group EntityBuilder
 * @author rolf
 *
 */
class EntityBuilderTest extends \PHPUnit_Framework_TestCase {
    
    public function testDefaultBuilder()
    {
        //create Entity in default state. this is enough to pass it 
        //to the builder
        $object_1 = new Identifier(-1, 'order');
        $object_2 = new Identifier(-2, 'order');
        $this->assertNotEquals($object_1, $object_2);
        
        
        
        //scenario: call it twice with same object
        $builder = new EntityBuilder();
        $result_1 = $builder->getEntity($object_1);
        $this->assertEquals($object_1, $result_1);
        //same result when we call it again (should be cached, but we can only test
        //this when we override the protected build() method of the builder).
        $result_2 = $builder->getEntity($object_1);
        $this->assertEquals($object_1, $result_2);
        $this->assertEquals($result_1, $result_2, 'obviously');
        $this->assertEquals('izzum\statemachine\EntityBuilder', $builder->toString());
        
        //scenario: call it with different objects
        $builder = new EntityBuilder();
        $result_1 = $builder->getEntity($object_1);
        $this->assertEquals($object_1, $result_1);
        //different result when we call it again
        $result_2 = $builder->getEntity($object_2);
        $this->assertEquals($object_2, $result_2);
    }
    
    /**
     * tests the overriden function for building/.
     * this is also used to check if the caching works
     */
    public function testBuilderOverride()
    {
        //create Entity in default state. this is enough to pass it
        //to the builder
        $object_1 = new Identifier(-1, 'order');
        $object_2 = new Identifier(-2, 'order');
        $this->assertNotEquals($object_1, $object_2);
        
        
        //scenario: call it with same objects to check CACHING! on the differently
        //returned references
        $builder = new EntityBuilderStdClss();
        $result_1 = $builder->getEntity($object_1);
        $this->assertNotEquals($object_1, $result_1, 'returns something different than input');
        //check values
        $this->assertEquals($object_1->getEntityId(), $result_1->entity_id);
        $this->assertEquals($object_1->getMachine(), $result_1->machine);
        //expect same result when we call it again
        $result_2 = $builder->getEntity($object_1);
        $this->assertEquals($object_1->getEntityId(), $result_2->entity_id);
        $this->assertEquals($object_1->getMachine(), $result_2->machine);
        //identity is exactly the same (same cached object)
        $this->assertEquals($result_1, $result_2, 'same identity because cached');
        $this->assertEquals('izzum\statemachine\EntityBuilderStdClss', $builder->toString());
        
        //scenario: call it twice with different object
        $builder = new EntityBuilderStdClss();
        $result_1 = $builder->getEntity($object_1);
        //different result when we call it again
        $result_2 = $builder->getEntity($object_2);
        $this->assertNotEquals($result_1, $result_2);
        
    }
    
    /**
     * @test
     */
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
    
    /**
     * @test
     */
    public function shouldUseModelBuilderCorrectly()
    {
    	$identifier = new Identifier(-1, 'order');
    	$entity = new \stdClass();
    	$entity->id = 123;
    	$builder = new ModelBuilder($entity);
    	$this->assertEquals($entity, $builder->getEntity($identifier));
    	$this->assertEquals($entity, $builder->getEntity($identifier, true));
    	$this->assertNotEquals($identifier, $entity);
    	$this->assertNotEquals($identifier, $builder->getEntity($identifier), 'default builder returns $identifer and modelbuilder does not');
    	
    	//now use a different model
    	$builder = new ModelBuilder($identifier);
    	$this->assertEquals($identifier, $builder->getEntity($identifier), 'the argument being the same is the method signature, it has no influence on the object returned (in this case)');
    	$this->assertEquals($identifier, $builder->getEntity($identifier, true));
    
    }
}

/**
 * helper class. this reference builder builds a stdClss.
 */
class EntityBuilderStdClss extends EntityBuilder {
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
class EntityBuilderException extends EntityBuilder {
    private $bool;
    public function __construct($bool) {
        $this->bool = $bool;
    }
    protected function build(Identifier $identifier)
    {
        if($this->bool) {
            throw new Exception('oops', 0);
        } else {
            throw new \Exception('ooops');
        }
    }
}
