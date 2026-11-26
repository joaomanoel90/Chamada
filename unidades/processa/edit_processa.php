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
    $id = (int)($_POST['id'] ?? 0);
    $unidade = strip_tags(trim((string)($_POST['unidade'] ?? '')));

    if ($id <= 0) {
        echo "ID não informado.";
        exit;
    }
    if ($unidade === '' || mb_strlen($unidade) > 230) {
        echo "Informe o nome da unidade (até 230 caracteres).";
        exit;
    }

    // Iniciar a transação
    sqlsrv_begin_transaction($conn);

    try {
        $sql_check = "SELECT * FROM TB_UNIDADES WHERE id = ?";
        $params_check = [$id];
        $stmt_check = sqlsrv_query($conn, $sql_check, $params_check);
        $unidade_existente = sqlsrv_fetch_array($stmt_check, SQLSRV_FETCH_ASSOC);

        if (!$unidade_existente || !ch_pode_unidade($id)) {
            throw new Exception("Unidade não encontrada.");
        }

        // Atualiza a unidade
        $sql_update = "UPDATE TB_UNIDADES SET unidade = ? WHERE id = ?";
        $params_update = [$unidade, $id];

        $stmt_update = sqlsrv_query($conn, $sql_update, $params_update);

        if ($stmt_update === false) {
            error_log('Erro ao atualizar a unidade: ' . print_r(sqlsrv_errors(), true));
            throw new Exception("Não foi possível atualizar a unidade.");
        }

        // Commit da transação
        sqlsrv_commit($conn);
        ch_auditar('Unidades', 'Atualizar unidade', 'UNIDADE', $id, [
            'ANTES' => (string)($unidade_existente['unidade'] ?? ''),
            'DEPOIS' => $unidade,
        ]);
        header("Location: index.php?msg=sucesso");
        exit;

    } catch (Exception $e) {
        // Rollback em caso de erro
        sqlsrv_rollback($conn);
        echo "Erro: " . $e->getMessage();
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['id'])) {
    $id = (int)$_GET['id'];

    // Consulta a unidade para preenchimento do formulário de edição
    $sql = "SELECT * FROM TB_UNIDADES WHERE id = ?";
    $params = [$id];
    $stmt_select = sqlsrv_query($conn, $sql, $params);

    if ($stmt_select === false) {
        error_log('Erro ao consultar a unidade: ' . print_r(sqlsrv_errors(), true));
        die("Não foi possível consultar a unidade.");
    }

    $unidade = sqlsrv_fetch_array($stmt_select, SQLSRV_FETCH_ASSOC);

    if (!$unidade || !ch_pode_unidade($id)) {
        die("Unidade não encontrada.");
    }
}
?>
