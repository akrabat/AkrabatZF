<?php

namespace Akrabat\Db\Schema;

class Manager  
{ 
    const RESULT_OK = 'RESULT_OK';
    const RESULT_AT_CURRENT_VERSION = 'RESULT_AT_CURRENT_VERSION';
    const RESULT_NO_MIGRATIONS_FOUND = 'RESULT_NO_MIGRATIONS_FOUND';
    
    protected $_schemaVersionTableName = 'schema_version';
    
    /**
     * Directory containing migration files
     * @var string
     */
    protected $_dir;
     
    /**
     * Database adapter
     * @var Zend_Db_Adapter_Abstract
     */ 
    protected $_db; 

    /**
     * Table prefix string for use by change classes
     * @var string
     */
    protected $_tablePrefix;
 
    /**
     * Constructor
     * 
     * Available $options keys:
     * 		'table_prefix' => prefix string to place before table names
     * 		'schema_version_table_name' => name of table to use for holding the schema version number
     * 
     * 
     * @param $dir              Directory where migrations files are stored
     * @param $db               Database adapter
     * @param $tablePrefix      Table prefix to be used by change files
     * @param $options          Options
     */
    function __construct($dir, \Zend\Db\Adapter\AbstractAdapter $db, $tablePrefix='')
    {
        $this->_dir = $dir;
        $this->_db = $db;
        $this->_tablePrefix = $tablePrefix;
    } 
    
    function getCurrentSchemaVersion() 
    {
        // Ensure we have valid connection to the database
        if (!$this->_db->isConnected()) {
            $this->_db->getServerVersion();
        }
        $schemaVersionTableName = $this->getPrefixedSchemaVersionTableName();
        
        $sql = "SELECT version FROM " . $schemaVersionTableName;
        try {
            $version = $this->_db->fetchOne($sql);
        } catch (Exception $e) {
            // exception means that the schema version table doesn't exist, so create it
            $createSql = "CREATE TABLE $schemaVersionTableName ( 
                version bigint NOT NULL,
                PRIMARY KEY (version)
            )";
            $this->_db->query($createSql);
            $insertSql = "INSERT INTO $schemaVersionTableName (version) VALUES (0)";
            $this->_db->query($insertSql);
            $version = $this->_db->fetchOne($sql);
        }
        
        return $version;
    } 
    
    function updateTo($version = null) 
    {
        if (is_null($version)) {
            $version = PHP_INT_MAX;
        }
        $version = (int)$version;
        $currentVersion = $this->getCurrentSchemaVersion();
        if($currentVersion == $version) {
            return self::RESULT_AT_CURRENT_VERSION;
        }
        
        $migrations = $this->_getMigrationFiles($currentVersion, $version);
        if(empty($migrations)) { 
            if ($version == PHP_INT_MAX) {
                return self::RESULT_AT_CURRENT_VERSION;
            }
            return self::RESULT_NO_MIGRATIONS_FOUND;
        }
        
        $direction = 'up';
        if ($currentVersion > $version) {
            $direction = 'down';
        }
        foreach ($migrations as $migration) {
            $this->_processFile($migration, $direction);
        }
        
        return self::RESULT_OK;
    } 
     
    protected function _getMigrationFiles($currentVersion, $stopVersion, $dir = null) 
    {
        if ($dir === null) {
            $dir = $this->_dir;
        }

        $direction = 'up';
        $from = $currentVersion;
        $to  = $stopVersion;
        if($stopVersion < $currentVersion) {
            $direction = 'down';
            $from  = $stopVersion;
            $to = $currentVersion;
        }

        $files = array();
        if (!is_dir($dir) || !is_readable($dir)) {
            return $files;
        }

        $d = dir($dir);
        while (false !== ($entry = $d->read())) {
            if (preg_match('/^([0-9]+)\-(.*)\.php/i', $entry, $matches) ) {
                $versionNumber = (int)$matches[1];
                $className = $matches[2];
                if ($versionNumber > $from && $versionNumber <= $to) {
                    $path = $this->_relativePath($this->_dir, $dir);
                    $files["v{$matches[1]}"] = array(
                        'path'=>$path,
                        'filename'=>$entry,
                        'version'=>$versionNumber,
                        'classname'=>$className);
                }
            } elseif ($entry != '.' && $entry != '..') {
                $subdir = $dir . '/' . $entry;
                if (is_dir($subdir) && is_readable($subdir)) {
                    $files = array_merge(
                        $files,
                        $this->_getMigrationFiles(
                            $currentVersion, $stopVersion, $subdir
                        )
                    );
                }
            }
        }
        $d->close();
        
        if($direction == 'up') {
            ksort($files);
        } else {
            krsort($files);
        }
        
        return $files;
    } 

    protected function _processFile($migration, $direction) 
    {
        $path = $migration['path'];
        $version = $migration['version'];
        $filename = $migration['filename'];
        $classname = $migration['classname'];
        require_once($this->_dir.'/'.$path.'/'.$filename);
        if (!class_exists($classname, false)) {
            throw new Exception("Could not find class '$classname' in file '$filename'");
        }
        $class = new $classname($this->_db, $this->_tablePrefix);
        $class->$direction();
        
        if($direction == 'down') {
            // current version is actually one lower than this version now
            $version--;
        }
        $this->_updateSchemaVersion($version);
    } 
    
    protected function _updateSchemaVersion($version) 
    {
        $schemaVersionTableName = $this->getPrefixedSchemaVersionTableName();
        $sql = "UPDATE  $schemaVersionTableName SET version = " . (int)$version;
        $this->_db->query($sql);
    }
    
    public function getPrefixedSchemaVersionTableName()
    {
        return $this->_tablePrefix . $this->_schemaVersionTableName;
    }

    protected function _relativePath($from, $to, $ps = DIRECTORY_SEPARATOR)
    {
        $arFrom = explode($ps, rtrim($from, $ps));
        $arTo = explode($ps, rtrim($to, $ps));
        while (count($arFrom) && count($arTo) && ($arFrom[0] == $arTo[0])) {
            array_shift($arFrom);
            array_shift($arTo);
        }
        return str_pad("", count($arFrom) * 3, '..'.$ps).implode($ps, $arTo);
    }
} 
