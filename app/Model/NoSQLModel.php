<?php

/**
 * This is just a wrapper for cakePHP CRUD operations, in order to make them available 
 * for a NoSQL DB.
 * As this is a NOSQL DB Model we want to avoid CakePHP.Model class functionalities,
 * as they were designed for relational databases and may perform some undesired actions, 
 * for that reason we just call to our NOSQL DataSource.    
 * 
 * How to use it :
 * Just extend NoSQLModel instead of AppModel, and all your
 * cakePHP Model methods, should be available as usual (find, create, delete ...)
 * 
 * Here is a example of a custom method for your model :
 * 
 * public function getByKey($key)
 * {
 *   return $this->find('all',array('conditions' =>array('key'=>$key) ));
 * }
 * 
 * As you can see, we use the same syntax that cakePHP gave us for SQL databases,
 * just keep in mind the name of the fields that are searchable in your NoSQL DB,
 * for instance, MongoDB lets you search by the associated value to a key, 
 * but Redis does not.
 *
 *
 * Copyright (c) 2012 Iban Martinez (iban@nnset.com)
 *
 * https://github.com/nnset/Cake-PHP-2.xx-Redis-datasource
 * 
 * Licensed under The MIT License
 * Redistributions of files must retain the above copyright notice.
 *
 * @author Iban Martinez (iban@nnset.com)
 * @license MIT License (http://www.opensource.org/licenses/mit-license.php)
 *
 **/
class NoSQLModel extends AppModel
{
    public $primaryKey = 'key';

   /**
    * We do not want cakePHP to look for a table, as many NoSQL DBs do not have such a thing.
    **/
    public $useTable = false;

    public function create(Model $model, $fields = null, $values = null)
    {
        return $this->getDataSource()->create($model, $fields, $values );
    }
    
    public function read(Model $model, $queryData = array())
    {
        return $this->getDataSource()->read($model,$queryData);
    }
        
    public function update(Model $model, $fields = null, $values = null)
    {
        return $this->getDataSource()->update($model, $fields, $values);
    }
        
    public function delete($key,$cascade = false)
    {
        return $this->getDataSource()->delete($this,$key);
    }
}
?>
