<?php
//we use ob_start for some tests that use php sessions
ob_start();
/**
 * add the composer autoloader, we assume this is the normal composer setup:
 * <root>/vendor/rolfvreijdenberger/izzum/
 * with the autoloader in:
 * <root>/vendor/autoload.php
 */
require_once __DIR__ . '/../../../../vendor/autoload.php';
