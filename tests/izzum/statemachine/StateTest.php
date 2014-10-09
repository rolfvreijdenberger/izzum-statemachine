<?php
namespace izzum\statemachine;
use izzum\statemachine\utils\EntityNull;

/**
 * @group statemachine
 * @group state
 * @author rolf
 *
 */
class StateTest extends \PHPUnit_Framework_TestCase {
    
    /**
     * @test
     */
    public function shouldWorkAsExpected(){
        $name = 'a';
        $type = State::TYPE_INITIAL;
        $state = new State($name, $type);
        $this->assertNotNull($state);
        $this->assertCount(0, $state->getTransitions());
        $sb = new State('b');
        $sc = new State('c');
        $t1 = new Transition($state, $sb);
        $t2 = new Transition($state, $sc);
        $trans = $state->getTransitions();
        $this->assertCount(2, $trans, 'biderectional associtation initiated through transition');
        $this->assertEquals($t1, $trans[0], 'in order transitions were created');
        $this->assertEquals($t2, $trans[1], 'in order transitions were created');
        $this->assertTrue($state->isInitial());
        $this->assertFalse($state->isFinal());
        $this->assertFalse($state->isNormal());
        $this->assertEquals($name, $state->getName());
        $this->assertEquals(State::TYPE_INITIAL,$state->getType());
        $this->assertTrue($state->hasTransition($t1->getName()));
        $this->assertTrue($state->hasTransition($t2->getName()));
        $this->assertFalse($sb->hasTransition($t1->getName()),'no bidirectional association on incoming transition');
        $this->assertFalse($sb->hasTransition($t2->getName()),'no bidirectional association on incoming transition');
        $this->assertFalse($sc->hasTransition($t1->getName()),'no bidirectional association on incoming transition');
        $this->assertFalse($sc->hasTransition($t2->getName()),'no bidirectional association on incoming transition');
        
        
        
        
        $this->assertFalse($state->hasTransition('bogus'));
        
        $this->assertFalse($state->addTransition($t1), 'already present');
       
    }
    /**
     * @test
     */
    public function shouldReturnType(){
        $name = 'state-izzum';
        $state = new State($name, State::TYPE_INITIAL);
        $this->assertTrue($state->isInitial());
        $this->assertFalse($state->isFinal());
        $this->assertFalse($state->isNormal());
        
        $state = new State($name, State::TYPE_NORMAL);
        $this->assertFalse($state->isInitial());
        $this->assertFalse($state->isFinal());
        $this->assertTrue($state->isNormal());
        
        $state = new State($name, State::TYPE_FINAL);
        $this->assertFalse($state->isInitial());
        $this->assertTrue($state->isFinal());
        $this->assertFalse($state->isNormal());
    }
}

