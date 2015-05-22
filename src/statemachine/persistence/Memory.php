<?php
namespace izzum\statemachine\persistence;
use izzum\statemachine\Identifier;
use izzum\statemachine\State;
/**
 * In memory storage adapter that stores statemachine data, best used in a runtime
 * environment.
 * This is the default persistence adapter.
 * 
 * TRICKY: This memory adapter only functions during the execution of 
 * one (1) php process. Therefore, it is best used in a runtime environment such
 * as a php daemon program or an interactive command line php script.
 *
 * @author rolf vreijdenberger
 */
class Memory extends Adapter {
    
    /**
     * hashmap. the key is Identifier->getId()
     * @var StorageData[]
     */
    private static $registry = array();
    

    protected function processGetState(Identifier $identifier) {
        return $this->getStateFromRegistry($identifier);    
    }

    protected function processSetState(Identifier $identifier, $state) {
        return $this->setStateInRegistry($identifier, $state);
    }

    public function add(Identifier $identifier, $state) {
    	//var_dump (debug_backtrace()[2]);
    	$added = true;
    	$storage = $this->getStorageFromRegistry($identifier);
    	if($storage != null) {
    		$added = false;
    	} else {
    		$data = new StorageData(
    				$identifier->getMachine(),
    				$identifier->getEntityId(),
    				$state,
    				null);
    		$this->writeRegistry($identifier->getId(), $data);
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
    
    
    
    protected final function getStateFromRegistry(Identifier $identifier) {
        $storage = $this->getStorageFromRegistry($identifier);
        if(!$storage) {
            $state = State::STATE_UNKNOWN;
        } else {
            $state = $storage->state;
        }
        return $state;
    }
    
    
    /**
     * 
     * @param Identifier $identifier
     * @param string $state
     * @return boolan false if already stored and overwritten, true if not stored before
     */
    protected final function setStateInRegistry(Identifier $identifier, $state) {
        $already_stored = true;
        $storage = $this->getStorageFromRegistry($identifier);
        if(!$storage) {
            $already_stored = false;   
        } 
        $data = StorageData::get($identifier, $state);
        $this->writeRegistry($identifier->getId(),$data);
        return !$already_stored;
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
    
    protected final function getStorageFromRegistry(Identifier $identifier){
        $registry = $this->getRegistry();
        if(!isset($registry[$identifier->getId()])) {
           $storage = null;   
        } else {
            $storage =  $registry[$identifier->getId()];
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
