<?php
require_once('../config.php');
require_once(DBAPI);

$conn = open_database();
if (!$conn) {
    die("Erro de conexão com o banco de dados.");
}

// 🔧 Ajusta identity se der erro
function adjustIdentitySeed($conn)
{
    $sql_max_id = 'SELECT MAX(id) AS max_id FROM TB_MOVIMENTO';
    $stmt_max_id = sqlsrv_query($conn, $sql_max_id);

    if ($stmt_max_id === false) {
        return;
    }

    $row = sqlsrv_fetch_array($stmt_max_id, SQLSRV_FETCH_ASSOC);
    $max_id = $row['max_id'] ?? 0;

    $sql_checkident = "DBCC CHECKIDENT ('TB_MOVIMENTO', RESEED, $max_id)";
    sqlsrv_query($conn, $sql_checkident);
}

// ==========================
// 🔍 FORNECEDORES
// ==========================
$fornecedores = [];

$sql_fornecedores = "SELECT * FROM TB_FORNECEDORES ORDER BY Fornecedor";
$stmt_fornecedores = sqlsrv_query($conn, $sql_fornecedores);

if ($stmt_fornecedores === false) {
    die(print_r(sqlsrv_errors(), true));
}

while ($row = sqlsrv_fetch_array($stmt_fornecedores, SQLSRV_FETCH_ASSOC)) {
    $fornecedores[] = $row;
}

$id_produto_req = intval($_GET['id'] ?? $_POST['id_produto'] ?? 0);

// ==========================
// 🔍 ALMOXARIFADOS DO RESPONSÁVEL (Vinculados ao Produto)
// ==========================
$almoxarifados = [];
if ($id_produto_req > 0) {
    $sql_almox = "SELECT a.* FROM TB_ALMOXARIFADOS a 
                  INNER JOIN TB_ALMOXARIFADO_RESPONSAVEL ar ON a.ID = ar.ID_Almoxarifado 
                  INNER JOIN TB_PRODUTO_ALMOXARIFADO pa ON a.ID = pa.ID_Almoxarifado
                  WHERE a.Ativo = 1 AND ar.ID_Usuario = ? AND ar.Ativo = 1
                  AND pa.ID_Produto = ? AND pa.Ativo = 1";
    $stmt_almox = sqlsrv_query($conn, $sql_almox, [$_SESSION['usuarioId'], $id_produto_req]);
    if ($stmt_almox) {
        while ($row = sqlsrv_fetch_array($stmt_almox, SQLSRV_FETCH_ASSOC)) {
            $almoxarifados[] = $row;
        }
    }
}

if (empty($almoxarifados)) {
    $erro = "Você não é responsável por nenhum almoxarifado que possua este produto vinculado.";
}

// ==========================
$produto = null;
$erro = '';

// ==========================
// 🔥 POST → ENTRADA
// ==========================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $id_produto    = intval($_POST['id_produto'] ?? 0);
    $quantidade    = floatval($_POST['quantidade'] ?? 0);
    $id_fornecedor = intval($_POST['id_fornecedor'] ?? 0);
    $id_almox      = intval($_POST['almoxarifado'] ?? 0);
    $numero_nota = isset($_POST['nota'])
        ? preg_replace('/\D/', '', $_POST['nota'])
        : '';

    if ($id_produto > 0 && $quantidade > 0 && $id_almox > 0) {

        // Verifica se o produto faz parte do almoxarifado selecionado
        $sql_check_almox = "SELECT COUNT(*) as qtd FROM TB_PRODUTO_ALMOXARIFADO WHERE ID_Produto = ? AND ID_Almoxarifado = ? AND Ativo = 1";
        $stmt_check = sqlsrv_query($conn, $sql_check_almox, [$id_produto, $id_almox]);
        $row_check = sqlsrv_fetch_array($stmt_check, SQLSRV_FETCH_ASSOC);

        if ($row_check['qtd'] == 0) {
            $erro = "Este produto não faz parte do almoxarifado selecionado. A entrada não é permitida.";
        } else {
            try {
                sqlsrv_begin_transaction($conn);

                // 🔥 REGISTRA ENTRADA
                $sql = "INSERT INTO TB_MOVIMENTO 
                    (ID_Produto, ID_Fornecedor, Numero_Nota, TipoMovimento, Quantidade, ID_Almoxarifado) 
                    VALUES (?, ?, ?, 'E', ?, ?)";

            $params = [
                $id_produto,
                $id_fornecedor,
                $numero_nota,
                $quantidade,
                $id_almox
            ];

            $stmt = sqlsrv_query($conn, $sql, $params);

            if (!$stmt) {
                throw new Exception(print_r(sqlsrv_errors(), true));
            }

            sqlsrv_commit($conn);

            header("location:index.php");
            exit;
            } catch (Exception $e) {
                sqlsrv_rollback($conn);
                adjustIdentitySeed($conn);
                $erro = "Erro ao registrar entrada: " . $e->getMessage();
            }
        }
    } else {
        $erro = "Informe produto, quantidade válida e almoxarifado.";
    }
}

// ==========================
// 🔍 GET → PRODUTO
// ==========================
elseif (isset($_GET['id'])) {

    $id_produto = intval($_GET['id']);

    $sql = "SELECT * FROM TB_PRODUTOS WHERE ID = ?";
    $stmt = sqlsrv_query($conn, $sql, [$id_produto]);

    if ($stmt && ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC))) {
        $produto = $row;
    } else {
        $erro = "Produto não encontrado.";
    }
} else {
    $erro = "ID do produto não informado.";
}
