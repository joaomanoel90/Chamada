<?php
require_once('../config.php');
require_once(DBAPI);

$conn = open_database();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = strip_tags(trim($_POST['id'] ?? ''));
    $fornecedor = strip_tags(trim($_POST['fornecedor'] ?? ''));
    $cnpj = strip_tags(trim($_POST['cnpj'] ?? ''));


    if (!$id) {
        echo "ID não informado.";
        exit;
    }

    // Iniciar a transação
    sqlsrv_begin_transaction($conn);

    try {
        $sql_check = "SELECT * FROM TB_FORNECEDORES WHERE ID = ?";
        $params_check = [$id];
        $stmt_check = sqlsrv_query($conn, $sql_check, $params_check);
        $fornecedor_existente = sqlsrv_fetch_array($stmt_check, SQLSRV_FETCH_ASSOC);

        if (!$fornecedor_existente) {
            throw new Exception("Fornecedor não encontrada.");
        }

        $sql_update = "UPDATE TB_FORNECEDORES SET Fornecedor = ?, CNPJ = ? WHERE ID = ?";
        $params_update = [$fornecedor, $cnpj, $id];

        $stmt_update = sqlsrv_query($conn, $sql_update, $params_update);

        if ($stmt_update === false) {
            throw new Exception("Erro ao atualizar a fornecedor: " . print_r(sqlsrv_errors(), true));
        }

        // Commit da transação
        sqlsrv_commit($conn);
        header("Location: index.php?msg=sucesso");
        exit;

    } catch (Exception $e) {
        // Rollback em caso de erro
        sqlsrv_rollback($conn);
        echo "Erro: " . $e->getMessage();
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['id'])) {
    $id = $_GET['id'];

    // Consulta Fornecedor para preenchimento do formulário de edição
    $sql = "SELECT * FROM TB_FORNECEDORES WHERE ID = ?";
    $params = [$id];
    $stmt_select = sqlsrv_query($conn, $sql, $params);

    if ($stmt_select === false) {
        die(print_r(sqlsrv_errors(), true));
    }

    $fornecedor = sqlsrv_fetch_array($stmt_select, SQLSRV_FETCH_ASSOC);

    if (!$fornecedor) {
        die("Fornecedor não encontrada.");
    }
}
?>
