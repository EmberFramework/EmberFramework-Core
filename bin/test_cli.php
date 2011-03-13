<?php

	define('EXCEPTION_LOG_FILE', 'error_log');
	require_once('/var/www/ember/system/include/common.inc.php');

	Debug::enable();

	foreach(Debug::getAvalableOptions() as $o)
		Debug::setOption($o);

	Debug::printDebuggingInfo();
