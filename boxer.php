#!/usr/bin/php
<?php
/**
 * Vagrant Boxer application
 *
 * Usage:
 * php -d memory_limit=1024M boxer.php [--bump-version] [--keep-package] [--config-file /path/to/boxer.json]
 *
 * @copyright 2014 Vubeology LLC
 * @author Ross Perkins <ross@vubeology.com>
 */

use Vube\VagrantBoxer\Boxer;

// Require composer autoloader
require __DIR__ .DIRECTORY_SEPARATOR. 'vendor' .DIRECTORY_SEPARATOR. 'autoload.php';


$boxer = new Boxer();

$boxer->init($_SERVER['argv']);
$boxer->exec();
