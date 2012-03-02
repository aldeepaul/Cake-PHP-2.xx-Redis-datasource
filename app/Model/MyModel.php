<?php
App::import('Model', 'RedisModel');

class MyModel extends RedisModel
{
    public $name        = 'MyModel';
    public $useDbConfig = 'redisDB_1'; // Defined un app/Config/database.php

   
    /**
     * Store on DB a new instance of a MyModel.
     * 
     * @param $key Redis key
     * @param $value Key's value.
     * 
     * @return Created instance will be returned in CakePHP's array way; will return false 
     *         otherwise is something went wrong. 
     *         Note that what we return is the value of cakephp's Model->save method.
     * 
     **/
    public function createMyModel( $key, $value )
    {        
        return $this->create($this,array('key','value'),array($key,$value));
    }


    /**
     * Get the value of a given key.
     * 
     * @param $key Redis key
     * 
     * @return Key's value.
     * 
     **/    
    public function getByKey($key)
    {
        return $this->read($this,array('conditions' =>array('key'=>$key) ));
    }    
}

?>
