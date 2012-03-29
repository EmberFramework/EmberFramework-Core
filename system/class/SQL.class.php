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

		const ERR_INTERNAL					= 'Internal error';
		const ERR_UNKNOWN_DATATYPE			= 'Unknown datatype during bind: "%type%"';
		const ERR_INVALID_ESCAPE_STYLE		= 'Invalid escape style: "%style%"';
		const ERR_INVALID_DATETIME			= 'Encountered invalid datetime value with not default value specified';
		const ERR_NO_SQLITE_DRIVER			= 'No compatible SQLite driver available';
		const ERR_EXPECTED_ARRAY			= 'Expected an array';
		const ERR_EXPECTED_UINT				= 'Expected an unsigned integer';
		const ERR_BIND_REQUIRED_INT			= 'Bind required an integer, but received: "%arg%"';
		const ERR_BIND_REQUIRED_REAL			= 'Bind required a real number, but received: "%arg%"';
		const ERR_INVALID_SQL_FIELD			= 'Encountered an invalid SQL field: "%fieldname%"';
		const ERR_CALC_FOUND_ROWS_DISABLED	= 'You must enabled the SQL_CALC_FOUND_ROWS feature before you can retrieve the results';
		const ERR_ARG_COUNT					= 'Incorrect number of arguments for query bind (expected %expected% but received %received%)';
		const ERR_EXPECTED_ARG_AT_POS		= 'Expected an argument to bind to position %pos%, but did not find it.';

//TODO: Exactly how long can a sql fieldname be?
		const SQLFIELD_MAX_LEN				= 45;

		/**
		 * The unbound query
		 * @var String
		 */
		private $sql;
		/**
		 * Arguments to bind into the query
		 * @var Array
		 */
		private $args = array();
		/**
		 * Whether or not to calculate and cache SQL_CALC_FOUND_ROWS
		 * @var Bool
		 */
		private $calc_found_rows = FALSE;
		/**
		 * Number of found rows after the query has been run with the Ember Database connector.
		 * ($calc_found_rows must have been set to TRUE when the query was bound and when the DB conector ran the query)
		 * @var NULL|uInt
		 */
		private $found_rows = NULL;
		/**
		 * Amount of time the query took to run.
		 * (Set by the Ember database connector when in debug mode)
		 * @var NULL|Number
		 */
		private $query_execution_time = NULL;
		/**
		 * When in debug mode, this will store the bound query after getSQL() is called.
		 * @var NULL|String
		 */
		private $bound_query = NULL;

		/**
		 * Creates an SQL object with $sql query.
		 * You probably want SQL::bind() in most cases.
		 * @param String $sql SQL query
		 */
		function __construct( $sql )
		{
			$this->sql = trim($sql);
		}

		/**
		 *
		 * @param String $sql
		 * @param Mixed N additional parameters to be bound into $sql
		 * @return SQL
		 */
		static public function bind( $sql )
		{
			$q = new SQL( $sql );

			$argc = func_num_args();
			if( $argc > 1 )
			{
				$args = func_get_args();
				array_shift( $args ); // we don't want the $sql query, just the args to bind
				$q->bindArray( $args );
			}

			return $q;
		}

		/**
		 * Bind N values (accepts n arguments)
		 * @param Mixed N arguments
		 */
		public function bindVal()
		{
			foreach( func_get_args() as $arg )
				$this->args[] = $arg;
		}

		/**
		 * Bind value to specific position in query
		 * @param uInt position
		 * @param Mixed value
		 * @throws Exception( SQL::ERR_EXPECTED_UINT )
		 */
		public function bindPosition( $pos, $val )
		{
			$pos = intval($pos);
			if( $pos < 0 )
				throw new Exception( SQL::ERR_EXPECTED_UINT );
			$this->args[$pos] = $val;
		}

		/**
		 * Bind an array of values, in order, to the next available bind positions.
		 * Keys in the array are ignored.
		 * @param Array $args values
		 * @throws Exception( SQL::ERR_EXPECTED_ARRAY )
		 */
		public function bindArray( $args )
		{
			if( !is_array( $args ) )
				throw new Exception( SQL::ERR_EXPECTED_ARRAY );

			foreach( $args as $a )
				$this->args[] = $a;
		}

		/**
		 * Clears all values set to bind into query.
		 * (Useful when reusing a SQL object for multiple queries to the db)
		 */
		public function bindClear()
		{
			$this->args = array();
		}

		/**
		 * Capture the number of rows found for this query (automatically using SQL_CALC_FOUND_ROWS)
		 * @param Bool
		 * @see SQL::willCalcFoundRows()
		 */
		public function setCalcFoundRows( $calc_found_rows = TRUE )
		{
			$this->calc_found_rows = $calc_found_rows;
		}

		/**
		 * Whether this object will calculate (and cash) the result of SELECT SQL_CALC_FOUND_ROWS ...
		 * @return Bool
		 * @see SQL::setCalcFoundRows()
		 */
		public function willCalcFoundRows()
		{
			return $this->calc_found_rows;
		}

		/**
		 * Returns result from SELECT FOUND_ROWS() call
		 * @return uInt
		 */
		public function getFoundRows()
		{
			if( !$this->calc_found_rows )
				throw new Exception( SQL::ERR_CALC_FOUND_ROWS_DISABLED );

			return intval( $this->found_rows );
		}

		/**
		 * Used by DB class as a callback to pass back the result of SQL_CALC_FOUND_ROWS.
		 * Do not usre. This is used internally by the Ember framework.
		 * @param uInt $foundrows
		 * @access private
		 */
		public function setFoundRowsCallback( $foundrows )
		{
			$this->found_rows = $foundrows;
		}

		/**
		 * Hook for DB class to record query execution time in this object.
		 * Do not use. It is used internally by the Ember framework.
		 * @param Number $t
		 * @access private
		 */
		public function recordQueryTimeHook( $t )
		{
			$this->query_execution_time = $t;
		}

		/**
		 * Returns the bound query.
		 * Note that every time this method is called it will perform the bind again, so
		 * do not call this repeatedly unless that's actually what you want.
		 * @return String
		 * @throws Exception( SQL::ERR_ARG_COUNT )
		 */
		public function getSQL()
		{
			$sql = $this->sql;;

			if( preg_match_all('/\?([A-Za-z])([A-Za-z])?/', $sql, $matches, PREG_SET_ORDER|PREG_OFFSET_CAPTURE) )
			{
				$arg_count = count($matches);
				if( count($this->args) != $arg_count )
					throw new Exception(
						str_replace( '%expected%', $arg_count,
						str_replace( '%received%', count($this->args),
						SQL::ERR_ARG_COUNT
					)));

				// Loop backward to preserve positions in the string
				for( $i=$arg_count-1; $i >= 0; $i-- )
				{
					if( !array_key_exists($i,$this->args) )
						throw new Exception( str_replace( '%pos%', $i, SQL::ERR_EXPECTED_ARG_AT_POS ) );

					$arg = $this->args[$i];
					$type = $matches[$i][1][0];
					$modifier = isset($matches[$i][2][0]) ? $matches[$i][2][0] : NULL;

					$arg = SQL::dataBind( $type, $arg, $modifier, 0 );

					$sql = substr_replace( $sql, $arg, $matches[$i][0][1], strlen($matches[$i][0][0]) );
				}
			}

			// $sql contains the bound query at this point

			if( $this->calc_found_rows )
			{
				// User requested the SQL_CALC_FOUND_ROWS result from the DB
				if( FALSE === strpos( strtoupper( $sql ), 'SQL_CALC_FOUND_ROWS' ) )
				{
					if( 'SELECT' == strtoupper( substr( $sql, 0, 6 ) ) )
						$sql = 'SELECT SQL_CALC_FOUND_ROWS' . substr( $sql, 6, strlen($sql)-6 );
				}
				$sql = trim($sql);
				if( FALSE === strpos( strtoupper( $sql ), 'SELECT FOUND_ROWS()' ) )
				{
					if (substr($sql, -1) != ';')
						$sql .= '; ';
					$sql .= 'SELECT FOUND_ROWS();';
				}
			}

			if( Debug::isEnabled() )
				$this->bound_query = $sql;

			return $sql;
		}

		/**
		 * Magically turn this object into the bound query it represents
		 * @return String
		 */
		public function __toString()
		{
			return $this->getSQL();
		}

		/**
		 * @param String $type
		 * @param Mixed $arg
		 * @param String|NULL $modifier
		 * @param Mixed $default
		 * @return Mixed
		 */
		static public function dataBind( $type, $arg, $modifier=NULL, $default = 0, $params=NULL )
		{
			$escape_style = SQL::ESCAPE_STYLE_MYSQL;

//TODO: breakout params

			switch( $type{0} )
			{
				case 'f': // mysql fieldname
//TODO: Can we autodetect mysql and sqlite from PDO when called from the DB class?
					$fieldname_parts = explode( '.', $arg );
					if( count($fieldname_parts) <= 0 || count($fieldname_parts) > 3 )
						throw new Exception( str_replace( '%fieldname%', $arg, SQL::ERR_INVALID_SQL_FIELD ) );

					foreach( $fieldname_parts as &$fp )
					{
						// escape backticks (for mysql)
						if( $escape_style == SQL::ESCAPE_STYLE_MYSQL )
							$fp = str_replace( '`', '``', $fp );
//TODO: Are there any other valid characters for a fieldname?
						if( 1 != preg_match( '/^[A-Za-z_`][A-Za-z0-9_`]*$/', $fp ) || strlen($fp) > SQL::SQLFIELD_MAX_LEN )
							throw new Exception( str_replace( '%fieldname%', $arg, SQL::ERR_INVALID_SQL_FIELD ) );
						switch( $escape_style )
						{
							case SQL::ESCAPE_STYLE_MYSQL:
								$fp = '`' . $fp . '`';
								break;
							case SQL::ESCAPE_STYLE_SQLITE:
								$fp = '"' . $fp . '"';
								break;
							default:
								throw new Exception( SQL::ERR_INTERNAL . ' ' . __LINE__ );
						}
					}
					$arg = implode( '.', $fieldname_parts );
					break;
				case 'd': // mysql datetime (accepts unix timestamp or MySQL DATETIME)
					if( ($modifier == 'n' && $arg === NULL) || ($modifier == 'N' && !$arg) )
					{
						$arg = 'NULL';
						break;
					}

					if( (String) intval($arg) == $arg )
						$arg = date( 'Y-m-d H:i:s', $arg );

					// YYYY-mm-dd HH:ii:ss
					if( 1 != preg_match( '/^([1-3][0-9]{3,3})-(0?[1-9]|1[0-2])-(0?[1-9]|[1-2][0-9]|3[0-1])\s([0-1][0-9]|2[0-4]):([0-5][0-9]):([0-5][0-9])$/', $arg ) )
					{
						// YYYY-mm-dd
						if( 1 != preg_match( '/^([1-3][0-9]{3,3})-(0?[1-9]|1[0-2])-(0?[1-9]|[1-2][0-9]|3[0-1])$/', $arg ) )
							throw new Exception( SQL::ERR_INVALID_DATETIME );
						$arg = SQL::stringEscape( $arg, $escape_style );
						break; // validated (YYYY-MM-DD)
					}
					$arg = SQL::stringEscape( $arg, $escape_style );
					break; // validated (YYYY-mm-dd HH:ii:ss)
				case 'h': // htmlspecialchars
					if( $arg )
						$arg = htmlspecialchars( $arg );
				case 's': // string
					$arg = ($modifier == 'n' && $arg === NULL) || ($modifier == 'N' && !$arg) ? 'NULL' : SQL::stringEscape( $arg, $escape_style );
					break;
				case 'i': // signed integer
					if( ($modifier == 'n' && $arg === NULL) || ($modifier == 'N' && !$arg) )
						$arg = 'NULL';
					else
					{
						$tmp = intval($arg);
						if( (string) $tmp == (string) $arg )
							$arg = $tmp;
						else if( PHP_VERSION_ID >= 50100 ) // need 5.0.5, but let's assume 5.1
						{
//TODO: optimize this check by caching it as a const when this file is loaded
							if( strlen($arg) > strlen(PHP_INT_MAX)-1 )
							{
								if( 1 != preg_match( '/^[-]{0,1}[0-9]+$/', (String) $arg ) )
								{
									if( $default === FALSE )
										throw new Exception( str_replace( '%arg%', $arg, SQL::ERR_BIND_REQUIRED_INT ) );
									else
										$arg = $default;
								}
							}
							else
							{
								if( $default === FALSE )
									throw new Exception( str_replace( '%arg%', $arg, SQL::ERR_BIND_REQUIRED_INT ) );
								else
									$arg = $default;
							}
						}
						else
							throw new Exception( SQL::ERR_INTERNAL . ' ' . __LINE__ );
					}
					break;
				case 'u': // unsigned integer
					if( ($modifier == 'n' && $arg === NULL) || ($modifier == 'N' && !$arg) )
						$arg = 'NULL';
					else
					{
						$tmp = intval($arg);
						if( (string) $tmp == (string) $arg && $tmp > 0 )
							$arg = $tmp;
						else if( PHP_VERSION_ID >= 50100 ) // need 5.0.5, but let's assume 5.1
						{
							if( strlen($arg) > strlen(PHP_INT_MAX)-1 )
							{
								if( 1 != preg_match( '/^[0-9]+$/', (String) $arg ) )
								{
									if( $default === FALSE )
										throw new Exception( str_replace( '%arg%', $arg, SQL::ERR_BIND_REQUIRED_INT ) );
									else
										$arg = $default;
								}
							}
							else
							{
								if( $default === FALSE )
									throw new Exception( str_replace( '%arg%', $arg, SQL::ERR_BIND_REQUIRED_INT ) );
								else
									$arg = $default;
							}
						}
						else
							throw new Exception( SQL::ERR_INTERNAL . ' ' . __LINE__ );
					}
					break;
				case 'a': // array (for use with IN)
					if( ($modifier == 'n' && $arg === NULL) || ($modifier == 'N' && !$arg) )
					{
						// this covers bad data, false/null data, and also empty arrays
						$arg = '(NULL)';
						break;
					}
					if( !is_array( $arg ) )
						throw new Exception( SQL::ERR_EXPECTED_ARRAY );
					foreach( $arg as &$arg_element )
						$arg_element = SQL::stringEscape( htmlspecialchars($arg_element), $escape_style );
					$arg = '(' . implode( ',', $arg ) . ')';
					break;
				case 'r': // real number
					if( ($modifier == 'n' && $arg === NULL) || ($modifier == 'N' && !$arg) )
						$arg = 'NULL';
					else
					{
						if( 1 != preg_match( '/^[\-]?[0-9]*[\.]?[0-9]*([eE][\-]?[0-9]*)?$/', (String) $arg ) )
						{
							if( $default === FALSE )
								throw new Exception( str_replace( '%arg%', $arg, SQL::ERR_BIND_REQUIRED_REAL ) );
							else
								$arg = $default;
						}
					}
					break;
				case 'z': // serialized data
					$arg = ($modifier == 'n' && $arg === NULL) || ($modifier == 'N' && !$arg) ? 'NULL' : SQL::stringEscape( @serialize($arg), $escape_style );
					break;
				default:
					throw new Exception( str_replace( '%type%', $type, SQL::ERR_UNKNOWN_DATATYPE ) );
			}
		
			return $arg;
		}

		/**
		 * Escape a string in the appropriate format
		 * @param String $arg (String to escape)
		 * @param String $escape_style (SQL::ESCAPE_STYLE_MYSQL or SQL::ESCAPE_STYLE_SQLITE)
		 */
		static public function stringEscape( $arg, $escape_style )
		{
			switch( $escape_style )
			{
				case SQL::ESCAPE_STYLE_MYSQL:
					return '"' . addslashes( (String) $arg ) . '"';
				case SQL::ESCAPE_STYLE_SQLITE:
					// EMBER_SQLCLASS_SQLITE_DRIVER is defined at the end of this file
					switch( EMBER_SQLCLASS_SQLITE_DRIVER )
					{
						case 'sqlite3':
							return '\'' . SQLite3::escapeString( (String) $arg ) . '\'';
						case 'sqlite':
							return '\'' . sqlite_escape_string( (String) $arg ) . '\'';
						default:
							throw new Exception( SQL::ERR_NO_SQLITE_DRIVER );
					}
					break;
				default:
					throw new Exception( str_replace( '%style%', $escape_style, SQL::ERR_INVALID_ESCAPE_STYLE ) );
			}
		}
	}

// define the EMBER_SQLCLASS_SQLITE_DRIVER constant for stringEscape()
if( extension_loaded('sqlite3') )
	define( 'EMBER_SQLCLASS_SQLITE_DRIVER', 'sqlite3' );
else if( extension_loaded('sqlite') )
	define( 'EMBER_SQLCLASS_SQLITE_DRIVER', 'sqlite' );
else
	define( 'EMBER_SQLCLASS_SQLITE_DRIVER', 'none' );

if( !defined('PHP_VERSION_ID') )
{
	$version = explode('.', PHP_VERSION);
	define('PHP_VERSION_ID', ($version[0] * 10000 + $version[1] * 100 + $version[2]));
}

