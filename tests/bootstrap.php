<?php
// Ensure library/ is on include_path
set_include_path(implode(PATH_SEPARATOR, array(
    realpath(realpath(dirname(__FILE__))  . '/../library'),
    realpath(realpath(dirname(__FILE__))  . '/..'),
    get_include_path(),
)));

require_once 'vendor/autoload.php';
require_once 'Zend/Loader/Autoloader.php';

Zend_Loader_Autoloader::getInstance();
