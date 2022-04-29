<?php

namespace LuisaeDev\QueryBuilder;

use PDO;
use PDOException;
use DateTime;
use DateTimeZone;
use LuisaeDev\SimplePDO\SimplePDO;

/**
 * Connect with SQL databases and perform complex SQL queries without define SQL statements manually.
 * 
 * @property-read string $dbname
 * @property-read string $driver
 * @property-read string $host
 * @property-read int    $port
 */
class QueryBuilder {

	/** @var SimplePDO Connection to interact with the database */
	private $sPDO = null;

	/** @var string SQL operation to perform for the current query: SELECT, INSERT, UPDATE, DELETE ...  */
	private $operation = null;

	/** @var string Table name defined for the current query */
	private $table = null;

	/** @var array Store the columns that will be used for the current query */
	private $columns = array();

	/** @var array Store the parameters that will be binded to the current query */
	private $params = array();

	/** @var array Store FROM clause */
	private $from = array();

	/** @var array Store WHERE groups */
	private $where = array();

	/** @var array Store GROUP BY statements */
	private $group = array();

	/** @var string Store HAVING clause */
	private $having = '';

	/** @var array Store ORDER BY keywords */
	private $order = array();

	/** @var array Store LIMIT clause */
	private $limit = array();

	/** @var string Store the query defined by 'query' method */
	private $query= '';

	/** @var array Store all table structures obtained by 'describe' method */
	private static $tableSchemas = [];

	/** @var array Store all global connections data  */
	private static $connectionsData = [];

	/**
	 * Constructor.
	 *
	 * @param string|array|SimplePDO $connectionData Array or string connection data for create a SimplePDO instance, or a SimplePDO instance as well
	 */
	public function __construct(mixed $connectionData)
	{
		$this->connect($connectionData);
	}

	/**
	 * Magic __get method.
	 */
	public function __get($property)
	{
		if (is_callable(array($this, $method = 'get_' . $property))) {
			return $this->$method();
		} else {
			return null;
		}
	}
	/**
	 * Magic __clone method.
	 */
	public function __clone()
	{
		$this->reset();
		$this->pdoStm = null;
	}

	/**
	 * Start a transaction.
	 *
	 * Disable the 'autocommit' mode
	 *
	 * @return SimplePDO Self instance for chain
	 */
	public function beginTransaction()
	{
		$this->sPDO->beginTransaction();
		return $this;
	}

	/**
	 * Commit the current transaction.
	 *
	 * Commit the transaction and enable 'autocommit' mode
	 * 
	 * @return SimplePDO Self instance for chain
	 */
	public function commit()
	{
		$this->sPDO->commit();
		return $this;
	}

	/**
	 * RollBack the current transaction.
	 *
	 * RollBack the transaction and enable 'autocommit' mode
	 *
	 * @return SimplePDO Self instance for chain
	 */
	public function rollBack()
	{
		$this->sPDO->rollBack();
		return $this;
	}

	/**
	 * Indicate the autocommit state.
	 *
	 * @return bool
	 */
	public function isAutocommit()
	{
		return $this->sPDO->isAutocommit();
	}

	/**
	 * Establish a SimplePDO connection for self instance use.
	 *
	 * @param string|array|SimplePDO $connectionData Array or string connection data for create a SimplePDO instance, or a SimplePDO instance as well
	 *
	 * @return QueryBuilder Self instance for chain
	 */
	public function connect(mixed $connectionData)
	{

		// Reset properties
		$this->reset();

		// If connection is a SimplePDO instance
		if ($connectionData instanceof \LuisaeDev\SimplePDO\SimplePDO) {
			$this->sPDO = $connectionData;

		} else {

			// If the connection data is a string, check first if exists a global connection data with that alias name
			if ((is_string($connectionData)) && (self::connectionDataExists($connectionData))) {
				$connectionData = self::getConnectionData($connectionData);
			}

			// Create a new SimplePDO instance
			$this->sPDO = new SimplePDO($connectionData);
		}

		return $this;
	}

	/**
	 * Start a INSERT query.
	 *
	 * @param string $table   Table's name
	 * @param array  $columns Array of columns to set
	 *
	 * @return QueryBuilder Self instance for chain
	 */
	public function insert(string $table, $columns = array())
	{

		// Reset properties
		$this->reset();

		// Set the current operation
		$this->operation = 'INSERT';

		// Set the table name
		$this->table = $table;

		// Save the columns and them values
		foreach ($columns as $columnName => $colValue) {
			$this->addColumn($columnName, $colValue);
		}

		return $this;
	}
	
	/**
	 * Start a UPDATE query.
	 *
	 * @param string $table   Table's name
	 * @param array  $columns Array of columns to update
	 *
	 * @return QueryBuilder Self instance for chain
	 */
	public function update(string $table, array $columns = array())
	{

		// Reset properties
		$this->reset();

		// Set the current operation
		$this->operation = 'UPDATE';

		// Set the table name
		$this->table = $table;

		// Save the columns and them values
		foreach ($columns as $columnName => $colValue) {
			$this->addColumn($columnName, $colValue);
		}

		return $this;
	}

	/**
	 * Start a DELETE query.
	 *
	 * @param string $table Nombre de la tabla
	 *
	 * @return QueryBuilder Self instance for chain
	 */
	public function delete(string $table)
	{

		// Reset properties
		$this->reset();

		// Set the current operation
		$this->operation = 'DELETE';

		// Set the table name
		$this->table = $table;

		return $this;
	}

	/**
	 * Start a SELECT query.
	 *
	 * @param string|array $columns Array of columns to get
	 * @param string|null  $table   Table name
	 *
	 * @return QueryBuilder Self instance for chain
	 */
	public function select(mixed $columns, string $table = null)
	{

		// Reset properties
		$this->reset();

		// Set the current operation
		$this->operation = 'SELECT';

		// Set the columns for the query
		if (is_array($columns)) {

			// Check the entire array, if is associative, the keyname is the column name, and its value will represent the alias name
			foreach ($columns as $key => $column) {
				if (is_string($key)) {
					$this->columns[] = $key . ' AS ' . $column;
				} else {
					$this->columns[] = $column;
				}
			}
		} else {
			$columns = explode(',', $columns);
			foreach ($columns as $value) {
				$this->columns[] = trim($value);
			}
		}

		// Set the table name and set the from clause
		if (is_string($table)) {
			$this->table = $table;
			array_push($this->from, array(
				'table' => $table,
				'join'  => null
			));
		}

		return $this;
	}

	/**
	 * Start a SELECT DISTINCT query.
	 *
	 * @param string|array $columns Array of columns to get
	 * @param string|null  $table   Table's name
	 *
	 * @return QueryBuilder Self instance for chain
	 */
	public function selectDistinct(mixed $columns, string $table = null)
	{

		// Perform a SELECT
		$this->select($columns, $table);

		// And change the operation for SELECT DISTINCT
		$this->operation = 'SELECT DISTINCT';

		return $this;
	}

	/**
	 * Start a INSERT IGNORE query.
	 *
	 * @param string $table   Table's name
	 * @param array  $columns Array of columns to insert
	 *
	 * @return QueryBuilder Self instance for chain
	 */
	public function insertIgnore(string $table, array $columns = array())
	{

		// Reset properties
		$this->reset();

		// Set the current operation
		$this->operation = 'INSERT IGNORE';

		// Set the table name
		$this->table = $table;

		// Save the columns and them values
		foreach ($columns as $columnName => $colValue) {
			$this->addColumn($columnName, $colValue);
		}

		return $this;
	}

	/**
	 * Start a REPLACE query.
	 *
	 * @param string $table   Table's name
	 * @param array  $columns Array of columns
	 *
	 * @return QueryBuilder Self instance for chain
	 */
	public function replace(string $table, array $columns = array())
	{

		// Reset properties
		$this->reset();

		// Set the current operation
		$this->operation = 'REPLACE';

		// Set the table name
		$this->table = $table;

		// Save the columns and them values
		foreach ($columns as $columnName => $colValue) {
			$this->addColumn($columnName, $colValue);
		}

		return $this;
	}

	/**
	 * Start a custom SQL query.
	 *
	 * @param string $query  Consulta SQL a ejecutar
	 * @param array  $params Array de parámetros agregados
	 *
	 * @return QueryBuilder Self instance for chain
	 */
	public function query(string $query, array $params = null)
	{

		// Set the current operation
		$this->operation = 'QUERY';

		// Save the query statement
		$this->query = $query;

		// Parameters to bind
		if (is_array($params)) {
			$this->bindParams($params);
		}

		return $this;
	}

	/**
	 * Set the first FROM clause and its respective JOIN.
	 *
	 * @param string            $table Table name
	 * @param string|array|null $join  One or several JOIN clauses
	 *
	 * @return QueryBuilder Self instance for chain
	 */
	public function from(string $table, mixed $join = null)
	{

		// Reset the from property
		$this->from = array();

		// Set a first FROM
		$this->addFrom($table, $join);

		return $this;
	}

	/**
	 * Add a FROM clause and its respective JOIN.
	 *
	 * @param string            $table Table name
	 * @param string|array|null $join  One or several JOIN clauses
	 * 
	 * JOIN clauses could be defined in 2 ways:
	 * 1- A string with a JOIN clause definition: "INNER JOIN table2 ON table1.id = table2.table1_id"
	 * 2- An array with a several JOIN clauses definitions, each position could be spacified in two ways, such as a string or an array with 3 values: ['INNER JOIN', 'table2', 'table1.id = table2.table1_id']
	 *
	 * @return QueryBuilder Self instance for chain
	 */
	public function addFrom(string $table, mixed $join = null)
	{
		array_push($this->from, array(
			'table' => 	$table,
			'join' =>	$join
		));

		return $this;
	}

	/**
	 * Add a column for the current query.
	 *
	 * @param string $name  Column name
	 * @param mixed  $value Column value
	 *
	 * The column value could be defined in 3 ways.
	 * 1- Such as an RawValue instance to be binded just as it was defined
	 * 2- Such as an array of two positions, this option will create a parameter that will be binded to the statement, so, the first position will specify the value and the second one will be the parameter type
	 * 3- Such a any value (different to an array), this option will create a parameter that will be binded to the statement, the value will be used as it was passed and the parameter type will be obtained automatically through the table structure
	 * 
	 * @return QueryBuilder Self instance for chain
	 */
	public function addColumn(string $name, mixed $value)
	{

		// If the value is a RawValue instance, it will be inserted as it was defined into the SQL statement
		if ($value instanceof \LuisaeDev\QueryBuilder\RawValue) {
			$this->columns[$name] = $value->get();

		} else if (is_array($value)) {

			// Set the parameter name according to the column name
			$paramName = ':_' . str_replace('`', '', $name);

			// Add the column name and its parameter name definition
			$this->columns[$name] = $paramName;

			// Bind the parameter to the query
			$this->bindParam($paramName, $value[0], $value[1]);

		// If the value it is not an array, its type will be determinated according to the column type
		} else {

			// Set the parameter name according to the column name
			$paramName = ':_' . str_replace('`', '', $name);

			// Add the column name and the parameter name definition
			$this->columns[$name] = $paramName;

			// If the value is NULL
			if ($value === null) {
				$paramType = PDO::PARAM_NULL;

			} else {

				// Obtain the table structure
				$tableStruc = $this->describe($this->table);

				// Check if the table structure and column info was obtained
				if (($tableStruc) && (isset($tableStruc['column'][$name]))) {

					// Obtain the parameter type for the specified column
					$paramType = $tableStruc['column'][$name]['param-type'];

					// If the passed value is a DateTime instance, and the column is 'date', 'datetime' or 'timestamp', the value will be formated as string before to be binded
					if ($value instanceof DateTime) {
						switch ($tableStruc['column'][$name]['type']) {
							case 'date':
								$value = $value->format('Y-m-d');
								break;

							case 'datetime':
								$value = $value->format('Y-m-d H:i:s');
								break;

							case 'timestamp':
								$value->setTimezone(new DateTimeZone('UTC'));
								$value = $value->format('Y-m-d H:i:s');
								break;
						}
					}

				} else {
					$paramType = PDO::PARAM_STR;
				}
			}

			// Bind the parameter to the query
			$this->bindParam($paramName, $value, $paramType);
		}

		return $this;
	}

	/**
	 * Add several columns to the current query.
	 *
	 * @param array $columns Multiple columns to add
	 *
	 * @return QueryBuilder Self instance for chain
	 */
	public function addColumns(array $columns)
	{
		foreach ($columns as $columnName => $colValue) {
			$this->addColumn($columnName, $colValue);
		}

		return $this;
	}

	/**
	 * Bind a paramater to the current query.
	 *
	 * @param string $name  Parameter's name
	 * @param mixed  $value Parameter's value
	 * @param string $type  Parameter's type, could be {'str', 'null', 'bool', 'int'}
	 *
	 * @return QueryBuilder Self instance for chain
	 */
	public function bindParam(string $name, mixed $value, string $type)
	{
		$this->params[$name] = [ $value, $type ];

		return $this;
	}

	/**
	 * Bind several parameters to the current query.
	 *
	 * @param array $params Multiple parameter to bind
	 *
	 * @return QueryBuilder Self instance for chain
	 */
	public function bindParams(array $params = null)
	{
		foreach ($params as $name => $value) {
			$this->bindParam($name, $value[0], $value[1]);
		}

		return $this;
	}

	/**
	 * Start a group of WHERE conditions.
	 *
	 * @param string|array $condition Condition or group of conditions to set
	 * @param array        $params    Parameters to bind
	 *
	 * @return QueryBuilder Self instance for chain
	 */
	public function where(mixed $condition, array $params = null)
	{
		$this->where = array();
		$this->where[] = $condition;
		if (is_array($params)) {
			$this->bindParams($params);
		}

		return $this;
	}

	/**
	 * Add an "AND" next to a previous group of WHERE conditions and start a new one group of WHERE conditions.
	 *
	 * @param string|array $condition Condition or group of conditions to set
	 * @param array        $params    Parameters to bind
	 *
	 * @return QueryBuilder Self instance for chain
	 */
	public function andWhere(mixed $condition, array $params = null)
	{
		$this->where[] = 'AND';
		$this->where[] = $condition;
		if (is_array($params)) {
			$this->bindParams($params);
		}

		return $this;
	}

	/**
	 * Add an "OR" next to a previous group of WHERE conditions and start a new one group of WHERE conditions.
	 *
	 * @param string|array $condition Condition or group of conditions to set
	 * @param array        $params    Parameters to bind
	 *
	 * @return QueryBuilder Self instance for chain
	 */
	public function orWhere(mixed $condition, array $params = null)
	{
		$this->where[] = 'OR';
		$this->where[] = $condition;
		if (is_array($params)) {
			$this->bindParams($params);
		}

		return $this;
	}

	/**
	 * Add a "XOR" next to a previous group of WHERE conditions and start a new one group of WHERE conditions.
	 *
	 * @param string|array $condition Condition or group of conditions to set
	 * @param array        $params    Parameters to bind
	 *
	 * @return QueryBuilder Self instance for chain
	 */
	public function xorWhere(mixed $condition, array $params = null)
	{
		$this->where[] = 'XOR';
		$this->where[] = $condition;
		if (is_array($params)) {
			$this->bindParams($params);
		}

		return $this;
	}

	/**
	 * Add a first GROUP BY statement
	 *
	 * @param string $stm GROUP BY statement
	 *
	 * @return QueryBuilder Self instance for chain
	 */
	public function group(string $stm)
	{
		$this->group = array();
		array_push($this->group, $stm);
		return $this;
	}

	/**
	 * Push a new GROUP BY statement
	 *
	 * @param string $stm GROUP BY statement definition to push
	 *
	 * @return QueryBuilder Self instance for chain
	 */
	public function addGroup(string $stm)
	{
		array_push($this->group, $stm);
		return $this;
	}

	/**
	 * Add a HAVING clause.
	 *
	 * @param string $clause Clausule definition
	 *
	 * @return QueryBuilder Self instance for chain
	 */
	public function having(string $clause)
	{
		$this->having = $clause;
		return $this;
	}

	/**
	 * Add a first ORDER BY keyword.
	 *
	 * @param string $keyword ORDER BY keyword definition
	 *
	 * @return QueryBuilder Self instance for chain
	 */
	public function order(string $keyword)
	{
		$this->order = array();
		array_push($this->order, $keyword);
		return $this;
	}

	/**
	 * Push a new ORDER BY keyword.
	 *
	 * @param string $keyword ORDER BY keyword definition
	 *
	 * @return QueryBuilder Self instance for chain
	 */
	public function addOrder(string $keyword)
	{
		array_push($this->order, $keyword);
		return $this;
	}

	/**
	 * Set a LIMIT clause.
	 *
	 * @param int|string $start Start of the clause, could be a number or a single string definition: '1 OFFSET 10'
	 * @param int|string $end   (Optional) End of the clause
	 *
	 * @return QueryBuilder Self instance for chain
	 */
	public function limit(mixed $start, int $end = null)
	{
		$this->limit['start'] = $start;
		if (isset($end)) {
			$this->limit['end'] = $end;
		}
		return $this;
	}
	
	/**
	 * Prepare and execute a query.
	 * 
	 * @param bool $throw Define if PDO Exceptions should be thrown
	 *
	 * @return QueryBuilder Self instance for chain
	 */
	public function execute(bool $throw = true)
	{

		// Obtain the SQL string
		$sql = $this->getSQL() . ';';

		// Prepare and execute the PDO statement
		try {

			$this->sPDO
				->prepare($sql, $this->params)
				->execute();

		} catch(PDOException $e) {
			if ($throw) {
				throw $e;
			}
		}

		return $this;
	}

	/**
	 * Fetch and return the next row.
	 *
	 * @return array|false
	 */
	public function fetch()
	{
		return $this->sPDO->fetch();
	}

	/**
	 * Fetch and return the next row as an object value.
	 * 
	 * @return object|false
	 */
	public function fetchObject() {
		return $this->sPDO->fetchObject();
	}

	/**
	 * Fetch and return an array with all results.
	 *
	 * @param bool $assoc Define if the result is going to be fecth as an associative array
	 * 
	 * @return array
	 */
	public function fetchAll(bool $assoc = false)
	{
		return $this->sPDO->fetchAll($assoc);
	}

	/**
	 * This method is a shortant and smart option to generate 'where' conditions, recognizing and binding automatically all the parameters according to the table columns definitions.
	 * 
	 * @param mixed $arg1 If this is not an array and is the unique argument, it will represent the value of the condition and the column name will be the primary key, if there arte more arguments, this is argument will be the column name
	 * @param mixed $arg2 If this is the last argument, it will be the value of the condition, else, it will represent the operator of the condition
	 * @param mixed $arg3 If it is defined, it will be the value of the condition
	 * 
	 * The argument $arg1 can be an array, in that case the rest of the arguments will be ignored, and this array will be a group of conditions defined by an array notation of one, two or three positions each one
	 * 
	 * @return QueryBuilder Self instance for chain
	 */
	public function match(mixed $arg1, mixed $arg2 = null, mixed $arg3 = null)
	{

		// Finish if the table name was not yet specified
		if (!isset($this->table)) {
			return;
		}

		// If the argument $arg1 is an array, the rest of them will be ignored
		if (is_array($arg1)) {
			$expression = $arg1;
		
		// Build the where expression using one, two or three arguments
		} else {
			$expression = [];
			if (isset($arg3)) {
				array_push($expression, [ $arg1, $arg2, $arg3 ]);
			} else if (isset($arg2)) {
				array_push($expression, [ $arg1, $arg2 ]);
			} else {
				array_push($expression, [ $arg1 ]);
			}
		}

		// Obtain the table structure
		$tableStruc = $this->describe($this->table);

		// Set an empty parameters array
		$params = array();
		$paramCounter = 0;

		// Iterate the expression to analize each condition
		foreach ($expression as $i => $condition) {

			// If the condition is an array
			if (is_array($condition)) {

				// Catch the column name, operator and value
				if (count($condition) == 1) {
					$columnName = null;
					$operator = '=';
					$value = $condition[0];
				} else if (count($condition) == 2) {
					$columnName = $condition[0];
					$operator = '=';
					$value = $condition[1];
				} else if (count($condition) == 3) {
					$columnName = $condition[0];
					$operator = $condition[1];
					$value = $condition[2];
				}

				// If the column name was not specified, obtain the column name of the primary key
				if ($columnName === null) {
					$columnName = $tableStruc['pk']['name'];
				}
				
				// If the value is a RawValue instance, its definition will be used as it was defined
				if ($value instanceof \LuisaeDev\QueryBuilder\RawValue) {
					$expression[$i] = $columnName . ' = ' . $value->get();
				} else {
					
					// Set the corresponding parameter name
					$paramCounter++;
					$paramName = ':__' . $columnName . $paramCounter;

					// Set the corresponding parameter type
					if ((isset($tableStruc)) && (isset($tableStruc['column'][$columnName]))) {
						$paramType = $tableStruc['column'][$columnName]['param-type'];
					} else {
						$paramType = 'str';
					}

					// Replace at the expression, the corresponding where condition and add the parameter to the array parameters
					if ($value === null) {
						if (($operator == '<>') || ($operator == '!=') || ($operator == 'IS NOT')) {
							$expression[$i] = $columnName . ' IS NOT NULL';
						} else {
							$expression[$i] = $columnName . ' IS NULL';
						}
					} else {
						$expression[$i] = $columnName . ' ' . $operator . ' ' . $paramName;
						$params[$paramName] = [ $value, $paramType ];
					}
				}

			// If the condition is a string, its definition will be used as it was defined
			} else {
				$expression[$i] = $condition;
			}
		}

		// Add the generated where conditions to the current query
		$this->where($expression);

		// Bind the generated parameters to the current query
		$this->bindParams($params);

		return $this;
	}
	
	/**
	 * Perform a SELECT query and return the record fetched.
	 *
	 * @param string       $table   Table's name
	 * @param mixed        $pk      Primary key value
	 * @param string|array $columns Columns to fetch
	 *
	 * @return array|false Object record or false in case did not found it
	 */
	public function get(string $table, mixed $pk, mixed $columns = '*')
	{

		// Start a select query and match the value by its primary key
		return $this
			->select($columns, $table)
			->match($pk)
			->limit(1)
			->execute()
			->fetchObject();
	}

	/**
	 * Perform an SELECT query with a pretty basic count(...) function.
	 *
	 * @param string $expression Expression for use as count argument
	 * @param string $table      Table's name
	 * @param mixed  $match      Match expression
	 *
	 * @return int Amount of records founded
	 */
	public function count(string $expression, string $table, mixed $match = null)
	{

		// Start a SELECT query
		$this->select('count(' . $expression . ') as count', $table);

		// Add a match expression if it was defined
		if (isset($match)) {
			$this->match($match);
		}

		// Perform de statement and fetch the result
		$result = $this
			->execute()
			->fetch();
		
		if ($result) {
			return (int)$result['count'];
		} else {
			return 0;
		}
	}

	/**
	 * Perform a query to check if a particular record exists by its primary key.
	 *
	 * @param string $table Table's name
	 * @param mixed  $pk    Primary key value
	 *
	 * @return bool Indicate if the record exists
	 */
	public function exists(string $table, mixed $pk)
	{
		$count = $this->count('*', $table, $pk);
		if ($count > 0) {
			return true;
		} else {
			return false;
		}
	}

	/**
	 * Return the last produced error after an executed statement.
	 *
	 * @return array|null
	 */
	public function errorInfo()
	{
		return $this->sPDO->errorInfo();
	}

	/**
	 * Return the last inserted ID after an executed statement.
	 *
	 * @return mixed|null
	 */
	public function lastInsertId()
	{
		return $this->sPDO->lastInsertId();
	}

	/**
	 * Return the total affected rows after an executed statement.
	 *
	 * @return int
	 */
	public function rowCount()
	{
		return $this->sPDO->rowCount();
	}

	/**
	 * Return all the columns added to the current query.
	 *
	 * @return array
	 */
	public function getColumns()
	{
		return $this->columns;
	}

	/**
	 * Return all the parameter binded to the current query.
	 *
	 * @return array
	 */
	public function getParams()
	{
		return $this->params;
	}

	/**
	 * Build and return the SQL statement.
	 *
	 * @return string
	 */
	public function getSQL()
	{
		$str = '';

		// Check the query operation
		switch ($this->operation) {
			case 'SELECT':
			case 'SELECT DISTINCT':
				$str = $this->operation . ' ';
				$str .= $this->getColsSQL();
				$str .= $this->getFromSQL();
				$str .= $this->getWhereSQL();
				$str .= $this->getGroupSQL();
				$str .= $this->getHavingSQL();
				$str .= $this->getOrderSQL();
				$str .= $this->getLimitSQL();
				break;

			case 'INSERT':
				$str = 'INSERT INTO ' . $this->table . ' '. $this->getColsSQL();
				break;

			case 'INSERT IGNORE':
				$str = 'INSERT IGNORE INTO ' . $this->table . ' '. $this->getColsSQL();
				break;

			case 'REPLACE':
				$str = 'REPLACE INTO ' . $this->table . ' '. $this->getColsSQL();
				break;

			case 'UPDATE':
				$str = 'UPDATE ' . $this->table . ' SET '. $this->getColsSQL();
				$str .= $this->getWhereSQL();
				break;

			case 'DELETE':
				$str = 'DELETE FROM ' . $this->table;
				$str .= $this->getWhereSQL();
				break;

			case 'QUERY':
				$str = $this->query;
				break;

			default:
				return '';
				break;
		}
		return $str;
	}

	/**
	 * 'dbname' property.
	 *
	 * @return string|null
	 */
	private function get_dbname()
	{
		return $this->sPDO->dbname;
	}

	/**
	 * 'driver' property.
	 *
	 * @return string|null
	 */
	private function get_driver()
	{
		return $this->sPDO->driver;
	}

	/**
	 * 'host' property.
	 *
	 * @return string|null
	 */
	private function get_host()
	{
		return $this->sPDO->host;
	}

	/**
	 * 'port' property.
	 *
	 * @return int|null
	 */
	private function get_port()
	{
		return $this->sPDO->port;
	}
	
	/**
	 * Reset the properties related to the current query.
	 *
	 * @return void
	 */
	private function reset()
	{
		$this->operation = null;
		$this->table = null;
		$this->columns = array();
		$this->params = array();
		$this->from = array();
		$this->where = array();
		$this->group = array();
		$this->having = '';
		$this->order = array();
		$this->limit = array();
		$this->query = '';
	}

	/**
	 * Return a standard structure for a table.
	 *
	 * @param string $table Table name
	 *
	 * @return array|bool Table structure or false
	 */
	private function describe(string $table)
	{

		// Define a fingerprint based on the SimplePDO connection data and the table name
		if (is_array($this->sPDO->getConnectionData())) {
			$fingerprint = implode(',', $this->sPDO->getConnectionData());
		} else {
			$fingerprint = $this->sPDO->getConnectionData();
		}
		$fingerprint = md5($fingerprint . ',' . $table);

		// Check and return the table structure if it was obtained before
		if (isset(self::$tableSchemas[$fingerprint])) {
			return self::$tableSchemas[$fingerprint];
		}

		// Set the SQL statement according to the database driver
		switch ($this->sPDO->driver) {
			case 'sqlite':
			case 'mysql':
				$sql = 'describe ' . $table;
				break;
		}

		// Prepare and execute the SQL statement
		$this->sPDO
			->prepare($sql)
			->execute();

		// Get the table's schema
		$structure = $this->sPDO->fetchAll(true);

		// Return false if could not get the schema
		if ((count($structure) == 0) || ($structure == null)) {
			return false;
		}

		// This variable will define a standard table structure
		$table = array(
			'pk'  => null,
			'column' => array()
		);

		// Iterate all columns to obtain its respective column name and type
		foreach ($structure as $column) {
			switch ($this->sPDO->driver) {
				case 'sqlite':
					$columnName = $column['name'];
					$colType = $column['type'];
					break;

				case 'mysql':
					$columnName = $column['Field'];
					$colType = $column['Type'];
					break;
			}
			$columnName = str_replace('`', '', $columnName);

			// Column type could have a definition of length, example: 'varchar(100)', so, it will separate only the column type definition
			preg_match_all('/^[a-zA-Z]{0,}/', $colType, $matches);
			$colType = $matches[0][0];

			// According to de column type, the parameter type it is going to be defined for PDO statement purpose
			switch (strtolower($colType)) {
				case 'boolean':
					$paramType = PDO::PARAM_BOOL;
					break;

				case 'int':
				case 'integer':
				case 'tinyint':
				case 'bigint':
				case 'smalint':
					$paramType = PDO::PARAM_INT;
					break;

				case 'numeric':
				case 'real':
				case 'decimal':
				case 'float':
				case 'double':
				case 'date':
				case 'datetime':
				case 'time':
				case 'timestamp':
					$paramType = PDO::PARAM_STR;
					break;

				case 'char':
				case 'nchar':
				case 'character':
				case 'varchar':
				case 'nvarchar':
				case 'test':

				default:
					$paramType = PDO::PARAM_STR;
					break;
			};

			// Define the column info
			$table['column'][$columnName] = array(
				'name'	 	 => $columnName,
				'type'		 => $colType,
				'param-type' => $paramType,
			);

			// Define if it is the primary key
			switch ($this->sPDO->driver) {
				case 'sqlite':
					if ($column['pk'] == 1) {
						$table['pk'] = array(
							'name'       => $columnName,
							'type'       => $colType,
							'param-type' => $paramType,
						);
					}
					break;

				case 'mysql':
					if ($column['Key'] == 'PRI') {
						$table['pk'] = array(
							'name'       => $columnName,
							'type'       => $colType,
							'param-type' => $paramType,
						);
					}
					break;
			}
		}

		// If did not found any primary key, it is going to be defined the first column as primary key
		if (!isset($table['pk'])) {
			$table['pk'] = current($table['column']);
		}

		// Save the table structure for futures request
		self::$tableSchemas[$fingerprint] = $table;

		return $table;
	}

	/**
	 * Build the columns SQL string part.
	 *
	 * @return string
	 */
	private function getColsSQL()
	{

		// The output depends on the type of query operation
		switch ($this->operation) {
			case 'SELECT':
			case 'SELECT DISTINCT':
				return implode(', ', $this->columns);
				break;

			case 'INSERT':
			case 'INSERT IGNORE':
			case 'REPLACE':
				$columns = array();
				$values = array();
				foreach ($this->columns as $i => $value) {
					array_push($columns, $i);
					array_push($values, $value);
				}
				return '(' . implode(', ', $columns) . ') VALUES (' . implode(', ', $values) . ')';
				break;

			case 'UPDATE':
				$str = '';
				foreach ($this->columns as $i => $value) {
					if (strlen($str) > 0) {
						$str .= ', ';
					}
					$str .= $i . ' = ' . $value;
				}
				return $str;
				break;
		}
	}

	/**
	 * Build the FROM and JOIN SQl string part.
	 *
	 * @return string
	 */
	private function getFromSQL()
	{
		$str = '';
		foreach ($this->from as $from) {

			// Check if the string is currently starting to build
			if (strlen($str) == 0) {
				$str .= ' FROM';
			} else {
				$str .= ',';
			}

			// Add the tablename
			$str .= ' ' . $from['table'];

			// Add the JOIN clauses
			if (is_array($from['join'])) {
				foreach ($from['join'] as $join) {

					// Each JOIN clause could be a string or array with 3 positions
					if (is_array($join)) {
						$str .= ' ' . $join[0] . ' ' . $join[1] . ' ON (' . $join[2] . ')';
					} else {
						$str .= ' ' . $join;
					}
				}
			} else if (isset($from['join'])) {
				$str .= ' ' . $from['join'];
			}
		}
		return $str;
	}

	/**
	 * Build the WHERE conditions SQL string part.
	 *
	 * @return string
	 */
	private function getWhereSQL()
	{
		if (count($this->where) > 0) {
			return ' WHERE (' . $this->parseWhereGroupConditions($this->where) . ')';
		} else {
			return '';
		}
	}

	/**
	 * Parse and build a SQL string for a group of WHERE conditions.
	 *
	 * This method is recursive, when the WHERE conditions has a nested group of WHERE conditions
	 *
	 * @param array|string $where WHERE condition or group of where conditions 
	 *
	 * @return string
	 */
	private function parseWhereGroupConditions(mixed $expression)
	{

		// If only exists one WHERE condition
		if (is_string($expression)) {
			return '(' . $expression . ')';

		// If there are several WHERE conditions
		} else if (is_array($expression)) {
			$stepCounter = 0;
			$str = '';
			foreach ($expression as $stepCondition) {

				// If is it a string format condition
				if (is_string($stepCondition)) {

					// Check if the step is an operator or an expresion
					switch (strtolower($stepCondition)) {
						case 'and':
						case '&&':
						case 'or':
						case '||':
						case 'xor':

							// Add the operator to the string if it isn't the first step
							if ($stepCounter > 0) {
								$str .= ' ' . $stepCondition . ' ';
							}
							break;
						
						default:
							$str .= '(' . $stepCondition . ')';
					}

				// If the step is a group or conditions
				} else if (is_array($stepCondition)) {

					// Perform a recursive call to this method for resolve it
					$str .= '(' . $this->parseWhereGroupConditions($stepCondition) . ')';
				}
				$stepCounter++;
			}
			return $str;
		}
	}

	/**
	 * Build the SQL string for GROUP BY statements.
	 *
	 * @return string
	 */
	private function getGroupSQL()
	{
		if (count($this->group) > 0) {
			return ' GROUP BY ' . implode(', ', $this->group);
		} else {
			return '';
		}
	}

	/**
	 * Build the SQL string for HAVING clause.
	 *
	 * @return string
	 */
	private function getHavingSQL()
	{
		if (strlen($this->having) > 0) {
			return ' HAVING ' . $this->having;
		} else {
			return '';
		}
	}

	/**
	 * Build the SQL string for ORDER BY keywords.
	 *
	 * @return string
	 */
	private function getOrderSQL()
	{
		if (count($this->order) > 0) {
			return ' ORDER BY ' . implode(', ', $this->order);
		} else {
			return '';
		}
	}

	/**
	 * Build the SQL string for LIMIT clause.
	 *
	 * @return string
	 */
	private function getLimitSQL()
	{
		$str = '';
		if (isset($this->limit['start'])) {
			$str .= ' LIMIT ' . $this->limit['start'];
			if (isset($this->limit['end'])) {
				$str .= ', ' . $this->limit['end'];
			}
		}
		return $str;
	}

	/**
	 * Set a global connection data definition.
	 *
	 * @param string $name           Name of the connection
	 * @param mixed  $connectionData
	 * 
	 * @return void
	 */
	public static function setConnectionData(string $name, mixed $connectionData)
	{
		self::$connectionsData[$name] = $connectionData;
	}

	/**
	 * Return a global connection data definition.
	 *
	 * @param string $name Name of the connection.
	 * 
	 * @return mixed
	 */
	public static function getConnectionData(string $name)
	{
		if (self::connectionDataExists($name)) {
			return self::$connectionsData[$name];
		} else {
			return null;
		}
	}

	/**
	 * Check if a global connection data was defined.
	 *
	 * @param string $name Name of the connection
	 * 
	 * @return bool
	 */
	public static function connectionDataExists(string $name)
	{
		return array_key_exists($name, self::$connectionsData);
	}
}
?>