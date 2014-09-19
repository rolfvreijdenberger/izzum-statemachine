<?php
/**
 * bootstrap file for phpunit.
 * autoloading or inclusion of files needed for the tests should take place here.
 */
$path = "../";

//commands
include_once($path . 'izzum/command/Exception.php');
include_once($path . 'izzum/command/ICommand.php');
include_once($path . 'izzum/command/IComposite.php');
include_once($path . 'izzum/command/Command.php');
include_once($path . 'izzum/command/ExceptionCommand.php');
include_once($path . 'izzum/command/Null.php');
include_once($path . 'izzum/command/Closure.php');
include_once($path . 'izzum/command/Composite.php');

//rules
include_once($path . 'izzum/rules/Exception.php');
include_once($path . 'izzum/rules/Rule.php');
include_once($path . 'izzum/rules/AndRule.php');
include_once($path . 'izzum/rules/OrRule.php');
include_once($path . 'izzum/rules/NotRule.php');
include_once($path . 'izzum/rules/XorRule.php');
include_once($path . 'izzum/rules/Boolean.php');
include_once($path . 'izzum/rules/True.php');
include_once($path . 'izzum/rules/False.php');
include_once($path . 'izzum/rules/Closure.php');
include_once($path . 'izzum/rules/Enforcer.php');
include_once($path . 'izzum/rules/ExceptionSupressor.php');
include_once($path . 'izzum/rules/RuleResult.php');
?>
