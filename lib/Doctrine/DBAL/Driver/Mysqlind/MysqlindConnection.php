<?php

namespace Doctrine\DBAL\Driver\Mysqlind;

use Doctrine\DBAL\Driver\Connection;
use Doctrine\DBAL\Driver\PingableConnection;
use Doctrine\DBAL\Driver\ServerInfoAwareConnection;

class MysqlindConnection implements Connection, PingableConnection, ServerInfoAwareConnection
{
	/**
	 * Name of the option to set connection flags
	 */
	const OPTION_FLAGS = 'flags';

	/**
	 * @var \mysqli
	 */
	private $conn;

	public function __construct(array $params, $username, $password, array $driverOptions = array())
	{
		$port = isset($params['port']) ? $params['port'] : ini_get('mysqli.default_port');

		// Fallback to default MySQL port if not given.
		if ( ! $port) {
			$port = 3306;
		}

		$socket = isset($params['unix_socket']) ? $params['unix_socket'] : ini_get('mysqli.default_socket');
		$dbname = isset($params['dbname']) ? $params['dbname'] : null;

		$flags = isset($driverOptions[static::OPTION_FLAGS]) ? $driverOptions[static::OPTION_FLAGS] : null;

		$this->conn = mysqli_init();

		$this->setDriverOptions($driverOptions);

		$previousHandler = set_error_handler(function () {});
		$connected = $this->conn->real_connect($params['host'], $username, $password, $dbname, $port, $socket, $flags);
		set_error_handler($previousHandler);

		if ( ! $connected) {
			$sqlState = 'HY000';
			if (@$this->conn->sqlstate) {
				$sqlState = $this->conn->sqlstate;
			}

			throw new MysqlindException($this->conn->connect_error, $sqlState, $this->conn->connect_errno);
		}

		if (isset($params['charset'])) {
			$this->conn->set_charset($params['charset']);
		}
	}

	public function prepare($prepareString)
	{
		return new MysqlindStatement($this->conn, $prepareString);
	}

	public function query() {
		$args = func_get_args();
		$sql = $args[0];
		$stmt = $this->prepare($sql);
		$stmt->execute();
		return $stmt;
	}

	public function quote( $input, $type = \PDO::PARAM_STR )
	{
		return "'". $this->conn->escape_string($input) ."'";
	}

	public function exec( $statement )
	{
		if (false === $this->conn->query($statement)) {
			throw new MysqlindException($this->conn->error, $this->conn->sqlstate, $this->conn->errno);
		}

		return $this->conn->affected_rows;
	}

	function lastInsertId( $name = null )
	{
		throw new \Exception('TODO');
		// TODO: Implement lastInsertId() method.
	}

	public function beginTransaction()
	{
		throw new \Exception('TODO');
		// TODO: Implement beginTransaction() method.
	}

	public function commit()
	{
		throw new \Exception('TODO');
		// TODO: Implement commit() method.
	}

	public function rollBack()
	{
		throw new \Exception('TODO');
		// TODO: Implement rollBack() method.
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

	public function ping()
	{
		throw new \Exception('TODO');
		// TODO: Implement ping() method.
	}

	public function getServerVersion()
	{
		$majorVersion = floor($this->conn->server_version / 10000);
		$minorVersion = floor(($this->conn->server_version - $majorVersion * 10000) / 100);
		$patchVersion = floor($this->conn->server_version - $majorVersion * 10000 - $minorVersion * 100);

		return $majorVersion . '.' . $minorVersion . '.' . $patchVersion;
	}

	public function requiresQueryForServerVersion()
	{
		return false;
	}

	private function setDriverOptions(array $driverOptions = array())
	{
		$supportedDriverOptions = array(
			\MYSQLI_OPT_CONNECT_TIMEOUT,
			\MYSQLI_OPT_LOCAL_INFILE,
			\MYSQLI_INIT_COMMAND,
			\MYSQLI_READ_DEFAULT_FILE,
			\MYSQLI_READ_DEFAULT_GROUP,
		);

		if (defined('MYSQLI_SERVER_PUBLIC_KEY')) {
			$supportedDriverOptions[] = \MYSQLI_SERVER_PUBLIC_KEY;
		}

		$exceptionMsg = "%s option '%s' with value '%s'";

		foreach ($driverOptions as $option => $value) {

			if ($option === static::OPTION_FLAGS) {
				continue;
			}

			if (!in_array($option, $supportedDriverOptions, true)) {
				throw new MysqliException(
					sprintf($exceptionMsg, 'Unsupported', $option, $value)
				);
			}

			if (@mysqli_options($this->conn, $option, $value)) {
				continue;
			}

			$msg  = sprintf($exceptionMsg, 'Failed to set', $option, $value);
			$msg .= sprintf(', error: %s (%d)', mysqli_error($this->conn), mysqli_errno($this->conn));

			throw new MysqliException(
				$msg,
				$this->conn->sqlstate,
				$this->conn->errno
			);
		}
	}
}