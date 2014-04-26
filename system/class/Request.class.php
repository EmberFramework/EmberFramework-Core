<?php
	class Request
	{
		const ERR_INTERNAL				= 'Internal error';

		static $_REQUEST, $file_extension;
		static $valid_csrf;

		/**
		 * Private function used by all the get functions
		 * @param string $var_list comma seperated list of values to get from request
		 * @param boolean $default default value to return if variable is not found
		 * @param String|Array $func Function to be called to cast incoming data
		 * @return Mixed
		 */
		private static function parseList($var_list, $default, $func)
		{
			$var_list = explode(',', $var_list);

			$values = array();
			if (func_num_args() <= 3)
				$argv = array();
			else
			{
				$argv = func_get_args();
				array_splice($argv, 0, 3);
			}

			foreach ($var_list as $key => $var_name)
			{
				list($src_name, $dest_name) = explode(':', $var_name.':');
				if (!$dest_name) $dest_name = $src_name;

				$var_list[$key] = $dest_name;

				if (!isset(self::$_REQUEST[$src_name]))
				{
					$values[$dest_name] = $default;
				}
				else if (!is_array(self::$_REQUEST[$src_name]))
				{
					if(sizeof($argv))
						$val = call_user_func($func, self::$_REQUEST[$src_name], $argv);
					else
						$val = call_user_func($func, self::$_REQUEST[$src_name]);

					$values[$dest_name] = $val;
				}
				else
				{
					$values[$dest_name] = self::parseArray(self::$_REQUEST[$src_name], $default, $func, $argv);
				}
			}

			if( count($var_list) == 1 )
				return $values[$var_list[0]];
			else
				return $values;
		}

		/**
		 * Private function used by parseList
		 * @param <type> $var_list
		 * @param <type> $default
		 * @param <type> $func
		 * @param <type> $argv
		 * @return <type>
		 */
		private static function parseArray($var_list, $default, $func, $argv)
		{
			$values = array();
			foreach ($var_list as $key=>$val)
			{
				$values[$key] = !is_array($val) ? call_user_func($func, $val, $argv) : self::parseArray($val, $default, $func, $argv);
			}
	
			return $values;
		}

		/**
		 * Private function used to cast a value to a date
		 * @param <type> $date
		 * @param <type> $argv
		 * @return <type>
		 */
		private static function castDate($date, $argv)
		{
			$ts = strtotime($date);
			return $ts !== FALSE ? date($argv[0], $ts) : NULL;
		}

		/**
		 * private function used to cast a value as an enum
		 * @param <type> $value
		 * @param <type> $argv
		 * @return <type>
		 */
		private static function castEnum($value, $argv)
		{
			return in_array($value, $argv[0]) ? $value : $argv[1];
		}

		/**
		 * Private function used to cast a value as a boolean
		 * @param <type> $value
		 * @return <type>
		 */
		private static function castBool($value)
		{
			return ($value == 'false' || !$value) ? FALSE : TRUE;
		}

// public:
		/**
		 * Called to setup the Request class
		 */
		public static function initialize()
		{

			self::$_REQUEST = $_REQUEST;
			Debug::log(self::$_REQUEST, Debug::LOG_REQUEST);
		}

		public static function initPathInfo( $vars = FALSE )
		{
			$path_info = FileDispatcher::getPathInfo();
			if($path_info === FALSE)
				$path_info = $_SERVER['PATH_INFO'];

			$pi_parts = explode('/', $path_info);
			if(array_shift($pi_parts) != '') throw new Exception( "URI error" );

			if($vars != FALSE)
			{
				$names = explode('/', $vars);
				array_shift($names);
				foreach($names as $key=>$n)
					if(isset($pi_parts[$key]))
						$url_vars[$n] = $pi_parts[$key];
				$pi_parts = $url_vars;
			}

			self::$_REQUEST = array_merge(self::$_REQUEST, $pi_parts);
		}

		/**
		 * Used to get a Date string from a request or get submission
		 *
		 * If you are requesting a path info variable use the syntax:
		 *
		 *	0:username
		 *
		 * you are requesting the first path info variable and naming it username
		 *
		 * @param string $var_list comma seperated list of values to get from request
		 * @param string $format date format string
		 * @param boolean $default default value to return if variable is not found
		 * @return mixed string if one item is requested, an array if multiple are requested
		 */
		public static function getDateString($var_list, $format='m/d/Y', $default = NULL)
		{
			return self::parseList($var_list, $default, array('Request', 'castDate'), $format);
		}

		/**
		 * Used to get a string from the request object validating it with a list of
		 * possible options for the enum
		 *
		 * If you are requesting a path info variable use the syntax:
		 *
		 *	0:username
		 *
		 * you are requesting the first path info variable and naming it username
		 * @param string $var_list comma seperated list of values to get from request
		 * @param array $options posible values for the enum
		 * @param boolean $default default value to return if variable is not found
		 * @return mixed string if one item is requested, an array if multiple are requested
		 */
		public static function getEnum($var_list, $options, $default = NULL)
		{
			return self::parseList($var_list, $default, array('Request', 'castEnum'), $options, $default);
		}

		/**
		 * Used to get a float from the request object validating it as a float
		 *
		 * If you are requesting a path info variable use the syntax:
		 *
		 *	0:username
		 *
		 * you are requesting the first path info variable and naming it username
		 *
		 * @param string $var_list comma seperated list of values to get from request
		 * @param boolean $default default value to return if variable is not found
		 * @return mixed string if one item is requested, an array if multiple are requested
		 */
		public static function getFloat($var_list, $default = 0.0)
		{
			return self::parseList($var_list, $default, 'floatval');
		}

		/**
		 * Used to get a integer from the request object validating it as a integer
		 *
		 * If you are requesting a path info variable use the syntax:
		 *
		 *	0:username
		 *
		 * you are requesting the first path info variable and naming it username
		 *
		 * @param string $var_list comma seperated list of values to get from request
		 * @param boolean $default default value to return if variable is not found
		 * @return mixed string if one item is requested, an array if multiple are requested
		 */
		public static function getInteger($var_list, $default = 0)
		{
			return self::parseList($var_list, $default, 'intval');
		}

		/**
		 * Used to get a boolean from the request object validating it as a boolean value
		 *
		 * If you are requesting a path info variable use the syntax:
		 *
		 *	0:username
		 *
		 * you are requesting the first path info variable and naming it username
		 *
		 * @param string $var_list comma seperated list of values to get from request
		 * @param boolean $default default value to return if variable is not found
		 * @return mixed string if one item is requested, an array if multiple are requested
		 */
		public static function getBoolean($var_list, $default = FALSE)
		{
			return self::parseList($var_list, $default, array('Request', 'castBool'));
		}

		/**
		 * Used to get a string from the request object
		 *
		 * If you are requesting a path info variable use the syntax:
		 *
		 *	0:username
		 *
		 * you are requesting the first path info variable and naming it username
		 *
		 * @param string $var_list comma seperated list of values to get from request
		 * @param boolean $default default value to return if variable is not found
		 * @return mixed string if one item is requested, an array if multiple are requested
		 */
		public static function getString($var_list, $default = '')
		{
			return self::parseList($var_list, $default, 'strval');
		}

		/**
		 * returns the name of the domain (www.domain.com)
		 * @return string
		 */
		public static function getDomain()
		{
			return $_SERVER['HTTP_HOST'];
		}

		/**
		 * returns the path requested by the client ($_SERVER['uri'])
		 * @return string
		 */
		public static function getURI()
		{
			return $_SERVER['REQUEST_URI'];
		}

		/**
		 * Returns the relative URL of the current page
		 * @return String
		 */
		public static function getRelativeURL()
		{
			$uri = Request::getURI();
			if( strpos($uri, '?') )
			{
				$uri_bits = explode('?',$uri);
				$uri = $uri_bits[0];
			}
			if( !$uri )
				$uri = '/';

			return $uri;
		}

		/**
		 * Returns the absolute url of the current page (including http protocol)
		 * @return String
		 */
		public static function getAbsoluteURL()
		{
			$relative_url = Request::getRelativeURL();
			$protocol = self::getProtocol();
			$host = $_SERVER['HTTP_HOST'];
			if( !$protocol || !$host || !$relative_url )
				throw new Exception( Request::ERR_INTERNAL );
			return $protocol . '://' . $host . $relative_url;
		}

		/**
		 * Returns the http protocol for the current page
		 * @return String
		 */
		public static function getProtocol()
		{
			$protocol = array_key_exists( 'HTTPS', $_SERVER ) ? 'https' : 'http';
			return $protocol;
		}

		/**
		 * Returns the document root
		 * @return string
		 */
		public static function getDocumentRoot()
		{
			return DOCUMENT_ROOT;
		}

		/**
		 *
		 * @return String Fieldname of CSRF key
		 */
		public static function get_csrf_key_field()
		{
			return CSRF_FIELD;
		}

		/**
		 *
		 * @return String CSRF key
		 */
		public static function get_csrf_key()
		{
			return $_SESSION[CSRF_FIELD];
		}

		/*
		 * check_csrf_key()
		 * $key: optional paramater to specify a key to be checked
		 *		instead of the standard key in the request values
		 *
		 * Checks and cache's the results of the default key, checks the
		 *		otional key live, take no action other than returning true or false
		 *
		 * @return: true if key is valid, false otherwise
		 */
		private static function check_csrf_key( $key = '' )
		{
			if(!isset($_SESSION[CSRF_FIELD]) || $_SESSION[CSRF_FIELD] == '' )
			{
				self::$valid_csrf = false;
				return false;
			}

			if(isset($key) && $key != '') return ($key == $_SESSION[CSRF_FIELD]);

			if(isset(self::$valid_csrf)) return self::$valid_csrf;

			if (isset(self::$request_csrf_key) && self::$request_csrf_key != '') $key = self::$request_csrf_key;
			else if (isset(self::$_REQUEST[CSRF_FIELD]))
			{
				self::$request_csrf_key = self::$_REQUEST[CSRF_FIELD];
				$key = self::$request_csrf_key;
			}
			else
			{
				self::$valid_csrf = false;
				return false;
			}

			self::$valid_csrf = ($key == $_SESSION[CSRF_FIELD]);

			return self::$valid_csrf;
		}

		/*
		 * verify_csrf_key()
		 * $key: optional paramater to specify a key to be checked
		 *		instead of the standard key in the request values
		 *
		 * logs all verification failures, in authoritative mode
		 *		will redirect to the index page.
		 *
		 * @return: true if key is valid, false otherwise
		 */
		public static function verify_csrf_key()
		{
			$key = '';
			$fatal = false;

			$param_count = func_num_args();

			if($param_count == 0)
			{
				$key = '';
				$fatal = true;
			}
			else if ($param_count == 1)
			{
				$arg = func_get_arg(0);
				if(is_bool($arg))
				{
					$key = '';
					$fatal = $arg;
				}
				else if(is_string($arg))
				{
					$key = $arg;
					$fatal = true;
				}
				else if( is_null($arg) )
				{
					$key = '';
					$fatal = true;
				}
				else
					throw new Exception("Wrong argument type given");
			}
			else if ($param_count == 2)
			{
				unset($key);
				unset($fatal);

				$arg = func_get_args();

				foreach($arg as $a)
				{
					if(is_bool($a) && !isset($fatal)) $fatal = $a;
					else if(is_string($a) && !isset($key)) $key = $a;
					else throw new Exception("Wrong argument type given");
				}

				if(!isset($key) || !isset($fatal)) throw new Exception("Wrong argument type given");
			}
			else throw new Exception("Wrong number of arguments given, got {$param_count} expected 0 - 2");

			if( self::check_csrf_key($key) ) return true;
			else self::log_csrf_failure($fatal);
			//TODO: when fatal is true ths should redirect rather than return
			if($fatal) die('Fatal CSRF failure');
			else return false;
		}

		/**
		 * log_csrf_failure()
		 * logs the site information
		 * @param boolean indicates if the error was fatal or not
		 */
		private static function log_csrf_failure($fatal)
		{
			$error_message = "Start CSRF Failure\n".$_SERVER['SCRIPT_FILENAME'].PHP_EOL.
					'Fatal: '.($fatal?'TRUE':'FALSE').PHP_EOL.
					$_SERVER['REMOTE_ADDR'].PHP_EOL.
					'$_SERVER[SERVER_NAME]: '.$_SERVER['SERVER_NAME'].PHP_EOL.
					'$_SERVER[REQUEST_URI]: '.$_SERVER['REQUEST_URI'].PHP_EOL.
					'_REQUEST: '.var_export($_REQUEST, TRUE).PHP_EOL.
					'$_SESSION[CSRF_FIELD]: '.$_SESSION[CSRF_FIELD].PHP_EOL.
					'$_COOKIE[CSRF_FIELD]: '.$_COOKIE[CSRF_FIELD].PHP_EOL.
					"End CSRF Failure\n";
			try{
				$error_log = @fopen(CSRF_LOG_FILE, 'a');
				if( !@fwrite($error_log, $error_message) )
					throw new Exception( 'Failed writing file.' );
				@fclose($error_log);
			}
			catch (Exception $e)
			{
				error_log($error_message);
			}
		}

	}

// no php end tag (not required- causes problems with unwanted whitespace)