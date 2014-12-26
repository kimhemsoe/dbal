<?php

namespace Doctrine\DBAL\Driver\Mysqlind;

use Doctrine\DBAL\Driver\Statement;
use PDO;

class MysqlindStatement implements \IteratorAggregate, Statement
{
	/**
	 * @var array
	 */
	private static $paramTypeMap = array(
		PDO::PARAM_STR => 's',
		PDO::PARAM_BOOL => 'i',
		PDO::PARAM_NULL => 's',
		PDO::PARAM_INT => 'i',
		PDO::PARAM_LOB => 's'
	);

	private static $fetchModeMap = array(
		PDO::FETCH_ASSOC => MYSQLI_ASSOC,
		PDO::FETCH_NUM => MYSQLI_NUM,
		PDO::FETCH_BOTH => MYSQLI_BOTH,
	);

	private $conn;

	private $stmt;

	protected $defaultFetchMode = PDO::FETCH_BOTH;

	private $types = '';

	private $boundValues;

	private $values = array();

	/**
	 * @var \mysqli_result
	 */
	private $result = null;

	public function __construct(\mysqli $conn, $prepareString)
	{
		$this->conn = $conn;
		$this->stmt = $conn->prepare($prepareString);
		if (false === $this->stmt) {
			throw new MysqlindException($this->conn->error, $this->conn->sqlstate, $this->conn->errno);
		}

		$paramCount = $this->stmt->param_count;
		if (0 < $paramCount) {
			$this->types = str_repeat('s', $paramCount);
			$this->boundValues = array_fill(1, $paramCount, null);
		}
	}

	public function getIterator()
	{
		$data = $this->fetchAll();

		return new \ArrayIterator($data);
	}

	public function closeCursor()
	{
		$this->stmt->free_result();

		return true;
	}

	public function columnCount()
	{
		throw new \Exception('TODO');
		// TODO: Implement columnCount() method.
	}

	public function setFetchMode($fetchMode, $arg2 = null, $arg3 = null )
	{
		$this->defaultFetchMode = $fetchMode;
	}

	public function fetch($fetchMode = null)
	{
		if ( ! $this->result) {
			throw new MysqlindException("No result");
		}

		if (null === $fetchMode) {
			$fetchMode = $this->defaultFetchMode;
		}

		if (isset(self::$fetchModeMap[$fetchMode])) {
			$realFetchMode = self::$fetchModeMap[$fetchMode];
			return $this->result->fetch_array($realFetchMode);
		} elseif ($fetchMode == PDO::FETCH_CLASS) {
			return $this->result->fetch_object();
		} else {
			throw new MysqlindException( "Unknown fetch type '{$fetchMode}'" );
		}
	}

	public function fetchAll($fetchMode = null)
	{
		if (!$this->result) {
			return array();
		}

		if ($fetchMode === null) {
			$fetchMode = $this->defaultFetchMode;
		}

		if (isset(self::$fetchModeMap[$fetchMode])) {
			$realFetchMode = self::$fetchModeMap[$fetchMode];
			return $this->result->fetch_all($realFetchMode);
		} elseif ($fetchMode == PDO::FETCH_CLASS) {
			$rows = array();
			while ($o = $this->result->fetch_object()) {
				$rows[] = $o;
			}
			return $rows;
		} elseif ($fetchMode == PDO::FETCH_COLUMN) {
			$rows = array();
			while ($row = $this->result->fetch_row()) {
				$rows[] = $row[0];
			}
			return $rows;
		} else {
			throw new MysqlindException( "Unknown fetch type '{$fetchMode}'" );
		}
	}

	public function fetchColumn($columnIndex = 0)
	{
		if (!$this->result) {
			throw new MysqlindException("No result");
		}

		$row = $this->result->fetch_row();
		if ($row) {
			return $row[$columnIndex];
		}
		return false;
	}

	public function bindValue($column, $value, $type = null)
	{
		var_dump(__METHOD__);

		if (null === $type) {
			$type = 's';
		} else {
			if (isset(self::$paramTypeMap[$type])) {
				$type = self::$paramTypeMap[$type];
			} else {
				throw new MysqlindException("Unknown type: '{$type}'");
			}
		}

		$this->values[$column] = $value;
		$this->types[$column - 1] = $type;
		$this->boundValues[$column] =& $this->values[$column];
	}

	public function bindParam($column, &$variable, $type = null, $length = null )
	{
		var_dump(__METHOD__);

		if (null === $type) {
			$type = 's';
		} else {
			if (isset(self::$paramTypeMap[$type])) {
				$type = self::$paramTypeMap[$type];
			} else {
				throw new MysqlindException("Unknown type: '{$type}'");
			}
		}

		$this->types[$column - 1] = $type;
		$this->boundValues[$column] =& $variable;
	}

	public function errorCode()
	{
		throw new \Exception('TODO');
		// TODO: Implement errorCode() method.
	}

	public function errorInfo()
	{
		throw new \Exception('TODO');
		// TODO: Implement errorInfo() method.
	}


	public function execute($params = null)
	{
		var_dump($this->types, $this->boundValues, $params);

		if (null !== $this->boundValues) {
			if (null === $params) {
				if ( ! $this->stmt->bind_param($this->types, ...$this->boundValues)) {
					throw new MysqlindException($this->_stmt->error, $this->_stmt->sqlstate, $this->_stmt->errno);
				}
			} else {
				var_dump('with_params');
				$ret = $this->bindValues($params);
				var_dump($ret);

				if ( ! $ret) {
					throw new MysqlindException($this->stmt->error, $this->stmt->sqlstate, $this->stmt->errno);
				}
			}
		}

		$ret = $this->stmt->execute();
		var_dump($ret);
		if ( ! $ret) {
			throw new MysqlindException($this->stmt->error, $this->stmt->sqlstate, $this->stmt->errno);
		}

		// if false we have an error or no result... This is also an issue for mysqli driver.
		// TODO detect error.
		$this->result = $this->stmt->get_result();

		return true;
	}

	public function rowCount()
	{
		if ($this->result) {
			return $this->result->num_rows;
		}
		return $this->stmt->affected_rows;
	}

	private function bindValues($params)
	{
		$types = str_repeat('s', count($params));

		return $this->stmt->bind_param($types, ...$params);
	}
}