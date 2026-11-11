<?php
require_once(dirname(__DIR__, 2) . '/config.php');
require_once(DBAPI);
require_once(ABSPATH . 'inc/chamada.php');
ch_db();
ch_require_gestor();

$conn = ch_db();

if (!empty($_POST)) {
    // Remover tags e espaços extras
    $unidade = strip_tags(trim($_POST['unidade'] ?? ''));

    // Iniciar transação
    sqlsrv_begin_transaction($conn);

    try {
        // Preparar e executar a consulta
        $sql = 'INSERT INTO TB_UNIDADES (unidade) VALUES(?)';
        $params = array($unidade);

        $stmt = sqlsrv_query($conn, $sql, $params);

        // Verificar se a execução foi bem-sucedida
        if ($stmt === false) {
            throw new Exception('Erro ao executar a consulta.');
        }

        // Confirmar a transação
        sqlsrv_commit($conn);

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
