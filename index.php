<?php
define('ROOT_DIR', realpath(dirname(__FILE__)) .'/');
define('CONTENT_DIR', ROOT_DIR .'storage/content/');
define('CONTENT_EXT', '.md');
define('LIB_DIR', ROOT_DIR .'pico/');
define('PLUGINS_DIR', LIB_DIR .'plugins/');
define('RESOURCES_DIR', ROOT_DIR .'dist/');

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', ROOT_DIR . 'storage/logs/error.log');

require_once(ROOT_DIR .'vendor/autoload.php');
require_once(LIB_DIR .'pico.php');
$pico = new Pico();