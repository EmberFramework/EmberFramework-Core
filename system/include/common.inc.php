<?php

	$PAGELOAD_starttime = microtime( true );
	global $PAGELOAD_starttime;

	function getPageLoadTime()
	{
		global $PAGELOAD_starttime;
		$end = microtime( true );
		$totaltime = $end - $PAGELOAD_starttime;
		$totaltime = round( $totaltime, 5 );
		return $totaltime;
	}

	switch(php_sapi_name())
	{
		case 'apache2handler':
			 $garbage_timeout = 60 * 60 * 4; // in seconds (4 hours)

                        ini_set('session.gc_maxlifetime', $garbage_timeout);

                        $sessdir = DIRECTORY_SEPARATOR.'tmp'.DIRECTORY_SEPARATOR.'ember';
                        if (!is_dir($sessdir))
                                mkdir($sessdir, 0777);

                        session_save_path($sessdir);

			session_start();
			define('SESSION_TYPE', 'web');
			break;
		case 'cli':
//TODO: make this generic
			$inital_path = ini_get('include_path');
			ini_set('include_path', '/var/www/ember/system/include:/var/www/ember/system/lib/php:'.$inital_path);
			define('SESSION_TYPE', 'cli');
			$_SERVER = array();
//TODO: make this generic
			$_SERVER['DOCUMENT_ROOT'] = '/var/www/ember/webroot';
			$_SERVER['HTTP_USER_AGENT'] = 'cli';
			$_SESSION = array();
			$_POST = array();
			$_GET = array();
			$_REQUEST = array();
			$_COOKIE = array();
			$_FILES = array();
			$_ENV = array();
			break;
		default:
			throw new Exception("Unknown php API");
			break;
	}

	require_once('setting.inc.php');

	function ember_exception_handler($exception, $die = TRUE)
	{
		$time = time();
		if(SESSION_TYPE == 'web')
			echo "<html><strong style='font-family:sans-serif'>An error has occurred with your request ({$time})</strong></html>";
		elseif(SESSION_TYPE == 'cli')
			echo "An error has occurred with your request ($time)\n";

		try {
			$error_log = fopen(EXCEPTION_LOG_FILE, 'a');
			fwrite($error_log, "\n*********************************\n");
			fwrite($error_log, "\nERROR START: {$time}\n");
			fwrite($error_log, "\nException Message:\n");
			fwrite($error_log, print_r($exception, true));
			fwrite($error_log, "\n_SERVER:\n");
			fwrite($error_log, print_r($_SERVER, true));
                        fwrite($error_log, "\nERROR END\n\n\n");
                        fclose($error_log);

			if(class_exists('Debug', FALSE))
				Debug::print_r($exception, 'Error');
		} catch( Exception $e )
		{}

		if($die)
			die();
        }

	set_exception_handler('ember_exception_handler');

	function __autoload($class)
	{
		if(substr($class, 0, 7) == 'Smarty_' )
		{
			$_class = strtolower($class);
			if (substr($_class, 0, 16) === 'smarty_internal_' || $_class == 'smarty_security')
			{
				if( is_file(SMARTY_SYSPLUGINS_OVERLOAD_DIR . $_class . '.php'))
					require(SMARTY_SYSPLUGINS_OVERLOAD_DIR . $_class . '.php');
				else
					require(SMARTY_SYSPLUGINS_DIR . $_class . '.php');
			}
		}
		//Search the EMBER Class directory, replacing _ with directories
		elseif(is_file(CODE_BASE_ROOT.'system/class/'.str_replace('_', '/', $class).'.class.php'))
			require(CODE_BASE_ROOT.'system/class/'.str_replace('_', '/', $class).'.class.php');
		//Search the Modules class directories, also replacing _ with directories, except for the plugin name
		else
		{
			$class_parts = explode('_', $class, 2);

			$file = EMBER_PLUGIN_DIR.$class_parts[0].DS.'class'.DS.$class_parts[0].'_'.str_replace("_", "/", $class_parts[1]).'.class.php';
			if(is_file($file))
				require $file;
			else
				return FALSE;
		}
	}

	//Used by FileDispatcher
	/**
	 * Merges any number of arrays / parameters recursively, replacing 
	 * entries with string keys with values from latter arrays. 
	 * If the entry or the next value to be assigned is an array, then it 
	 * automagically treats both arguments as an array.
	 * Numeric entries are appended, not replaced, but only if they are 
	 * unique
	 *
	 * calling: result = array_merge_recursive_distinct(a1, a2, ... aN)
	 *
	 * From:
	 * http://php.net/manual/en/function.array-merge-recursive.php
	 */

	function array_merge_recursive_distinct ()
	{
		$arrays = func_get_args();
		$base = array_shift($arrays);
		if(!is_array($base)) $base = empty($base) ? array() : array($base);
		foreach($arrays as $append)
		{
			if(!is_array($append)) $append = array($append);
			foreach($append as $key => $value)
			{
				if(!array_key_exists($key, $base) and !is_numeric($key))
				{
					$base[$key] = $append[$key];
					continue;
				}
				if(is_array($value) or is_array($base[$key]))
				{
					$base[$key] = array_merge_recursive_distinct($base[$key], $append[$key]);
				}
				else if(is_numeric($key))
				{
					if(!in_array($value, $base)) $base[] = $value;
				}
				else
				{
					$base[$key] = $value;
				}
			}
		}
		return $base;
	}

//TODO: should be using this, play nice with smarty
	//spl_autoload_register('emberAutoload', true, true);


	Session::init();

	Debug::init();
	
	//INIT DB
	//INIT Cache

	Site::init();

	if(SESSION_TYPE == 'web')
	{
		global $smarty;
		$smarty = SmartyPlus::init();

		Site::initSmarty();
	}

	//INIT Log
	//INIT Permissions
	//INIT User
