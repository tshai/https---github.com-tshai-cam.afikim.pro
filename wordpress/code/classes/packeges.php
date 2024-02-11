<?php
require($_SERVER['DOCUMENT_ROOT'] . '/vendor/autoload.php');
// First, include the WordPress load script to access WordPress functions
require_once($_SERVER['DOCUMENT_ROOT'] . '/wp-load.php');

use Ramsey\Uuid\Uuid;

class_alias('\RedBeanPHP\R', '\R');
