<?php

App::import('Model', 'RedisModel');


class MyModel extends RedisModel
{
    public $name        = 'MyModel';
    public $useDbConfig = 'redisDB_1'; // Defined at app/Config/database.php
  

    /**
     * Get the value of a given key.
     * 
     * @param $key Redis key
     * 
     * @return Key's value.
     * 
     **/  
    public function get($key)
    {
        return $this->execute("get", array($key));
    }
    

    /**
     * Store on DB a new instance of a MyModel.
     * 
     * @param $key Redis key
     * @param $value Key's value.
     * 
     * @return http://redis.io/commands/set
     * 
     **/ 
    public function set( $key, $value )
    {   
        return $this->execute("set", array("key"=>$key,"value"=>$value));
    }
        
}

?>