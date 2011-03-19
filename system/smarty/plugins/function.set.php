<?php

	/**
	 * Assigns the params to global smarty vars
	 * @param array $params
	 * @param SmartyPlus $smarty
	 */
	function smarty_function_set($params, &$smarty)
	{
		foreach($params as $key => $var)
			$smarty->assignGlobal($key, $var);
	}