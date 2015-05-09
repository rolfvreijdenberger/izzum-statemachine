<?php
namespace izzum\statemachine;
use izzum\statemachine\utils\EntityNull;
use izzum\command\ExceptionCommand;
use izzum\command\Command;
use izzum\command\Null;
use izzum\statemachine\builder\ModelBuilder;
use izzum\statemachine\utils\Utils;

/**
 * @group statemachine
 * @group state
 * @author rolf
 *
 */
class UtilsTest extends \PHPUnit_Framework_TestCase {
    
    /**
     * @test
     */
    public function shouldGetCommandWithEntity(){
    
    	$command_name = 'izzum\statemachine\IncreaseId';
    	$entity = new \stdClass();
    	$entity->id = 0;
    	$entity->event = null;
    	//modelbuilder always returns the model we give it in the constructor
    	$context = new Context(new Identifier('1','test'), new ModelBuilder($entity));
    	$event = null;
    	
    	$command = Utils::getCommand($command_name, $context, $event);
    	$this->assertTrue(is_a($command, 'izzum\command\Composite'));
    	$this->assertContains('IncreaseId', $command->toString());
    	$this->assertEquals(0, $entity->id);
    	$this->assertEquals(null, $entity->event);
    	$command->execute();
    	$this->assertEquals(1, $entity->id);
    	$this->assertEquals(null, $entity->event);
    }
    
    /**
     * @test
     */
    public function shouldGetCommandWithEntityAndEvent(){
    
    	$command_name = 'izzum\statemachine\IncreaseId';
    	$entity = new \stdClass();
    	$entity->id = 0;
    	$entity->event = null;
    	//modelbuilder always returns the model we give it in the constructor
    	$context = new Context(new Identifier('1','test'), new ModelBuilder($entity));
    	$event = 'event';
    	 
    	$command = Utils::getCommand($command_name, $context, $event);
    	$this->assertTrue(is_a($command, 'izzum\command\Composite'));
    	$this->assertContains('IncreaseId', $command->toString());
    	$this->assertEquals(0, $entity->id);
    	$this->assertEquals(null, $entity->event);
    	$command->execute();
    	$this->assertEquals(1, $entity->id);
    	$this->assertEquals($event, $entity->event);
    }
    
    /**
     * @test
     */
    public function shouldGetCompositeCommand(){
    
    	//id should be increased three times
    	$command_name = 'izzum\statemachine\IncreaseId,izzum\statemachine\IncreaseId,izzum\statemachine\IncreaseId';
    	$entity = new \stdClass();
    	$entity->id = 0;
    	$entity->event = null;
    	//modelbuilder always returns the model we give it in the constructor
    	$context = new Context(new Identifier('1','test'), new ModelBuilder($entity));
    	$event = 'event';
    
    	$command = Utils::getCommand($command_name, $context, $event);
    	$this->assertTrue(is_a($command, 'izzum\command\Composite'));
    	$this->assertContains('IncreaseId', $command->toString());
    	$this->assertEquals(0, $entity->id);
    	$this->assertEquals(null, $entity->event);
    	$command->execute();
    	$this->assertEquals(3, $entity->id);
    	$this->assertEquals($event, $entity->event);
    	$command->execute();
    	$this->assertEquals(6, $entity->id);
    	$this->assertEquals($event, $entity->event);
    }
    
    /**
     * @test
     */
    public function shouldGetNullCommand(){
    
    	$command_name = '';
    	$context = new Context(new Identifier('1','test'));
    	$event = '';
    	 
    	$command = Utils::getCommand($command_name, $context, $event);
    	$this->assertTrue(is_a($command, 'izzum\command\Null'));
    	
    	$command_name = null;
    	$context = new Context(new Identifier('1','test'));
    	$event = 'event';
    	
    	$command = Utils::getCommand($command_name, $context, $event);
    	$this->assertTrue(is_a($command, 'izzum\command\Null'));
    	 
    }
    
    /**
     * @test
     */
    public function shouldGetExceptionForInvalidCommand(){
    	$command_name = 'izzum\statemachine\CannotCreate';
    	$context = new Context(new Identifier('1','test'));
    	$event = '';
    	
    	try {
    		$command = Utils::getCommand($command_name, $context, $event);
    		$this->fail('should not come here, command should throw exception on failure');
    	} catch (Exception $e) {
    		$this->assertEquals(Exception::COMMAND_CREATION_FAILURE, $e->getCode());
    		$this->assertContains('cannot create', $e->getMessage());
    		$this->assertContains('objects to construction', $e->getMessage());
    		//echo $e->getMessage() . PHP_EOL;
    	}
    }
    
    /**
     * @test
     */
    public function shouldGetExceptionForNonExistingCommand(){
    
    	$command_name = 'bogus';
    	$context = new Context(new Identifier('1','test'));
    	$event = '';
    
    	try {
    		$command = Utils::getCommand($command_name, $context, $event);
    		$this->fail('should not come here, command does not exist');
    	} catch (Exception $e) {
    		$this->assertEquals(Exception::COMMAND_CREATION_FAILURE, $e->getCode());
    		$this->assertContains('class does not exist', $e->getMessage());
    		//echo $e->getMessage() . PHP_EOL;
    	}
    }
    
    /**
     * @test
     */
    public function shouldReturnCorrectTransitionName(){
        $from = 'state-from';
        $to = 'state-to';
        $this->assertEquals($from . Utils::STATE_CONCATENATOR . $to, Utils::getTransitionName($from, $to));
    }
    
   
}

//helper class, increases the id on an entity when executed.
class IncreaseId extends Command {
	private $entity;
	private $event;
	public function __construct($entity)
	{
		$this->entity = $entity;
	}
	public function setEvent($event)
	{
		$this->event = $event;
	}
	
	protected function _execute()
	{
		//proof that we can manipulate the entity and that 'setEvent' was called.
		$this->entity->id += 1;
		$this->entity->event = $this->event;
	}
}

//helper class, throws exception on execution
class CannotCreate extends Command {
	public function __construct($entity)
	{
		throw new Exception("cannot create");
	}

	protected function _execute(){}
}

