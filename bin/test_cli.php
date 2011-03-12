<?php

	require_once('/var/www/ember/system/include/common.inc.php');

	print_r($GLOBALS);

	echo 'DOCUMENT_ROOT: '.DOCUMENT_ROOT . PHP_EOL;
	echo 'CODE_BASE: '.CODE_BASE . PHP_EOL;
	echo 'CODE_BASE_ROOT:'.CODE_BASE_ROOT . PHP_EOL;

	echo 'Page Load Time: ' . getPageLoadTime() . PHP_EOL;
