<?php
class Akrabat_Db_Schema_Manager  
{ 
    const RESULT_OK = 'RESULT_OK';
    const RESULT_AT_CURRENT_VERSION = 'RESULT_AT_CURRENT_VERSION';
    const RESULT_NO_MIGRATIONS_FOUND = 'RESULT_NO_MIGRATIONS_FOUND';
    const RESULT_AT_MAXIMUM_VERSION = 'RESULT_AT_MAXIMUM_VERSION';
    const RESULT_AT_MINIMUM_VERSION = 'RESULT_AT_MINIMUM_VERSION';
    
    
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
        
        // figure out what the real version we're going to is if going down
        if ($direction == 'down') {
        	$files = $this->_getMigrationFiles($version, 0);
        	$versionFile = array_shift($files);
        	if (!empty($files)) {
        		$realVersion = $versionFile['version'];
        	} else {
        		$realVersion = 0;
        	}
        	// update the database to the version we're actually at
        	$this->_updateSchemaVersion($realVersion);
        }
        
        return self::RESULT_OK;
    } 

    public function incrementVersion($versions)
    {
    	$versions = (int)$versions;
    	if ($versions < 1) {
    		$versions = 1;
    	}
    	$currentVersion = $this->getCurrentSchemaVersion();
    	
    	$files = $this->_getMigrationFiles($currentVersion, PHP_INT_MAX);
    	if (empty($files)) {
    		return self::RESULT_AT_MAXIMUM_VERSION;
    	}
    	
    	$files = array_slice($files, 0, $versions);

    	$nextFile = array_pop($files);
    	$nextVersion = $nextFile['version'];
    	
    	return $this->updateTo($nextVersion);
    }
    
    public function decrementVersion($versions)
    {
    	$versions = (int)$versions;
    	if ($versions < 1) {
    		$versions = 1;
    	}
    	$currentVersion = $this->getCurrentSchemaVersion();
    	
    	$files = $this->_getMigrationFiles($currentVersion, 0);
    	if (empty($files)) {
    		return self::RESULT_AT_MINIMUM_VERSION;
    	}
    	
    	$files = array_slice($files, 0, $versions+1);
    	$nextFile = array_pop($files);
    	$nextVersion = $nextFile['version'];
    	
    	return $this->updateTo($nextVersion);
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
        if (!is_dir($this->_dir) || !is_readable($this->_dir)) {
        	return $files;
        } 
        
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