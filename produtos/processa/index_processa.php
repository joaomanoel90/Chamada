<?php
require_once('../config.php');
require_once(DBAPI);


$conn = open_database();



$sql_indice = "SELECT * FROM VIEW_ESTOQUE";
$stmt_indice = sqlsrv_query($conn, $sql_indice);
$rows_indice = sqlsrv_has_rows($stmt_indice);


?>