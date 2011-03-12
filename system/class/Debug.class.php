<?php
class Debug implements iSession
{
	//Session saved vars
	private static $enabled = FALSE;
	private static $mode;
	private static $options;

	//Vars not saved in session
	private static $data;
	private static $debug_printed = FALSE;

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

	const LOG_LOADED_FILES = 'LOG_LOADED_FILES';
	const LOG_GLOBALS = 'LOG_GLOBALS';
	const LOG_PAGELOAD_TIME = 'LOG_PAGE_LOAD_TIME';
	const LOG_CODEBASE = 'LOG_CODEBASE';

	static $avalable_options = array(
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

	public static function isEnabled()
	{
		return self::$enabled;
	}

	private static function validateOption($option)
	{
		if(in_array($option, self::$avalable_options) === FALSE)
			return FALSE;
		else
			return TRUE;
	}

	public static function getAvalableOptions()
	{
		return self::$avalable_options;
	}

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

	public static function isOptionEnabled($option)
	{
		if(!self::$enabled) return FALSE;

		if(isset(self::$options[$option])) return self::$options[$option];
		else return FALSE;
	}

	public static function enable()
	{
		self::$enabled = TRUE;
	}

	public static function disable()
	{
		self::$enabled = FALSE;
	}

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


	public static function getCallingFunction()
	{
		return 'Obj:method';
	}

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