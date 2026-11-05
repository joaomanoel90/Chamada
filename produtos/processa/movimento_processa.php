<?php
require_once('../config.php');
require_once(DBAPI);

$conn = open_database();
if (!$conn) {
    die("Erro de conexão com o banco de dados.");
}

// Buscar produtos para filtro
$produtos = array();
$stmt_produtos = sqlsrv_query($conn, "SELECT ID, Descricao FROM TB_PRODUTOS ORDER BY Descricao");
while ($row = sqlsrv_fetch_array($stmt_produtos, SQLSRV_FETCH_ASSOC)) {
    $produtos[] = $row;
}

// Buscar fornecedores para filtro
$fornecedores = array();
$stmt_fornecedores = sqlsrv_query($conn, "SELECT ID, Fornecedor FROM TB_FORNECEDORES ORDER BY Fornecedor");
while ($row = sqlsrv_fetch_array($stmt_fornecedores, SQLSRV_FETCH_ASSOC)) {
    $fornecedores[] = $row;
}

// Montar query de movimentações usando a view
$movimentacoes = array();
$sql = "SELECT * FROM VIEW_MOVIMENTOS_PRODUTOS WHERE 1=1 
        AND EXISTS (
            SELECT 1 FROM TB_ALMOXARIFADO_RESPONSAVEL ar 
            WHERE ar.ID_Almoxarifado = VIEW_MOVIMENTOS_PRODUTOS.ID_Almoxarifado 
            AND ar.ID_Usuario = ? 
            AND ar.Ativo = 1
        )"; // 1=1 para filtros opcionais

$params = array($_SESSION['usuarioId']);

// Filtros opcionais
if (isset($_GET['id_produto']) && intval($_GET['id_produto']) > 0) {
    $sql .= " AND ID_Produto = ?";
    $params[] = intval($_GET['id_produto']);
}

if (isset($_GET['id_fornecedor']) && intval($_GET['id_fornecedor']) > 0) {
    $sql .= " AND ID_Fornecedor = ?";
    $params[] = intval($_GET['id_fornecedor']);
}

$sql .= " ORDER BY DataMovimento  DESC"; // usar o nome da coluna da view

$stmt = sqlsrv_query($conn, $sql, $params);
if ($stmt === false) {
    die("Erro ao buscar movimentações: " . print_r(sqlsrv_errors(), true));
}

while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
    $movimentacoes[] = $row;
}
