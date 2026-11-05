<?php

function open_database()
{
	try {

		/* DB_HOST pode ser:
		     - "127.0.0.1" ou "SERVIDOR"        -> instância padrão
		     - "SERVIDOR\SQLEXPRESS"            -> instância nomeada (não leva porta)
		     - "SERVIDOR,1433"                  -> porta já informada no host
		   DB_PORT só é acrescentado quando faz sentido. Antes ele era
		   definido no config.php e simplesmente ignorado aqui. */
		$serverName = DB_HOST;
		if (defined('DB_PORT') && DB_PORT !== ''
			&& strpos($serverName, '\\') === false
			&& strpos($serverName, ',') === false) {
			$serverName .= ',' . DB_PORT;
		}

		$connectionOptions = array(
			"Database"     => DB_NAME,
			"Uid"          => DB_USER,
			"PWD"          => DB_PASSWORD,
			"CharacterSet" => "UTF-8",
			"LoginTimeout" => 15,
		);
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