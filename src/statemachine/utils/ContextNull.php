<?php
namespace izzum\statemachine\utils;
use izzum\statemachine\persistence\Memory;
use izzum\statemachine\Context;
use izzum\statemachine\EntityBuilder;
/**
 * ContextNull provides NullObject behaviour. 
 * 
 * It will be a completely 'harmless' Context that has no side-effects through
 * it's writers or readers or builders.
 * 
 * useful for testing or where only an Context is needed for a reference 
 * to the entity_id and machine.
 * 
 * Using this object makes it clear that you don't need a full Context.
 * 
 * builders, readers and writers all default to the simplest version and cannot be
 * set.
 * 
 * @link https://en.wikipedia.org/wiki/Null_Object_pattern
 * @author Rolf Vreijdenberger
 *
 */
class ContextNull extends Context {

    const NULL_ENTITY_ID = "-1";
    const NULL_STATEMACHINE = 'null-machine';
    

    public function __construct($entity_id, $machine, $builder = null, $persistence_adapter = null)
    {
        //provide simple, default behaviour without side effects.
        $persistence_adapter = new Memory();
        $builder = new EntityBuilder();
        parent::__construct($entity_id, $machine, $builder, $persistence_adapter);
    }
    
    /**
     * get a fully configured EntityNull with default values.
     * easy for testing
     * @return EntityNull
     */
    public static function forTest()
    {
        return new self(self::NULL_ENTITY_ID, self::NULL_STATEMACHINE);
    }
    
    
}