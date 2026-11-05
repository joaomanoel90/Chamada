<?php

function open_database()
{
	try {

		$serverName = DB_HOST;
		$connectionOptions = array("Database" => DB_NAME, "Uid" => DB_USER, "PWD" => DB_PASSWORD, "CharacterSet" => "UTF-8");
		$conn = sqlsrv_connect($serverName, $connectionOptions);
		return $conn;
	} catch (Exception $e) {
		die(print_r(sqlsrv_errors(), true));

	}
}

function close_database($conn)
{
	if ($conn) {
		sqlsrv_close($conn);
	}
}