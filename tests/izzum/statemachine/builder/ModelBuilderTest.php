<?php
namespace izzum\statemachine\builder;
use izzum\statemachine\builder\ModelBuilder;
use izzum\statemachine\Identifier;
/**
 * 
 * @group statemachine
 * @group builder
 * @author rolf
 *
 */
class ModelBuilderTest extends \PHPUnit_Framework_TestCase {
    
  
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
