<?php
	echo 'index.php';

	Debug::print_r('Test', 'No Label', Debug::MODE_CLI);

	Session::finalize();

	Debug::printDebuggingInfo();