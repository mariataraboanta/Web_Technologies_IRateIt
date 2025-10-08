<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
define('BASE_URL', '/IRI_Ballerina_Cappuccina/');
define('SEP', DIRECTORY_SEPARATOR);
define('PDIR', dirname(__DIR__));
session_start();

require_once __DIR__ . '/../vendor/autoload.php';

use Dotenv\Dotenv;

$dotenv = Dotenv::createImmutable(dirname(__DIR__));
$dotenv->load();

require_once '../app/core/autoload.php';
require_once '../app/core/Router.php';

$router = new Router();
$router->dispatch($_SERVER['REQUEST_URI'], $_SERVER['REQUEST_METHOD']);
