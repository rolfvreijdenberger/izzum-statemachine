<?php

namespace Izzum\Command;

/**
 * Command Pattern [GoF] implementation
 * CompositeCommand (aka MacroCommand) can be used to insert multiple ICommand
 * instances which will be handled in the execute() method
 *
 * @author Rolf Vreijdenberger
 * @link https://en.wikipedia.org/wiki/Command_pattern
 */
class Composite extends Command implements IComposite
{
    /**
     * an array of commands
     *
     * @var ICommand[]
     */
    private array $commands;

    public function __construct()
    {
        $this->commands = [];
    }

    /**
     * this method will call all commands added to this class in order of
     * addition
     *
     * @throws Exception
     */
    protected function _execute(): void
    {
        foreach ($this->commands as $command) {
            $command->execute();
        }
    }

    /**
     * this method can be used to add multiple commands that will be used in the
     * execute() method
     */
    public function add(ICommand $command): void
    {
        $this->commands [] = $command;
    }

    /**
     * Removes a command if it is part of the composite (based on identity ===)
     */
    public function remove(ICommand $command): bool
    {
        $total = count($this->commands);
        $removed = false;
        for ($i = ($total - 1); $i >= 0; $i--) {
            $current = $this->commands [$i];
            if ($current === $command) {
                array_splice($this->commands, $i, 1);
                $removed = true;
            }
        }
        return $removed;
    }

    /**
     * does this contain a command (based on identity ===)
     */
    public function contains(ICommand $command): bool
    {
        $contains = false;
        foreach ($this->commands as $current) {
            if ($current === $command) {
                $contains = true;
                break;
            }
        }
        return $contains;
    }

    /**
     * (non-PHPdoc)
     *
     * @see \Izzum\Command\IComposite::count()
     */
    public function count(): int
    {
        return count($this->commands);
    }

    #[\Override]
    public function toString(): string
    {
        $composition = [];
        $children = '';
        foreach ($this->commands as $command) {
            $composition [] = $command->toString();
        }
        if (count($composition) != 0) {
            $children = " consisting of: [" . implode(", ", $composition) . "]";
        }
        return static::class . $children;
    }
}
