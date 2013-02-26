<?php

class Akrabat_Db_Schema_ManagerTest extends PHPUnit_Framework_TestCase
{
    private $_manager;
    
    public function setUp()
    {
    
        $dir = realpath(dirname(__FILE__) . '/../../scripts/migrations');
        $dbAdapter = Zend_Db::factory('pdo_sqlite', array(
            'dbname'     => dirname(__FILE__) . '/test.sqlite'
            )
        );
		
        $this->_manager = new Akrabat_Db_Schema_Manager($dir, $dbAdapter);
    }

    public function testMangagerCreated()
    {
        $this->assertTrue($this->_manager instanceof Akrabat_Db_Schema_Manager);
    }
    
}
	