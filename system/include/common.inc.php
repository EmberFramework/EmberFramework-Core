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
			session_start();
			define('SESSION_TYPE', 'web');
			break;
		case 'cli':
//TODO: make this generic
			ini_set('include_path', '.:/var/www/ember/system/include:/var/www/ember/system:/var/www/ember/system/lib:/usr/share/php:/usr/share/pear');
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
//TODO: use Debug here
			echo '<pre>';
			print_r($exception);
			echo '</pre>';
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
		elseif (is_file(CODE_BASE_ROOT.'system/class/'.str_replace("_", "/", $class).".class.php"))
			require(CODE_BASE_ROOT.'system/class/'.str_replace("_", "/", $class).".class.php");
		else
			return FALSE;
	}

//TODO: should be using this, play nice with smarty
	//spl_autoload_register('emberAutoload', true, true);


	//INIT Session
	//INIT Site
	//INIT Smarty
	//INIT Permissions
	//INIT User