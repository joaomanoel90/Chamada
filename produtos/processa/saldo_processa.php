<?php

require_once('../config.php');
require_once(DBAPI);

$conn = open_database();

$erro = '';
$sucesso = '';

$saldoAtual = 0;

$produto = null;

/* ==========================
   VALIDAR PRODUTO
========================== */

if (!isset($_GET['id']) || intval($_GET['id']) <= 0) {

    $erro = "Produto não informado.";
    return;
}

$id_produto = intval($_GET['id']);

/* ==========================
   BUSCAR PRODUTO
========================== */

$sql_produto = "
    SELECT *
    FROM TB_PRODUTOS
    WHERE ID = ?
";

$stmt_produto = sqlsrv_query(
    $conn,
    $sql_produto,
    [$id_produto]
);

if ($stmt_produto) {

    $produto = sqlsrv_fetch_array(
        $stmt_produto,
        SQLSRV_FETCH_ASSOC
    );
}

if (!$produto) {

    $erro = "Produto não encontrado.";
    return;
}

/* ==========================
   ALMOXARIFADOS DO RESPONSÁVEL
========================== */
$almoxarifados = [];
$sql_almox = "SELECT a.* FROM TB_ALMOXARIFADOS a 
              INNER JOIN TB_ALMOXARIFADO_RESPONSAVEL ar ON a.ID = ar.ID_Almoxarifado 
              WHERE a.Ativo = 1 AND ar.ID_Usuario = ? AND ar.Ativo = 1";
$stmt_almox = sqlsrv_query($conn, $sql_almox, [$_SESSION['usuarioId']]);
if ($stmt_almox) {
    while ($row = sqlsrv_fetch_array($stmt_almox, SQLSRV_FETCH_ASSOC)) {
        $almoxarifados[] = $row;
    }
}

if (empty($almoxarifados)) {
    $erro = "Você não é responsável por nenhum almoxarifado.";
}

/* ==========================
   CALCULAR SALDO ATUAL (GERAL OU ESPECIFICO)
========================== */

$sql_saldo = "

SELECT ISNULL(SUM(

    CASE

        WHEN TipoMovimento = 'E'
            THEN Quantidade

        WHEN TipoMovimento = 'S'
            THEN -Quantidade

        WHEN TipoMovimento = 'A'
            THEN Quantidade

        ELSE 0

    END

),0) AS Saldo

FROM TB_MOVIMENTO

WHERE ID_Produto = ?
";

$stmt_saldo = sqlsrv_query(
    $conn,
    $sql_saldo,
    [$id_produto]
);

if ($stmt_saldo) {

    $rowSaldo = sqlsrv_fetch_array(
        $stmt_saldo,
        SQLSRV_FETCH_ASSOC
    );

    $saldoAtual = floatval($rowSaldo['Saldo']);
}

/* ==========================
   PROCESSAR AJUSTE
========================== */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $novoSaldo = str_replace(',', '.', $_POST['novo_saldo']);
    $novoSaldo = floatval($novoSaldo);
    $motivo = trim($_POST['motivo']);
    $id_almox = intval($_POST['almoxarifado'] ?? 0);

    if ($novoSaldo < 0 || $id_almox <= 0) {
        $erro = "Saldo ou almoxarifado inválido.";
    } else {
        // Calculate current stock for the selected almoxarifado
        $sql_saldo_almox = "
            SELECT ISNULL(SUM(
                CASE
                    WHEN TipoMovimento = 'E' THEN Quantidade
                    WHEN TipoMovimento = 'S' THEN -Quantidade
                    WHEN TipoMovimento = 'A' THEN Quantidade
                    ELSE 0
                END
            ),0) AS Saldo
            FROM TB_MOVIMENTO
            WHERE ID_Produto = ? AND ID_Almoxarifado = ?
        ";
        $stmt_saldo_almox = sqlsrv_query($conn, $sql_saldo_almox, [$id_produto, $id_almox]);
        $saldoAlmoxAtual = 0;
        if ($stmt_saldo_almox && $r = sqlsrv_fetch_array($stmt_saldo_almox, SQLSRV_FETCH_ASSOC)) {
            $saldoAlmoxAtual = floatval($r['Saldo']);
        }

        // diferença real
        $diferenca = $novoSaldo - $saldoAlmoxAtual;

        if ($diferenca == 0) {
            $erro = "O novo saldo é igual ao saldo atual deste almoxarifado.";
        } else {
            try {
                sqlsrv_begin_transaction($conn);

                /* ==========================
                   INSERE MOVIMENTO AJUSTE
                ========================== */
                $sql_insert = "
                    INSERT INTO TB_MOVIMENTO
                    (ID_Produto, TipoMovimento, Quantidade, Observacao, ID_Almoxarifado)
                    VALUES (?, ?, ?, ?, ?)
                ";

                $params = [
                    $id_produto,
                    'A',
                    $diferenca,
                    'AJUSTE DE SALDO: ' . $motivo,
                    $id_almox
                ];

                $stmt_insert = sqlsrv_query($conn, $sql_insert, $params);

                if (!$stmt_insert) {

                    throw new Exception(
                        print_r(sqlsrv_errors(), true)
                    );
                }

                sqlsrv_commit($conn);

                // atualiza saldo exibido
                $saldoAtual = $novoSaldo;

                $sucesso = "Saldo ajustado com sucesso.";
            } catch (Exception $e) {

                sqlsrv_rollback($conn);

                $erro = $e->getMessage();
            }
        }
    }
}
