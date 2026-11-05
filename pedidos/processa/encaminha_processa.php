<?php
require_once('../config.php');
require_once(DBAPI);

function delete($id = null)
{
	$conn = open_database();

	// Verifica se existem produtos inseridos no pedido
	$sql_check = "SELECT COUNT(*) as qtd FROM TB_ITENS_PEDIDO WHERE ID_Pedido = ? AND Ativo = 1";
	$stmt_check = sqlsrv_query($conn, $sql_check, array($id));
	if ($stmt_check) {
		$row_check = sqlsrv_fetch_array($stmt_check, SQLSRV_FETCH_ASSOC);
		if (intval($row_check['qtd']) === 0) {
			$_SESSION['message'] = "Não é possível encaminhar o pedido #$id pois ele está vazio. Insira os produtos primeiro.";
			$_SESSION['type'] = "danger";
			header("location:" . BASEURL . 'pedidos/index.php');
			exit;
		}
	}

	$status = 'Em Análise';
	$sql_delete = "UPDATE TB_PEDIDOS SET Status = ?, DataEnvio = GETDATE() WHERE ID = ?";
	$params = array($status, $id);
	$stmt_delete = sqlsrv_query($conn, $sql_delete, $params);


	header("location:" . BASEURL . 'pedidos/index.php');
}
