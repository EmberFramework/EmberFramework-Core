<?php

class Debug implements iSession
{
	//Session saved vars
	/**
	 * Indecates if debugging is enabled
	 * @var boolean
	 */
	private static $enabled = FALSE;
	/**
	 * Indicates the output mode used by debugging
	 * @var string
	 */
	private static $mode;
	/**
	 *
	 * @var array keyed by option contains TRUE for eneabled FALSE or not set for disabled
	 */
	private static $options;

	//Vars not saved in session
	/**
	 * Data saved by the Debugging class when the log call is made
	 * to be displayed at the end of the session
	 * @var array
	 */
	private static $data;
	/**
	 *
	 * @var boolean prevents the debug output from being printed twice
	 */
	private static $debug_printed = FALSE;

	/**
	 *
	 * @var array Variables to be stored in the session
	 */
	private static $required_vars = array(
		'enabled' => TRUE,
		'mode' => TRUE,
		'options' => TRUE,
	);

	const OBJECT_VERSION = 1;

	//Log states
	const LOG_DEFAULT = 'LOG_DEFAULT';
	const LOG_SESSION = 'LOG_SESSION';
	const LOG_SITE = 'LOG_SITE';
	const LOG_DEBUG = 'LOG_DEBUG';
	const LOG_DB = 'LOG_DB';
	const LOG_CACHE = 'LOG_CACHE';
	const LOG_USER = 'LOG_USER';
	const LOG_PERMISSION = 'LOG_PERMISSION';
	const LOG_SMARTY = 'LOG_SMARTY';
	const LOG_FILEDISPATCHER = 'LOG_FILEDISPATCHER';

	const LOG_LOADED_FILES = 'LOG_LOADED_FILES';
	const LOG_GLOBALS = 'LOG_GLOBALS';
	const LOG_PAGELOAD_TIME = 'LOG_PAGE_LOAD_TIME';
	const LOG_CODEBASE = 'LOG_CODEBASE';
	const LOG_REQUEST = 'LOG_REQUEST';
	/**
	 * Used by validate options
	 * @var array of avalable options
	 */
	private static $avalable_options = array(
		self::LOG_DEFAULT,
		self::LOG_SESSION,
		self::LOG_SITE,
		self::LOG_DEBUG,
		self::LOG_LOADED_FILES,
		self::LOG_GLOBALS,
		self::LOG_PAGELOAD_TIME,
		self::LOG_CODEBASE,
		self::LOG_DB,
		self::LOG_CACHE,
		self::LOG_USER,
		self::LOG_PERMISSION,
		self::LOG_SMARTY,
		self::LOG_FILEDISPATCHER,
		self::LOG_REQUEST,
		);

	//Display modes
	const MODE_CLI = 'MODE_CLI';
	const MODE_SIMPLE = 'MODE_SIMPLE';
	const MODE_STANDARD = 'MODE_STANDARD';
	const MODE_ADVANCED = 'MODE_ADVANCED';
	
	/**
	 * Sets up the Debug object regenerating it from the session
	 * or building it from scratch
	 * @return boolean True of the init came from the session, false if it is defaults
	 */
	public static function init()
	{
		$data = Session::getObject(get_class());

		$new = TRUE;

		//Attempt to recover Object
		if(is_array($data))
		{
			$required = self::$required_vars;

			foreach($data as $key => $value)
			{
				if(isset($required[$key]))
					unset($required[$key]);

				self::$$key = $value;
			}

			//Detect if Object recovered
			$new = array_search(TRUE, $required) === FALSE ? FALSE : TRUE;

			if(self::$enabled)
				foreach($required as $k => $r)
				{
					if($r)
						Debug::log('Debug: var not recovered: '.$k, self::LOG_SESSION);
				}
		}

		if($new)
		{
			self::$enabled = FALSE;
			switch(SESSION_TYPE)
			{
				case 'web':
					self::$mode = self::MODE_SIMPLE;
					break;
				case 'cli':
					self::$mode = self::MODE_CLI;
					break;
			}
			self::$options = array();

			Session::register(get_class());
		}

		self::$data = array();

		return !$new;

	}
	
	/**
	 * Serializes the object to be stored in the session
	 * @return array data to be stored in the session
	 */
	public static function serialize()
	{
		$result = array();
		foreach(self::$required_vars as $var => $required)
			$result[$var] = self::$$var;

		return $result;
	}

	/**
	 * Retruns the current debugging status
	 * @return boolean
	 */
	public static function isEnabled()
	{
		return self::$enabled;
	}

	/**
	 * Validates options with the list of available options
	 * @param sting $option
	 * @return boolean
	 */
	private static function validateOption($option)
	{
		if(in_array($option, self::$avalable_options) === FALSE)
			return FALSE;
		else
			return TRUE;
	}

	/**
	 * Validates the modes with the available modes
	 * @param string $mode
	 * @return boolean
	 */
	private static function validateMode($mode)
	{
		if(in_array($mode, array(self::MODE_ADVANCED, self::MODE_CLI, self::MODE_SIMPLE, self::MODE_STANDARD)) === FALSE)
			return FALSE;
		else
			return TRUE;
	}

	/**
	 * Returns the list of available options
	 * @return array
	 */
	public static function getAvalableOptions()
	{
		return self::$avalable_options;
	}

	/**
	 * Activates or disables a debugging option
	 * @param string $option option to enable or disable
	 * @param bool $enable status to set the option to
	 * @throws Unknown option
	 * @throws Invalid enable status
	 */
	public static function setOption($option, $enable = TRUE)
	{
		if(!self::$enabled) return;
		if(!self::validateOption($option))
			throw new Exception ('Unknown option '.$option);

		if(is_bool($enable))
			self::$options[$option] = $enable;
		else
			throw new Exception ('Invalid enable value, must be boolean');
	}

	/**
	 * checks if an option is enabled or not
	 * @param string $option
	 * @return bool status of the option
	 */
	public static function isOptionEnabled($option)
	{
		if(!self::$enabled) return FALSE;

		if(isset(self::$options[$option])) return self::$options[$option];
		else return FALSE;
	}

	/**
	 * Enables Debugging
	 */
	public static function enable()
	{
		self::$enabled = TRUE;
	}

	/**
	 * Disables Debugging
	 */
	public static function disable()
	{
		self::$enabled = FALSE;
	}

	/**
	 * uses print_r to print the variable to the output imidatly if debugging
	 * is enabled
	 * A label can be specified as well as a mode to print the output with
	 * @param mixed $var Variable to be printed
	 * @param string $label Label to identify the output, defaults to 'Display'
	 * @param string $mode Mode to use
	 * @throws Unknown mode
	 */
	public static function print_r($var, $label = 'Display', $mode = NULL)
	{
		if(!self::$enabled) return;

		if(!isset($mode))
			$mode = self::$mode;
		elseif(!self::validateMode($mode))
			throw new exception('Unknown mode '.$mode);


		switch($mode)
		{
			case self::MODE_CLI:
				echo PHP_EOL.'###################### Start '.$label.' ########################'.PHP_EOL;
				print_r($var);
				echo PHP_EOL.'######################## End '.$label.' ########################'.PHP_EOL;
				break;
			case self::MODE_SIMPLE:
			case self::MODE_STANDARD:
			case self::MODE_ADVANCED:
				echo PHP_EOL.'<pre><strong>'.$label.'</strong>'.PHP_EOL;
				print_r($var);
				echo PHP_EOL.'</pre>'.PHP_EOL;
				break;
		}
	}

	/**
	 * Logs a value to the Debug object to pe printed at the end of the session
	 * The Log Option can be specified as well as a label.
	 *
	 * Will only log values if debugging and the specified option is enabled
	 * @param mixed $value Value to log
	 * @param string $log_option Log Option to log under
	 * @param string $label Label to log the value under
	 */
	public static function log($value, $log_option = self::LOG_DEFAULT, $label = NULL)
	{
		if(!self::$enabled) return;
		if(!is_array(self::$data)) self::$data = array();
		if(!isset(self::$data[$log_option]) || !is_array(self::$data[$log_option])) self::$data[$log_option] = array();

		if(isset($label))
			self::$data[$log_option][$label] = $value;
		else
			self::$data[$log_option][] = $value;
	}

	/**
	 * Traverses the backtrace to find the first non Debug method call.
	 * Optionaly can be given a number of over steps to skip.
	 *
	 * returns either the object and method, or just function name.
	 * @param int $skip levels to skip after the debugging object has been detected.
	 * @param bool $skip_debug If set to FALSE it will NOT skip the debugging object and just honor the $skip param
	 * @return <type>
	 */
	public static function getCallingFunction($skip = 0, $skip_debug = TRUE)
	{
		//TODO
		return 'Obj:method';
	}

	/**
	 * Will print out the debugging information stored in the debug class when enabled
	 * as well as printing the enabled options outputs
	 * @param boolean $overide skips the douple print prevention
	 */
	public static function printDebuggingInfo($overide = FALSE)
	{
		if(!self::$enabled || (self::$debug_printed && !$overide))
			return;

		if(self::isOptionEnabled(self::LOG_PAGELOAD_TIME))
			Debug::print_r('Page Load Time: ' . getPageLoadTime(), 'Page Load Time');

		if(self::isOptionEnabled(self::LOG_GLOBALS))
			Debug::print_r($GLOBALS, 'Globals');


		Debug::print_r(self::$data, 'RAW Debug');

		if(self::isOptionEnabled(self::LOG_CODEBASE))
			Debug::print_r(array('DOCUMENT_ROOT: '.DOCUMENT_ROOT,
				'CODE_BASE: '.CODE_BASE,
				'CODE_BASE_ROOT:'.CODE_BASE_ROOT,
				));

		if(self::isOptionEnabled(self::LOG_LOADED_FILES))
			Debug::print_r(get_included_files(), 'Included Files');

		self::$debug_printed = TRUE;
	}
}

