# QueryBuilder.php

## The easy way to build SQL statements

Connect with databases and compose complex SQL queries without define any SQL statement manually.

```php
$users = $query
	->select('*', 'users')
	->match('status', 1)
	->limit(10)
	->fetchAll();
```

# Powerful class of easy and intuitive usage

Check how the next SQL example could be easily coded in php using QueryBuilder class.

```sql
SELECT DATE_FORMAT(co.order_date, "%Y-%m-%d") AS order_day,
COUNT(DISTINCT co.order_id) AS num_orders,
COUNT(ol.book_id) AS num_books
FROM cust_order co
INNER JOIN order_line ol ON (co.order_id = ol.order_id)
GROUP BY DATE_FORMAT(co.order_date), "%Y-%m-%d")
ORDER BY co.order_date ASC;
```

```php
$query->select([
	'DATE_FORMAT(co.order_date, "%Y-%m-%d")' => 'order_day',
	'COUNT(DISTINCT co.order_id)'.           => 'num_orders',
	'COUNT(ol.book_id)'.                     => 'num_books'
])
	->from('cust_order co', [
	  'INNER JOIN' => ['order_line ol', 'co.order_id = ol.order_id']
	])
	->groupBy('DATE_FORMAT(co.order_date), "%Y-%m-%d")')
	->orderBy('co.order_date ASC');
```

# Installation

You can install QueryBuilder class via composer.

```php
composer require luisaedev/query-builder
```

# Requirements

QueryBuilder use PHP version 8.0 or higher and [PDO](https://www.php.net/manual/en/intro.pdo.php) extension .

# Connection with database (Constructor Class)

```php
use LuisaeDev\QueryBuilder\QueryBuilder;

// Example of usage with mandatory connection data values
$query = new QueryBuilder([
	'dbname'   => 'test',
	'user'     => 'root',
	'password' => ''
]);
```

```php
use LuisaeDev\QueryBuilder\QueryBuilder;

// Example of usage with all connection data values allowed and $throws argument to prevent/allow throws PDOException
$query = new QueryBuilder([
	'dbname'       => 'test',
	'user'         => 'root',
	'password'     => '',
	'driver'       => 'mysql',
	'host'         => '127.0.0.1',
	'port'         => '3306',
	'dsn-template' => '$driver:host=$host;port=$port;dbname=$dbname;charset=utf8'
], true);
```

### Arguments

| Argument name | Value Type | Description | Default Value |
| --- | --- | --- | --- |
| $connectionData | array | Array with connection data values |  |
| $throws | bool | Defines whether the contained PDO instance should throws PDOExeption | true |

### Throws

| Exception Class | Code | Description |
| --- | --- | --- |
| PDOException |  | All PDO exceptions throws by PDO class |

# Any bug?

Please, don't hesitate to contact me if you found any error. You can write me at [dev@luisae.com](mailto:hi@luisae.com)

# Documentation

<aside>
ℹ️ This class is in alpha version, however, its documentation is currently being written.

</aside>

# Methods

beginTransaction()

addColumn(name, value)

addColumns(columns)

addFrom(table, join)

addGroupBy(stm)

addOrderBy(keyword)

andWhere(condition, params)

bindParam(name, value, type)

bindParams(params)

commit()

count(expression, table, match)

delete(table)

errorExists()

errorInfo()

execute()

exists(table, pk)

fetch()

fetchAll()

fetchObject()

from(table, join)

get(table, pk, columns)

getColumns()

getParams()

getSQL()

groupBy(stm)

having(clause)

insert(table, columns)

insertIgnore(table, columns)

isAutocommit()

lastInsertId()

limit(start, end)

match(arg1, arg2, arg3)

orderBy(keyword)

orWhere(condition, params)

query(query, params)

replace(table, columns)

rollBack()

rowCount()

select(columns, table)

selectDistinct(columns, table)

update(table, columns)

where(condition, params)

xorWhere(condition, params)

# Properties

dbname

driver

host

port
