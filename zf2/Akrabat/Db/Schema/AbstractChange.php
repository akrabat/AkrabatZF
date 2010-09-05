<?php

namespace Akrabat\Db\Schema;

abstract class AbstractChange 
{ 
    /**
     * @var \Zend\Db\Adapter\AbstractAdapter
     */ 
    protected $_db; 

    /**
     * @var string
     */
    protected $_tablePrefix;
     
    function __construct(\Zend\Db\Adapter\AbstractAdapter $db, $tablePrefix = '')
    {
        $this->_db = $db;
        $this->_tablePrefix = $tablePrefix;
    } 
     
    /**
     * Changes to be applied in this change
     */ 
    abstract function up(); 
 
    /**
     * Rollback the changes made in up()
     */ 
    abstract function down(); 
     
} 