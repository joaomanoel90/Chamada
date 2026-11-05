<?php
require_once('../config.php');
require_once(DBAPI);


$conn = open_database();



$sql = "SELECT * FROM TB_USUARIOS ORDER BY ID DESC";
$stmt = sqlsrv_query($conn, $sql);
$rows = sqlsrv_has_rows($stmt);


?>