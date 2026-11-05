<?php
require_once('../config.php');
require_once(DBAPI);

$conn = open_database();

$erro = '';
$pedido = null;
$itens_pedido = [];
$valores_postados = [];


// =====================================================
// POST - SALVAR LIBERAÇÃO
// =====================================================

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $id_pedido = intval($_POST['id_pedido']);

    $liberadas = $_POST['liberada'] ?? [];

    $valores_postados = $liberadas;

    // Fetch the Almoxarifado of this Pedido
    $sql_almox_pedido = "SELECT ID_Almoxarifado FROM TB_PEDIDOS WHERE ID = ?";
    $stmt_almox_pedido = sqlsrv_query($conn, $sql_almox_pedido, [$id_pedido]);
    $row_almox_pedido = sqlsrv_fetch_array($stmt_almox_pedido, SQLSRV_FETCH_ASSOC);
    $id_almox_do_pedido = $row_almox_pedido ? $row_almox_pedido['ID_Almoxarifado'] : 1;

    try {

        // Garante que todos os itens ativos do pedido tenham a quantidade preenchida
        $sql_itens_check = "SELECT ID FROM TB_ITENS_PEDIDO WHERE ID_Pedido = ? AND Ativo = 1";
        $stmt_itens_check = sqlsrv_query($conn, $sql_itens_check, [$id_pedido]);
        $itens_obrigatorios = [];
        if ($stmt_itens_check) {
            while ($row_chk = sqlsrv_fetch_array($stmt_itens_check, SQLSRV_FETCH_ASSOC)) {
                $itens_obrigatorios[] = $row_chk['ID'];
            }
        }

        foreach ($itens_obrigatorios as $id_obrig) {
            if (!isset($liberadas[$id_obrig]) || trim($liberadas[$id_obrig]) === '') {
                throw new Exception("Todos os itens devem ter a quantidade liberada preenchida (informe 0 caso não vá liberar).");
            }
        }

        // Garante rollback automático em erro SQL
        $stmt_xact = sqlsrv_query(
            $conn,
            "SET XACT_ABORT ON"
        );

        if (!$stmt_xact) {
            throw new Exception(print_r(sqlsrv_errors(), true));
        }


        if (!sqlsrv_begin_transaction($conn)) {
            throw new Exception(
                "Erro ao iniciar transação: " .
                    print_r(sqlsrv_errors(), true)
            );
        }



        foreach ($liberadas as $id_item => $valor_novo) {


            $id_item = intval($id_item);

            $novo = floatval($valor_novo);



            // =====================================================
            // BLOQUEIA ITEM
            // =====================================================

            $sql_item = "
            SELECT 
                IP.ID_Produto,
                IP.Liberado,
                IP.Quantidade,
                P.Descricao AS Produto

            FROM TB_ITENS_PEDIDO IP WITH (UPDLOCK, ROWLOCK)

            INNER JOIN TB_PRODUTOS P
                ON P.ID = IP.ID_Produto

            WHERE IP.ID = ?
            ";


            $stmt_item = sqlsrv_query(
                $conn,
                $sql_item,
                [$id_item]
            );


            if (!$stmt_item) {
                throw new Exception(print_r(sqlsrv_errors(), true));
            }


            $item = sqlsrv_fetch_array(
                $stmt_item,
                SQLSRV_FETCH_ASSOC
            );


            if (!$item) {
                continue;
            }



            $id_produto = $item['ID_Produto'];

            $antigo = floatval(
                $item['Liberado'] ?? 0
            );

            $qtd_pedida = floatval(
                $item['Quantidade']
            );



            // =====================================================
            // VALIDAÇÕES
            // =====================================================

            if ($novo < 0) {

                throw new Exception(
                    "Quantidade inválida."
                );
            }


            if ($novo > $qtd_pedida) {

                throw new Exception(
                    "Quantidade liberada maior que a solicitada: "
                        . $item['Produto']
                );
            }



            // =====================================================
            // ESTOQUE DISPONÍVEL
            // =====================================================

            $sql_estoque = "

            SELECT ISNULL(SUM(

                CASE

                    WHEN TipoMovimento = 'E'
                        THEN Quantidade

                    WHEN TipoMovimento = 'S'
                        THEN -Quantidade

                    WHEN TipoMovimento = 'R'
                        THEN -Quantidade

                    WHEN TipoMovimento = 'A'
                        THEN Quantidade

                    ELSE 0

                END

            ),0) AS Saldo


            FROM TB_MOVIMENTO WITH (UPDLOCK)

            WHERE ID_Produto = ?
            AND ID_Almoxarifado = ?

            ";


            $stmt_est = sqlsrv_query(
                $conn,
                $sql_estoque,
                [$id_produto, $id_almox_do_pedido]
            );


            if (!$stmt_est) {
                throw new Exception(print_r(sqlsrv_errors(), true));
            }


            $row_est = sqlsrv_fetch_array(
                $stmt_est,
                SQLSRV_FETCH_ASSOC
            );


            $saldo = floatval(
                $row_est['Saldo']
            );


            // devolve a reserva antiga para cálculo
            $disponivel = $saldo + $antigo;



            if ($novo > $disponivel) {

                throw new Exception(
                    "Estoque insuficiente para: "
                        . $item['Produto']
                );
            }




            // =====================================================
            // PROCURA RESERVA EXISTENTE
            // =====================================================

            $sql_mov = "

            SELECT TOP 1 ID

            FROM TB_MOVIMENTO WITH (UPDLOCK)

            WHERE ID_ItemPedido = ?

            AND ID_Pedido = ?

            AND TipoMovimento = 'R'

            ";


            $stmt_mov = sqlsrv_query(
                $conn,
                $sql_mov,
                [
                    $id_item,
                    $id_pedido
                ]
            );


            if (!$stmt_mov) {
                throw new Exception(print_r(sqlsrv_errors(), true));
            }


            $mov = sqlsrv_fetch_array(
                $stmt_mov,
                SQLSRV_FETCH_ASSOC
            );





            // =====================================================
            // REMOVE RESERVA
            // =====================================================

            if ($novo <= 0) {


                if ($mov) {


                    $stmt = sqlsrv_query(
                        $conn,

                        "
                        DELETE FROM TB_MOVIMENTO
                        WHERE ID = ?
                        ",

                        [
                            $mov['ID']
                        ]
                    );


                    if (!$stmt) {
                        throw new Exception(print_r(sqlsrv_errors(), true));
                    }
                }
            }

            // =====================================================
            // ATUALIZA OU CRIA RESERVA
            // =====================================================

            else {


                if ($mov) {


                    $stmt = sqlsrv_query(
                        $conn,

                        "
                        UPDATE TB_MOVIMENTO

                        SET

                            Quantidade = ?,

                            DataMovimento = GETDATE()


                        WHERE ID = ?

                        ",

                        [
                            $novo,
                            $mov['ID']
                        ]
                    );
                } else {


                    $stmt = sqlsrv_query(
                        $conn,

                        "
                        INSERT INTO TB_MOVIMENTO
                        (
                            ID_Produto,
                            TipoMovimento,
                            Quantidade,
                            DataMovimento,
                            ID_Pedido,
                            ID_ItemPedido,
                            ID_Almoxarifado
                        )

                        VALUES
                        (
                            ?,
                            'R',
                            ?,
                            GETDATE(),
                            ?,
                            ?,
                            ?
                        )
                        ",

                        [
                            $id_produto,
                            $novo,
                            $id_pedido,
                            $id_item,
                            $id_almox_do_pedido
                        ]
                    );
                }


                if (!$stmt) {
                    throw new Exception(print_r(sqlsrv_errors(), true));
                }
            }





            // =====================================================
            // ATUALIZA ITEM
            // =====================================================


            $stmt_up = sqlsrv_query(
                $conn,

                "
                UPDATE TB_ITENS_PEDIDO

                SET Liberado = ?

                WHERE ID = ?

                ",

                [
                    $novo,
                    $id_item
                ]
            );


            if (!$stmt_up) {
                throw new Exception(print_r(sqlsrv_errors(), true));
            }
        }



        if (!sqlsrv_commit($conn)) {

            throw new Exception(
                "Erro no commit: " .
                    print_r(sqlsrv_errors(), true)
            );
        }


        $total_liberado = 0;
        foreach ($liberadas as $valor_novo) {
            $total_liberado += floatval($valor_novo);
        }

        if ($total_liberado > 0) {
            header("Location:status.php?id=" . $id_pedido);
        } else {
            $_SESSION['message'] = "Liberações salvas, mas o total é zero. O status não pode ser alterado até que haja pelo menos 1 item liberado.";
            $_SESSION['type'] = "warning";
            header("Location:index.php");
        }
        exit;
    } catch (Exception $e) {


        sqlsrv_rollback($conn);

        $erro = $e->getMessage();
    }
}




// =====================================================
// GET - CARREGAR PEDIDO
// =====================================================


$id_pedido = intval($_GET['id'] ?? 0);



$sql = "
SELECT *

FROM VIEW_PEDIDOS_UNIDADES

WHERE ID_Pedido = ?
";


$stmt = sqlsrv_query(
    $conn,
    $sql,
    [$id_pedido]
);


if ($stmt) {

    $pedido = sqlsrv_fetch_array(
        $stmt,
        SQLSRV_FETCH_ASSOC
    );
}




// =====================================================
// LISTA ITENS
// =====================================================


$sql_itens = "

SELECT

    IP.ID,

    IP.ID_Produto,

    IP.Quantidade,

    IP.Liberado,

    IP.Ativo,

    P.Descricao AS Produto,


    ISNULL((

        SELECT SUM(

            CASE

                WHEN TipoMovimento = 'E'
                    THEN Quantidade

                WHEN TipoMovimento = 'S'
                    THEN -Quantidade

                WHEN TipoMovimento = 'R'
                    THEN -Quantidade

                WHEN TipoMovimento = 'A'
                    THEN Quantidade

                ELSE 0

            END

        )

        FROM TB_MOVIMENTO M

        WHERE M.ID_Produto = IP.ID_Produto
        AND M.ID_Almoxarifado = ?

        AND M.TipoMovimento IN ('E','S','R','A')


    ),0) AS EstoqueAtual


FROM TB_ITENS_PEDIDO IP

INNER JOIN TB_PRODUTOS P

ON P.ID = IP.ID_Produto


WHERE IP.ID_Pedido = ?

AND IP.Ativo = 1

";


$stmt_itens = sqlsrv_query(
    $conn,
    $sql_itens,
    [$pedido['ID_Almoxarifado'], $id_pedido]
);



if (!$stmt_itens) {

    die(print_r(sqlsrv_errors(), true));
}



while ($item = sqlsrv_fetch_array(
    $stmt_itens,
    SQLSRV_FETCH_ASSOC
)) {

    $itens_pedido[] = $item;
}


sqlsrv_close($conn);
