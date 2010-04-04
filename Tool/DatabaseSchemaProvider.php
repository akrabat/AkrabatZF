<?php
class Akrabat_Tool_DatabaseSchemaProvider extends Zend_Tool_Framework_Provider_Abstract
{
    /**
     * @var Zend_Db_Adapter_Interface
     */
    protected $_db;

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
            $manager = new Akrabat_Db_Schema_Manager($dir, $db);
            
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
            $manager = new Akrabat_Db_Schema_Manager($dir, $db);
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
        defined('APPLICATION_ENV') || define('APPLICATION_ENV', $env);
        defined('APPLICATION_PATH') || define('APPLICATION_PATH', realpath('./application'));

        
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
            $config = new Zend_Config_Ini(APPLICATION_PATH . '/configs/application.ini', APPLICATION_ENV);
            $dbConfig = $config->resources->db;
            $this->_db = Zend_Db::factory($dbConfig->adapter, $dbConfig->params);
        }
        return $this->_db;
    }

    
    
    
}