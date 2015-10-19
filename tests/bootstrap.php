<?php
//we use ob_start for some tests that use php sessions
ob_start();
/**
 * add the composer autoloader, we assume this is the normal composer setup:
 * <root>/vendor/rolfvreijdenberger/izzum/
 * with the autoloader in:
 * <root>/vendor/autoload.php
 */
$files = array(__DIR__ . '/../../../../vendor/autoload.php', __DIR__ . '/../vendor/autoload.php');
foreach($files as $file) {
    if(file_exists($file)) {
        require_once($file);
        break;
    }
}
error_reporting(E_ALL & ~E_STRICT);
