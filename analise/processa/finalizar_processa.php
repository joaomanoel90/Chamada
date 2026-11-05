<?php
require_once('../config.php');
require_once(DBAPI);

$conn = open_database();
$pedido = null;
$erro = '';

// Captura o ID do pedido via GET (ao carregar a página) ou POST (ao enviar o formulário)
$id_pedido = isset($_REQUEST['id']) ? intval($_REQUEST['id']) : 0;

if (!$id_pedido) {
    header("Location: index.php");
    exit;
}


if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $nome_recebedor = strip_tags(trim($_POST['nome_recebedor'] ?? ''));
    $documento_recebedor = strip_tags(trim($_POST['documento_recebedor'] ?? ''));

    if (empty($nome_recebedor)) {

        $erro = "O nome do recebedor é obrigatório.";
    } else {

        try {

            sqlsrv_query($conn, "SET XACT_ABORT ON");


            if (!sqlsrv_begin_transaction($conn)) {
                throw new Exception(
                    "Erro ao iniciar transação: " . print_r(sqlsrv_errors(), true)
                );
            }


            /*
            =====================================================
            1 - BLOQUEIA PEDIDO E CONFERE STATUS
            =====================================================
            */

            $sql_status = "
            SELECT Status
            FROM TB_PEDIDOS WITH (UPDLOCK, ROWLOCK)
            WHERE ID = ?
            ";

            $stmt_status = sqlsrv_query(
                $conn,
                $sql_status,
                [$id_pedido]
            );


            if (!$stmt_status) {
                throw new Exception(print_r(sqlsrv_errors(), true));
            }


            $row_status = sqlsrv_fetch_array(
                $stmt_status,
                SQLSRV_FETCH_ASSOC
            );


            if (!$row_status) {
                throw new Exception("Pedido não encontrado.");
            }


            if (strtoupper($row_status['Status']) == 'FINALIZADO') {
                throw new Exception(
                    "Este pedido já foi finalizado."
                );
            }



            /*
            =====================================================
            2 - CONFERE RESERVA
            =====================================================
            */

            $sql_confere = "
            SELECT COUNT(*) AS Qtde
            FROM TB_MOVIMENTO WITH (UPDLOCK, ROWLOCK)
            WHERE ID_Pedido = ?
            AND TipoMovimento = 'R'
            AND Quantidade > 0
            AND ID_ItemPedido IS NOT NULL
            AND ID_Produto IS NOT NULL
            ";


            $stmt_confere = sqlsrv_query(
                $conn,
                $sql_confere,
                [$id_pedido]
            );


            if (!$stmt_confere) {
                throw new Exception(print_r(sqlsrv_errors(), true));
            }


            $row_confere = sqlsrv_fetch_array(
                $stmt_confere,
                SQLSRV_FETCH_ASSOC
            );


            if ($row_confere['Qtde'] <= 0) {

                throw new Exception(
                    "Este pedido não possui reservas de estoque."
                );
            }



            /*
            =====================================================
            3 - TRANSFORMA RESERVA EM SAÍDA
            =====================================================
            */

            $sql_saida = "
            UPDATE TB_MOVIMENTO
            SET
                TipoMovimento = 'S',
                DataMovimento = GETDATE()
            WHERE ID_Pedido = ?
            AND TipoMovimento = 'R'
            AND Quantidade > 0
            AND ID_ItemPedido IS NOT NULL
            AND ID_Produto IS NOT NULL
            ";


            $stmt_saida = sqlsrv_query(
                $conn,
                $sql_saida,
                [$id_pedido]
            );


            if (!$stmt_saida) {
                throw new Exception(print_r(sqlsrv_errors(), true));
            }


            if (sqlsrv_rows_affected($stmt_saida) <= 0) {

                throw new Exception(
                    "Não foi encontrada reserva para baixar o estoque."
                );
            }



            /*
            =====================================================
            4 - FINALIZA PEDIDO
            =====================================================
            */

            $sql_update = "
            UPDATE TB_PEDIDOS SET

                Status = 'Finalizado',
                NomeRecebedor = ?,
                DocumentoRecebedor = ?,
                DataFinalizado = GETDATE(),
                UsuarioFinalizou = ?

            WHERE ID = ?
            ";


            $stmt_update = sqlsrv_query(
                $conn,
                $sql_update,
                [
                    $nome_recebedor,
                    $documento_recebedor,
                    $_SESSION['usuarioId'],
                    $id_pedido
                ]
            );


            if (!$stmt_update) {
                throw new Exception(print_r(sqlsrv_errors(), true));
            }


            if (sqlsrv_rows_affected($stmt_update) <= 0) {

                throw new Exception(
                    "Não foi possível finalizar o pedido."
                );
            }



            /*
            =====================================================
            5 - CONFIRMA
            =====================================================
            */

            if (!sqlsrv_commit($conn)) {

                throw new Exception(
                    "Erro ao confirmar transação: " .
                        print_r(sqlsrv_errors(), true)
                );
            }


            header("Location: comprovante.php?id=$id_pedido");
            exit;
        } catch (Exception $e) {


            sqlsrv_rollback($conn);

            $erro = "Erro na finalização: " . $e->getMessage();
        }
    }
}

// Busca os dados do pedido e do usuário que realizou a solicitação original para preencher os campos automaticamente
// Nota: Fazemos um JOIN para buscar o Nome e CPF de quem abriu o pedido
$sql_busca = "SELECT p.*, 
                     u.NOME as NomeUsuario, 
                     u.CPF as CPFUsuario 
              FROM TB_PEDIDOS p
              LEFT JOIN TB_USUARIOS u ON p.ID_Usuario = u.ID
              WHERE p.ID = ?";

$stmt_busca = sqlsrv_query($conn, $sql_busca, [$id_pedido]);

if ($stmt_busca && ($row = sqlsrv_fetch_array($stmt_busca, SQLSRV_FETCH_ASSOC))) {
    $pedido = $row;
} else {
    if (empty($erro)) $erro = "Não foi possível localizar o pedido ou o solicitante original.";
}

sqlsrv_close($conn);
