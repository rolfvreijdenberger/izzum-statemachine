<?php

namespace Izzum\StateMachine\Utils;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use Codeception\Attribute\Group;
use Izzum\StateMachine\Utils\ExternalData;

/**
 * @author rolf
 *
 */
#[Group('statemachine', 'ExternalData')]
class ExternalDataTest extends TestCase
{
    #[Test]
    public function shouldWorkAsExpectedViaPublicMethods()
    {
        //cleanup
        ExternalData::clear();

        $testString = 'test';
        $testArray = ['test', 'test'];
        $this->assertFalse(ExternalData::has());
        $this->assertNull(ExternalData::get());

        ExternalData::set($testString);
        $this->assertTrue(ExternalData::has());
        $this->assertEquals($testString, ExternalData::get());
        $this->assertEquals($testString, ExternalData::get(), 'call it twice, still has context');

        ExternalData::clear();
        $this->assertFalse(ExternalData::has());
        $this->assertNull(ExternalData::get());

        ExternalData::set($testArray);
        $this->assertTrue(ExternalData::has());
        $this->assertEquals($testArray, ExternalData::get());
        $this->assertEquals($testArray, ExternalData::get(), 'call it twice, still has context');

        //cleanup
        ExternalData::clear();

    }

}
