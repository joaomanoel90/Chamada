<?php
require_once(dirname(__DIR__, 2) . '/config.php');
require_once(DBAPI);
require_once(ABSPATH . 'inc/chamada.php');
ch_db();
ch_require_gestor();

$conn = ch_db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!ch_csrf_ok()) {
        header('Location: index.php');
        exit;
    }
    $id = strip_tags(trim($_POST['id'] ?? ''));
    $unidade = strip_tags(trim($_POST['unidade'] ?? ''));

    if (!$id) {
        echo "ID não informado.";
        exit;
    }

    // Iniciar a transação
    sqlsrv_begin_transaction($conn);

    try {
        $sql_check = "SELECT * FROM TB_UNIDADES WHERE id = ?";
        $params_check = [$id];
        $stmt_check = sqlsrv_query($conn, $sql_check, $params_check);
        $unidade_existente = sqlsrv_fetch_array($stmt_check, SQLSRV_FETCH_ASSOC);

        if (!$unidade_existente) {
            throw new Exception("Unidade não encontrada.");
        }

        // Atualiza a unidade
        $sql_update = "UPDATE TB_UNIDADES SET unidade = ? WHERE id = ?";
        $params_update = [$unidade, $id];

        $stmt_update = sqlsrv_query($conn, $sql_update, $params_update);

        if ($stmt_update === false) {
            throw new Exception("Erro ao atualizar a unidade: " . print_r(sqlsrv_errors(), true));
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

    // Consulta a unidade para preenchimento do formulário de edição
    $sql = "SELECT * FROM TB_UNIDADES WHERE id = ?";
    $params = [$id];
    $stmt_select = sqlsrv_query($conn, $sql, $params);

    if ($stmt_select === false) {
        die(print_r(sqlsrv_errors(), true));
    }

    $unidade = sqlsrv_fetch_array($stmt_select, SQLSRV_FETCH_ASSOC);

    if (!$unidade) {
        die("Unidade não encontrada.");
    }
}
?>
