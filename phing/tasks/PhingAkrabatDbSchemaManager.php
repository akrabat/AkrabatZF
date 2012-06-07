<?php

/**
 * Phing class for migrating the database using Akrabat_Db_Schema_Manager
 * 
 * @see https://github.com/akrabat/Akrabat
 */
class PhingAkrabatDbSchemaManager extends Task
{
	/**
	 * db adapter
	 * @param string
	 */
	public function setAdapter($adapter) {
	
		$this->_adapter = $adapter;
	}
	
	/**
	 * db host
	 * @param string
	 */
	public function setHost($host) {
	
		$this->_host = $host;
	}
	
	/**
	 * db username
	 * @param string
	 */
	public function setUsername($username) {
	
		$this->_username = $username;
	}
	
	/**
	 * db password
	 * @param string
	 */
	public function setPassword($password) {
	
		$this->_password = $password;
	}
	
	/**
	 * db name
	 * @param string
	 */
	public function setDbname($dbname) {
	
		$this->_dbname = $dbname;
	}
	
	public function main()
	{
		try {

			$dir = realpath(dirname(__FILE__) . '/../../scripts/migrations');
			$db = Zend_Db::factory($this->_adapter, array(
					'host'     => $this->_host,
					'username' => $this->_username,
					'password' => $this->_password,
					'dbname'   => $this->_dbname,
			));
			
			$manager = new Akrabat_Db_Schema_Manager($dir, $db);
			$this->log('Current database schema version is ' . $manager->getCurrentSchemaVersion());
			$manager->updateTo(null);
			$this->log('Updated database schema version updated to ' . $manager->getCurrentSchemaVersion());
		} catch (Zend_Exception $e) {
			$this->log('Failed executing database migration - ' . $e->getMessage(), Project::MSG_ERR);
		}
		
	}
	
}