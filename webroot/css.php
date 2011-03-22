<?php

	$uri_parts = explode('/', $_SERVER['REQUEST_URI']);

	array_shift($uri_parts);
	if(array_shift($uri_parts) != 'css')
		FileDispatcher::http404();

	$uri = '/'.implode('/', $uri_parts);

	FileDispatcher::init(FileDispatcher::FILETYPE_CSS, $uri);

	FileDispatcher::displayFile(FileDispatcher::getFilePath(), FileDispatcher::FILETYPE_CSS);