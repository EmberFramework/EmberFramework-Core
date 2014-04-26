<?php

	define('EXCEPTION_LOG_FILE', 'error_log');
	//domain of your site here:
	//$_SERVER['SERVER_NAME'] = '';
	require_once('/var/www/ember/system/include/common.inc.php');

	Debug::enable();

	foreach(Debug::getAvalableOptions() as $o)
		Debug::setOption($o);

	Debug::printDebuggingInfo();
