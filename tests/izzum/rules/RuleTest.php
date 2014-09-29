<?php
use izzum\rules\Rule;
use izzum\rules\NotRule;
use izzum\rules\AndRule;
use izzum\rules\OrRule;
use izzum\rules\XorRule;
use izzum\rules\False;
use izzum\rules\True;
use izzum\rules\Closure;
use izzum\rules\ExceptionRule;
use izzum\rules\Exception;
use izzum\rules\RuleResult;

/**
 * This class should test the core rule mechanism.
 *
 * @group rule
 * @group rules
 * @group all
 */

class RuleTest extends PHPUnit_Framework_TestCase
{
    
    public function testBooleanRule()
    {
        $rule = new True();
        $this->assertTrue($rule->applies());
        
        $rule = new False();
        $this->assertFalse($rule->applies());
    }
    
    public function testExceptionRule() {
        $rule = new ExceptionRule();
        try {
            $rule->applies();
            $this->fail('should not come here');
        } catch (Exception $e) {
            $this->assertEquals(Exception::CODE_GENERAL, $e->getCode());
        }
    }

    /**
     * Either the rule applies (true) or doesn't (false).
     * Any other outcome should always been thrown as an exception so no false
     * assumptions can be made by the caller. For example when a rule returns a
     * NULL value the caller may asume that false is meant. The rule cannot
     * trust that the caller checks the boolean type so we will.
     * 
     * @expectedException izzum\rules\Exception
     * @expectedExceptionCode izzum\rules\Exception::CODE_NONBOOLEAN
     */
    public function testRuleNullResult()
    {
        $rule = $this->getMockBuilder('izzum\rules\Rule')
                ->disableOriginalConstructor()
                ->setMethods(array('_applies'))
                ->getMock();

        $rule->expects($this->once())
                ->method('_applies')
                ->will($this->returnValue(NULL));

        $rule->applies();
    }

    /**
     * The same test as testRuleNullResult only in this case a string is 
     * returned.
     * 
     * @expectedException izzum\rules\Exception
     * @expectedExceptionCode izzum\rules\Exception::CODE_NONBOOLEAN
     */
    public function testRuleStringResult()
    {
        $rule = $this->getMockBuilder('izzum\rules\Rule')
                ->disableOriginalConstructor()
                ->setMethods(array('_applies'))
                ->getMock();

        $rule->expects($this->once())
                ->method('_applies')
                ->will($this->returnValue('a string'));

        $rule->applies();
    }

    /**
     * The same test as testRuleNullResult only in this case an integer 0 is 
     * returned.
     * 
     * @expectedException izzum\rules\Exception
     * @expectedExceptionCode izzum\rules\Exception::CODE_NONBOOLEAN
     */
    public function testRuleInt0Result()
    {
        $rule = $this->getMockBuilder('izzum\rules\Rule')
                ->disableOriginalConstructor()
                ->setMethods(array('_applies'))
                ->getMock();

        $rule->expects($this->once())
                ->method('_applies')
                ->will($this->returnValue(0));

        $rule->applies();
    }

    /**
     * The same test as testRuleNullResult only in this case an integer 1 is 
     * returned.
     * 
     * @expectedException izzum\rules\Exception
     * @expectedExceptionCode izzum\rules\Exception::CODE_NONBOOLEAN
     */
    public function testRuleInt1Result()
    {
        $rule = $this->getMockBuilder('izzum\rules\Rule')
                ->disableOriginalConstructor()
                ->setMethods(array('_applies'))
                ->getMock();

        $rule->expects($this->once())
                ->method('_applies')
                ->will($this->returnValue(1));

        $rule->applies();
    }

    /**
     * The applies method should be final and cannot be altered.
     * This case tries to overwrite it anyway.
     * 
     * No boolean should be returned and expect an non boolean exception.
     * 
     * @expectedException izzum\rules\Exception
     * @expectedExceptionCode izzum\rules\Exception::CODE_NONBOOLEAN
     */
    public function testAppliesMethodIsFinal()
    {
        $rule = $this->getMockBuilder('izzum\rules\Rule')
                ->disableOriginalConstructor()
                ->setMethods(array('applies', '_applies'))
                ->getMock();

        $rule->expects($this->any())
                ->method('applies')
                ->will($this->returnValue(true));

        $rule->applies();
    }

    /**
     * Basic andRule test with a rule that applies
     */
    public function testAndChainingTrueTrue()
    {
        $ruletrue = new True();
        $rule = $ruletrue->andRule($ruletrue);
        $this->assertTrue($rule->applies());
    }

    /**
     * Basic andRule test with a rule that doesn't apply
     */
    public function testAndChainingTrueFalse()
    {
        $ruletrue = new True();
        $rulefalse = new False();
        $rule = $ruletrue->andRule($rulefalse);
        $this->assertFalse($rule->applies());
        $this->assertNotNull($rule->toString());
    }

    /**
     * Basic andRule test where both rules don't apply
     */
    public function testAndChainingFalseFalse()
    {
        $rulefalse = new False();
        $rule = $rulefalse->andRule($rulefalse);
        $this->assertFalse($rule->applies());
    }

    /**
     * Basic orRule chaining test where both rules apply
     */
    public function testOrChainingTrueTrue()
    {
        $ruletrue = new True();
        $rule = $ruletrue->orRule($ruletrue);
        $this->assertTrue($rule->applies());
    }

    /**
     * Basic orRule chaining test where one rule applies
     */
    public function testOrChainingTrueFalse()
    {
        $ruletrue = new True();
        $rulefalse = new False();
        $rule = $ruletrue->orRule($rulefalse);
        $this->assertTrue($rule->applies());
        $this->assertNotNull($rule->toString());
    }

    /**
     * Basic orRule chaining test where none apply
     */
    public function testOrChainingFalseFalse()
    {
        $rulefalse = new False();
        $rule = $rulefalse->orRule($rulefalse);
        $this->assertFalse($rule->applies());
    }
    
    /**
     * Basic xorRule chaining test where both rules apply
     */
    public function testXorChainingTrueTrue()
    {
    	$ruletrue = new True();
    	$rule = $ruletrue->xorRule($ruletrue);
    	$this->assertFalse($rule->applies());
        $this->assertNotNull($rule->toString());
    }
    
    /**
     * Basic xorRule chaining test where one rule applies
     */
    public function testXorChainingTrueFalse()
    {
    	$ruletrue = new True();
    	$rulefalse = new False();
    	$rule = $ruletrue->xorRule($rulefalse);
    	$this->assertTrue($rule->applies());
    }
    
    /**
     * Basic xorRule chaining test where none apply
     */
    public function testXorChainingFalseFalse()
    {
    	$rulefalse = new False();
    	$rule = $rulefalse->xorRule($rulefalse);
    	$this->assertFalse($rule->applies());
    }

    /**
     * Basic notRule chaining test where the chained rule applies.
     * This should cause the rule to fail.
     */
    public function testNotChainingTrueTrue()
    {
        $rule = new True();
        $rule = $rule->not();
        $this->assertFalse($rule->applies());
    }

    /**
     * Basic notRule chaining test where the chained rule not applies.
     * This should cause the rule to apply.
     */
    public function testNotChainingTrueFalse()
    {
        $rule = new False();
        $rule = $rule->not();
        $this->assertTrue($rule->applies());
    }

    /**
     * Test the supressor in case of an exception and expect false
     */
    public function testRuleSuppressorFalse()
    {
        $rule = $this->getMockBuilder('izzum\rules\Rule')
                ->disableOriginalConstructor()
                ->setMethods(array('_applies'))
                ->getMock();

        $rule->expects($this->once())
                ->method('_applies')
                ->will($this->throwException(new Exception('not good')));

        $supressedrule = new izzum\rules\ExceptionSupressor($rule, false);
        $this->assertFalse($supressedrule->applies());
    }

    /**
     * Test the supressor in case of an exception and expect true
     */
    public function testRuleSuppressorTrue()
    {
        $rule = $this->getMockBuilder('izzum\rules\Rule')
                ->disableOriginalConstructor()
                ->setMethods(array('_applies'))
                ->getMock();

        $rule->expects($this->once())
                ->method('_applies')
                ->will($this->throwException(new Exception('not good')));

        $supressedrule = new izzum\rules\ExceptionSupressor($rule, true);
        $this->assertTrue($supressedrule->applies());
    }
    
    public function testClosureRuleTrue() {
        $closure = function ($a, $b) {
            return $a === $b;
        };
        
        $rule = new Closure($closure, array(1,1));
        $this->assertTrue($rule->applies());   
    }
    
    public function testClosureRuleFalse() {
        $closure = function ($a, $b) {
            return $a === $b;
        };
        
        $rule = new Closure($closure, array(1,2));
        $this->assertFalse($rule->applies());   
    }
    
    public function testFalseRule(){
        $rule = new False();
        $this->assertFalse($rule->applies());
    }
    
    public function testTrueRule(){
        $rule = new True();
        $this->assertTrue($rule->applies());
    }
    
    
    public function testRuleCaching()
    {
        //fake non-determinism by using a helper rule
        $rule = new RandomNumberRule();
        //defaults to cache off.
        $this->assertFalse($rule->getCacheEnabled());
        $this->assertEquals(0, $rule->getCount());
        //enable caching
        $rule->setCacheEnabled(true);
        $this->assertTrue($rule->getCacheEnabled());
        $result = $rule->applies();
        $this->assertTrue($result);
        $this->assertTrue($rule->getCacheEnabled());
        $this->assertEquals($result, $rule->applies());
        $this->assertEquals($result, $rule->applies());
        $this->assertEquals(1, $rule->getCount());
        
        //caching off
        $rule->setCacheEnabled(false);
        $this->assertFalse($rule->getCacheEnabled());
        $this->assertNotEquals($result, $rule->applies());
        $this->assertEquals(2, $rule->getCount());
        $this->assertNotEquals($result, $rule->applies());
        $this->assertEquals(3, $rule->getCount());
        
        //caching on again
        $rule->setCacheEnabled(true);
        $this->assertTrue($rule->getCacheEnabled());
        $this->assertFalse($rule->applies());
        $this->assertEquals(4, $rule->getCount());
        $this->assertFalse($rule->applies());
        $this->assertEquals(4, $rule->getCount());
         
    }
    
    public function testRuleResult()
    {
        $rule = new True();
        $result = 'rule failed';
        $r = new RuleResult($rule, $result);
        $this->assertEquals($rule, $r->getRule());
        $this->assertEquals($result, $r->getResult());
        
        //a new rule
        $rule = new RuleResultRule();
        $this->assertFalse($rule->containsResult(RuleResultRule::RESULT_CONDITIONAL));
        $this->assertFalse($rule->hasResult());
        $rule->applies();
        $result = $rule->getResults()[0];
        $this->assertEquals($rule, $result->getRule());
        $this->assertEquals(RuleResultRule::RESULT_CONDITIONAL, $result->getResult());
        $this->assertTrue($rule->containsResult(RuleResultRule::RESULT_CONDITIONAL));
        $this->assertTrue($rule->hasResult());
        
    }
   
}

class RandomNumberRule extends Rule {
    private $count = 0;
    
    public function getCount() {
        return $this->count;
    }
    protected function _applies()
    {
        $this->count++;
        if($this->count === 1) {
            return true;
        }
        
        return false;
    }
}

class RuleResultRule extends Rule {
    const RESULT_CONDITIONAL = 'we did not come into a conditional statement';
    protected function _applies()
    {
        $this->addResult(self::RESULT_CONDITIONAL);
        return true;
    }
}
