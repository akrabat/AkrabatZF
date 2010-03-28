<?php

abstract class Akrabat_Db_Schema_AbstractChange 
{ 
    /**
     * @var Zend_Db_Adapter_Abstract
     */ 
    protected $_db; 
     
     
    function __construct(Zend_Db_Adapter_Abstract $db) 
    {
        $this->_db = $db;
    } 
     
    /**
     * Changes to be applied in this change
     */ 
    abstract function up(); 
 
    /**
     * Rollback changes made in up()
     */ 
    abstract function down(); 
     
} 