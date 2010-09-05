<?php
class Akrabat_Db_Schema_Manager  
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
    function __construct($dir, Zend_Db_Adapter_Abstract $db, $tablePrefix='')
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
        } catch (Zend_Db_Exception $e) {
            // exception means that the schema version table doesn't exist, so create it
            $createSql = "CREATE TABLE $schemaVersionTableName ( 
                version int NOT NULL,
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
     
    protected function _getMigrationFiles($currentVersion, $stopVersion) 
    {
        $direction = 'up';
        $from = $currentVersion;
        $to  = $stopVersion;
        if($stopVersion < $currentVersion) {
            $direction = 'down';
            $from  = $stopVersion;
            $to = $currentVersion;
        }

        $files = array();
        $d = dir($this->_dir);
        while (false !== ($entry = $d->read())) {
            if (preg_match('/^([0-9]+)\-(.*)\.php/i', $entry, $matches) ) {
                $versionNumber = (int)$matches[1];
                $className = $matches[2];
                if ($versionNumber > $from && $versionNumber <= $to) {
                    $files[$versionNumber] = array(
                        'filename'=>$entry,
                        'version'=>$versionNumber,
                        'classname'=>$className);
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
        $version = $migration['version'];
        $filename = $migration['filename'];
        $classname = $migration['classname'];
        require_once($this->_dir.'/'.$filename);
        if (!class_exists($classname, false)) {
            throw new Akrabat_Db_Schema_Exception("Could not find class '$classname' in file '$filename'");
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

} 