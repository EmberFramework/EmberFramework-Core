<?php

	class DBConnector
	{
		const ERR_CANNOT_CONNECT		= 'Cannot connect to DB';
		const ERR_EXPECTED_SQL_OBJ		= 'Expected object of type SQL';


		public $pdo;
		private $tz;
		private $dsn;
		private $user;
		private $pass;
		private $connected = FALSE;

		public function __construct( $dsn, $user = '', $pass = '' )
		{
			$this->connected = FALSE;
			$this->tz = DB::DEFAULT_TZ;
			$this->dsn = $dsn;
			$this->user = $user;
			$this->pass = $pass;
		}

		private function con()
		{
			if( !$this->connected )
				$this->connect();
		}

		public function connect()
		{
			if( $this->connected ) return TRUE;

			try {
				// Without this check, PDO seems to lose 216 bytes or so
				// of memory on failure of connect... -mpelmear
				if( $this->dsn == '' )
					throw new Exception( DBConnector::ERR_CANNOT_CONNECT );
				$this->pdo = new PDO( $this->dsn, $this->user, $this->pass );
			} catch( PDOException $pdo ) {
				throw new Exception( DBConnector::ERR_CANNOT_CONNECT );
			}

			$this->pdo->exec( 'SET time_zone="' . $this->tz . '"' );
			$this->connected = TRUE;

			return TRUE;
		}

		public function disconnect()
		{
			$this->pdo = NULL;
			$this->connected = FALSE;
		}

		/**
		 * Sets connection TimeZone.
		 * @param String $tz MySQL-compatible timezone
		 */
		public function setTZ( $tz )
		{
			$this->tz = $tz;
			if( $this->connected )
				$this->pdo->exec( 'SET time_zone="' . $this->tz . '"' );
		}

		/**
		 * @param SQL $sql
		 * @param uInt $col (optional, defaults to 0)
		 * @return Array
		 * @throws PDOException
		 */
		public function getCol( $sql, $col = 0 )
		{
			$this->con();
			$q = $this->_query( $sql );

			$ret = $q->fetchAll(PDO::FETCH_COLUMN, $col);
			if ($sql->willCalcFoundRows() && $q->nextRowset())
				$sql->setFoundRowsCallback($q->fetchColumn());
			return $ret;
		}

		/**
		 * @param SQL
		 * @return Array
		 * @throws PDOException
		 */
		public function getAll( $sql )
		{
			$this->con();
			$s = $this->_query( $sql );

			$ret = $s->fetchAll(PDO::FETCH_ASSOC);
			if ($sql->willCalcFoundRows() && $s->nextRowset())
				$sql->setFoundRowsCallback($s->fetchColumn());
			return $ret;
		}

		/**
		 * @param SQL
		 * @return Array
		 * @throws PDOException
		 */
		public function getRow( $sql )
		{
			$this->con();
			$q = $this->_query( $sql );

			$ret = $q->fetch(PDO::FETCH_ASSOC);
			if ($sql->willCalcFoundRows() && $q->nextRowset())
				$sql->setFoundRowsCallback($q->fetchColumn());
			return $ret;
		}

		/**
		 * If there are two columns in the resultset, return a k=>v array
		 * with col1 as key, col2 as value.
		 * If there are more than two columns in the resultset,
		 * return a k=>v array where col1 is the key and the
		 * remainder of the columns stay in an array as the value.
		 * @param SQL
		 * @return Array
		 * @throws PDOException
		 */
		public function getAssoc( $sql )
		{
			$this->con();
			$sth = $this->_query( $sql );

			if( !is_object( $sth ) )
				$this->throwError();

			$rs = array();

			if( $sth->columnCount() == 2 )
			{
				while ($row = $sth->fetch(PDO::FETCH_ASSOC))
					$rs[array_shift($row)] = array_shift($row);
			}
			else
			{
				while ($row = $sth->fetch(PDO::FETCH_ASSOC))
					$rs[array_shift($row)] = $row;
			}
			if ($sql->willCalcFoundRows() && $sth->nextRowset())
				$sql->setFoundRowsCallback($sth->fetchColumn());
			return $rs;
		}

		/**
		 * Returns the first column from the first row of the result set.
		 * @param SQL
		 * @return Mixed
		 * @throws PDOException
		 */
		public function getOne( $sql )
		{
			$this->con();
			$sth = $this->_query( $sql );

			$ret = $sth ? $sth->fetchColumn() : NULL;
			if ($sql->willCalcFoundRows() && $sth->nextRowset())
				$sql->setFoundRowsCallback($sth->fetchColumn());
			return $ret;
		}

		/**
		 * Use for buffered queries and non-SELECT statements.
		 * Returns DBRecordSet for SELECT statements,
		 * Returns uInt (number of rows affected) for other statements.
		 * @param SQL
		 * @return DBRecordSet|uInt
		 * @throws PDOException
		 */
		public function query( $sql )
		{
			$this->con();

			$q = $sql->getSQL();
			if( strtoupper(substr(ltrim($q), 0, 6)) == 'SELECT' )
			{
				$res = $this->_query( $sql );
				return new DBRecordSet( $res );
			}
			else
				return $this->_exec( $sql );
		}

		/**
		 * Use only for statements that only return number of rows affected.
		 * (INSERT, UPDATE, etc.)
		 * @param SQL
		 * @return uInt (Number of rows affected)
		 * @throws PDOException
		 */
		public function exec( $sql )
		{
			$this->con();
			return $this->_exec( $sql );
		}

		/**
		 * Internally execute various types of query calls.
		 * @param SQL $sql
		 * @param String $query_or_exec (either 'exec' or 'query')
		 */
		private function _query( $sql, $query_or_exec='query' )
		{
			$bind_style_params = $this->prepareBindStyleParams();

			if( $query_or_exec == 'exec' )
				$r = $this->pdo->exec( $sql->getSQL( $bind_style_params ) );
			else
				$r = $this->pdo->query( $sql->getSQL( $bind_style_params ) );

			if( $r === FALSE )
			{
				// There was an error. See what to do
				$error_info = $this->pdo->errorInfo();
				if( $error_info[0] == 'HY000' && $error_info[1] == '2006' )
				{
					// HY000/2006 seems to be the error we get when we lost our connection.
					// Try to reconnect and re-run the query.
					$this->disconnect();
					$this->connect();

					if( $query_or_exec == 'exec' )
						$r = $this->pdo->exec( $sql->getSQL( $bind_style_params ) );
					else
						$r = $this->pdo->query( $sql->getSQL( $bind_style_params ) );

					if( $r === FALSE )
						$this->throwError();
				}
				else
					$this->throwError();
			}

			return $r;
		}

		private function _exec( $sql )
		{
			return $this->_query( $sql, 'exec' );
		}

		private function prepareBindStyleParams()
		{
			$bind_style_params = array();
			switch( $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME) )
			{
				case 'mysql':
					$bind_style_params = array( SQL::PARAM_ESCAPE_STYLE => SQL::ESCAPE_STYLE_MYSQL );
					break;
				case 'sqlite':
					$bind_style_params = array( SQL::PARAM_ESCAPE_STYLE => SQL::ESCAPE_STYLE_SQLITE );
					break;
				default:
					throw new Exception( 'Unsupported database driver: ' . $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME) );
			}

			return $bind_style_params;
		}

		/**
		 * Verifies that $sql is a valid SQL object.
		 * @param Mixed $sql
		 * @throws Exception( DBConnector::ERR_EXPECTED_SQL_OBJ )
		 */
		public static function checkSQLobject( $sql )
		{
			if( !is_object( $sql ) )
				throw new Exception( DBConnector::ERR_EXPECTED_SQL_OBJ );
			if( get_class( $sql ) != 'SQL' )
				throw new Exception( DBConnector::ERR_EXPECTED_SQL_OBJ );
		}

		/**
		 * Gathers PDO exception data an throws an exception
		 * @throws PDOException
		 */
		private function throwError()
		{
//TODO: Probably don't need to htmlspecialchars here... maybe that should happen on the UI layer?
			$error = $this->pdo->errorInfo();
			throw new PDOException($error[0].' ('.$error[1].'): '.htmlspecialchars($error[2]));
		}

		/**
		 * @return String Last insert ID for the current DB
		 */
		public function getLastInsertID()
		{
			return $this->pdo->lastInsertId();
		}
	}

