<?php

	require_once __DIR__ . '/vendor/autoload.php';

	use LuisaeDev\QueryBuilder\QueryBuilder;

	// Example of usage with mandatory connection data values
	$query = new QueryBuilder([
		'dbname'   => 'mydb',
		'user'     => 'root',
		'password' => ''
	]);

	// Begin a transaction
	$query->beginTransaction();
	
	try {

		// Insert roles
		$query
			->insert('roles', [ 'id' => 'admin', 'name' => 'Administrator', 'status' => 1 ])
			->execute()
			->insert('roles', [ 'id' => 'editor', 'name' => 'Editor', 'status' => 0 ])
			->execute();

		// Insert users
		$query
			->insert('users', [
				'name' => 'Walter White',
				'age' => 50,
				'role_id' => 'admin'
			])
			->execute()
			->insert('users', [
				'name' => 'Walter White',
				'age' => 26,
				'role_id' => 'admin'
			])
			->execute()
			->insert('users', [
				'name' => 'Kim Wexler',
				'age' => 38,
				'role_id' => 'editor'
			])
			->execute();
		
		// Commit the transaction
		$query->commit();

	} catch(PDOException $e) {
		$query->rollBack();
	}

	//
	$query->select('users.id, users.name, users.age, roles.id as role_id, roles.name as role_name, roles.status as role_status')
		->from('users', [
			'INNER JOIN' => [ 'roles', 'users.role_id = roles.id']
		])
		->where([
			'users.age' => [ '>=', 30 ],
			'AND',
			'users.age' => [ '<=', 50 ],
		])
		->andWhere('role_status = :role_status', [
			':role_status' => 1
		])
		->execute();

	while($row = $query->fetch()) {
		print_r($row);
	}
    die();

	// $query->get('countries', ['code', '+504']);

	// $query->match([
	// 	[ 'age', '>=', 20 ],
	// 	[ 'age', '<=', 50 ]
	// ]);
?>