<?php
require_once('../config.php');
require_once(DBAPI);

function delete($id = null)
{
	$conn = open_database();
	$ativo = '0';
	$liberado = null;
	$sql_delete = "UPDATE TB_ITENS_PEDIDO SET Ativo = ?, Liberado = ? WHERE ID = ?";
	$params = array($ativo, $liberado, $id);
	$stmt_delete = sqlsrv_query($conn, $sql_delete, $params);


	   header("Location: " . $_SERVER['HTTP_REFERER']); 
    exit();

}