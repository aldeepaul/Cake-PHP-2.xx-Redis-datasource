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
            $this->executeCommand( $this->buildCommand("select",array(intval($config['database_number']))));

        $this->connected = true;
    }


    public function __destruct()
    {
       $this->closeConnection();
       parent::__destruct();
    }
  

    public function closeConnection()
    {
        fclose($this->socket);
        $this->connected = false;        
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
    public function buildCommand($commandName, $commandArgs)
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
    public function executeCommand($command)
    {
        for ($written = 0; $written < strlen($command); $written += $fwrite)
        {
            $fwrite = fwrite($this->socket, substr($command, $written));

            if ($fwrite === FALSE || $fwrite <= 0)
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


    // Methods needed to avoid CakePHP ORM and SQL dependency.

    public function query() 
    {
        return true;
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