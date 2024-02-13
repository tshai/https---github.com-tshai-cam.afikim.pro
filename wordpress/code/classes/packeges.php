<?php
if (!defined('PROJECT_ROOT')) {
    define('PROJECT_ROOT', $_SERVER['DOCUMENT_ROOT']);
}

require_once(PROJECT_ROOT . '/vendor/autoload.php');
// First, include the WordPress load script to access WordPress functions
require_once(PROJECT_ROOT . '/wp-load.php');

use Ramsey\Uuid\Uuid;

class_alias('\RedBeanPHP\R', '\R');
