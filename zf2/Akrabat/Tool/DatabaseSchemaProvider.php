<?php

namespace Akrabat\Tool;

class DatabaseSchemaProvider 
    extends \Zend\Tool\Project\Provider\AbstractProvider
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
            $manager = new \Akrabat\Db\Schema\Manager($dir, $db, $this->getTablePrefix());
            
            $result = $manager->updateTo($version); 
        
            switch ($result) {
                case \Akrabat\Db\Schema\Manager::RESULT_AT_CURRENT_VERSION:
                    if (!$version) {
                        $version = $manager->getCurrentSchemaVersion();
                    }
                    $response->appendContent("Already at version $version");
                    break;
                    
                case \Akrabat\Db\Schema\Manager::RESULT_NO_MIGRATIONS_FOUND :
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
            $manager = new \Akrabat\Db\Schema\Manager($dir, $db, $this->getTablePrefix());
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
            throw new \Zend\Tool\Project\Exception('A project with an application config file is required to use this provider.');
        }
        $appConfigFilePath = $appConfigFileResource->getPath();
        $this->_config = new \Zend\Config\Ini($appConfigFilePath, $env);

        require_once 'Zend/Loader/StandardAutoloader.php';
        $autoloader = new \Zend\Loader\StandardAutoloader();
        $autoloader->registerNamespace('Akrabat', realpath(__DIR__. '/../'));
        $autoloader->register();
    }
    
    /**
     * Retrieve initialized DB connection
     *
     * @return \Zend\Db\Adapter\AbstractAdapter
     */
    protected function _getDbAdapter()
    {
        if ((null === $this->_db)) {
        	if($this->_config->resources->db){
        		$dbConfig = $this->_config->resources->db;
            	$this->_db = \Zend\Db\Db::factory($dbConfig->adapter, $dbConfig->params);
        	} elseif($this->_config->resources->multidb){
        		foreach ($this->_config->resources->multidb as $db) {
        			if($db->default){
        				$this->_db = \Zend\Db\Db::factory($db->adapter, $db);
        			}
        		}
        	}
        	if($this->_db instanceof \Zend\Db\Adapter\AbstractAdapter) {
        		throw new Akrabat\Db\Schema\Exception('Database was not initialized');
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
        }
        return $this->_tablePrefix;
    }

}