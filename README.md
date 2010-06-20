Akrabat ZF library
==================

To get the Zend_Tool provider working:

1. `zf --setup storage-directory`
2. `zf --setup config-file`
3. Edit the created `zf.ini`. Change path so that it includes ZF and Akrabat/library
e.g:

    php.include_path = "/usr/local/include/M5/1.0/library:/usr/local/include/Akrabat/library/"

Also, add the provider class, by adding the following line.

    basicloader.classes.0 = "Akrabat_Tool_MigrationProvider"



To use:

1. Create scripts/migrations folder in your ZF application
2. Create migration files within migrations with the file name format of nnn-Xxxx.php. e.g. 001-Users.php
   where:  
       nnn => any number. The lower numbered files are executed first  
       Xxx => any name. This is the class name within the file.
3. Create a class in your migrations file. Example for 001-Users.php:
  
    <?php
    class Users extends Akrabat_Db_Schema_AbstractChange 
    {
        function up()
        {
            $tableName = $this->_tablePrefix . 'users';
            $sql = "
                CREATE TABLE IF NOT EXISTS $tableName (
                  id int(11) NOT NULL AUTO_INCREMENT,
                  username varchar(50) NOT NULL,
                  password varchar(75) NOT NULL,
                  roles varchar(200) NOT NULL DEFAULT 'user',
                  PRIMARY KEY (id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8;";
            $this->_db->query($sql);
    
            $data = array();
            $data['username'] = 'admin';
            $data['password'] = sha1('password');
            $data['roles'] = 'user,admin';
            $this->_db->insert($tableName, $data);
        }
        
        function down()
        {
            $tableName = $this->_tablePrefix . 'users';
            $sql= "DROP TABLE IF EXISTS $tableName";
            $this->_db->query($sql);
        }
    
    }
    

See http://akrabat.com/zend-framework/akrabat_db_schema_manager-zend-framework-database-migrations/ for full details
