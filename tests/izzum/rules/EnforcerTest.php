<?php
use izzum\rules\Boolean;
use izzum\rules\Enforcer;
use izzum\command\Command;
use izzum\command\Exception;
use izzum\command\ExceptionCommand;

/**
 * @group command
 * @group rules
 * @group enforcer
 *
 */
class EnforcerTest extends PHPUnit_Framework_TestCase
{
    
    public function testEnforcerInstance()
    {
        //test an enforcer instance
        
        //test rule 'true'
        $rule = new Boolean(true);
        $true = new CountCommand(10, true);
        $false = new CountCommand(20, false);
        $enforcer = new Enforcer($rule, $true, $false);
        $this->assertEquals(10, $true->getCount());
        $this->assertEquals(20, $false->getCount());
        $result = $enforcer->enforce();
        $this->assertTrue($result);
        $this->assertEquals(11, $true->getCount(), 'should be increased by 1');
        $this->assertEquals(20, $false->getCount());
        
        //test rule 'false'
        $rule = new Boolean(false);
        $true = new CountCommand(10, true);
        $false = new CountCommand(20, false);
        $enforcer = new Enforcer($rule, $true, $false);
        $this->assertEquals(10, $true->getCount());
        $this->assertEquals(20, $false->getCount());
        $result = $enforcer->enforce();
        $this->assertFalse($result);
        $this->assertEquals(10, $true->getCount());
        $this->assertEquals(19, $false->getCount(), 'should be decreased by 1');
    }
    
    
    public function testEnforcerStatic()
    {
        //test the class methods of the enforcer
        
        //test rule 'true'
        $rule = new Boolean(true);
        $true = new CountCommand(10, true);
        $false = new CountCommand(20, false);
        $this->assertEquals(10, $true->getCount());
        $this->assertEquals(20, $false->getCount());
        $result = Enforcer::obey($rule, $true, $false);
        $this->assertTrue($result);
        $this->assertEquals(11, $true->getCount());
        $this->assertEquals(20, $false->getCount());
        
        //test rule 'false'
        $rule = new Boolean(false);
        $true = new CountCommand(10, true);
        $false = new CountCommand(20, false);
        $this->assertEquals(10, $true->getCount());
        $this->assertEquals(20, $false->getCount());
        $result = Enforcer::obey($rule, $true, $false);
        $this->assertFalse($result);
        $this->assertEquals(10, $true->getCount());
        $this->assertEquals(19, $false->getCount());
    }
    
    
    public function testEnforcerException()
    {
        //test that a command throws an exception in the enforcer
        
        //scenario: true exception
        $rule = new Boolean(true);
        //use exception commands
        $true = new ExceptionCommand("exceptiontrue", 222);
        $false = new ExceptionCommand("exceptionfalse", 333);
        try {
            $result = Enforcer::obey($rule, $true, $false);
            $this->fail("should have thrown an exception");
        } catch (\Exception $e)
        {
            $this->assertEquals(222, $e->getCode());
            $this->assertEquals("izzum\command\Exception", get_class($e));
        }
        
        
        //scenario: false exception
        $rule = new Boolean(false);
        //use exception commands
        $true = new ExceptionCommand("exceptiontrue", 222);
        $false = new ExceptionCommand("exceptionfalse", 333);
        try {
            $result = Enforcer::obey($rule, $true, $false);
            $this->fail("should have thrown an exception");
        } catch (\Exception $e)
        {
            $this->assertEquals(333, $e->getCode());
            $this->assertEquals("izzum\command\Exception", get_class($e));
        }
    }
    
    public function testEnforcerNoFalseCommandProvided()
    {
        //no 'false' command provided and false executed (null behaviour
        $rule = new Boolean(false);
        $true = new CountCommand(10, true);
        $enforcer = new Enforcer($rule, $true);
        $this->assertEquals(10, $true->getCount());
        $result = $enforcer->enforce();
        $this->assertFalse($result);
        $this->assertEquals(10, $true->getCount());
    
        //no 'false' command provided and true executed
        $rule = new Boolean(true);
        $true = new CountCommand(10, true);
        $enforcer = new Enforcer($rule, $true);
        $this->assertEquals(10, $true->getCount());
        $result = $enforcer->enforce();
        $this->assertTrue($result);
        $this->assertEquals(11, $true->getCount());
    }
    
}



/**
 * helper class for the test
 * @author rolf
 *
 */
class CountCommand extends Command{
    private $up;
    private $count;
    public function __construct($start, $up = true)
    {
        $this->count = $start;
        $this->up = $up;
    }
    public function getCount()
    {
        return $this->count;
    }
    protected function _execute()
    {
        if($this->up)
        {
            $this->count++;
        } else
        {
            $this->count--;
        }
    }
}