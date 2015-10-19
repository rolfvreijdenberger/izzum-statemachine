<?php
/**
 * Autoload file for all the examples.
 * this adds the composer autoloader, we assume this is the normal composer setup:
 * <root>/vendor/rolfvreijdenberger/izzum/
 * with the autoloader in:
 * <root>/vendor/autoload.php
 */
$files = array(__DIR__ . '/../../../../vendor/autoload.php', __DIR__ . '/../vendor/autoload.php');
foreach($files as $file) {
	if(file_exists($file)) {
		$loader = require_once($file);
		$loader->addPsr4('izzum\examples\\', __DIR__ . '/');
		$loader->register();
		break;
	}
}

