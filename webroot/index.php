<?php

	FileDispatcher::init();

	$smarty->displayURI();

	//$smarty->displayURILegacy();

	Session::finalize();

	Debug::printDebuggingInfo();