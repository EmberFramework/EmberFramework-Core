<?php

	/**
	 * Ember DB base class
	 * @version 1.0
	 * @package Ember
	 * @subpackage DB
	 * @author Matt Pelmear <mjpelmear@gmail.com>
	 */
	class DB
	{
		const MODE_AUTO			= 'AUTO';
		const MODE_READONLY		= 'READONLY';
		const MODE_READWRITE	= 'READWRITE';

		const ERR_NAME_EMPTY_STRING		= 'DB Connector Name cannot be an empty string';
		const ERR_INVALID_DBCONN_MODE	= 'Invalid DB connection mode: "%mode%"';
		const ERR_NO_SUCH_CONNECTION	= 'No such DB connection: "%name%" (%mode%)';
		
		const DEFAULT_TZ = 'US/Eastern';

		static private $connections = array();
		static private $default_conn = NULL;
		static private $tz = NULL;

		/**
		 * @param SQL $sql
		 * @param String|NULL $db (Optional; defaults to NULL)
		 * @return Array|NULL
		 */
		static public function getCol( $sql, $db=NULL )
		{
			return DB::doDBcall( 'getCol', $sql, $db );
		}

		/**
		 * @param SQL $sql
		 * @param uInt $col
		 * @param String|NULL $db (Optional; defaults to NULL)
		 * @return Array|NULL
		 */
		static public function getColN( $sql, $col, $db=NULL )
		{
//TODO: getColN()
		}

		/**
		 * @param SQL $sql
		 * @param String|NULL $db (Optional; defaults to NULL)
		 * @return Array|NULL
		 */
		static public function getAll( $sql, $db=NULL )
		{
			return DB::doDBcall( 'getAll', $sql, $db );
		}

		/**
		 * @param SQL $sql
		 * @param String|NULL $db (Optional; defaults to NULL)
		 * @return Array|NULL
		 */
		static public function getRow( $sql, $db=NULL )
		{
			return DB::doDBcall( 'getRow', $sql, $db );
		}

		/**
		 * @param SQL $sql
		 * @param String|NULL $db (Optional; defaults to NULL)
		 * @return Array|NULL
		 */
		static public function getAssoc( $sql, $db=NULL )
		{
			return DB::doDBcall( 'getAssoc', $sql, $db );
		}

		/**
		 * @param SQL $sql
		 * @param String|NULL $db (Optional; defaults to NULL)
		 * @return Mixed
		 */
		static public function getOne( $sql, $db=NULL )
		{
			return DB::doDBcall( 'getOne', $sql, $db );
		}

		/**
		 * @param SQL $sql
		 * @param String|NULL $db (Optional; defaults to NULL)
		 * @return Mixed
		 */
		static public function query( $sql, $db=NULL )
		{
			return DB::doDBcall( 'query', $sql, $db );
		}

		/**
		 * @param SQL $sql
		 * @param String|NULL $db (Optional; defaults to NULL)
		 * @return Mixed
		 */
		static public function lowPriorityQuery( $sql, $db=NULL )
		{
//TODO: DB::lowPriorityQuery()
			return DB::doDBcall( 'query', $sql, $db );
		}

		/**
		 * Returns the insert ID from the last query that was run.
		 * @params String $db
		 * @return Int
		 */
		static public function getLastInsertID( $db='default' )
		{
			if( !isset(DB::$connections[$db][DB::MODE_READWRITE]) )
				throw new Exception( str_replace( '%name%', $db,
						str_replace( '%mode%', $mode,
						DB::ERR_NO_SUCH_CONNECTION )));

			return DB::$connections[$db][DB::MODE_READWRITE]->getLastInsertID();
		}

		/**
		 * @param String $method
		 * @param SQL $sql
		 * @param String|NULL $db
		 * @return Mixed
		 */
		static private function doDBcall( $method, $sql, $db )
		{
$mode = DB::MODE_AUTO;
			if( $db === NULL )
			{
				if( $sql->getDatabase() === NULL ) $db = 'default';
				else $db = $sql->getDatabase();
			}

			$sql->setDatabase( DB::$connections[$db] );
			$sql->setMode( $mode );

			if( Debug::isEnabled() )
			{
				$qid = Debug::registerQuery( $sql, $db );
				$qt_start = microtime(TRUE);
			}

			switch( $mode )
			{
				case DB::MODE_AUTO:
//TODO: Implement MODE_AUTO choosing instead of short circuiting to R/W connector
//TODO: Report for debugging which connector was actually chosen when we're doing AUTO
				case DB::MODE_READWRITE:
					if( !isset(DB::$connections[$db][DB::MODE_READWRITE]) )
						throw new Exception(
							str_replace( '%name%', $db,
							str_replace( '%mode%', $mode, DB::ERR_NO_SUCH_CONNECTION )));

					$conn = DB::$connections[$db][DB::MODE_READWRITE];
					break;
				case DB::MODE_READONLY:
					$conn_number = DB::pickRO($db);
					if( !isset(DB::$connections[$db][DB::MODE_READONLY]['con'][$conn_number]) )
						throw new Exception(
							str_replace( '%name%', $db,
							str_replace( '%mode%', $mode, DB::ERR_NO_SUCH_CONNECTION )));

					$conn = DB::$connections[$db][DB::MODE_READONLY]['con'][$conn_number];
					break;
				default:
					throw new Exception( "unsupported mode" );
			}

			$result = $conn->$method($sql);

			if( Debug::isEnabled() )
			{
				$qt_end = microtime(TRUE);
				$query_time = round( $qt_end - $qt_start, 5 );
				Debug::registerQueryTime( $qid, $query_time );
			}

			return $result;
		}

		/**
		 * @param String $db (Optional; Defaults to NULL, meaning the default connector)
		 * @param String $mode (See DB::MODE_READWRITE, DB::MODE_READONLY)
		 * @return Bool
		 */
		static public function isConnected( $db=NULL, $mode=DB::MODE_READWRITE )
		{
			if( $db === NULL )
				$db = 'default';
			
			if( !isset(DB::$connections[$name][$mode]) )
				throw new Exception( str_replace( '%name%', $db,
						str_replace( '%mode%', $mode,
						DB::ERR_NO_SUCH_CONNECTION )));

			return DB::$connections[$name][$mode]->isConnected();
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

		/**
		 * Removes a connector from the pool of available connectors.
		 * If you remove the READONLY connection, this will remove all READONLY connectors.
		 * @param String $name
		 * @param String $mode (See DB::MODE_READWRITE, DB::MODE_READONLY)
		 */
		static public function removeConnection( $name, $mode )
		{
//TODO: What to do when we remove the default connector?
			$name = (String) $name;
			switch( $mode )
			{
				case DB::MODE_READWRITE:
					if( isset(DB::$connections[$name][DB::MODE_READWRITE]) )
						DB::$connections[$name][DB::MODE_READWRITE]->disconnect();
					else
						throw new Exception( str_replace( '%name%', $name,
								str_replace( '%mode%', DB::MODE_READWRITE,
								DB::ERR_NO_SUCH_CONNECTION )));
					break;

				case DB::MODE_READONLY:
					if( isset(DB::$connections[$name][DB::MODE_READONLY]) )
					{
						foreach( DB::$connections[$name][DB::MODE_READONLY]['con'] as $db )
							$db->disconnect();
					}
					else
						throw new Exception( str_replace( '%name%', $name,
								str_replace( '%mode%', DB::MODE_READONLY,
								DB::ERR_NO_SUCH_CONNECTION )));
					break;

				default:
					throw new Exception( str_replace( '%mode%', $mode, DB::ERR_INVALID_DBCONN_MODE ) );
			}
		}

		/**
		 * Disconnects all connectors
		 */
		static public function disconnectAll()
		{
			foreach( DB::$connections as $conn )
			{
				if( isset($conn[DB::MODE_READWRITE]) )
					$conn[DB::MODE_READWRITE]->disconnect();
				if( isset($conn[DB::MODE_READONLY]) )
				{
					foreach( $conn[DB::MODE_READONLY]['con'] as $ro )
						$ro->disconnect();
				}
			}
		}

		/**
		 * Sets connector with name $name as the default to be used.
		 * @param String $name
		 */
		static public function setDefault( $name )
		{
			DB::$default_conn = $name;
			DB::$connections['default'] =& DB::$connections[$name];
		}
	}

