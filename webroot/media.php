<?php

	$uri_parts = explode('/', $_SERVER['REQUEST_URI']);

	array_shift($uri_parts);
	if(array_shift($uri_parts) != 'media')
		FileDispatcher::http404();

	switch($uri_parts[0])
	{
		// Media loaded from the site or theme directories
		case 's':
			array_shift($uri_parts);
			$uri = '/'.implode('/', $uri_parts);

			FileDispatcher::init(FileDispatcher::FILETYPE_MEDIA, $uri);

			$file = FileDispatcher::getFilePath();
			break;
		// Media to be loaded from the dynamic media system
		// Used for things like user media
		// NOT IMPLEMENTED YET
		case 'u':
		default:
			FileDispatcher::http404();
			break;
	}

	$path_parts = pathinfo($file);

	FileDispatcher::displayFile($file, strtolower($path_parts['extension']));

