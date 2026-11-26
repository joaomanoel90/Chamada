<?php
require_once(dirname(__DIR__, 2) . '/config.php');
require_once(DBAPI);
require_once(ABSPATH . 'inc/chamada.php');
ch_db();
ch_require_rede();

$conn = ch_db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!ch_csrf_ok()) {
        header('Location: index.php');
        exit;
    }

    // Remover tags e espaços extras
    $unidade = strip_tags(trim((string)($_POST['unidade'] ?? '')));
    if ($unidade === '' || mb_strlen($unidade) > 230) {
        ch_flash('Informe o nome da unidade (até 230 caracteres).', 'danger');
        header('Location: add.php');
        exit;
    }

    // Iniciar transação
    sqlsrv_begin_transaction($conn);

    try {
        // Preparar e executar a consulta
        $sql = 'INSERT INTO TB_UNIDADES (unidade) OUTPUT INSERTED.id AS ID VALUES(?)';
        $params = array($unidade);

        $stmt = sqlsrv_query($conn, $sql, $params);

        // Verificar se a execução foi bem-sucedida
        if ($stmt === false) {
            throw new Exception('Erro ao executar a consulta.');
        }
        $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
        sqlsrv_free_stmt($stmt);
        if (!$row) {
            throw new Exception('Erro ao executar a consulta.');
        }

        // Confirmar a transação
        sqlsrv_commit($conn);
        ch_auditar('Unidades', 'Criar unidade', 'UNIDADE', (int)$row['ID'], ['NOME' => $unidade]);

        // Redirecionar após sucesso
        header("location:index.php");
        exit();
    } catch (Exception $e) {
        // Se houver erro, reverter a transação
        sqlsrv_rollback($conn);

        // Opcional: Exibir mensagem de erro
        echo "Erro: " . $e->getMessage();
    }
}
?>
