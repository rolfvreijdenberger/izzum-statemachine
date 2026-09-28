<?php

namespace Izzum\StateMachine\Utils;

use Izzum\StateMachine\Persistence\Adapter;
use Izzum\StateMachine\Loader\Loader;
use Izzum\StateMachine\Identifier;
use Izzum\StateMachine\Exception;
use Izzum\StateMachine\Transition;
use Izzum\StateMachine\StateMachine;

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
 *      Izzum\StateMachine\Loader\XML & Izzum\StateMachine\Persistence\PDO
 * - use a json file with the Redis adapter:
 *      Izzum\StateMachine\Loader\JSON & Izzum\StateMachine\Persistence\Redis classes
 * - use php code to configure the machine with the Session adapter:
 *      Izzum\StateMachine\Loader\LoaderArray & Izzum\StateMachine\Persistence\Session classes)
 *
 *
 * @author Rolf Vreijdenberger
 * @link https://en.wikipedia.org/wiki/Delegation_pattern
 *
 */
class ReaderWriterDelegator extends Adapter implements Loader
{
    /**
     * @param Loader $reader the Loader instance to decorate, which reads data
     * @param Adapter $writer the Adapter instance to decorate, which writes data
     */
    public function __construct(
        private readonly Loader $reader,
        private readonly Adapter $writer,
    ) {}

    public function getReader(): Loader
    {
        return $this->reader;
    }

    public function getWriter(): Adapter
    {
        return $this->writer;
    }

    public function load(StateMachine $stateMachine): int
    {
        return $this->reader->load($stateMachine);
    }

    public function getEntityIds(string $machine, ?string $state = null): array
    {
        return $this->writer->getEntityIds($machine, $state);
    }
    public function isPersisted(Identifier $identifier): bool
    {
        return $this->writer->isPersisted($identifier);
    }

    #[\Override]
    public function processSetState(Identifier $identifier, string $state, $message = null)
    {
        return $this->writer->processSetState($identifier, $state, $message);
    }

    public function processGetState(Identifier $identifier): string
    {
        return $this->writer->processGetState($identifier);
    }

    #[\Override]
    public function add(Identifier $identifier, string $state, $message = null): bool
    {
        return $this->writer->add($identifier, $state, $message);
    }

    #[\Override]
    public function setFailedTransition(Identifier $identifier, Transition $transition, \Exception $e): void
    {
        $this->writer->setFailedTransition($identifier, $transition, $e);
    }

    #[\Override]
    public function toString(): string
    {
        return parent::toString() . " [reader] " . $this->reader->toString() . " [writer] " . $this->writer->toString();
    }

    #[\Override]
    public function __toString(): string
    {
        return $this->toString();
    }
}
