<?php

namespace Izzum\Command;

/**
 * The Interface for a CompositeCommand aka.
 * MacroCommand.
 * It extends ICommand and therefore functions as a command(with execute())
 *
 * @author Rolf Vreijdenberger
 */
interface IComposite extends ICommand
{
    /**
     * add a command to the composite, to be executed in the sequence of
     * commands
     */
    public function add(ICommand $command): void;

    /**
     * remove a command from the composite
     */
    public function remove(ICommand $command): bool;

    /**
     * checks if a certain command is present.
     */
    public function contains(ICommand $command): bool;

    /**
     * how many commands does this composite contain?
     */
    public function count(): int;
}
