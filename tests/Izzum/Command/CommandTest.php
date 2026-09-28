<?php

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use Codeception\Attribute\Group;
use Izzum\Command\Command;
use Izzum\Command\NullCommand;
use Izzum\Command\Exception;
use Izzum\Command\ExceptionCommand;
use Izzum\Command\Composite;
use Izzum\Command\Closure;

/**
 * This class tests the basic workings of the Core Command package.
 * Since all commands build upon the Core, these tests should cover all
 * public methods and workings
 * @author rolf
 *
 */
#[Group('command')]
class CommandTest extends TestCase
{
    public function testCommand()
    {
        //use a command that has a reference to a list.
        //we can then check the list to see what happens to it.
        $list = [];
        $command = new AddToListCommand($list);

        //check basics
        $this->assertFalse(in_array('Izzum\Command\IComposite', class_implements($command)));
        $this->assertTrue(in_array('Izzum\Command\ICommand', class_implements($command)));

        //check that execute does something
        $this->assertEquals(0, count($list));
        $command->execute();
        $this->assertEquals(1, count($list));
        $command->execute();
        $this->assertEquals(2, count($list));

        $this->assertStringContainsString('AddToListCommand', $command . '', '__toString()');
    }

    public function testNullCommand()
    {
        //test creation
        $command = new NullCommand();
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
            $this->fail("command should throw an exception");
        } catch (\Exception $e) {
            $this->assertTrue(is_a($e, 'Izzum\Command\Exception'), "should be of type Izzum\Command\Exception");
            $this->assertTrue(is_a($e, 'Exception'));
            $this->assertEquals($message, $e->getMessage());
            $this->assertEquals($code, $e->getCode());
        }
    }


    public function testClosureCommandOneArgument()
    {
        //one argument for closure
        $output = null;
        $closure = function (&$output): void {
            $output = 1;
        };
        $command = new Closure($closure, [&$output]);
        $command->execute();
        $this->assertEquals(1, $output);

    }

    public function testClosureCommandMultipleArguments()
    {
        //multiple arguments for closure
        $output = null;
        $input = 5;
        $closure = function (&$output, $input): void {
            $output = $input;
        };
        $command = new Closure($closure, [&$output, $input]);
        $command->execute();
        $this->assertEquals(5, $output);
    }

    public function testCompositeCommand()
    {
        $composite = new Composite();

        //test basics of this command
        $this->assertTrue(in_array('Izzum\Command\IComposite', class_implements($composite)));
        $this->assertTrue(in_array('Izzum\Command\ICommand', class_implements($composite)));

        $list = [];
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


        $list = [];
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

        //nested composite
        $composite = new Composite();
        $nested = new Composite();
        $composite->add($nested);
    }


    public function testExceptionFromCommandException()
    {
        $command = new ExceptionCommand("test", 111);
        try {
            $command->execute();
            $this->fail("exception should have been thrown");
        } catch (\Exception $e) {
            $this->assertEquals(111, $e->getCode());
            $this->assertEquals('test', $e->getMessage());
            $this->assertEquals("Izzum\Command\Exception", $e::class);
        }
    }

    #[Test]
    public function shouldThrowNormalAndCommandException()
    {
        //coverage test
        $command = new throwsExceptionCommand(true);
        try {
            $command->execute();
            $this->fail('should throw exception');
        } catch (Exception) {

        }

        $command = new throwsExceptionCommand(false);
        try {
            $command->execute();
            $this->fail('should throw exception');
        } catch (Exception) {

        }


    }
}

/**
 * helper command class to test commands and composite commands
 * @author rolf
 *
 */
class AddToListCommand extends Command
{
    private static $ID = 0;
    private array $list;
    /**
     * @param array $list passed by reference so we can acces 'list' from outside this class
     */
    public function __construct(&$list)
    {
        //pass by reference
        $this->list = &$list;
    }

    protected function _execute(): void
    {
        //add an incrementing counter to the list reference
        $this->list[] = self::$ID++;
    }
}


class throwsExceptionCommand extends Command
{
    public function __construct(private $bool) {}

    protected function _execute(): void
    {
        if ($this->bool) {
            throw new Exception('oops');
        } else {
            throw new \Exception('ooops');
        }
    }
}
