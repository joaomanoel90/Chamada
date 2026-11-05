<?php
require_once(dirname(__DIR__, 2) . '/config.php');
require_once(DBAPI);
require_once(ABSPATH . 'inc/functions.php');

if (!isset($_SESSION['usuarioId'])) {
    header("Location: " . BASEURL . "login.php");
    exit;
}

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
        $_SESSION['message'] = $erro;
        $_SESSION['type'] = "danger";
    } else {
        try {
            sqlsrv_query($conn, "SET XACT_ABORT ON");

            if (!sqlsrv_begin_transaction($conn)) {
                throw new Exception("Erro ao iniciar transação: " . print_r(sqlsrv_errors(), true));
            }

            // Busca o pedido com bloqueio
            $sql_status = "SELECT * FROM TB_PEDIDOS WITH (UPDLOCK, ROWLOCK) WHERE ID = ?";
            $stmt_status = sqlsrv_query($conn, $sql_status, [$id_pedido]);
            if (!$stmt_status) {
                throw new Exception(print_r(sqlsrv_errors(), true));
            }
            $row_status = sqlsrv_fetch_array($stmt_status, SQLSRV_FETCH_ASSOC);

            if (!$row_status) {
                throw new Exception("Pedido não encontrado.");
            }

            // Permissão: admin, criador ou usuário com acesso à unidade
            $user_id = intval($_SESSION['usuarioId']);
            $nivel = intval($_SESSION['usuarioNiveisAcessoId'] ?? 0);
            $tem_acesso = ($nivel == 1 || $nivel == 2 || $row_status['ID_Usuario'] == $user_id || usuario_tem_acesso_unidade($conn, $user_id, $row_status['ID_Unidade']));

            if (!$tem_acesso) {
                throw new Exception("Você não tem permissão para cancelar este pedido.");
            }

            // Valida se status é Aberto
            if (strtolower(trim($row_status['Status'])) !== 'aberto') {
                throw new Exception("Apenas pedidos em status 'Aberto' podem ser cancelados nesta tela. Status atual: " . $row_status['Status']);
            }

            // Cancela movimentações caso existam (por precaução)
            $sql_cancela_mov = "
            UPDATE TB_MOVIMENTO
            SET 
                TipoMovimento = 'C',
                DataMovimento = GETDATE()
            WHERE ID_Pedido = ? 
            AND TipoMovimento IN ('R', 'S')
            ";
            sqlsrv_query($conn, $sql_cancela_mov, [$id_pedido]);

            // Atualiza status do pedido
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
                $user_id,
                $id_pedido
            ]);

            if (!$stmt_update) {
                throw new Exception(print_r(sqlsrv_errors(), true));
            }

            if (!sqlsrv_commit($conn)) {
                throw new Exception("Erro ao confirmar transação: " . print_r(sqlsrv_errors(), true));
            }

            $_SESSION['message'] = "Pedido #" . $id_pedido . " cancelado com sucesso!";
            $_SESSION['type'] = "success";

            header("Location: index.php");
            exit;

        } catch (Exception $e) {
            sqlsrv_rollback($conn);
            $erro = "Erro ao cancelar o pedido: " . $e->getMessage();
            $_SESSION['message'] = $erro;
            $_SESSION['type'] = "danger";
        }
    }
}

// Carregar o pedido para exibição caso acesse a página de confirmação
$sql_busca = "SELECT p.*, 
                     u.unidade as NomeUnidade,
                     usr.NOME as NomeUsuario 
              FROM TB_PEDIDOS p
              LEFT JOIN TB_UNIDADES u ON p.ID_Unidade = u.ID
              LEFT JOIN TB_USUARIOS usr ON p.ID_Usuario = usr.ID
              WHERE p.ID = ?";

$stmt_busca = sqlsrv_query($conn, $sql_busca, [$id_pedido]);
if ($stmt_busca) {
    $pedido = sqlsrv_fetch_array($stmt_busca, SQLSRV_FETCH_ASSOC);
}
