<?php
namespace izzum\statemachine\utils;
use izzum\statemachine\persistence\Adapter;
use izzum\statemachine\loader\Loader;
use izzum\statemachine\Identifier;
use izzum\statemachine\Exception;
use izzum\statemachine\State;
use izzum\statemachine\Transition;
use izzum\statemachine\StateMachine;
use izzum\statemachine\utils\Utils;

/**
 * mix and match a loader (reader) and a persistance adapter (writer) by wrapping 
 * both of them and use them to delegate the handling logic to.
 * 
 * This allows us to load the statemachine configuration from a specific source and use
 * a different sink to write the current state and history information to.
 * 
 * You might want to do this to read the relatively static content for the configuration from
 * the filesystem and use a fast backend system to write the state and transition history data to.
 * 
 * for example:
 * - use an xml file with the PDO (sql) adapter:
 *      izzum\statemachine\loader\XML & izzum\statemachine\persistence\PDO
 * - use a json file with the Redis adapter:
 *      izzum\statemachine\loader\JSON & izzum\statemachine\persistence\Redis classes
 * - use php code to configure the machine with the Session adapter:
 *      izzum\statemachine\loader\LoaderArray & izzum\statemachine\persistence\Session classes)
 * 
 *
 * @author Rolf Vreijdenberger
 * @link https://en.wikipedia.org/wiki/Delegation_pattern
 *        
 */
class ReaderWriterDelegator extends Adapter implements Loader {
    /**
     * an instance of a Loader, which reads data
     * @var Loader
     */
    private $reader;
    /**
     * an instance of an Adapter, which writes data
     * @var Adapter
     */
    private $writer;

    /**
     * @param Loader $reader the Loader instance to decorate
     * @param Adapter $writer the Adapter instance to decorate
     */
    public function __construct(Loader $reader, Adapter $writer)
    {
        $this->reader = $reader;
        $this->writer = $writer;
    }
    /**
     * gets the reader/Loader
     * @return Loader
     */
    public function getReader() {
        return $this->reader;
    }
    /**
     * gets the writer/Adapter
     * @return Adapter
     */
    public function getWriter() {
        return $this->writer;
    }

    public function load(StateMachine $stateMachine)
    {
        return $this->reader->load($stateMachine);
    }

    public function getEntityIds($machine, $state = null)
    {
        return $this->writer->getEntityIds($machine, $state);
    }
    public function isPersisted(Identifier $identifier)
    {
        return $this->writer->isPersisted();
    }

    public function processSetState(Identifier $identifier, $state, $message = null)
    {
        return $this->writer->processSetState($identifier, $state, $message);
    }

    public function processGetState(Identifier $identifier)
    {
        return $this->writer->processGetState($identifier);
    }

    public function add(Identifier $identifier, $state, $message = null)
    {
        return $this->writer->add($identifier, $state, $message);
    }
    
    public function setFailedTransition(Identifier $identifier, Transition $transition, \Exception $e)
    {
        $this->writer->setFailedTransition($identifier, $transition, $e);
    }
    
    public function toString()
    {
        return parent::toString() . " [reader] " . $this->reader->toString() .  " [writer] " . $this->writer->toString();
    }
    
    public function __toString()
    {
        return $this->toString();
    }
}