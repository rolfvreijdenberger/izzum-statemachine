<?php
namespace izzum\command;

/**
 * specifies an interface for a generic type of Command [GoF]
 * Intent: Encapsulate a request as an object, thereby letting you parameterize
 * clients with different requests, queue or log requests.
 *
 * @author Rolf Vreijdenberger
 * @link https://en.wikipedia.org/wiki/Command_pattern
 */
interface ICommand {

    /**
     * The execute method which is executed somewhere at runtime when the
     * command is invoked.
     * Traditionally you should not provide any context to the execute method,
     * as this implies knowledge of where the command is invoked.
     *
     * Any context should be provided at command creation time via dependency
     * injection.
     * The context provided might know how to get additional information at
     * execution time from objects it knows about.
     *
     * Context provided can also be a mock (for unittesting)
     * 
     * @throws izzum\Exception
     */
    public function execute();

    /**
     * gives a string representation of the instance
     */
    public function toString();
}