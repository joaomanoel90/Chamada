<?php
require_once('../config.php');
require_once(DBAPI);

$conn = open_database();
$pedido = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
    $status = isset($_POST['status']) ? trim($_POST['status']) : '';

    if (!$id || !$status) {
        echo "ID ou Status não informado.";
        exit;
    }

    // Busca o pedido atual
    $sql = "SELECT * FROM TB_PEDIDOS WHERE ID = ?";
    $stmt = sqlsrv_query($conn, $sql, [$id]);
    $pedido = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);

    if (!$pedido) {
        echo "Pedido não encontrado.";
        exit;
    }

    // Bloqueia alteração se o status atual for "Aberto"
    if (strtolower($pedido['Status']) === 'aberto') {
        echo "Status do pedido não permite alteração.";
        exit;
    }

    // Valida novo status
    $validos = ['Aberto', 'Em Análise', 'Em Separação', 'Finalizado'];
    if (!in_array($status, $validos)) {
        echo "Status inválido.";
        exit;
    }

    // Bloqueia alteração se nenhum item estiver liberado
    $sql_check_lib = "SELECT ISNULL(SUM(Liberado), 0) AS total FROM TB_ITENS_PEDIDO WHERE ID_Pedido = ? AND Ativo = 1";
    $stmt_check_lib = sqlsrv_query($conn, $sql_check_lib, [$id]);
    $row_check_lib = sqlsrv_fetch_array($stmt_check_lib, SQLSRV_FETCH_ASSOC);
    if (!$row_check_lib || floatval($row_check_lib['total']) <= 0) {
        $_SESSION['message'] = "Não é possível alterar o status: nenhum item foi liberado neste pedido.";
        $_SESSION['type'] = "danger";
        header("location:index.php");
        exit;
    }

    sqlsrv_begin_transaction($conn);

    try {
        // Atualiza o status e a data de finalização se for o caso
        if ($status === 'Finalizado') {
            $sql_update = "UPDATE TB_PEDIDOS SET Status = ?, DataFinalizado = GETDATE() WHERE ID = ?";
        } else {
            $sql_update = "UPDATE TB_PEDIDOS SET Status = ? WHERE ID = ?";
        }

        $params_update = [$status, $id];
        $stmt_update = sqlsrv_query($conn, $sql_update, $params_update);

        if ($stmt_update === false) {
            throw new Exception("Erro ao atualizar status: " . print_r(sqlsrv_errors(), true));
        }

        // Se status for "Finalizado", gerar movimentações de saída
        if ($status === 'Finalizado') {
            // Verifica se já existem movimentações para este pedido
            $sql_verifica_mov = "SELECT COUNT(*) AS total FROM TB_MOVIMENTO WHERE ID_Pedido = ?";
            $stmt_verifica = sqlsrv_query($conn, $sql_verifica_mov, [$id]);
            $verifica = sqlsrv_fetch_array($stmt_verifica, SQLSRV_FETCH_ASSOC);

            if ($verifica['total'] > 0) {
                throw new Exception("Este pedido já possui movimentações registradas.");
            }

            // Busca os itens do pedido
            $sql_itens = "SELECT ID_Produto, Liberado 
                          FROM TB_ITENS_PEDIDO 
                          WHERE ID_Pedido = ? AND ATIVO = 1 AND Liberado > 0";

            $stmt_itens = sqlsrv_query($conn, $sql_itens, [$id]);

            if ($stmt_itens === false) {
                throw new Exception("Erro ao buscar itens do pedido: " . print_r(sqlsrv_errors(), true));
            }

            while ($item = sqlsrv_fetch_array($stmt_itens, SQLSRV_FETCH_ASSOC)) {
                $id_produto = $item['ID_Produto'];
                $quantidade = $item['Liberado'];

                // Registra a movimentação
                $sql_mov = "INSERT INTO TB_MOVIMENTO (ID_Produto, TipoMovimento, Quantidade, DataMovimento, ID_Pedido, ID_Almoxarifado)
                            VALUES (?, 'S', ?, GETDATE(), ?, ?)";

                $stmt_mov = sqlsrv_query($conn, $sql_mov, [$id_produto, $quantidade, $id, $pedido['ID_Almoxarifado']]);

                if ($stmt_mov === false) {
                    throw new Exception("Erro ao inserir na TB_MOVIMENTO (Item $id_itens_pedido): " . print_r(sqlsrv_errors(), true));
                }
            }
        }

        sqlsrv_commit($conn);
        header("location:index.php");
        exit;

    } catch (Exception $e) {
        sqlsrv_rollback($conn);
        echo "Erro: " . $e->getMessage();
    }

} elseif ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['id'])) {
    $id = intval($_GET['id']);

    $sql = "SELECT * FROM TB_PEDIDOS WHERE ID = ?";
    $stmt = sqlsrv_query($conn, $sql, [$id]);

    if ($stmt === false) {
        die(print_r(sqlsrv_errors(), true));
    }

    $pedido = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);

    if (!$pedido) {
        die("Pedido não encontrado.");
    }

    $sql_check_lib = "SELECT ISNULL(SUM(Liberado), 0) AS total FROM TB_ITENS_PEDIDO WHERE ID_Pedido = ? AND Ativo = 1";
    $stmt_check_lib = sqlsrv_query($conn, $sql_check_lib, [$id]);
    $total_liberado = 0;
    if ($stmt_check_lib && ($row_check_lib = sqlsrv_fetch_array($stmt_check_lib, SQLSRV_FETCH_ASSOC))) {
        $total_liberado = floatval($row_check_lib['total']);
    }
}
?>
