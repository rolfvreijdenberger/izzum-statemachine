<?php
/**
 * bootstrap file for phpunit.
 * autoloading or inclusion of files needed for the tests should take place here.
 */

//path to where code is located
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
include_once($path . 'izzum/rules/True.php');
include_once($path . 'izzum/rules/False.php');
include_once($path . 'izzum/rules/ExceptionRule.php');
include_once($path . 'izzum/rules/Closure.php');
include_once($path . 'izzum/rules/Enforcer.php');
include_once($path . 'izzum/rules/ExceptionSupressor.php');
include_once($path . 'izzum/rules/RuleResult.php');

//statemachine
//specifically for tests that make use of sessions
ob_start();
include_once($path . 'izzum/statemachine/State.php');
include_once($path . 'izzum/statemachine/Transition.php');
include_once($path . 'izzum/statemachine/Exception.php');
include_once($path . 'izzum/statemachine/StateMachine.php');

include_once($path . 'izzum/statemachine/Context.php');
include_once($path . 'izzum/statemachine/EntityBuilder.php');
include_once($path . 'izzum/statemachine/utils/ContextNull.php');

include_once($path . 'izzum/statemachine/loader/Loader.php');
include_once($path . 'izzum/statemachine/loader/LoaderArray.php');
include_once($path . 'izzum/statemachine/loader/LoaderData.php');

include_once($path . 'izzum/statemachine/persistence/Adapter.php');
include_once($path . 'izzum/statemachine/persistence/Memory.php');
include_once($path . 'izzum/statemachine/persistence/Session.php');
include_once($path . 'izzum/statemachine/persistence/StorageData.php');

include_once($path . 'izzum/statemachine/factory/AbstractFactory.php');

include_once($path . 'izzum/statemachine/utils/ExternalData.php');
include_once($path . 'izzum/statemachine/utils/Utils.php');
include_once($path . 'izzum/statemachine/utils/uml/PlantUml.php');
