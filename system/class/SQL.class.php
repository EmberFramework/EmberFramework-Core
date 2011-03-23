<?php

	/**
	 * Ember SQL class
	 * Supports bind
	 * @version 1.0
	 * @package Ember
	 * @subpackage DB
	 * @author Matt Pelmear <mjpelmear@gmail.com>
	 */
	class SQL
	{
		const PARAM_ESCAPE_STYLE			= 'param-escape-style';
		const ESCAPE_STYLE_MYSQL			= 'mysql';
		const ESCAPE_STYLE_SQLITE			= 'sqlite';

		private $sql;

		function __construct( $sql )
		{
//TODO
			$this->sql = $sql;
		}
		
		static public function bind( $sql )
		{
//TODO
			$q = new SQL( $sql );
			return $q;
		}
		
		public function setDatabase( $db )
		{
//TODO
		}
		
		public function getDatabase()
		{
			return NULL;
		}
		
		public function setMode( $mode )
		{
//TODO
		}
		
		public function willCalcFoundRows()
		{
//TODO
			return FALSE;
		}
		
		public function getSQL()
		{
//TODO
			return $this->sql;
		}
	}

