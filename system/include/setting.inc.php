<?php

	if (!defined('DS')) define('DS', DIRECTORY_SEPARATOR);
	define('LOG_PATH', '/var/log/ember/');

	// CSRF SECURITY
	define('CSRF_LOG_FILE', LOG_PATH.'csrf-log');
	define('CSRF_FIELD', 'csrf_key');

	// EXCEPTION HANDLER
	if(!defined('EXCEPTION_LOG_FILE'))
		define('EXCEPTION_LOG_FILE', LOG_PATH.'exception-log');

	// DOC ROOT
	define('DOCUMENT_ROOT', $_SERVER['DOCUMENT_ROOT'].DS);
	define('CODE_BASE', 'ember');
	$parts = explode(DS, $_SERVER['DOCUMENT_ROOT']);
	array_pop($parts);
	define('CODE_BASE_ROOT', implode(DS, $parts).DS);
	unset($parts);

	define('THEMES_DIR', CODE_BASE_ROOT.'themes');
	define('SITES_DIR', CODE_BASE_ROOT.'sites');

	define('EMBER_LIB_DIR', CODE_BASE_ROOT.'system'.DS.'lib');
	define('PHP_LIB_DIR', EMBER_LIB_DIR.DS.'php');
	define('JS_LIB_DIR', EMBER_LIB_DIR.DS.'js');
	define('CSS_LIB_DIR', EMBER_LIB_DIR.DS.'css');

	define('EMBER_CORE_PLUGIN_DIR', CODE_BASE_ROOT.'system'.DS.'plugins'.DS);
	define('EMBER_PLUGIN_DIR', CODE_BASE_ROOT.'plugins'.DS);

	define('SMARY_PLUGINS_OVERLOAD_DIR', CODE_BASE_ROOT.'system'.DS.'smarty'.DS.'plugins'.DS);
	define('SMARTY_SYSPLUGINS_OVERLOAD_DIR', CODE_BASE_ROOT.'system'.DS.'smarty'.DS.'sysplugins'.DS);

	define('TEMP_SPACE', '/tmp/');

	//used by the file uploader
	define('TEMP_UPLOAD_DIR', TEMP_SPACE . 'ember-http-uploads'.DS);

	define('CONFIG_MODE_XML', 'xml');
	define('CONFIG_MODE_DB', 'db');

	define('DEFAULT_CACHE_TIME', 604800);

	// no php end tag (not required- causes problems with unwanted whitespace)