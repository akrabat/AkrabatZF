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


