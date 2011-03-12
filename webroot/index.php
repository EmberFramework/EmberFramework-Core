<?php

echo '<pre>';
	print_r($GLOBALS);
echo '</pre>';

echo '<pre>';
	print_r(get_included_files());
echo '</pre>';

echo '<pre>';
	echo 'DOCUMENT_ROOT: '.DOCUMENT_ROOT . PHP_EOL;
	echo 'CODE_BASE: '.CODE_BASE . PHP_EOL;
	echo 'CODE_BASE_ROOT:'.CODE_BASE_ROOT . PHP_EOL;
echo '</pre>';

echo '<pre>';
echo 'Page Load Time: ' . getPageLoadTime();
echo '</pre>';

Session::finalize();