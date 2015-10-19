<?php
namespace izzum\statemachine\utils;
use izzum\statemachine\utils\ExternalData;

/**
 * @group statemachine
 * @group ExternalData
 * @author rolf
 *
 */
class ExternalDataTest extends \PHPUnit_Framework_TestCase {
    
    /**
     * @test
     */
    public function shouldWorkAsExpectedViaPublicMethods()
    {
        //cleanup
        ExternalData::clear();
        
        $test_string = 'test';
        $test_array = array('test', 'test');
        $this->assertFalse(ExternalData::has());
        $this->assertNull(ExternalData::get());
        
        ExternalData::set($test_string);
        $this->assertTrue(ExternalData::has());
        $this->assertEquals($test_string, ExternalData::get());
        $this->assertEquals($test_string, ExternalData::get(), 'call it twice, still has context');
        
        ExternalData::clear();
        $this->assertFalse(ExternalData::has());
        $this->assertNull(ExternalData::get());
        
        ExternalData::set($test_array);
        $this->assertTrue(ExternalData::has());
        $this->assertEquals($test_array, ExternalData::get());
        $this->assertEquals($test_array, ExternalData::get(), 'call it twice, still has context');
        
        //cleanup
        ExternalData::clear();
        
    }
    
}