<?php
	require_once('Smarty/Smarty.class.php');

	/* Resource handlers for templates */
	function ember_template_get_template ($tpl_name, &$tpl_source, $smarty_obj)
	{

		$tpl_file = FileDispatcher::getFilePath(FileDispatcher::FILETYPE_TPL, $tpl_name);

		if($tpl_file === FALSE) return FALSE;

		$tpl_source = file_get_contents($tpl_file);

		return true;
	}

	function ember_template_get_timestamp($tpl_name, &$tpl_timestamp, $smarty_obj)
	{
		$tpl_file = FileDispatcher::getFilePath(FileDispatcher::FILETYPE_TPL, $tpl_name);

		if($tpl_file === FALSE) return FALSE;
		
		$time = filemtime($tpl_file);
		
		if($time === FALSE) return FALSE;
		
		$tpl_timestamp = $time;

		return true;
	}

	function ember_template_get_secure($tpl_name, $smarty_obj)
	{
	    // assume all templates are secure
	    return true;
	}

	function ember_template_get_trusted($tpl_name, $smarty_obj)
	{
	    // not used for templates
	}

	/* Resource handlers for container */
	function ember_container_get_template ($tpl_name, &$tpl_source, $smarty_obj)
	{

		$tpl_file = FileDispatcher::getFilePath(FileDispatcher::FILETYPE_CONTAINER, $tpl_name);

		if($tpl_file === FALSE) return FALSE;

		$tpl_source = file_get_contents($tpl_file);

		return true;
	}

	function ember_container_get_timestamp($tpl_name, &$tpl_timestamp, $smarty_obj)
	{
		$tpl_file = FileDispatcher::getFilePath(FileDispatcher::FILETYPE_CONTAINER, $tpl_name);

		if($tpl_file === FALSE) return FALSE;
		
		$time = filemtime($tpl_file);
		
		if($time === FALSE) return FALSE;
		
		$tpl_timestamp = $time;

		return true;
	}

	function ember_container_get_secure($tpl_name, $smarty_obj)
	{
	    // assume all templates are secure
	    return true;
	}

	function ember_container_get_trusted($tpl_name, $smarty_obj)
	{
	    // not used for templates
	}




	class SmartyPlus extends Smarty
	{
		private static $site_init = FALSE;
		private static $mod_vars = array();

		private static $ember_plugins_dir = array();

		private static $ember_plugin_modules = array();

		/**
		 * Sets up the SmartyPlus enviroment, must set up the Site before this call
		 * @global SmartyPlus $smarty
		 * @return SmartyPlus
		 */
		public static function init()
		{
			global $smarty;

			if(isset($smarty) && get_class($smarty) == 'SmartyPlus')
				return $smarty;

			$smarty = new SmartyPlus();

			$smarty->addPluginsDir(SMARY_PLUGINS_OVERLOAD_DIR);

			self::$ember_plugins_dir[] = EMBER_PLUGIN_DIR;
			self::$ember_plugins_dir[] = EMBER_CORE_PLUGIN_DIR;

			self::initSite();


			$smarty->registerResource("ember_container", array("ember_container_get_template",
							       "ember_container_get_timestamp",
							       "ember_container_get_secure",
							       "ember_container_get_trusted"));
			
			$smarty->registerResource("ember_template", array("ember_template_get_template",
							       "ember_template_get_timestamp",
							       "ember_template_get_secure",
							       "ember_template_get_trusted"));

			$smarty->default_resource_type = 'ember_template';

//			if(Debug::isOptionEnabled(Debug::LOG_SMARTY))
//				$smarty->debugging = TRUE;

			return $smarty;
		}


		/**
		 * Configures SmartyPlus using the site settings
		 */
		public static function initSite()
		{
			if(!class_exists('Site', FALSE) || !Site::isSetup())
				throw new exception('Site must be set up before SmartyPlus::initSite()');
			
			if(self::$site_init)
				return;

			global $smarty;

			if(get_class($smarty) != 'SmartyPlus')
				throw new exception('SmartyPlus::init() must be called before SmartyPlus::initSite()');

			$site_name = Site::getSiteName();

			$smarty->addTemplateDir(SITES_DIR.DS.$site_name.DS.'templates');
			$theme = Site::getTheme();
			if(isset($theme))
				$smarty->addTemplateDir(THEMES_DIR.DS.$theme.DS.'templates');


			$smarty->compile_dir = SITES_DIR.DS.$site_name.DS.'smarty/compiled';
			$smarty->config_dir = SITES_DIR.DS.$site_name.DS.'smarty/config';
			$smarty->cache_dir = SITES_DIR.DS.$site_name.DS.'smarty/cache';

			self::$site_init = TRUE;
		}

		/**
		 * Returns the currently configured SmartyPlus object
		 * @global SmartyPlus $smarty
		 * @return SmartyPlus
		 */
		public static function getSmarty()
		{
			global $smarty;

			if(get_class($smarty) == 'SmartyPlus')
				return $smarty;
			else
				return self::init();
		}

		/**
		 * Uses the current uri from the webserver to display the template
		 * found by FileDispatcher::init().
		 *
		 * This uses template inheritence to handle the loading of containers
		 * Containers should be loaded with 'ember_container:' resource handler
		 * in the {extends} tag.
		 */
		public function displayURI()
		{
			//Look up template from url (processed by FileDispatcher)
			parent::display('file:'.FileDispatcher::getFilePath());
		}

		/**
		 * Uses the current uri from the webserver to display the template
		 * found by FileDispatcher::init().
		 *
		 * This uses the set routine to set the container param to the name of the
		 * template to use as the container for the current template. If no container
		 * is specified 'default.tpl' is used
		 */
		public function displayURILegacy()
		{

			//Look up template from url (processed by FileDispatcher)
			$buffer = parent::fetch('file:'.FileDispatcher::getFilePath());

			parent::assignGlobal('buffer', $buffer);

			//Select Container and look up from FileDispatcher
			$container = parent::getTemplateVars('container');

			if(!isset($container))
				$container = 'default.tpl';
			//Display container

			parent::display('ember_container:'.$container);
		}

		/**
		 * Assigns a variable into the module variable space in smarty. this
		 * should be the only way used by modules to register variables to smarty
		 * for their templates.
		 *
		 * @param string $name name of the variable
		 * @param mixed $value value to store
		 */
		public function assignMod($name, $value)
		{
			self::$mod_vars[$name] = $value;
		}

		/**
		 * Renders the template for the module. Uses parses the first part of the template name as the plugin name
		 * the module belongs to. The plugin name should be followed by an underscore then the module name and posibly
		 * a template name:
		 *
		 * plugin: core
		 * module: user
		 * template: detail
		 *
		 * 'core_user_detail'
		 *
		 * the extention '.tpl' is automaticaly added to the end of the template name
		 *
		 * @param string $module name of the module template should be prefixed by the module name ie. 'core_'
		 * @param boolean $overide if set to TRUE the module vars are NOT purged after compiling the template
		 * if the module needs to compile more than template. The last call to fetchModule by a module MUST use $override = FALSE
		 * (the default value)
		 * @return string output of the rendered template
		 */
		public function fetchModule($module, $override = FALSE)
		{
			//Look up module template
			$mod_parts = explode('_', $module, 2);

			foreach(self::$ember_plugins_dir as $dir)
			{
				$file = $dir.$mod_parts[0].DS.'templates'.DS.$module.'.tpl';
				if(file_exists($file))
					$template = $file;
			}

			//Save the 'mod' variable from smarty
			$parent_vars = parent::getTemplateVars('mod');

			//Register the new mod array
			$current_vars = self::$mod_vars;
			parent::assignGlobal('mod', $current_vars);

			//Clear the current vars incase there are modules in the template
			self::$mod_vars = array();
			//Render template
			$output = parent::fetch('file:'.$template);

			//Restore the parent mod array
			parent::assignGlobal('mod', $parent_vars);

			//Purge the child array unless overriden
			if($override)
				self::$mod_vars = $current_vars;

			return $output;
		}

		/**
		 * Takes unknown classes and loads plugin files for them
		 * class name format: Smarty_PluginType_PluginName
		 * plugin filename format: plugintype.pluginname.php
		 *
		 * SmartyPlus adds searching the ember sysplugins directories
		 * Works with the Auto loader which loads classes for smarty
		 * also providing ember overload
		 *
		 * @param string $plugin_name class plugin name to load
		 * @return string |boolean filepath of loaded file or false
		 */
		public function loadPlugin($plugin_name, $check = true)
		{
			$_plugin_name = strtolower($plugin_name);
			$_name_parts = explode('_', $_plugin_name, 3);
			// class name must have three parts to be valid plugin
			if (count($_name_parts) < 3 || $_name_parts[0] !== 'smarty') {
				throw new SmartyException("plugin {$plugin_name} is not a valid name format");
				return false;
			}
			// if type is "internal", get plugin from sysplugins
			if ($_name_parts[1] == 'internal')
			{
				$file = SMARTY_SYSPLUGINS_OVERLOAD_DIR . $_plugin_name . '.php';
				if (file_exists($file))
				{
					require_once($file);
					return $file;
				}
			}

			$val = parent::loadPlugin($plugin_name, $check);

			if($val) return $val;

			return FALSE;
		}

		/**
		 * Looks up ember modules for smarty, returns the path to the module file
		 * 
		 * @param string $module_name
		 * @param string $module_type
		 * @return string filename
		 */
		public function getModuleFile($module_name, $module_type)
		{
			$plugin_name = explode('_', $module_name, 2);

			if(count($plugin_name) != 2)
				return NULL;

			$file_name = DS.$plugin_name[0].DS.'modules'.DS.$module_type.'.'.$module_name.'.php';

			foreach(self::$ember_plugins_dir as $_plugin_dir)
			{
				$file = rtrim($_plugin_dir, '/\\') . $file_name;

				if(file_exists($file))
				{
					self::$ember_plugin_modules['smarty_' . $module_type . '_' . $module_name] = TRUE;
					Debug::log('Ember Plugin Found: smarty_' . $module_type . '_' . $module_name, Debug::LOG_SMARTY);
					return $file;
				}
			}
		}

		/**
		 * Checks if a module is an ember module or not, relies on getModuleFile()
		 * to build the list of modules. This should only be needed to compile
		 * templates which should use the getModuleFile()
		 * @param string $function name of the function
		 * @return boolean
		 */
		public static function isEmberModule($function)
		{
			if(isset(self::$ember_plugin_modules[$function]))
				return self::$ember_plugin_modules[$function];
			else
				return false;
		}
	}