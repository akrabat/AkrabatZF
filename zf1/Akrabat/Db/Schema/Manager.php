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
     * The current version of the schema
     * @var integer|null Null if not yet set
     */
    protected $_currentVersion;

    /**
     * Process files data from the migration scripts folder
     * @var array|null Null if not yet set
     */
    protected $_fileList;

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
    public function __construct($dir, Zend_Db_Adapter_Abstract $db, $tablePrefix='')
    {
        $this->_dir = $dir;
        $this->_db = $db;
        $this->_tablePrefix = $tablePrefix;
    }

    /**
     * Fetch's the current schema version number from out schema table
     *
     * @return integer
     */
    public function getCurrentSchemaVersion()
    {
        if ($this->_currentVersion === null) {
            // Ensure we have valid connection to the database
            if (!$this->_db->isConnected()) {
                $this->_db->getServerVersion();
            }

            $schemaVersionTableName = $this->getPrefixedSchemaVersionTableName();
            $sql = "SELECT version FROM " . $schemaVersionTableName;
            try {
                $this->_currentVersion = (int)$this->_db->fetchOne($sql);
            } catch (Zend_Db_Exception $e) {
                // exception means that the schema version table doesn't exist, so create it
                $createSql = "CREATE TABLE $schemaVersionTableName (
                    version bigint NOT NULL,
                    PRIMARY KEY (version)
                )";
                $this->_db->query($createSql);
                $insertSql = "INSERT INTO $schemaVersionTableName (version) VALUES (0)";
                $this->_db->query($insertSql);
                $this->_currentVersion = (int)$this->_db->fetchOne($sql);
            }
        }
        return $this->_currentVersion;
    }

    public function updateTo($version)
    {
        $currentVersion = $this->getCurrentSchemaVersion();
        if($currentVersion == $version) {
            return self::RESULT_AT_CURRENT_VERSION;
        }

        $migrationList = $this->_getMigrationVersionList($currentVersion, $version);

        if(empty($migrationList)) {
            return self::RESULT_NO_MIGRATIONS_FOUND;
        }

        $direction = 'up';
        if ($currentVersion > $version) {
            $direction = 'down';
        }

        $fileList  = $this->_getFiles();
        if($direction == 'down') {
            krsort($fileList);
        }
        $file      = reset($fileList);
        $migration = reset($migrationList);
        do {
            if ($file['version'] == $migration) {
                $this->_processFile($file, $direction);

                $currentVersion = $migration;
                if ($direction == 'down') {
                    $nextFile = next($fileList);

                    if ($nextFile === false) {
                        $currentVersion--;
                    } else {
                        $currentVersion = $nextFile['version'];
                    }
                    // Move the pointer back
                    prev($fileList);
                }
                $this->_updateSchemaVersion($currentVersion);
                $migration = next($migrationList);
            }

            $file = next($fileList);
        } while ($file && $migration);
        return self::RESULT_OK;
    }

    /**
     * Will take the version number passed by the user and turn it into something useful
     *
     * @param string $version
     *
     * @return integer
     */
    public function processVersion($version)
    {
        $orig       = $version;
        $strVersion = strtolower($version);

        // If this is a normal integer them we're done
        if ((string)(int)$version === $version && $version[0] != '-') {
            return (int)$version;
        }

        // A workaround as Zend_Tool can't process 0 as an argument
        // @link http://framework.zend.com/issues/browse/ZF-11808
        if ($strVersion == 'zero') {
            return 0;
        }

        // Get current version and the list of files
        $currentVersion = $this->getCurrentSchemaVersion();
        $fileList       = $this->_getFiles();
        $lastFile       = end($fileList);
        $firstFile      = reset($fileList);

        // If the version is null, then just go to the last migration file
        if (is_null($version)) {
            $version = $lastFile['version'];
            // The version may be set too high when downgrading in another
            // migrations folder
            if ($currentVersion > $version) {
                $version = $currentVersion;
            }
            return (int)$version;
        }

        $realCurrentVersion = $this->determinRealCurrent($currentVersion, $fileList);

        // Move the array pointer to the current version
        if ($realCurrentVersion != 0) {
            while (key($fileList) != $realCurrentVersion) {
                next($fileList);
            }
        }

        // Process
        if (empty($strVersion) === false) {
            switch (true) {
                case $strVersion == 'next':
                    if ($realCurrentVersion == 0) {
                        $version = $firstFile['version'];
                        break;
                    }
                    $steps = 1;
                    // Fall
                case $strVersion[0] == '+':
                    if (isset($steps) === false) {
                        $steps = (int)substr($strVersion, 1);
                    }

                    $version = $this->findNextFile($fileList, $steps, 'up', $lastFile['version']);
                    break;
                case $strVersion == 'prev':
                    if ($realCurrentVersion == 0) {
                        break;
                    }
                    $steps = 1;
                    // Fall

                // A workaround as Zend_Tool can't process negative values as arguments
                // @link http://framework.zend.com/issues/browse/ZF-11808
                case strpos($strVersion, 'minus') === 0:
                    if (isset($steps) === false) {
                        $steps = (int)substr($strVersion, 5);
                    }
                    // Fall
                case $strVersion[0] == '-':
                    if (isset($steps) === false) {
                        $steps = (int)substr($strVersion, 1);
                    }

                    $version = $this->findNextFile($fileList, $steps, 'down', $firstFile['version']);
                    break;
            }
        }

        $this->validateVersion($version, $orig);

        return (int)$version;
    }

    /**
     * Is version a valid integer?
     *
     * @param integer|string $version         The version number to check
     * @param integer        $originalVersion The version that was originally requested
     *
     * @return void
     */
    protected function validateVersion($version, $originalVersion)
    {
        if ($version && ((string)(integer)$version !== (string)$version)) {
            throw new Akrabat_Db_Schema_Exception(
                "Version $originalVersion is not a valid version choice"
            );
        }
    }

    protected function findNextFile(&$fileList, $steps, $direction, $defaultVersion)
    {
        $version = $defaultVersion;
        for ($i = 0; $i < $steps; $i++) {
            if ($direction == 'up') {
                $nextFile = next($fileList);
            } else {
                $nextFile = prev($fileList);
            }
            if ($nextFile === false) {
                // Too many steps, get out of this current for loop
                break;
            }
            $version = $nextFile['version'];
        }

        return $version;
    }

    /**
     * Try to determin the real current version
     *
     * @param integer $currentVersion The current version as stored in the schema table
     * @param array   $fileList       The list of files we will be processing
     *
     * @return integer
     */
    protected function determinRealCurrent($currentVersion, $fileList)
    {
        $realCurrentVersion = $currentVersion;
        while ($realCurrentVersion != 0 && isset($fileList[$realCurrentVersion]) === false) {
            $realCurrentVersion--;
        }
        return $realCurrentVersion;
    }

    /**
     * Builds an array of migrations version numbers to execute
     *
     * @param integer $currentVersion The current version of the schema
     * @param integer $stopVersion    The version we must get to
     * @return array
     */
    protected function _getMigrationVersionList($currentVersion, $stopVersion)
    {
        $direction = 'up';
        $from      = $currentVersion;
        $to        = $stopVersion;
        if ($stopVersion < $currentVersion) {
            $direction = 'down';
            $from      = $stopVersion;
            $to        = $currentVersion;
        }

        $migrations = array();
        foreach($this->_getFiles() as $file) {
            $versionNumber = $file['version'];
            if ($versionNumber > $from && $versionNumber <= $to) {
                $migrations[] = $versionNumber;
            }
        }

        if ($direction == 'up') {
            sort($migrations);
        } else {
            rsort($migrations);
        }
        return $migrations;
    }

    /**
     * Run the migration
     *
     * @param array  $migration Migration data
     * @param string $direction 'up' or 'down'
     * @throws Akrabat_Db_Schema_Exception
     * @return void
     */
    protected function _processFile($migration, $direction)
    {
        $filename  = $migration['filename'];
        $classname = $migration['classname'];
        require_once($this->_dir.'/'.$filename);
        if (!class_exists($classname, false)) {
            throw new Akrabat_Db_Schema_Exception("Could not find class '$classname' in file '$filename'");
        }
        $class = new $classname($this->_db, $this->_tablePrefix);
        $class->$direction();
    }

    /**
     * Update the schema table with a new version
     *
     * @param integer $version The version to change to
     * @return void
     */
    protected function _updateSchemaVersion($version)
    {
        $this->_currentVersion  = (int)$version;
        $schemaVersionTableName = $this->getPrefixedSchemaVersionTableName();
        $sql = "UPDATE  $schemaVersionTableName SET version = " . (int)$version;
        $this->_db->query($sql);
    }

    /**
     * The schema table name with table prefix
     *
     * @return void
     */
    public function getPrefixedSchemaVersionTableName()
    {
        return $this->_tablePrefix . $this->_schemaVersionTableName;
    }

    /**
     * Get a processed list of files from the migration script folder
     *
     * @return array
     */
    protected function _getFiles()
    {
        if ($this->_fileList === null) {
            $this->_fileList = array();
            $d           = dir($this->_dir);
            while (false !== ($entry = $d->read())) {
                if (preg_match('/^([0-9]+)\-(.*)\.php/i', $entry, $matches) ) {
                    $versionNumber = (int)$matches[1];
                    $className     = $matches[2];

                    $this->_fileList[$versionNumber] = array(
                        'filename'  => $entry,
                        'version'   => $versionNumber,
                        'classname' => $className);
                }
            }
            $d->close();
            ksort($this->_fileList);

            if (empty($this->_fileList)) {
                throw new Akrabat_Db_Schema_Exception(
                    'No migrations found in '.$this->_dir
                );
            }
        }
        return $this->_fileList;
    }
}
