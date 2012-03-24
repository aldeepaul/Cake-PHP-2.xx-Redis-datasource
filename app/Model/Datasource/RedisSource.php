<?php

App::uses('HttpSocket', 'Network/Http');

if(!defined('CRLF'))
    define('CRLF', "\r\n");

/**
 * CakePHP 2.0 Redis Datasource.
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
 * @note This DataSource has been tested with Redis 2.2.11
 * 
 * Credits : 
 *  Some methods where taken from https://github.com/jdp/redisent
 *  by Justin Poliey <jdp34@njit.edu>
 * 
 **/
class RedisSource extends DataSource 
{
    protected $redisHost = null;
    protected $redisPort = null;
    protected $socket    = null;

    protected $_schema = array(
                'key' => array(
                	'type' => 'text'
            ),
            	'value' => array(
                    'type' => 'text'
            )
        );


    public function __construct($config) 
    {
        parent::__construct($config);
        $this->cacheSources = false;

        $this->redisHost = $config['host'];
        $this->redisPort = $config['port'];

        $this->socket = @fsockopen($this->redisHost, $this->redisPort, $errno, $errstr);

        if (!$this->socket)
            throw new RedisException("",RedisException::$CONNECTION_ERROR);

        if(isset($config['database_number']))
            $this->executeRedisCommand( $this->buildRedisCommand("select",array(intval($config['database_number']))));

        $this->connected = true;
    }


   /**
    * Required by  Cake.Model.Datasource destructor method.
    * 
    * @see Cake.Model.Datasource
    **/
    protected function close()
    {
        fclose($this->socket);
        $this->connected = false;        
    }

    
   /**
    * Required by  Cake.Model.Datasource
    *
    * @see Cake.Model.Datasource
    **/    
    public function listSources() 
    {
        return array('redis');
    }

    
    public function describe($model)
    {
        return $this->_schema['redis'];
    }
    

    /////////////////////////////////////////////////////////////////////////////
    // Standard cakePHP CRUD methods (they may be used as in a SQL DB,         //
    // just make sure  that a param called 'key' is present).                  //
    /////////////////////////////////////////////////////////////////////////////


   /**
    *
    * Required by Cake.Model.Datasource.
    * This method will be used when performing any create operation in Model.
    *
    * @param $model An instance of Model.
    * @param $queryData Query params from a Model class.
    *
    * @throws RedisException if there is an issue with Redis protocol or server connection.
    *
    * @see Cake.Model.Datasource
    *
    **/
    public function create(Model $model, $fields = null, $values = null)
    {
        return $this->update($model,$fields,$values);
    }


   /**
    *
    * Required by Cake.Model.Datasource.
    * This method will be used when performing any find operation in Model.
    *
    * @param $model An instance of Model.
    * @param $queryData Query params from a Model class.
    *
    * @throws RedisException if there is an issue with Redis protocol or server connection.
    *
    * @see Cake.Model.Datasource
    *
    **/
    public function read(Model $model, $queryData = array())
    {        
        return $this->executeRedisCommand( $this->buildRedisCommand("get",array($this->getKeyInQueryParams($model,$queryData))) );
    }    


    /**
     *
     * Required by Cake.Model.Datasource.
     * This method will be used when performing any update/save operation in Model.
     *
     * @param $model An instance of Model.
     * @param $queryData Query params from a Model class.
     * @return As Redis will always return "OK "since SET can't fail, we always return true.
     *
     * @throws RedisException if there is an issue with Redis protocol or server connection.
     *
     * @see Cake.Model.Datasource
     *
     **/
    public function update(Model $model, $fields = array(), $values = array())
    {
        $data = array_combine($fields, $values);
    
        $key = $this->getKeyInQueryParams($model, $data);
    
        if (!isset($data['value']))
            throw new RedisException("",RedisException::$MISSING_VALUE);

        $this->executeRedisCommand( $this->buildRedisCommand("set",array($key,$data['value'])) );

        return true;
    }


   /**
    *
    * Required by Cake.Model.Datasource.
    * This method will be used when performing any delete operation in Model.
    * 
    * NOTE : Model should call directly this method avoiding al CakePHP.Model delete logic
    * as I was unable to make it work,  there are some checks, like counts, finds etc, so
    * to keep it simple, just override delete function from your model like this :
    * <pre>
    *     public function delete($key,$cascade = false)
    *	  {
	*        return $this->getDataSource()->delete($this,$key);
    *	  } 
    * </pre>
    *
    * @param $model An instance of Model.
    * @param $queryData Query params from a Model class.
    *
    * @return Integer reply: The number of keys that were removed.
    * 
    * @throws RedisException if there is an issue with Redis protocol or server connection.
    *
    * @see Cake.Model.Datasource
    *
    **/
    public function delete($model, $key = "")
    {
        if (empty($key))
            throw new RedisException("",RedisException::$MISSING_KEY);

        return $this->executeRedisCommand( $this->buildRedisCommand("del",array($key)) );
    }

    
    /////////////////////
    // Redis Commands  //
    /////////////////////
    

   /**
	*  Appends a value to a given key's current value.
	*    -> redis['my_key'] => 'Hello ' after an append operation we will have :
	*      -> append('my_key','World')
	*    -> redis['my_key'] => 'Hello World'
	*
	*   @param $model CakePHP Model instance.
	*   @param $key Database key
	*   @param $valueToAppend string Value that will be append to given key value.
	*   
	*   @return Integer reply: the length of the string after the append operation.
	*   
	*   @see http://redis.io/commands/append
	**/    
    public function append($model, $key = "", $valueToAppend = "")
    {
        if (empty($key))
            throw new RedisException("",RedisException::$MISSING_KEY);

        return $this->executeRedisCommand( $this->buildRedisCommand("append",array($key,$valueToAppend)) );
    }


   /**
    *  Request for authentication in a password protected Redis server. 
    *
    *   @param $model CakePHP Model instance.
    *   @param $password Database password
    *
    *   @return Redis Status code reply
    *
    *   @see http://redis.io/commands/auth
    **/
    public function authenticate($model, $password)
    {
        // TODO Test this method.
        return $this->executeRedisCommand( $this->buildRedisCommand("auth",array($password)) );
    }

    
   /**
    * Asynchronously rewrite the append-only file.
    *
    * @param $model CakePHP Model instance.
    *
    * @return Always will return OK Status reply.
    *
    * @see http://redis.io/commands/bgrewriteaof
    **/
    public function rewriteAppendOnlyFile($model)
    {
        // TODO Test this method.
        return $this->executeRedisCommand( $this->buildRedisCommand("bgrewritraof",array()));
    }


   /**
    * Saves the DB on disk in background.
    *
    * @param $model CakePHP Model instance.
    *
    * @return Always will return OK Status reply, as is an asynchronous process.
    *         Use lastSaveTimeStamp in order to get when was the last time DB 
    *         was stored in disk.
    *
    * @see http://redis.io/commands/bgsave
    **/
    public function bgsave($model)
    {
        return $this->executeRedisCommand( $this->buildRedisCommand("bgsave",array()));
    }    


   /**
    * Return the number of keys in the currently selected database.
    *
    * @param $model CakePHP Model instance.
    *
    * @return integer value with the number of elements stored in current selected DB.
    *
    * @see http://redis.io/commands/dbsize
    **/
    public function dbSize($model)
    {
        return $this->executeRedisCommand( $this->buildRedisCommand("dbsize",array()));
    }


   /**
    * Decrements the number stored at key by one. 
    * 
    * @param $model CakePHP Model instance.
	* @param $key Database key
	* 
    * @return Integer reply: the value of key after the decrement
    * 
    * @see http://redis.io/commands/decr
    * 
    **/
    public function decrement($model, $key)
    {
        if (empty($key))
            throw new RedisException("",RedisException::$MISSING_KEY);
                
        return $this->executeRedisCommand( $this->buildRedisCommand("decr",array($key)));
    }
    
    
   /**
    * Decrements the number stored at key by $value units.
    *
    * @param $model CakePHP Model instance.
    * @param $key Database key
    * @param $value number of units to decrement to key's value.
    *
    * @return Integer reply: the value of key after the decrement
    *
    * @see http://redis.io/commands/decr
    *
    **/
    public function decrementBy($model, $key, $value)
    {
        if (empty($key))
            throw new RedisException("",RedisException::$MISSING_KEY);
    
        if (empty($value))
            throw new RedisException("",RedisException::$MISSING_VALUE);        

        return $this->executeRedisCommand( $this->buildRedisCommand("decrby",array($key, $value)));        
    }    
    
    
   /**
    * Expires a db entry with the given $key in $expireInSeconds seconds.
    * Note : Expiring in Redis means delete.
    *
    * @param $model CakePHP Model instance.
    * @param $key Database key
    * @param $expireInSeconds expiration timeout in seconds.
    *
    * @return Integer reply: 1 if the timeout was set.  0 if key does not exist or
    *         the timeout could not be set.
    *
    * @see http://redis.io/commands/expire
    *
    **/    
    public function expire($model, $key, $expireInSeconds)
    {
        if (empty($key))
            throw new RedisException("",RedisException::$MISSING_KEY);
        
        if (empty($expireInSeconds))
            throw new RedisException("You did not specify an expiration timeout",RedisException::$MISSING_VALUE);

        return $this->executeRedisCommand( $this->buildRedisCommand("expire",array($key, $expireInSeconds)));
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
    public function expireAt($model, $key, $unixTimeStamp)
    {
        if (empty($key))
            throw new RedisException("",RedisException::$MISSING_KEY);
    
        if (empty($unixTimeStamp))
            throw new RedisException("You did not specify an UNIX timestamp for expiration",RedisException::$MISSING_VALUE);
    
        return $this->executeRedisCommand( $this->buildRedisCommand("expireat",array($key, $unixTimeStamp)));
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
    public function getRange($model, $key, $start, $end)
    {
        if (empty($key))
            throw new RedisException("",RedisException::$MISSING_KEY);        

        return $this->executeRedisCommand( $this->buildRedisCommand("getrange",array($key, intval($start), intval($end))));
    }
    
    
   /**
    * Atomically sets key to value and returns the old value stored at key. 
    * Returns an error when key exists but does not hold a string value.
    *  
    * @param $model CakePHP Model instance.
    * @param $key Database key
    * @param $value new value to set to key.
    *
    * @return old value stored at $key
    * 
    * @see http://redis.io/commands/getset
    * 
    **/
    public function getAndSet($model, $key, $value)
    {
        if (empty($key))
            throw new RedisException("",RedisException::$MISSING_KEY);

        if (empty($value))
            throw new RedisException("You did not specify a new value for your key.",RedisException::$MISSING_VALUE);

        return $this->executeRedisCommand( $this->buildRedisCommand("getset",array($key, $value)));
    }    


   /**
    * Increments the number stored at key by one. If the key does not exist, 
    * it is set to 0 before performing the operation. An error is returned if 
    * the key contains a value of the wrong type or contains a string that is 
    * not representable as integer. 
    * This operation is limited to 64 bit signed integers.
    * 
    * @param $model CakePHP Model instance.
	* @param $key Database key
	* 
    * @return Integer reply: the value of key after the increment
    * 
    * @see http://redis.io/commands/incr
    * 
    **/
    public function increment($model, $key)
    {
        if (empty($key))
            throw new RedisException("",RedisException::$MISSING_KEY);

        return $this->executeRedisCommand( $this->buildRedisCommand("incr",array($key)));
    }
    
    
   /**
    * Increments the number stored at key by increment. 
    * If the key does not exist, it is set to 0 before performing the operation.
    * An error is returned if the key contains a value of the wrong type or 
    * contains a string that is not representable as integer. 
    * This operation is limited to 64 bit signed integers.
    *
    * @param $model CakePHP Model instance.
    * @param $key Database key
    * @param $value number of units to increment to key's value.
    *
    * @return Integer reply: the value of key after the increment
    *
    * @see http://redis.io/commands/incrby
    *
    **/
    public function incrementBy($model, $key, $value)
    {
        if (empty($key))
            throw new RedisException("",RedisException::$MISSING_KEY);

        if (empty($value))
            throw new RedisException("",RedisException::$MISSING_VALUE);        

        return $this->executeRedisCommand( $this->buildRedisCommand("incrby",array($key, $value)));
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
    public function keys($model, $keysPattern)
    {
        if (empty($keysPattern))
            throw new RedisException("You did not specify a pattern to look for in redis keys.",RedisException::$MISSING_VALUE);

        return $this->executeRedisCommand( $this->buildRedisCommand("keys",array($keysPattern)));
    }


   /**
    * Gets a unix timestamp from the last time DB was stored in disk.
    *
    * @param $model CakePHP Model instance.
    *
    * @return Unix timestamp.
    *
    * @see http://redis.io/commands/lastsave
    **/
    public function lastSaveTimeStamp($model)
    {
        return $this->executeRedisCommand( $this->buildRedisCommand("lastsave",array()));
    }
    
    
   /**
    * 
    * Set key to hold the string value. If key already holds a value, 
    * it is overwritten, regardless of its type.
    * 
    * @return Status code reply: always OK since SET can't fail.
    * 
    * @see http://redis.io/commands/set
    * 
    **/
    public function set($model,$key, $value)
    {
        if (empty($key))
            throw new RedisException("",RedisException::$MISSING_KEY);
        
        if (empty($value))
            throw new RedisException("",RedisException::$MISSING_VALUE);
        
        return $this->executeRedisCommand( $this->buildRedisCommand("set",array($key, $value)));
    }
        
    
   /**
    * Will parse parameters and generate a Redis command, ready to be sent 
    * through our socket. 
    * 
    * @param $commandName Redis command name.
    * @param $commandArgs Command's params.
    * 
    * @author Justin Poliey <jdp34@njit.edu>
    * @see This method is taken from https://github.com/jdp/redisent
    * 
    * Copyright (c) 2009 Justin Poliey <jdp34@njit.edu>
    * 
    **/
    protected function buildRedisCommand($commandName, $commandArgs)
    {
        array_unshift($commandArgs, strtoupper($commandName));
        return sprintf('*%d%s%s%s', count($commandArgs), CRLF, implode(array_map(function($arg) {
            return sprintf('$%d%s%s', strlen($arg), CRLF, $arg);
                    }, $commandArgs), CRLF), CRLF);
    }
    
    
   /**
    * 
    * This method will send Redis commannds using $this->socket.
    * Also will read and parse server response.
    *  
    * @param $command the
    * 
    * @return sting with server response to $command
    *
    * @throws RedisException if there is a problem with Redis protocol or server connection.
    *
    * This method was taken from https://github.com/jdp/redisent
    * @author Justin Poliey <jdp34@njit.edu>
    * @see https://github.com/jdp/redisent/blob/master/redisent.php
    * 
    * Copyright (c) 2009 Justin Poliey <jdp34@njit.edu>
    * 
    **/
    protected function executeRedisCommand($command)
    {
        for ($written = 0; $written < strlen($command); $written += $fwrite)
        {
            $fwrite = fwrite($this->socket, substr($command, $written));

            if ($fwrite === FALSE)
                throw new RedisException("",RedisException::$CONNECTION_ERROR_WHILE_SENDING_DATA);
        }

        
        /* Parse the response based on the reply identifier */
        $reply = trim(fgets($this->socket, 512));
        
        switch (substr($reply, 0, 1))
        {
            /* Error reply */
            case '-':
                throw new RedisException(substr(trim($reply), 4), RedisException::$SERVER_ERROR_REPLY);
                break;
                /* Inline reply */
            case '+':
                $response = substr(trim($reply), 1);
                break;
                /* Bulk reply */
            case '$':
                $response = null;
                if ($reply == '$-1')
                    break;

                $read = 0;
                $size = substr($reply, 1);
                if ($size > 0)
                {
                    do {
                        $block_size = ($size - $read) > 1024 ? 1024 : ($size - $read);
                        $response .= fread($this->socket, $block_size);
                        $read += $block_size;
                    } while ($read < $size);
                }
                
                fread($this->socket, 2); /* discard crlf */
                break;
                /* Multi-bulk reply */
            case '*':
                $count = substr($reply, 1);
                if ($count == '-1')
                    return null;

                $response = array();
                
                for ($i = 0; $i < $count; $i++) 
                {
                    $bulk_head = trim(fgets($this->socket, 512));
                    $size = substr($bulk_head, 1);
                    if ($size == '-1') 
                        $response[] = null;
                    else 
                    {
                        $read = 0;
                        $block = "";
                        
                        do {
                            $block_size = ($size - $read) > 1024 ? 1024 : ($size - $read);
                            $block .= fread($this->socket, $block_size);
                            $read += $block_size;
                        } while ($read < $size);
                        
                        fread($this->socket, 2); /* discard crlf */
                        $response[] = $block;
                    }
                }
                break;
                /* Integer reply */
            case ':':
                $response = intval(substr(trim($reply), 1));
                break;
            default:
                throw new RedisException($reply,RedisException::$INVALID_SERVER_RESPONSE);
            break;
        }
        return $response;
    }


   /**
    *
    * This helper method will try to find key value inside our queryData, as key may
    * be added in a array in diferents ways we want to look for :
    *   $queryData['conditions']['key']
    *   $queryData['conditions']['ModelName.key']
    *   $queryData['key']
    *
    * @param $model
    * @param $queryData
    * 
    * @return string with the value of the key.
    * 
    * @throws RedisException if key not found.
    *  
    **/
    protected function getKeyInQueryParams(&$model,$queryData)
    {
        if (isset($queryData['key']))
            return $queryData['key'];
                
        if (isset($queryData['conditions'][$model->name.'.key']))
            return $queryData['conditions'][$model->name.'.key'];

        if (isset($queryData['conditions']['key']))
            return $queryData['conditions']['key'];

        throw new RedisException("",RedisException::$MISSING_KEY);
    }
}


class RedisException extends Exception
{
    public static $MISSING_KEY   = 1;
    public static $MISSING_VALUE   = 2;
    public static $CONNECTION_ERROR = 3;
    public static $SERVER_ERROR_REPLY = 4;
    public static $INVALID_SERVER_RESPONSE = 5;   
    public static $CONNECTION_ERROR_WHILE_SENDING_DATA = 6;
    
    
    function __construct($message, $code, $previous = null)
    {
        parent::__construct($message, $code, $previous);
    
        switch ($code)
        {
            case RedisException::$CONNECTION_ERROR:
                $this->message = "Unable to connect to Redis server: {$errno} - {$errstr}";
            break;

            case RedisException::$CONNECTION_ERROR_WHILE_SENDING_DATA:
                $this->message = "Connection to Redis server failed while sending data: {$errno} - {$errstr}";
            break;

            case RedisException::$MISSING_KEY:
                $this->message = "Redis error: Missing key.";
            break;

            case RedisException::$MISSING_VALUE:
                $this->message = "Redis error: Missing value for given key.";
            break;

            case RedisException::$INVALID_SERVER_RESPONSE:
                $this->message = "Invalid server response: {$message}.";
            break;

            case RedisException::$SERVER_ERROR_REPLY:
                $this->message = "Redis server send an error: {$message}.";
            break;
        }
    }
}

?>