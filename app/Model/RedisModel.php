<?php

App::import('Model', 'NoSQLModel');


/**
 *
 * This is just a wrapper for RedisDatasource public methods.
 *
 * As this is a NOSQL Model we want to avoid CakePHP Model class functionalities,
 * as they were designed for relational databases and may perform some undesired
 * actions (some counts where driving me crazy for instance), for that reason
 * we directly call to our NOSQL DataSource.
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
class RedisModel extends NoSQLModel
{

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
    public function deleteWithWildcard($keyWithWildCard)
    {
        $keysRemoved  = 0;
        $keysToDelete = $this->getDataSource()->keys($this, $keyWithWildCard);

        foreach ($keysToDelete as $key)
            $keysRemoved += $this->getDataSource()->delete($this,$key);

        return $keysRemoved;
    }

    
    ////////////////////////////////
    // Redis standard operations  //
    ////////////////////////////////
    
    
   /**
    * 
    * Will delete a key.
    * 
    * @return Integer reply: The number of keys that were removed.
    *
    **/
    public function deleteKey($key)
    {
        return  $this->getDataSource()->delete($this,$key);
    }       


   /**
    * Appends a value to a given key's current value.
    *   -> redis['my_key'] => 'Hello ' after an append operation we will have :
    *     -> append('my_key','World')
    *   -> redis['my_key'] => 'Hello World'
    *
    * @param $model CakePHP Model instance.
    * @param $key Database key
    * @param $valueToAppend string Value that will be append to given key value.
    *
    * @return Integer reply: the length of the string after the append operation.
    * @throws RedisException if $key has no value
    *
    * @see http://redis.io/commands/append
    * 
    **/
    public function append($key,$value)
    {
        return $this->getDataSource()->append($this,$key,$value);
    }

    
   /**
    * Request for authentication in a password protected Redis server.
    *
    * @param $model CakePHP Model instance.
    * @param $password Database password
    *
    * @return Redis Status code reply
    *
    * @see http://redis.io/commands/auth
    * 
    **/    
    public function auth($password)
    {
        // TODO : Test this method.
        return $this->getDataSource()->authenticate($this,$password);
    }


   /**
    *  Asynchronously rewrite the append-only file.
    *
    * @param $model CakePHP Model instance.
    *
    * @return Always will return OK Status reply.
    *
    * @see http://redis.io/commands/bgrewriteaof
    **/
    public function rewriteAppendOnlyFile()
    {
        // TODO : Test this method.
        return $this->getDataSource()->rewriteAppendOnlyFile($this);
    }


   /**
    * Saves DB on disk in background mode.
    *
    * @param $model CakePHP Model instance.
    *
    * @return Always will return OK Status reply, as this an asynchronous process.
    *         Use lastSaveStatusCode in order to get when was the last time DB 
    *         was stored on disk.
    *
    * @see http://redis.io/commands/bgsave
    **/
    public function saveDatabaseToDisk()
    {
        return $this->getDataSource()->bgsave($this);
    }


   /**
    * Gets a unix timestamp from the last time DB was stored on disk.
    *
    * @param $model CakePHP Model instance.
    *
    * @return Unix timestamp.
    *
    * @see http://redis.io/commands/lastsave
    **/    
    public function lastSaveStatusCode()
    {
        return $this->getDataSource()->lastSaveStatusCode($this);
    }


   /**
    * Return the number of keys in the currently selected database.
    *
    * @param $model CakePHP Model instance.
    *
    * @return integer value with the number of elements stored in current DB.
    *
    * @see http://redis.io/commands/dbsize
    **/    
    public function dbSize()
    {
       return intval(preg_replace("/[^0-9\s]/", "", $this->getDataSource()->dbSize($this)));
    }
    
    
   /**
    * Decrements the number stored at key by one if $decValue is not given or 
    * by $decValue units.
    *
    * @param $model CakePHP Model instance. 
    * @param $key Database key
    * @param $decValue integer with the number of units to decrement. 
    *
    * @return Integer reply: the value of key after the decrement
    *
    * @see http://redis.io/commands/decr
    *
    **/
    public function decrement($key, $decValue = null)
    {
        if ($decValue != null)
            return $this->getDataSource()->decrementBy($this, $key, $decValue);
        else
            return $this->getDataSource()->decrement($this, $key);
    }
   

   /**
    * Expires a db entry with the given $key in $expirationTimeout seconds.
    * Note : Expiring in Redis means delete.
    *
    * @param $model CakePHP Model instance.
    * @param $key Database key
    * @param $expirationTimeout expiration timeout in seconds.
    *
    * @return Integer reply: 1 if the timeout was set.  0 if key does not exist or
    *         the timeout could not be set.
    *
    * @see http://redis.io/commands/expire
    *
    **/ 
    public function expireIn($key, $expirationTimeout)
    {
        return $this->getDataSource()->expire($this, $key, $expirationTimeout);
    }


   /**
    * Expires a db entry with the given $key at $unixTimeStamp time.
    *
    * @param $model CakePHP Model instance.
    * @param $key Database key
    * @param $unixTimeStamp A unix timestamp.
    *
    * @return Integer reply: 1 if the timeout was set.  0 if key does not exist or
    *         the timeout could not be set.
    *
    * @see http://redis.io/commands/expireat
    *
    **/
    public function expireAt($key, $unixTimeStamp)
    {
        return $this->getDataSource()->expireAt($this, $key, $unixTimeStamp);
    }


   /**
    * Returns the substring of the string value stored at key, determined by the
    * offsets start and end (both are inclusive).
    * Negative offsets can be used in order to provide an offset starting
    * from the end of the string. So -1 means the last character, -2 the
    * penultimate and so forth.
    *
    * @param $model CakePHP Model instance.
    * @param $key Database key
    * @param $start start offset
    * @param $end end offset
    *
    * @return Key's value substring
    *
    * @see http://redis.io/commands/getrange
    *
    **/
    public function getRange($key, $start, $end)
    {
        return $this->getDataSource()->getRange($this, $key, $start, $end);
    }
    
    
   /**
    * Atomically sets key to value and returns the old value stored at key.
    * Returns an error when key exists but does not hold a string value.
    *
    * @param $key Database key
    * @param $value new value to set to key.
    *
    * @return old value stored at $key
    *
    * @see http://redis.io/commands/getset
    *
    **/
    public function getAndSet($key, $value)
    {
        return $this->getDataSource()->getAndSet($this, $key, $value);
    }    

    
   /**
    * Increment the number stored at key by one if $incValue is not given or
    * by $incValue units.
    *
    * @param $model CakePHP Model instance.
    * @param $key Database key
    * @param $incValue integer with the number of units to increment.
    *
    * @return Integer reply: the value of key after the increment
    *
    * @see http://redis.io/commands/decr
    *
    **/
    public function increment($key, $incValue = null)
    {
        if ($incValue != null)
            return $this->getDataSource()->incrementBy($this, $key, $incValue);
        else
            return $this->getDataSource()->increment($this, $key);
    }


   /**
    * Returns all keys matching pattern.
    *
    * @param $model CakePHP Model instance.
    * @param $keysPattern Pattern to look for.
    *
    * @return list of keys matching pattern.
    *
    * @see http://redis.io/commands/keys
    *
    **/
    public function keys($keysPattern)
    {
        return $this->getDataSource()->keys($this, $keysPattern);
    }


   /**
    *
    * Set key to hold the string value. If key already holds a value,
    * it is overwritten, regardless of its type.
    * 
    * @param $key Database key
    * @param $value new value to set to key.
    *
    * @return Status code reply: always OK since SET can't fail.
    *
    * @see http://redis.io/commands/set
    *
    **/
    public function setKeyValue($key, $value)
    {
        return $this->getDataSource()->set($this, $key,$value);
    }
    
}
?>