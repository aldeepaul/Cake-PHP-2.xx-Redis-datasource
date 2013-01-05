<?php

/**
 *
 * As this is a NOSQL Model we want to avoid CakePHP Model class functionalities,
 * as they were designed for relational databases and may perform some undesired
 * actions (some counts where driving me crazy for instance), for that reason
 * we directly call to our NOSQL DataSource.
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
class RedisModel extends AppModel
{
   /**
    * We do not want cakePHP to look for a table, as NoSQL DBs do not have such a thing.
    **/
    public $useTable   = false;
    public $primaryKey = 'key';       

    protected $ds = NULL; // RedisDatasource alias.

    public function __construct($id = false, $table = null, $ds = null)
    {
        parent::__construct($id, $table, $ds);
        $this->ds = $this->getDataSource();
    }


   /**
    *  Execute a Redis command, you mau execute any command available into your redis server.
    *
    *  Usage $redis->execute("get",array("key_name"));
    *
    * @param $command String with the key of the command, for instance "get".
    * @param $params  Array with all the parameters required by the command.
    *
    * @see http://redis.io/commands
    *
    **/
    protected function execute($command, $params)
    {                
        return $this->ds->executeCommand( $this->ds->buildCommand($command, $params) );
    }   


    ////////////////////////////////////////////////////
    // Custom methods not included in Redis commands. // 
    ////////////////////////////////////////////////////


   /**
    *
    * Will delete many keys using "*" wildcard. For instance deleteWithWildcard("user*").
    * Rememeber that by now, Redis does not support wildcard deletes, so this method
    * will perform 2 Redis operations :
    *  1. Generate list of keys using Redis "keys" command.
    *  2. For each key, will perform a delete operation.
    *
    * @return Integer reply: The number of keys that were removed.
    *
    **/
    protected function deleteWithWildcard($keyWithWildCard)
    {
        $keysRemoved  = 0;
        $keysToDelete = $this->ds->executeCommand($this->ds->buildCommand("keys", array($keyWithWildCard)));

        foreach ($keysToDelete as $key)
            $keysRemoved +=$this->ds->executeCommand($this->ds->buildCommand("del", array($key)));

        return $keysRemoved;
    }    
}
?>