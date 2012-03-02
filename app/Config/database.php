<?php
/**
 * This is core configuration file.
 *
 * Use it to configure core behaviour of Cake.
 *
 * PHP 5
 *
 * CakePHP(tm) : Rapid Development Framework (http://cakephp.org)
 * Copyright 2005-2011, Cake Software Foundation, Inc. (http://cakefoundation.org)
 *
 * Licensed under The MIT License
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright     Copyright 2005-2011, Cake Software Foundation, Inc. (http://cakefoundation.org)
 * @link          http://cakephp.org CakePHP(tm) Project
 * @package       app.Config
 * @since         CakePHP(tm) v 0.2.9
 * @license       MIT License (http://www.opensource.org/licenses/mit-license.php)
 */

class DATABASE_CONFIG {
 
	public $redisDB_1 = array(
		'datasource' => 'RedisSource',
		'host' => 'localhost',
		'port'=>'6379',
		'database_number' => 0 /* Redis database number */
	);

	public $redisDB_2 = array(
		'datasource' => 'RedisSource',
		'host' => 'localhost',
		'port'=>'6379',
		'database_number' => 1
	);

}


