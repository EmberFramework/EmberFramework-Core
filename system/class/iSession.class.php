<?php
interface iSession
{

	/**
	 * Initalizes the class from the session
	 * @return boolean True of the init came from the session, false if it is a new object
	 */
	public static function init();

	/**
	 * Serializes the static variables for the object
	 */
	public static function serialize();

/*
 * Sample code:
	public static function serialize()
	{
		$result = array();
		foreach(self::$required_vars as $var => $required)
			$result[$var] = self::$$var;

		return $result;
	}
 *
 */
}
