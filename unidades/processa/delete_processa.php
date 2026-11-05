<?php
require_once('../config.php');
require_once(DBAPI);

function delete($id = null)
{
	$conn = open_database();
	$ativo = '0';
	$sql_delete = "UPDATE TB_PRODUTOS SET ATIVO = ? WHERE ID =" . $id . "";
	$params = array($ativo);
	$stmt_delete = sqlsrv_query($conn, $sql_delete, $params);


	header("location:" . BASEURL . 'produtos');
}