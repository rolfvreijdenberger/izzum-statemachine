<?php
namespace izzum\statemachine\persistence;
use izzum\statemachine\Context;
use izzum\statemachine\State;
/**
 * In memory storage adapter that stores statemachine data.
 * This is the default persistence adapter
 * 
 * TRICKY: This memory adapter only functions during the execution of 
 * one (1) php process.
 *
 * @author rolf
 */
class Memory extends Adapter {
    
    /**
     * hashmap. the key is Context->getId()
     * @var StorageData[]
     */
    private static $registry = array();
    

    protected function processGetState(Context $context) {
        return $this->getStateFromRegistry($context);    
    }

    protected function processSetState(Context $context, $state) {
        return $this->setStateInRegistry($context, $state);
    }

    public function add(Context $context) {
        $added = true;
        $storage = $this->getStorageFromRegistry($context);
        if($storage != null) {
            $added = false;
        } else {
           $data = new StorageData(
                    $context->getMachine(), 
                    $context->getEntityId(), 
                    State::STATE_NEW, 
                    null);
           $this->writeRegistry($context->getId(), $data);
        }
        return $added;
    }
    
    
    public function getEntityIds($machine, $state = null) {
        $ids = array();
        foreach($this->getRegistry() as $key=>$storage){
            if(strstr($key, $machine)) {
                if($state) {
                    if($storage->state === $state) {
                       $ids[] = $storage->id; 
                    }
                } else {
                    $ids[] = $storage->id;
                }
            }
        }
        return $ids;
    }
    
    
    
    protected final function getStateFromRegistry(Context $context) {
        $storage = $this->getStorageFromRegistry($context);
        if(!$storage) {
            $state = $this->getInitialState($context);
        } else {
            $state = $storage->state;
        }
        return $state;
    }
    
    
    
    protected final function setStateInRegistry(Context $context, $state) {
        $already_stored = true;
        $storage = $this->getStorageFromRegistry($context);
        if(!$storage) {
            $already_stored = false;   
        } 
        $data = StorageData::get($context, $state);
        $this->writeRegistry($context->getId(),$data);
        return $already_stored;
    }
    

    /**
     * 
     * @param string $key
     * @param StorageData $value
     */
    protected final function writeRegistry($key, $value) {
        self::$registry[$key] = $value;
    }
    
        
    /**
     * 
     * @return StorageData[]
     */
    protected final function getRegistry() {
        return self::$registry;
    }
    
    protected final function getStorageFromRegistry(Context $context){
        if(!isset($this->getRegistry()[$context->getId()])) {
           $storage = null;   
        } else {
            $storage =  $this->getRegistry()[$context->getId()];
        }
        return $storage;
    }
    
    /**
     * clears the storage facility.
     * Not a method we want to have on the Adapter interface.
     * this method is useful for testing.
     */
    public static function clear()
    {
        self::$registry = array();
    }
    
    public static function get()
    {
        return self::$registry;
    }
   
}
