<?php

use Phalcon\Autoload\Loader;

$loader = new Loader();


// Register namespaces (using setNamespaces instead of registerNamespaces)
$loader->setNamespaces([
    'App\Controllers' => '../app/controllers/',
    'App\Models'      => '../app/models/',
    'App\Utils'     => '../app/utils/',
]);

$loader->setDirectories([
    APP_PATH . '/app/controllers/',
    APP_PATH . '/app/models/',
    APP_PATH . '/app/utils/',
]);



$loader->register();