<?php
require_once('../config.php');
require_once(DBAPI);

function delete($id = null)
{
	$conn = open_database();
	$status = 'Em Análise';
	$sql_delete = "UPDATE TB_PEDIDOS SET Status = ?, DataEnvio = GETDATE() WHERE ID = " . $id;
	$params = array($status);
	$stmt_delete = sqlsrv_query($conn, $sql_delete, $params);


	header("location:" . BASEURL . 'pedidos');
}