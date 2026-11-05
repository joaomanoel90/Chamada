<?php
require_once('../config.php');
require_once(DBAPI);

$conn = open_database();
$pedido = null;
$erro = '';

$id_pedido = isset($_REQUEST['id']) ? intval($_REQUEST['id']) : 0;

if (!$id_pedido) {
    header("Location: index.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $motivo_cancelamento = strip_tags(trim($_POST['motivo_cancelamento'] ?? ''));

    if (empty($motivo_cancelamento)) {
        $erro = "O motivo do cancelamento é obrigatório.";
    } else {
        try {
            sqlsrv_query($conn, "SET XACT_ABORT ON");

            if (!sqlsrv_begin_transaction($conn)) {
                throw new Exception("Erro ao iniciar transação: " . print_r(sqlsrv_errors(), true));
            }

            // Busca status atual para verificação
            $sql_status = "SELECT Status FROM TB_PEDIDOS WITH (UPDLOCK, ROWLOCK) WHERE ID = ?";
            $stmt_status = sqlsrv_query($conn, $sql_status, [$id_pedido]);
            if (!$stmt_status) {
                throw new Exception(print_r(sqlsrv_errors(), true));
            }
            $row_status = sqlsrv_fetch_array($stmt_status, SQLSRV_FETCH_ASSOC);

            if (!$row_status) {
                throw new Exception("Pedido não encontrado.");
            }

            if (strtolower($row_status['Status']) == 'cancelado') {
                throw new Exception("Este pedido já foi cancelado.");
            }

            // Transforma movimentos R e S em C (Cancelado)
            $sql_cancela_mov = "
            UPDATE TB_MOVIMENTO
            SET 
                TipoMovimento = 'C',
                DataMovimento = GETDATE()
            WHERE ID_Pedido = ? 
            AND TipoMovimento IN ('R', 'S')
            ";
            
            $stmt_cancela_mov = sqlsrv_query($conn, $sql_cancela_mov, [$id_pedido]);
            if (!$stmt_cancela_mov) {
                throw new Exception(print_r(sqlsrv_errors(), true));
            }

            // Atualiza pedido para Cancelado
            $sql_update_pedido = "
            UPDATE TB_PEDIDOS 
            SET 
                Status = 'Cancelado',
                MotivoCancelamento = ?,
                DataCancelado = GETDATE(),
                UsuarioFinalizou = ?
            WHERE ID = ?
            ";

            $stmt_update = sqlsrv_query($conn, $sql_update_pedido, [
                $motivo_cancelamento,
                $_SESSION['usuarioId'],
                $id_pedido
            ]);

            if (!$stmt_update) {
                throw new Exception(print_r(sqlsrv_errors(), true));
            }

            if (!sqlsrv_commit($conn)) {
                throw new Exception("Erro ao confirmar transação: " . print_r(sqlsrv_errors(), true));
            }

            $_SESSION['message'] = "Pedido cancelado com sucesso e os itens retornaram ao estoque.";
            $_SESSION['type'] = "success";

            header("Location: index.php");
            exit;

        } catch (Exception $e) {
            sqlsrv_rollback($conn);
            $erro = "Erro ao cancelar o pedido: " . $e->getMessage();
        }
    }
}

// Carregar o pedido para exibição
$sql_busca = "SELECT p.*, 
                     u.NOME as NomeUsuario 
              FROM TB_PEDIDOS p
              LEFT JOIN TB_USUARIOS u ON p.ID_Usuario = u.ID
              WHERE p.ID = ?";

$stmt_busca = sqlsrv_query($conn, $sql_busca, [$id_pedido]);

if ($stmt_busca && ($row = sqlsrv_fetch_array($stmt_busca, SQLSRV_FETCH_ASSOC))) {
    $pedido = $row;
} else {
    if (empty($erro)) $erro = "Não foi possível localizar o pedido.";
}

sqlsrv_close($conn);
