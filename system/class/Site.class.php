<?php

class Site implements iSession
{
	/**
	 * Id of site, could be from DB or XML
	 * @var uInt
	 */
	private static $id;
	
	/**
	 * short name of site, used for the site's directory
	 * @var string
	 */
	private static $short_name;
	/**
	 * Name of the sites theme, if not set no theme is used
	 * @var string
	 */
	private static $theme;

	/**
	 * Array of settings for the site. Generaly id numbers and settings
	 * to control the users experience.
	 * @var array
	 */
	private static $setting;

	/**
	 * Array of data field for the site. Generaly text to display to the user
	 * @var array
	 */
	private static $data;

	/**
	 * Vars to be saved in the session
	 * @var array
	 */
	private static $required_vars = array(
		'id' => TRUE,
		'short_name' => TRUE,
		'theme' => FALSE,
		'setting' => TRUE,
		'data' => TRUE,
		);

	/**
	 * Inicates if the Site::init() has been called.
	 * @var boolean
	 */
	private static $setup = FALSE;

	const OBJECT_VERSION = 1;

	//The two constants values must match the static variables names
	const VAR_TYPE_SETTING = 'setting';
	const VAR_TYPE_DATA = 'data';

	/**
	 * Sets up the Site object regenerating it from the session
	 * or building it from scratch
	 * @return boolean True of the init came from the session, false if it is defaults
	 */
	public static function init()
	{
		if(self::$setup)
			return;
		
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

			if(Debug::isEnabled())
				foreach($required as $k => $r)
				{
					if($r)
						Debug::log('Site: var not recovered: '.$k, Debug::LOG_SESSION);
				}
		}

		if($new)
		{
			switch(SESSION_TYPE)
			{
				case 'web':
					self::loadSite();
					break;
				case 'cli':
//TODO: Improve this, currenlty it assumes you set $_SERVER['SERVER_NAME'] to the site you want to load
					if(isset($_SERVER['SERVER_NAME']))
						self::loadSite();
					break;
				default:
					break;
			}

			Session::register(get_class());
		}

		self::$setup = TRUE;
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
	 * Indicates if the Site has been setup
	 * @return boolean
	 */
	public static function isSetup()
	{
		return self::$setup;
	}

	/**
	 * Loads the site from either an XML file if specified or the database
	 * Identifies the site by the hostname.
	 *
	 * Loads vars from both the database if available and the xml file.
	 * First loading global vars and then loading the site vars.
	 */
	private static function loadSite()
	{
		$xml_conig_file = CODE_BASE_ROOT.'system'.DS.'config'.DS.'site.xml';
		if(file_exists($xml_conig_file))
		{
			$siteConfig = new DOMDocument();
			$siteConfig->load($xml_conig_file);

			if($siteConfig->documentElement->nodeName !== 'siteConfig')
				throw new exception('Invalid Site Config file');
			
			$mode = $siteConfig->documentElement->getAttribute('mode');
			
			switch($mode)
			{
				case '':
					$mode = CONFIG_MODE_XML;
				case CONFIG_MODE_XML:
					break;
				case CONFIG_MODE_DB:
					break(1);
			}
		}
		else
			$mode = CONFIG_MODE_DB;

		Debug::log($mode, Debug::LOG_SITE, 'Config Mode');
		switch($mode)
		{
			case CONFIG_MODE_XML:

				$configElement = $siteConfig->documentElement;

				$domains = $configElement->getElementsByTagName('domains');

				if($domains->length != 1)
					throw new Exception('Invalid config file, missing or to many <domains> tags, should only be 1');

				$server_name = strtolower($_SERVER['SERVER_NAME']);

				foreach($domains->item(0)->childNodes as $node)
				{
					if($node->nodeName != 'domain')
						continue;

					if($node->getAttribute('wildcard') == 'true')
					{
						//TODO: perform wildcard domain check here
					}
					else
					{
						$host = $node->getAttribute('host');
						if($host == $server_name && $host != '' && $node->getAttribute('status') == 'active')
						{
							$site_name = $node->getAttribute('site_name');
							$domain_type = $node->getAttribute('type');
							break;
						}
					}
				}

				if(!isset($site_name) || $site_name == '')
					throw new exception('Domain Not Found');

				Debug::log($site_name, Debug::LOG_SITE, 'Site Name');
				Debug::log($domain_type, Debug::LOG_SITE, 'Domain Type');

				$sites = $configElement->getElementsByTagName('site');

				for($i = 0; $sites->length > $i; $i++)
				{
					$site_check = $sites->item($i);
					if($site_check->getAttribute('site_name') == $site_name)
						$site = $site_check;
				}

				if(!isset($site))
					throw new exception('Site Not Found');

				$id = $site->getAttribute('site_id');
				$theme = $site->getAttribute('theme');

				if(((int)$id) == 0)
					throw new exception('Invalid id, not an integer or 0');

//TODO: validate the id with the db, should also have the short name there to double check

				self::$id = $id;
				if($theme != '')
				{
					Debug::log($theme, Debug::LOG_SITE, 'Theme');
					self::$theme = $theme;
				}

				self::$short_name = $site_name;

				//Load Gloabal Args

				foreach($configElement->childNodes as $node)
					if($node->nodeName == 'vars')
						self::loadVarsXML ($node);

				self::loadVarsDB(TRUE);

				$vars = $site->getElementsByTagName('vars');

				if($vars->length > 1)
					throw new Exception('Invalid config file, to many vars tags, should not be more than 1');

				if($vars->length == 1)
					self::loadVarsXML($vars->item(0));

				self::loadVarsDB();

				break;
			case CONFIG_MODE_DB:
				//TODO: do DB mode here
				self::loadVarsDB(TRUE);
				self::loadVarsDB();
				break;
		}

		//TODO: validate the theme, short_name with the file system
		if(isset(self::$theme) && self::$theme != '')
		{
			if(!is_dir(THEMES_DIR.DS.self::$theme))
				throw new exception('Invalid theme name: '.self::$theme.', no such path');
		}

		if(!is_dir(SITES_DIR.DS.self::$short_name))
			throw new exception('Invalid site name: '.self::$short_name.', no such path');
	}

	/**
	 * Loads vars from a DOMNode
	 * @param DOMNode $vars
	 */
	private static function loadVarsXML($vars)
	{
		//TODO: error check here. currently silently ignores errors
		foreach($vars->childNodes as $node)
		{
			switch($node->nodeName)
			{
				case self::VAR_TYPE_SETTING:
					$name = $node->getAttribute('name');
					if($name == '')
						break;
					self::$setting[$name] = $node->nodeValue;
					break;
				case self::VAR_TYPE_DATA:
					$name = $node->getAttribute('name');
					if($name == '')
						break;
					self::$data[$name] = $node->nodeValue;
					break;
			}
		}
	}

	/**
	 * Loads the Vars into the site from the Database
	 *
	 * @param bool $global indicates if it should load global or site vars
	 */
	private static function loadVarsDB($global = FALSE)
	{
//TODO: Loads the vars from the DATABASE instad of xml
	}

	/**
	 * Returns the site's short name, used to locate the site's root
	 * @return string
	 */
	public static function getSiteName()
	{
		return self::$short_name;
	}

	/**
	 * Returns the site's theme, or null if no theme has been set
	 * @return string|null
	 */
	public static function getTheme()
	{
		if(isset(self::$theme) && self::$theme != '')
			return self::$theme;
		else
			return NULL;
	}

	/**
	 * Returns either the value if a key is specified or all the Settings
	 *
	 * If a key is specified it will return NULL if the value is not set or the value which may be NULL
	 * @param string $key
	 * @return mixed var value, NULL if not set
	 */
	public static function getSetting($key = NULL)
	{
		return self::getVar(self::VAR_TYPE_SETTING, $key);
	}

	/**
	 * Returns either the value if a key is specified or all the Data
	 *
	 * If a key is specified it will return NULL if the value is not set or the value which may be NULL
	 * @param string $key
	 * @return mixed var value, NULL if not set
	 */
	public static function getData($key = NULL)
	{
		return self::getVar(self::VAR_TYPE_DATA, $key);
	}

	/**
	 * Returns either the value if a key is specified or all the Vars of the type requested
	 *
	 * If a key is specified it will return NULL if the value is not set or the value which may be NULL
	 * @param string $type one of the constants Site::VAR_TYPE_SETTING or Site::VAR_TYPE_DATA
	 * @param string $key
	 * @return mixed var value, NULL if not set
	 */
	public static function getVar($type, $key = NULL)
	{
		switch($type)
		{
			case self::VAR_TYPE_SETTING:
			case self::VAR_TYPE_DATA:
				break;
			default:
				throw new exception('Invalid var type '.$type);
		}

		if(!isset($key))
			return self::$$type;

		if(key_exists($key, self::$$type))
		{
			Debug::log($type . ':'.$key.' requested', Debug::LOG_SITE);
			return self::${$type}[$key];
		}
		else
		{
			Debug::log($type . ':'.$key.' requested - not set', Debug::LOG_SITE);
			return NULL;
		}
	}

	/**
	 * Assigns the data and setting arrays into the SmartyPlus object.
	 */
	public static function initSmarty()
	{
		$data = array();
		$data['data'] = self::$data;
		$data['setting'] = self::$setting;

		$smarty = SmartyPlus::getSmarty();

		$smarty->assignGlobal('site', $data);
	}

}