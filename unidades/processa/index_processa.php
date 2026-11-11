<?php
require_once(dirname(__DIR__, 2) . '/config.php');
require_once(DBAPI);
require_once(ABSPATH . 'inc/chamada.php');
ch_db();
ch_require_gestor();

$conn = ch_db();

$sql_indice = "SELECT * FROM TB_UNIDADES";
$stmt_indice = sqlsrv_query($conn, $sql_indice);
$rows_indice = sqlsrv_has_rows($stmt_indice);


?>