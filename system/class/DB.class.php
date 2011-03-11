<?php

	class DB
	{
		const MODE_AUTO			= 'AUTO';
		const MODE_READONLY		= 'READONLY';
		const MODE_READWRITE	= 'READWRITE';

		const ERR_NAME_EMPTY_STRING		= 'DB Connector Name cannot be an empty string';
		const ERR_INVALID_DBCONN_MODE	= 'Invalid DB connection mode: "%mode%"';

		static private $connections = array();
		static private $default_conn = NULL;
		static private $tz = NULL;

		static public function getCol( $sql, $col=0, $db=NULL )
		{
		
		}

		static public function getAll( $sql, $db=NULL )
		{
		
		}

		static public function getRow( $sql, $db=NULL )
		{
		
		}

		static public function getAssoc( $sql, $db=NULL )
		{
		
		}

		static public function getOne( $sql, $db=NULL )
		{
		
		}

		static public function query( $sql, $db=NULL )
		{
		
		}

		static public function lowPriorityQuery( $sql, $db=NULL )
		{
		
		}

		static public function getLastInsertID( $db=NULL )
		{
		
		}

		static public function isConnected( $db=NULL, $mode=DB::MODE_READWRITE )
		{
		
		}

		/**
		 * The first connector set up becomes the default.
		 * The default can be changed later with DB::setDefault()
		 * @param String $name Connector reference ID
		 * @param String $mode (see DB::MODE_READONLY, DB::MODE_READWRITE)
		 * @param String $dsn
		 * @param String $user (Optional; defaults to NULL)
		 * @param String $pass (Optional; defaulst to NULL)
		 */
		static public function addConnection( $name, $mode, $dsn, $user=NULL, $pass=NULL )
		{
			$name = (String) $name;
			if( strlen($name) <= 0 )
				throw new Exception( DB::ERR_NAME_EMPTY_STRING );

			switch( (String) $mode )
			{
				case DB::MODE_READWRITE:
					if( isset(DB::$connections[$name]) )
						DB::disconnect( $name, DB::MODE_READWRITE );
					else
						DB::$connections[$name] = array();
					if( !isset(DB::$connections[$name][DB::MODE_READWRITE]) )
						DB::$connections[$name][DB::MODE_READWRITE] = array();

					DB::$connections[$name][DB::MODE_READWRITE] = new DBConnector( $dsn, $user, $pass );
					DB::$connections[$name][DB::MODE_READWRITE]->setTZ( DB::$tz );
					break;

				case DB::MODE_READONLY:
					if( !isset(DB::$connections[$name]) )
						DB::$connections[$name] = array();
					if( !isset(DB::$connections[$name][DB::MODE_READONLY]) )
						DB::$connections[$name][DB::MODE_READONLY] = array( 'con' => array(), 'last_used' => -1 );
					$id = count(DB::$connections[$name][DB::MODE_READONLY]['con']);

					$con = new DBConnector( $dsn, $user, $pass );
					$con->setTZ( DB::$tz );
					DB::$connections[$name][DB::MODE_READONLY]['con'][$id] = $con;
						
					break;

				default:
					throw new Exception( str_replace( '%mode%', $mode, DB::ERR_INVALID_DBCONN_MODE ) );
			}

			if( count(DB::$connections) == 1 )
				DB::setDefault( $name );
		}

		static public function removeConnection( $name, $mode )
		{
		
		}

		static public function setDefault( $name )
		{

		}
	}

