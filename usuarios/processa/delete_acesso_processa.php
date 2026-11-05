<?php
require_once('../../config.php');
require_once(DBAPI);

$conn = open_database();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['id_acesso'])) {
    $id_acesso = intval($_POST['id_acesso']);

    // Atualiza o acesso: desativa e registra o horário
    $sql = "UPDATE TB_ACESSO SET ATIVO = 0, DESATIVADO = GETDATE() WHERE ID = ?";
    $params = array($id_acesso);

    sqlsrv_query($conn, $sql, $params);
}

header("Location: " . $_SERVER['HTTP_REFERER']);
exit;
?>
