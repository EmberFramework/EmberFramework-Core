<?php
class Session implements iSession
{
	private static $id;
	private static $user_id;
	private static $session_page_number;
	private static $session_start_time;
	private static $tz_offset;

	/**
	 * Array of static variables to store in the session
	 * Names of variables are the keys and the values are
	 * boolean, TRUE for required variables, FALSE for
	 * optional variables
	 * @var array
	 */
	private static $required_vars = array(
			'id' => TRUE,
			'user_id' => TRUE,
			'session_start_time' => TRUE,
			'tz_offset' => TRUE,
			);

	// Saved directly in session with finalize
	private static $data = array();
	private static $objects = array();

	/*
	 * Not stored in session
	 */
	/**
	 * Indecates if the current page load has been counted
	 * @var boolean
	 */
	private static $page_counted = FALSE;

	const SESSION_OBJECT_KEY = 'EMBER_SESSION';
        const OBJECT_VERSION = 1;

	/**
	 * Sets up the Session object regenerating it from the session
	 * or building it from scratch
	 * @return boolean True of the init came from the session, false if it is defaults
	 */
	public static function init()
	{
		//Set up the session for the session object, done outside the usual session object regeneration
		// Because it is used to make the session object recoverable by it's self
		if(!isset($_SESSION[self::SESSION_OBJECT_KEY]) || 
			!is_array($_SESSION[self::SESSION_OBJECT_KEY]) ||
			!isset($_SESSION[self::SESSION_OBJECT_KEY]['data']) ||
			!is_array($_SESSION[self::SESSION_OBJECT_KEY]['data']))
			$_SESSION[self::SESSION_OBJECT_KEY] = array('data' => array(), 'objects' => array());
		else
		{
			self::$data = $_SESSION[self::SESSION_OBJECT_KEY]['data'];
			self::$objects = $_SESSION[self::SESSION_OBJECT_KEY]['objects'];
		}

		$data = self::getObject(get_class());

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

			if(Debug::isEnabled())
				foreach($required as $k => $r)
				{
					if($r)
						Debug::log('Session: var not recovered: '.$k, Debug::LOG_SESSION);
				}
		}

		if($new)
		{
			self::$id = 0;
			self::$user_id = 0;
			self::$session_page_number = 0;
			self::$session_start_time = time();
			self::$tz_offset = NULL;

			Session::register(get_class());
		}

		if (!isset($_SESSION[CSRF_FIELD]))
			$_SESSION[CSRF_FIELD] = session_id();

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
	 * Clears the current session, starts a new session
	 * must be called before a new user can login after a user
	 * has already logged in
	 */
	public static function newSession()
	{
		//TODO
	}

	/**
	 * Registers the object to be saved in the session
	 * The class must implement iSession
	 * @param string $class name of the class to be saved to the session
	 */
	public static function register($class)
	{
		$implements = class_implements($class);
		if(array_search('iSession', $implements) === FALSE)
			throw new Exception("Class: {$class} not compatable, does not implement iSession");

		Debug::log('Registering: '.$class, Debug::LOG_SESSION);
		self::$objects[$class] = $class::OBJECT_VERSION;
	}

	/**
	 * Recovers the data for the specified class
	 * @param string $class class to be recovered from the session
	 * @return array
	 */
	public static function getObject($class)
	{
		//Rebuilding object, version violation
		if(!isset(self::$objects[$class]))
		{
			Debug::log('Object not in session: '.$class, Debug::LOG_SESSION);
			return array();
		}

		if($class::OBJECT_VERSION != self::$objects[$class])
		{
			Debug::log('Version changed: '.$class, Debug::LOG_SESSION);
			unset(self::$objects[$class]);
			return array();
		}
		else
		{
			Debug::log('Object in session: '.$class, Debug::LOG_SESSION);
			return self::$data[$class];
		}
	}

	/**
	 * Saves the session to the dabase
	 * populates the id value, if the session was lost recovers what it
	 * can from the DB
	 */
	public static function initDBSession()
	{
//TODO
	}

	/**
	 * Saves the user_id to the session and the database
	 * @param uInt $user_id id to save to the db and in the session
	 */
	public static function setUserId($user_id)
	{
		self::$user_id = $user_id;

		//TODO: sync to the database
	}

	/**
	 * Saves the named class to the data array.
	 * @param string $class name of the class to save
	 */
	public static function saveObject($class)
	{
		if(!isset(self::$objects[$class]))
			throw new Exception( "Class not registered" );

		Debug::log('Object saved to session: '.$class, Debug::LOG_SESSION);

		self::$data[$class] = $class::serialize();
		self::$objects[$class] = $class::OBJECT_VERSION;
	}

	/**
	 * Saves all the registered objects to the session
	 */
	public static function finalize()
	{
		foreach(self::$objects as $class => $version)
			self::saveObject ($class);

		$_SESSION[self::SESSION_OBJECT_KEY]['data'] = self::$data;
		$_SESSION[self::SESSION_OBJECT_KEY]['objects'] = self::$objects;
	}

	/**
	 * Increments the page counter.
	 * Will only ever allow a page load to be counted once
	 */
	public static function incrementPageCount()
	{
		if(self::$page_counted)
			return;

		self::$session_page_number++;
	}
}