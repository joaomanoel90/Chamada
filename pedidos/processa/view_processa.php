<?php
require_once('../config.php');
require_once(DBAPI);
require_once('../inc/functions.php'); 

$conn = open_database();
if (!$conn) {
    die("Erro de conexão com o banco de dados.");
}

// Inicializa variáveis
$pedido = null;
$erro = '';
$itens_pedido = [];

// ----------------------------
// Carrega pedido e itens
// ----------------------------
if (isset($_GET['id'])) {
    $id_pedido = intval($_GET['id']);

    // Busca pedido
    $sql = "SELECT * FROM VIEW_PEDIDOS_UNIDADES WHERE ID_Pedido = ?";
    $stmt = sqlsrv_query($conn, $sql, [$id_pedido]);

    if ($stmt && ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC))) {
        $id_unidade = $row['ID_Unidade'];

        // 🔐 Verifica se o usuário tem acesso à unidade
        if (!usuario_tem_acesso_unidade($conn, $_SESSION['usuarioId'], $id_unidade)) {
            $erro = "Você não tem permissão para visualizar este pedido.";
        } elseif (strtolower($row['Status']) !== 'finalizado' && strtolower($row['Status']) !== 'cancelado') {
            // Bloqueia acesso se não for finalizado nem cancelado
            $erro = "Este pedido não pode ser visualizado pois está com status: " . $row['Status'];
        } else {
            $pedido = $row;

            // Busca dados de cancelamento da tabela base caso a view não os tenha
            if (strtolower($row['Status']) === 'cancelado') {
                $sql_canc = "SELECT MotivoCancelamento, DataCancelado FROM TB_PEDIDOS WHERE ID = ?";
                $stmt_canc = sqlsrv_query($conn, $sql_canc, [$id_pedido]);
                if ($stmt_canc && ($row_canc = sqlsrv_fetch_array($stmt_canc, SQLSRV_FETCH_ASSOC))) {
                    $pedido['MotivoCancelamento'] = $row_canc['MotivoCancelamento'];
                    $pedido['DataCancelado'] = $row_canc['DataCancelado'];
                }
            }
        }
    } else {
        $erro = "Pedido não encontrado.";
    }

    // Busca itens do pedido (só se não houve erro)
    if (empty($erro)) {
        $sql_itens = "SELECT * FROM VIEW_ITENS_PEDIDO_PRODUTO WHERE ID_Pedido = ? AND Ativo = 1";
        $stmt_itens = sqlsrv_query($conn, $sql_itens, [$id_pedido]);

        if ($stmt_itens) {
            while ($row_item = sqlsrv_fetch_array($stmt_itens, SQLSRV_FETCH_ASSOC)) {
                $itens_pedido[] = $row_item;
            }
        }
    }
} else {
    $erro = "ID do pedido não informado.";
}
