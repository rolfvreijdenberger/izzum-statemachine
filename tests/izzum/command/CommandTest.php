<?php
use izzum\command\Command;
use izzum\command\NullCommand;
use izzum\command\Exception;
use izzum\command\ExceptionCommand;
use izzum\command\Composite;
use izzum\command\Closure;
use izzum\command\ICommand;
use izzum\command\IComposite;
/**
 * This class tests the basic workings of the Core Command package.
 * Since all commands build upon the Core, these tests should cover all
 * public methods and workings
 * @group command
 * @author rolf
 *
 */
class CommandTest extends PHPUnit_Framework_TestCase {


    public function testCommand()
    {
        //use a command that has a reference to a list.
        //we can then check the list to see what happens to it.
        $list = array();
        $command = new AddToListCommand($list);

        //check basics
        $this->assertTrue(is_subclass_of($command, 'izzum\command\Command'));
        $this->assertFalse(in_array('izzum\command\IComposite', class_implements($command)));
        $this->assertTrue(in_array('izzum\command\ICommand', class_implements($command)));

        //check that execute does something
        $this->assertEquals(0, count($list));
        $command->execute();
        $this->assertEquals(1, count($list));
        $command->execute();
        $this->assertEquals(2, count($list));

        $this->assertNotNull($command->toString());
        $this->assertContains('AddToListCommand', $command . '', '__toString()');
    }

    public function testNullCommand()
    {
        //test creation
        $command = new izzum\command\NullCommand();
        $command->execute();
    }

    public function testException()
    {
        //scenario: use a helper command that throws a regular exception
        //it should bubble up as an exception of the type in the command package
        $message = "test";
        $code = 123;
        $command = new ExceptionCommand($message, $code);
        try {
           $command->execute();
           $this->shouldNotComeHere("command should throw an exception");
        } catch (\Exception $e)
        {
            $this->assertTrue(is_a($e, 'izzum\command\Exception'), "should be of type izzum\Exception");
            $this->assertTrue(is_a($e, 'Exception'));
            $this->assertEquals($message, $e->getMessage());
            $this->assertEquals($code, $e->getCode());
        }
    }


    public function testClosureCommandOneArgument()
    {
        //one argument for closure
        $output;
        $closure = function(&$output) { $output = 1;};
        $command = new Closure($closure, array(&$output));
        $this->assertNull($output);
        $command->execute();
        $this->assertEquals(1, $output);

    }

    public function testClosureCommandMultipleArguments()
    {
        //multiple arguments for closure
        $output;
        $input = 5;
        $closure = function(&$output, $input) { $output = $input;};
        $command = new Closure($closure, array(&$output, $input));
        $this->assertNull($output);
        $command->execute();
        $this->assertEquals(5, $output);
    }

    public function testCompositeCommand()
    {
        $composite = new Composite();

        //test basics of this command
        $this->assertTrue(is_subclass_of($composite, 'izzum\command\Command'));
        $this->assertTrue(in_array('izzum\command\IComposite', class_implements($composite)));
        $this->assertTrue(in_array('izzum\command\ICommand', class_implements($composite)));

        $list = array();
        //create 3 commands with a reference to the same list
        $command1 = new AddToListCommand($list);
        $command2 = new AddToListCommand($list);
        $command3 = new AddToListCommand($list);

        //executing the composite does not affect list
        $composite->execute();
        $this->assertEquals(0, count($list));

        //add commands to composite 'in order'
        $composite->add($command1);
        $composite->add($command2);
        $composite->add($command3);

        //execute the composite, we expect to have an array with incrementing numbers,
        //proving the composite executes 'in order'
        $composite->execute();
        $this->assertEquals(3, count($list));
        $this->assertTrue($list[1] == ($list[0] + 1));
        $this->assertTrue($list[2] == ($list[1] + 1));


        $list = array();
        $command1 = new AddToListCommand($list);
        $command2 = new AddToListCommand($list);
        $command3 = new AddToListCommand($list);
        $composite = new Composite();
        //add the commands one by one and check everything works out
        //check add() and contains()
        $composite->add($command1);
        $this->assertTrue($composite->contains($command1));
        $this->assertFalse($composite->contains($command2));
        $this->assertFalse($composite->contains($command3));
        $composite->add($command2);
        $this->assertTrue($composite->contains($command1));
        $this->assertTrue($composite->contains($command2));
        $this->assertFalse($composite->contains($command3));
        $composite->add($command3);
        $this->assertTrue($composite->contains($command1));
        $this->assertTrue($composite->contains($command2));
        $this->assertTrue($composite->contains($command3));

        //remove one by one
        //check remove() and contains()
        $this->assertEquals(3, $composite->count());
        $composite->remove($command1);
        $this->assertFalse($composite->contains($command1));
        $this->assertTrue($composite->contains($command2));
        $this->assertTrue($composite->contains($command3));
        $composite->remove($command2);
        $this->assertFalse($composite->contains($command1));
        $this->assertFalse($composite->contains($command2));
        $this->assertTrue($composite->contains($command3));
        $composite->remove($command3);
        $this->assertFalse($composite->contains($command1));
        $this->assertFalse($composite->contains($command2));
        $this->assertFalse($composite->contains($command3));
        $this->assertNotNull($composite->toString());

        //nested composite
        $composite = new Composite();
        $nested = new Composite();
        $composite->add($nested);
        $this->assertNotNull($composite->toString());
    }


    public function testExceptionFromCommandException()
    {
        $command = new ExceptionCommand("test", 111);
        try {
            $command->execute();
            $this->shouldNotComeHere("exception should have been thrown");
        } catch (\Exception $e)
        {
            $this->assertEquals(111, $e->getCode());
            $this->assertEquals('test', $e->getMessage());
            $this->assertEquals("izzum\command\Exception", get_class($e));
        }
    }

    /**
     * @test
     */
    public function shouldThrowNormalAndCommandException()
    {
        //coverage test
        $command = new throwsExceptionCommand(true);
        try {
            $command->execute();
            $this->fail('should throw exception');
        } catch (Exception $e) {

        }

        $command = new throwsExceptionCommand(false);
        try {
            $command->execute();
            $this->fail('should throw exception');
        } catch (Exception $e) {

        }


    }
}

/**
 * helper command class to test commands and composite commands
 * @author rolf
 *
 */
class AddToListCommand extends izzum\command\Command {
    private static $ID = 0;
    /**
     * @param array $list passed by reference so we can acces 'list' from outside this class
     */
    public function __construct(&$list)
    {
        //pass by reference
        $this->list = &$list;
    }

    protected function _execute()
    {
        //add an incrementing counter to the list reference
        $this->list[] = self::$ID++;
    }
}


class throwsExceptionCommand extends izzum\command\Command {
    private $bool;
    public function __construct($bool)
    {
        $this->bool = $bool;
    }

    protected function _execute()
    {
        if($this->bool) {
            throw new  Exception('oops');
        } else {
            throw new \Exception('ooops');
        }
    }
}

