<?php
require_once('../config.php');
require_once(DBAPI);

$conn = open_database();
if (!$conn) {
    die("Erro de conexão");
}

$erro = '';
$pedido = null;
$itens = [];

// ==========================
// 🔍 VALIDAR ID
// ==========================
if (!isset($_GET['id']) || intval($_GET['id']) <= 0) {
    $erro = "ID do pedido inválido.";
    return;
}

$id = intval($_GET['id']);

// ==========================
// 🔍 BUSCAR PEDIDO
// ==========================
$sql_pedido = "
SELECT 
    P.ID,
    P.DataCadastro,
    P.Objetivo,
    P.DataEnvio,
    P.DataFinalizado,
    P.NomeRecebedor,
    P.DocumentoRecebedor,
    U.Unidade,
    USU.nome AS UsuarioFinalizou
FROM TB_PEDIDOS P
INNER JOIN TB_UNIDADES U ON U.ID = P.ID_Unidade
LEFT JOIN TB_USUARIOS USU ON USU.id = P.UsuarioFinalizou
WHERE P.ID = ?
";

$stmt_pedido = sqlsrv_query($conn, $sql_pedido, [$id]);

if ($stmt_pedido && ($row = sqlsrv_fetch_array($stmt_pedido, SQLSRV_FETCH_ASSOC))) {
    $pedido = $row;
} else {
    $erro = "Pedido não encontrado.";
    return;
}

// ==========================
// 🔍 BUSCAR ITENS LIBERADOS
// ==========================
$sql_itens = "
SELECT *
FROM VIEW_ITENS_PEDIDO_PRODUTO
WHERE ID_Pedido = ?
AND Liberado > 0
ORDER BY Produto
";

$stmt_itens = sqlsrv_query($conn, $sql_itens, [$id]);

if ($stmt_itens) {
    while ($row = sqlsrv_fetch_array($stmt_itens, SQLSRV_FETCH_ASSOC)) {
        $itens[] = $row;
    }
} else {
    $erro = "Erro ao buscar itens.";
}