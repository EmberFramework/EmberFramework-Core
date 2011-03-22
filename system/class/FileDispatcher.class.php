<?php
class FileDispatcher
{
	private static $dir_tree = NULL;
	private static $search_cache = array();
	private static $type = NULL;
	private static $file = NULL;
	private static $path_info;

	const FILETYPE_CONTAINER = "containers";
	const FILETYPE_TPL = "templates";
	const FILETYPE_CSS = "css";
	const FILETYPE_JS = "js";
	const FILETYPE_MEDIA = "media";

	const EMBER_PLUGIN_CORE = 'plugin_core';
	const EMBER_PLUGIN = 'plugin_user';

	/**
	 * Sets up the FileDispatcher
	 * Used to tell the FileDispatcher what type of file the current page load
	 * is going to render. Also makes sure that the directory tree is parsed if it
	 * is not already.
	 *
	 * If the $_SERVER[REQUEST_URI] is not the uri you want the FileDispatcher to use
	 * (for example with JS CSS and Media directorys) you can pass the uri it should use
	 *
	 * @param string $type one of the FILETYPE constants in the FileDispatcher class
	 * @param string $uri if the $_SERVER[REQUEST_URI] is not the current page uri for the FileDispatcher
	 */
	public static function init($type = self::FILETYPE_TPL, $uri = NULL)
	{
		//TODO: validate type
		//TODO: cache this
		self::buildSiteCache();

		self::$search_cache = array(
		    self::FILETYPE_CONTAINER => array(),
		    self::FILETYPE_TPL => array(),
		    self::FILETYPE_CSS => array(),
		    self::FILETYPE_JS => array(),
		    self::FILETYPE_MEDIA => array(),
		);

		Debug::log(self::$dir_tree, Debug::LOG_FILEDISPATCHER);

		self::$type = $type;

		self::$file = self::_getFilePath($type, $uri, TRUE);

		if(self::$file === FALSE)
		{
			if($type == FileDispatcher::FILETYPE_TPL)
				self::$file = self::getFilePath ($type, '404.tpl');
			self::http404();
		}

		Debug::log(array('type' => self::$type,
		    'file' => self::$file,
		    'path_info' => self::$path_info),
			Debug::LOG_FILEDISPATCHER);
	}

	/**
	 * Outputs the contense of a file of the specified type. Will send the aproprate
	 * headers for the file types it is aware of.
	 *
	 * @param string $type one of the FILETYPE constants in the FileDispatcher class
	 * @param string $uri set if the inited uri is not the uri to display
	 */
	public static function displayFile($file, $file_type, $cache_limit = NULL)
	{
		if(!is_file($file))
			FileDispatcher::http404();
		
		if( $cache_limit === NULL )
			$cache_limit = DEFAULT_CACHE_TIME;

		$ifModifiedSince = isset( $_SERVER['HTTP_IF_MODIFIED_SINCE'] ) ? $_SERVER['HTTP_IF_MODIFIED_SINCE'] : FALSE;
		$mtime = filemtime( $file );

		$FILETYPES_MIMETYPES = array(
			self::FILETYPE_CSS => 'text/css',
			self::FILETYPE_JS => 'application/x-javascript',
			'gif' => 'image/gif',
			'jpe' => 'image/jpeg',
			'jpeg' => 'image/jpeg',
			'jpg' => 'image/jpeg',
			'png' => 'image/png',
			'swf' => 'application/x-shockwave-flash',
			'mov' => 'video/quicktime',
			'doc' => 'application/msword',
			'pdf' => 'application/pdf',
			'zip' => 'application/zip',
			'flv'=> 'flv-application/octet-stream',
			'mp3'=> 'audio/mpeg',
			'xml' => 'text/xml'
		);

		if( !array_key_exists( $file_type, $FILETYPES_MIMETYPES ) )
			throw new Exception( 'Unknown FileType' );


		// might want to consider checking HTTP protocol: $_SERVER['SERVER_PROTOCOL']
		if( $ifModifiedSince && strtotime( $ifModifiedSince ) >= $mtime) {
			header( 'HTTP/1.1 304 Not Modified' );
			header( 'Cache-Control: max-age=' . $cache_limit . ', public' );
			header( 'Last-Modified: ' . gmdate( 'D, d M Y H:i:s', $mtime ) . ' GMT' );
			header( 'Expires: ' . gmdate( 'D, d M Y H:i:s', time() + $cache_limit ) . ' GMT' );
			die();
		}

		header( 'HTTP/1.1 200 OK');
		header( 'Date: ' . gmdate( 'D, d M Y H:i:s', $mtime ) . ' GMT' );
		header( 'Server: ' . $_SERVER['SERVER_SOFTWARE'] ); // ex: Apache/2.2.4 (Fedora)
		header( 'Pragma: ' );
		header( 'Cache-Control: max-age=' . $cache_limit . ', public' );
		header( 'Expires: ' . gmdate( 'D, d M Y H:i:s', time() + $cache_limit ) . ' GMT' );
		header( 'Last-Modified: ' . gmdate( 'D, d M Y H:i:s', $mtime ) . ' GMT' );
		header( 'Content-Length: ' . filesize($file) );
		header( 'Content-type: ' . $FILETYPES_MIMETYPES[$file_type] );


		ob_clean();
		flush();
		readfile($file);
		die();
	}

	/**
	 * Looks up the full path to the file specified by the uri and type
	 * @param string $type one of the FILETYPE constants in the FileDispatcher class, set if not using the
	 * inited type
	 * @param string $uri set if the inited uri is not the uri to display
	 * @return string|boolean FALSE if no file found
	 */
	public static function getFilePath($type = NULL, $uri = NULL)
	{
		if(!isset($type) || !isset($uri))
			return self::_getFilePath($type, $uri);

		if(isset(self::$search_cache[$type])
			&& isset($uri)
			&& isset(self::$search_cache[$type][$uri]))
			return self::$search_cache[$type][$uri];
		else
			return self::$search_cache[$type][$uri] = self::_getFilePath($type, $uri);
	}

	/**
	 * Looks up the full path to the file specified by the uri and type
	 * will also calculate the pathinfo string if asked to
	 * @param string $type one of the FILETYPE constants in the FileDispatcher class, set if not using the
	 * inited type
	 * @param string $uri set if the inited uri is not the uri to display
	 * @param boolean $init indicates if path_info should be calculated
	 * @return string|boolean FALSE if no file found
	 */
	private static function _getFilePath($type = NULL, $uri = NULL, $init = FALSE)
	{
		//TODO: validate type
		if(!isset($type) && isset(self::$file))
			return self::$file;

		$ext = '';

		if(isset($uri))
			$uri_parts = explode('/', $uri);
		else
		{
			$uri_parts = explode('/', $_SERVER['REQUEST_URI']);

			if($type == self::FILETYPE_CONTAINER || $type == self::FILETYPE_TPL)
				$ext = '.tpl';
		}

		$parts = count($uri_parts);
		if($parts > 1 && $uri_parts[0] == '') array_shift($uri_parts);
		elseif($parts == 0) $uri_parts[] = '';

		$found = FALSE;
		$path_info = '';

		$cur = self::$dir_tree[$type];
		foreach($uri_parts as $node)
		{
			//Populate path info after a file is found
			if($found === TRUE)
				$path_info .= '/'.$node;
			//Check for a directory (if so look in the dir)
			elseif(isset($cur[$node]) && is_array($cur[$node]))
				$cur = $cur[$node];
			//Check for a file
			elseif(isset($cur[$node.$ext]) && !is_array($cur[$node.$ext]))
			{
				$file = $cur[$node.$ext];
				$found = TRUE;
			}
			//IF a tpl, check for index file (only time an empty string can match any thing
			elseif($type == self::FILETYPE_TPL && isset($cur['index.tpl']) && !is_array($cur['index.tpl']))
			{
				$file = $cur['index.tpl'];
				$path_info = '/'.$node;
				$found = TRUE;
			}
			else
			{
				$found = FALSE;
				break;
			}

		}

		if($init && $found)
		{
			if($path_info == '')
				self::$path_info = '/';
			else
				self::$path_info = $path_info;
		}

		if($found)
			return $file;
		else
			return false;
	}

	/**
	 * Builds a list of all the plugins loaded in the system
	 * @param string $type type of plugins to list, core_plugins, plugins or NULL for both
	 * @return array
	 */
	private static function _getPluginList( $plugin_type = NULL )
	{
//TODO: Cache the results of this.
		$search = array();
		if(!isset($plugin_type))
			$search = array(EMBER_CORE_PLUGIN_DIR, EMBER_PLUGIN_DIR);
		elseif($plugin_type == self::EMBER_PLUGIN_CORE)
			$search[] = EMBER_CORE_PLUGIN_DIR;
		elseif($plugin_type == self::EMBER_PLUGIN)
			$search[] = EMBER_PLUGIN_DIR;
		else
			throw new exception('Unknown plugin type');

		$modules = array();
		foreach($search as $dir)
		{
			$real_path = realpath($dir);
			$nodes = scandir($real_path);

			foreach($nodes as $node)
			{
				if(substr($node, 0, 1) == '.')
					continue;
				
				if(is_dir($real_path.DS.$node))
					$modules[$node] = TRUE;
			}
		}

		return array_keys($modules);
	}

	/**
	 * Builds a list of the modules and the files they contain of a particular type
	 * @param array $file_type
	 */
	private static function _listModuleFiles($file_type)
	{
		$module_files = array(self::EMBER_PLUGIN_CORE, self::EMBER_PLUGIN);
		foreach(array(self::EMBER_PLUGIN_CORE => EMBER_CORE_PLUGIN_DIR,
		    self::EMBER_PLUGIN => EMBER_PLUGIN_DIR) as $type => $plug_path)
		{
			$plugins = self::_getPluginList($type);
			foreach($plugins as $plugin)
				$module_files[$type][$plugin] = self::scandirRecursive($plug_path.$plugin.DS.$file_type.DS);
		}

		return array_merge_recursive_distinct(
				$module_files[self::EMBER_PLUGIN_CORE],
				$module_files[self::EMBER_PLUGIN]);
	}


	/**
	 * Builds the cache of the site and theme directories accounting for overloaded files.
	 * Site::init() must be called before this can be called
	 */
	private static function buildSiteCache()
	{
		if(isset(self::$dir_tree))
			return;

		if(!class_exists('Site', FALSE) || !Site::isSetup())
				throw new exception('Site must be set up before SmartyPlus::initSite()');

		$file_types = array(
		    self::FILETYPE_CONTAINER,
		    self::FILETYPE_CSS,
		    self::FILETYPE_JS,
		    self::FILETYPE_MEDIA,
		    self::FILETYPE_TPL);

		$tree = array();

		$site_dir = SITES_DIR.DS.Site::getSiteName().DS;
		$theme = Site::getTheme();
		if(isset($theme))
			$theme_dir = THEMES_DIR.DS.$theme.DS;

		foreach($file_types as $ft)
		{
			switch($ft)
			{
				case self::FILETYPE_JS:
					$lib_tree = array();
					$lib_tree['lib'] = self::scandirRecursive(JS_LIB_DIR);
					$lib_tree['modules'] = self::_listModuleFiles(self::FILETYPE_JS);
					break;
				case self::FILETYPE_CSS:
					$lib_tree = array();
					$lib_tree['lib'] = self::scandirRecursive(CSS_LIB_DIR);
					break;
				default:
					$lib_tree = NULL;
					break;
			}

			$site_tree = self::scandirRecursive($site_dir.$ft);

			$site_tree = array_merge_recursive_distinct($lib_tree, $site_tree);

			if(isset($theme))
			{
				$theme_tree = self::scandirRecursive ($theme_dir.$ft);
				$tree[$ft] = array_merge_recursive_distinct($theme_tree, $site_tree);
			}
			else
				$tree[$ft] = $site_tree;
		}

		self::$dir_tree = $tree;
	}

	/**
	 * Builds an array of the directory using directory / file nodes as keys
	 * and full paths to the files in the array
	 * @param string $path
	 * @return array
	 */
	private static function scandirRecursive($path)
	{
		if(is_dir($path))
		{
			$real_path = realpath($path);
			$nodes = scandir($real_path);

			$tree = array();
			foreach($nodes as $node)
			{
				if(substr($node, 0, 1) == '.')
					continue;
				$node_path = $real_path.DS.$node;
				if(is_dir($node_path))
					$tree[$node] = self::scandirRecursive ($node_path);
				else
					$tree[$node] = $node_path;
			}
			
			return $tree;
		}
		else
			throw new exception('Not a dir');
	}

	/**
	 * Get function for the path_info cacluated by init()
	 * @return string
	 */
	public static function getPathInfo()
	{
		return self::$path_info;
	}

	/**
	 * Issue 404 error
	 */
	public static function http404($die = TRUE)
	{
		header("HTTP/1.0 404 Not Found");
		if($die)
			die;
	}
}