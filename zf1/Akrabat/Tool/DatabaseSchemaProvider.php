<?php
class Akrabat_Tool_DatabaseSchemaProvider extends Zend_Tool_Project_Provider_Abstract
{
    /**
     * @var Zend_Db_Adapter_Interface
     */
    protected $_db;
    
    /**
     * @var string
     */
    protected $_tablePrefix;
    
    /**
     * @var Zend_Config
     */
    protected $_config;
    
    /**
     * Section name to load from config
     * @var string
     */
    protected $_appConfigSectionName;

    public function update($env='development', $dir='./scripts/migrations')
    {
        return $this->updateTo(null, $env, $dir);
    }
    
    public function updateTo($version, $env='development', $dir='./scripts/migrations')
    {
        $this->_init($env);
        $response = $this->_registry->getResponse();
        try {
            $db = $this->_getDbAdapter();
            $manager = new Akrabat_Db_Schema_Manager($dir, $db, $this->getTablePrefix());
            
            $result = $manager->updateTo($version); 
        
            switch ($result) {
                case Akrabat_Db_Schema_Manager::RESULT_AT_CURRENT_VERSION:
                    if (!$version) {
                        $version = $manager->getCurrentSchemaVersion();
                    }
                    $response->appendContent("Already at version $version");
                    break;
                    
                case Akrabat_Db_Schema_Manager::RESULT_NO_MIGRATIONS_FOUND :
                    $response->appendContent("No migration files found to migrate from {$manager->getCurrentSchemaVersion()} to $version");
                    break;
                    
                default:
                    $response->appendContent('Schema updated to version ' . $manager->getCurrentSchemaVersion());
            } 
            
            return true;
        } catch (Exception $e) {
            $response->appendContent('AN ERROR HAS OCCURED:');
            $response->appendContent($e->getMessage());
            return false;
        }
    }
    

    /**
     * Provide the current schama version number
     */
    public function current($env='development', $dir='./migrations')
    {
        $this->_init($env);
        try {
            
            // Initialize and retrieve DB resource
            $db = $this->_getDbAdapter();
            $manager = new Akrabat_Db_Schema_Manager($dir, $db, $this->getTablePrefix());
            echo 'Current schema version is ' . $manager->getCurrentSchemaVersion() . PHP_EOL;
            
            return true;
        } catch (Exception $e) {
            echo 'AN ERROR HAS OCCURED:' . PHP_EOL;
            echo $e->getMessage() . PHP_EOL;
            return false;
        }
    } 
    
    protected function _getDirectory()
    {
        $dir = './scripts/migrations';
        return realpath($dir);
    }

    protected function _init($env)
    {
        $profile = $this->_loadProfile(self::NO_PROFILE_THROW_EXCEPTION);
        $appConfigFileResource = $profile->search('applicationConfigFile');
        if ($appConfigFileResource == false) {
            throw new Zend_Tool_Project_Exception('A project with an application config file is required to use this provider.');
        }
        $appConfigFilePath = $appConfigFileResource->getPath();
        $this->_config = new Zend_Config_Ini($appConfigFilePath, $env);

        require_once 'Zend/Loader/Autoloader.php';
        $autoloader = Zend_Loader_Autoloader::getInstance();
        $autoloader->registerNamespace('Akrabat_');
    }
    
    /**
     * Retrieve initialized DB connection
     *
     * @return Zend_Db_Adapter_Interface
     */
    protected function _getDbAdapter()
    {
        if ((null === $this->_db)) {
            if($this->_config->resources->db){
                $dbConfig = $this->_config->resources->db;
                $this->_db = Zend_Db::factory($dbConfig->adapter, $dbConfig->params);
            } elseif($this->_config->resources->multidb){
                foreach ($this->_config->resources->multidb as $db) {
                    if($db->default){
                        $this->_db = Zend_Db::factory($db->adapter, $db);
                    }
                }
            }
            if($this->_db instanceof Zend_Db_Adapter_Interface) {
                throw new Akrabat_Db_Schema_Exception('Database was not initialized');
            }
        }
        return $this->_db;
    }
    
    /**
     * Retrieve table prefix
     *
     * @return string
     */
    public function getTablePrefix()
    {
        if ((null === $this->_tablePrefix)) {
            $prefix = '';
            if (isset($this->_config->resources->db->table_prefix)) {
                $prefix = $this->_config->resources->db->table_prefix . '_';
            }
            $this->_tablePrefix = $prefix;
        }
        return $this->_tablePrefix;
    }
    

    
    /**
     * 
     * Creates a migration script template.
     * <code>
     * zf create-migration Akrabat $classname
     * </code>
     * <p> This will generate a UTC timestamped migration script in the 
     * scripts/migrations directory.
     * 
     * @param string $migrationName
     */
    public function createMigration($migrationName){
        require_once 'Zend/CodeGenerator/Php/Class.php';
        require_once 'Zend/CodeGenerator/Php/Docblock.php';
        require_once 'Zend/CodeGenerator/Php/Docblock/Tag/Return.php';
        $response = $this->_registry->getResponse();
        $methods = array( 
            array(
                'name' => 'up',
                'body'       => PHP_EOL,
                'docblock'   => new Zend_CodeGenerator_Php_Docblock(array(
                    'shortDescription' => 'The migration forward',
                    'tags'             => array(
                        new Zend_CodeGenerator_Php_Docblock_Tag_Return(array(
                            'datatype'  => 'void',
                        )),
                    ),
                )),
            ),
            array(
                'name' => 'down',
                'body'       => PHP_EOL,
                'docblock'   => new Zend_CodeGenerator_Php_Docblock(array(
                    'shortDescription' => 'The migration reversion',
                    'tags'             => array(
                        new Zend_CodeGenerator_Php_Docblock_Tag_Return(array(
                            'datatype'  => 'void',
                        )),
                    ),
                )),
            )
        );
        $utc_timestamp = time();
        $fileName = "{$utc_timestamp}-{$migrationName}.php";
        $cg = new Zend_CodeGenerator_Php_Class();
        $docblock = new Zend_CodeGenerator_Php_Docblock(array(
            'shortDescription' => 'Akrabat Migration generated class',
            'longDescription'  => 'This is a autogenerated Akrabat migration class.',
            'tags'             => array(
                array(
                    'name'        => 'version',
                    'description' => '$Id: $',
                ),
                array(
                    'name'        => 'license',
                    'description' => '$License: $',
                ),
                array(
                    'name'        => '@author',
                    'description' => ' ',
                ),
                array(
                    'name'        => '@link',
                    'description' => ' ',
                ),
                array(
                    'name'        => '@since',
                    'description' => ' ',
                )
            ),
        ));
        $data = '<?php '.PHP_EOL;
        $data .= $cg->setName($migrationName)
            ->setExtendedClass('Akrabat_Db_Schema_AbstractChange')
            ->setMethods($methods)
            ->setDocblock($docblock)
            ->generate();
        file_put_contents($this->_getDirectory() . '.' . $fileName, $data);
        $response->appendContent('Migration Script Saved...', array('color' => 'green'));
    }

}