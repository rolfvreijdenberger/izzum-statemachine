<?php
/**
 * bootstrap file for phpunit.
 * autoloading or inclusion of files needed for the tests should take place here.
 */
$path = "../";

include_once($path . 'izzum/command/Exception.php');
include_once($path . 'izzum/command/ICommand.php');
include_once($path . 'izzum/command/IComposite.php');
include_once($path . 'izzum/command/Command.php');
include_once($path . 'izzum/command/ExceptionCommand.php');
include_once($path . 'izzum/command/Null.php');
include_once($path . 'izzum/command/Closure.php');
include_once($path . 'izzum/command/Composite.php');
?>
