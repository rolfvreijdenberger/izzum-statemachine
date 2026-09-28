<?php

use PHPUnit\Framework\TestCase;
use Codeception\Attribute\Group;
use Izzum\Rules\Rule;
use Izzum\Rules\TrueRule;
use Izzum\Rules\FalseRule;
use Izzum\Rules\Closure;
use Izzum\Rules\ExceptionRule;
use Izzum\Rules\Exception;
use Izzum\Rules\RuleResult;

/**
 * This class should test the core rule mechanism.
 *
 */
#[Group('rule', 'rules', 'all')]
class RuleTest extends TestCase
{
    public function testBooleanRule()
    {
        $rule = new TrueRule();
        $this->assertTrue($rule->applies());

        $rule = new FalseRule();
        $this->assertFalse($rule->applies());

        $this->assertStringContainsString('False', $rule . '', '__toString');
    }

    public function testExceptionRule()
    {
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
     */
    public function testRuleNullResult()
    {
        $rule = $this->getMockBuilder('Izzum\Rules\Rule')
                ->disableOriginalConstructor()
                ->onlyMethods(['_applies'])
                ->getMock();

        $rule->expects($this->once())
                ->method('_applies')
                ->willReturn(null);

        $this->expectException(Exception::class);
        $this->expectExceptionCode(Exception::CODE_NONBOOLEAN);
        $rule->applies();
    }

    /**
     * The same test as testRuleNullResult only in this case a string is
     * returned.
     */
    public function testRuleStringResult()
    {
        $rule = $this->getMockBuilder('Izzum\Rules\Rule')
                ->disableOriginalConstructor()
                ->onlyMethods(['_applies'])
                ->getMock();

        $rule->expects($this->once())
                ->method('_applies')
                ->willReturn('a string');

        $this->expectException(Exception::class);
        $this->expectExceptionCode(Exception::CODE_NONBOOLEAN);
        $rule->applies();
    }

    /**
     * The same test as testRuleNullResult only in this case an integer 0 is
     * returned.
     */
    public function testRuleInt0Result()
    {
        $rule = $this->getMockBuilder('Izzum\Rules\Rule')
                ->disableOriginalConstructor()
                ->onlyMethods(['_applies'])
                ->getMock();

        $rule->expects($this->once())
                ->method('_applies')
                ->willReturn(0);

        $this->expectException(Exception::class);
        $this->expectExceptionCode(Exception::CODE_NONBOOLEAN);
        $rule->applies();
    }

    /**
     * The same test as testRuleNullResult only in this case an integer 1 is
     * returned.
     */
    public function testRuleInt1Result()
    {
        $rule = $this->getMockBuilder('Izzum\Rules\Rule')
                ->disableOriginalConstructor()
                ->onlyMethods(['_applies'])
                ->getMock();

        $rule->expects($this->once())
                ->method('_applies')
                ->willReturn(1);

        $this->expectException(Exception::class);
        $this->expectExceptionCode(Exception::CODE_NONBOOLEAN);
        $rule->applies();
    }

    /**
     * The applies method should be final and cannot be altered.
     * This case tries to overwrite it anyway.
     *
     * No boolean should be returned and expect an non boolean exception.
     *
     * Mocking a final method (`onlyMethods(['applies'])`) succeeds, but PHPUnit
     * refuses to override the final method at call time - calling applies()
     * directly invokes the real implementation, which delegates to the mocked
     * (abstract, non-final) _applies(), which defaults to returning null.
     */
    public function testAppliesMethodIsFinal()
    {
        $rule = $this->getMockBuilder('Izzum\Rules\Rule')
                ->disableOriginalConstructor()
                ->onlyMethods(['applies', '_applies'])
                ->getMock();

        $this->expectException(Exception::class);
        $this->expectExceptionCode(Exception::CODE_NONBOOLEAN);
        $rule->applies();
    }

    /**
     * Basic andRule test with a rule that applies
     */
    public function testAndChainingTrueTrue()
    {
        $ruletrue = new TrueRule();
        $rule = $ruletrue->andRule($ruletrue);
        $this->assertTrue($rule->applies());
    }

    /**
     * Basic andRule test with a rule that doesn't apply
     */
    public function testAndChainingTrueFalse()
    {
        $ruletrue = new TrueRule();
        $rulefalse = new FalseRule();
        $rule = $ruletrue->andRule($rulefalse);
        $this->assertFalse($rule->applies());
    }

    /**
     * Basic andRule test where both rules don't apply
     */
    public function testAndChainingFalseFalse()
    {
        $rulefalse = new FalseRule();
        $rule = $rulefalse->andRule($rulefalse);
        $this->assertFalse($rule->applies());
        $this->assertCount(0, $rule->getResults());
    }

    /**
     * Basic orRule chaining test where both rules apply
     */
    public function testOrChainingTrueTrue()
    {
        $ruletrue = new TrueRule();
        $rule = $ruletrue->orRule($ruletrue);
        $this->assertTrue($rule->applies());
    }

    /**
     * Basic orRule chaining test where one rule applies
     */
    public function testOrChainingTrueFalse()
    {
        $ruletrue = new TrueRule();
        $rulefalse = new FalseRule();
        $rule = $ruletrue->orRule($rulefalse);
        $this->assertTrue($rule->applies());
    }

    /**
     * Basic orRule chaining test where none apply
     */
    public function testOrChainingFalseFalse()
    {
        $rulefalse = new FalseRule();
        $rule = $rulefalse->orRule($rulefalse);
        $this->assertFalse($rule->applies());
        $this->assertCount(0, $rule->getResults());
    }

    /**
     * Basic xorRule chaining test where both rules apply
     */
    public function testXorChainingTrueTrue()
    {
        $ruletrue = new TrueRule();
        $rule = $ruletrue->xorRule($ruletrue);
        $this->assertFalse($rule->applies());
    }

    /**
     * Basic xorRule chaining test where one rule applies
     */
    public function testXorChainingTrueFalse()
    {
        $ruletrue = new TrueRule();
        $rulefalse = new FalseRule();
        $rule = $ruletrue->xorRule($rulefalse);
        $this->assertTrue($rule->applies());
    }

    /**
     * Basic xorRule chaining test where none apply
     */
    public function testXorChainingFalseFalse()
    {
        $rulefalse = new FalseRule();
        $rule = $rulefalse->xorRule($rulefalse);
        $this->assertFalse($rule->applies());
        $this->assertCount(0, $rule->getResults());
    }

    /**
     * Basic notRule chaining test where the chained rule applies.
     * This should cause the rule to fail.
     */
    public function testNotChainingTrueTrue()
    {
        $rule = new TrueRule();
        $rule = $rule->not();
        $this->assertFalse($rule->applies());
        $this->assertCount(0, $rule->getResults());
    }

    /**
     * Basic notRule chaining test where the chained rule not applies.
     * This should cause the rule to apply.
     */
    public function testNotChainingTrueFalse()
    {
        $rule = new FalseRule();
        $rule = $rule->not();
        $this->assertTrue($rule->applies());
    }

    /**
     * Test the supressor in case of an exception and expect false
     */
    public function testRuleSuppressorFalse()
    {
        $rule = $this->getMockBuilder('Izzum\Rules\Rule')
                ->disableOriginalConstructor()
                ->onlyMethods(['_applies'])
                ->getMock();

        $rule->expects($this->once())
                ->method('_applies')
                ->willThrowException(new Exception('not good'));

        $supressedrule = new Izzum\Rules\ExceptionSupressor($rule, false);
        $this->assertFalse($supressedrule->applies());
    }

    /**
     * Test the supressor in case of an exception and expect true
     */
    public function testRuleSuppressorTrue()
    {
        $rule = $this->getMockBuilder('Izzum\Rules\Rule')
                ->disableOriginalConstructor()
                ->onlyMethods(['_applies'])
                ->getMock();

        $rule->expects($this->once())
                ->method('_applies')
                ->willThrowException(new Exception('not good'));

        $supressedrule = new Izzum\Rules\ExceptionSupressor($rule, true);
        $this->assertTrue($supressedrule->applies());
    }

    public function testClosureRuleTrue()
    {
        $closure = (fn($a, $b) => $a === $b);

        $rule = new Closure($closure, [1,1]);
        $this->assertTrue($rule->applies());
    }

    public function testClosureRuleFalse()
    {
        $closure = (fn($a, $b) => $a === $b);

        $rule = new Closure($closure, [1,2]);
        $this->assertFalse($rule->applies());
    }

    public function testFalseRule()
    {
        $rule = new FalseRule();
        $this->assertFalse($rule->applies());
    }

    public function testTrueRule()
    {
        $rule = new TrueRule();
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
        $rule = new TrueRule();
        $result = 'rule failed';
        $r = new RuleResult($rule, $result);
        $this->assertEquals($rule, $r->getRule());
        $this->assertEquals($result, $r->getResult());

        //a new rule
        $rule = new RuleResultRule();
        $this->assertFalse($rule->containsResult(RuleResultRule::RESULT_CONDITIONAL));
        $this->assertFalse($rule->hasResult());
        $rule->applies();
        $result = $rule->getResults();
        $result = $result[0];
        $this->assertEquals($rule, $result->getRule());
        $this->assertEquals(RuleResultRule::RESULT_CONDITIONAL, $result->getResult());
        $this->assertTrue($rule->containsResult(RuleResultRule::RESULT_CONDITIONAL));
        $this->assertTrue($rule->hasResult());

    }

    public function testException()
    {
        //for complete code coverage of exception paths
        $rule = new throwsExceptionRule(true);
        try {
            $rule->applies();
            $this->fail('should not come here');
        } catch (Exception $e) {
            $this->assertEquals(1, $e->getCode());
        }

        $rule = new throwsExceptionRule(false);
        try {
            $rule->applies();
            $this->fail('should not come here');
        } catch (Exception $e) {
            $this->assertEquals(2, $e->getCode());

        }
    }

}

class RandomNumberRule extends Rule
{
    private $count = 0;

    public function getCount()
    {
        return $this->count;
    }
    protected function _applies()
    {
        $this->count++;
        if ($this->count === 1) {
            return true;
        }

        return false;
    }
}

class RuleResultRule extends Rule
{
    public const RESULT_CONDITIONAL = 'we did not come into a conditional statement';
    protected function _applies()
    {
        $this->addResult(self::RESULT_CONDITIONAL);
        return true;
    }
}

class throwsExceptionRule extends Rule
{
    public function __construct(private $bool) {}

    protected function _applies()
    {
        if ($this->bool) {
            throw new Exception('oops', 1);
        } else {
            throw new \Exception('ooops', 2);
        }
    }
}
