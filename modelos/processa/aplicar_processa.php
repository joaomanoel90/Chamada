<?php
require_once(dirname(__DIR__, 2) . '/config.php');
require_once(DBAPI);
require_once(ABSPATH . 'inc/chamada.php');
ch_db();
ch_require_gestor();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../index.php');
    exit;
}
if (!ch_csrf_ok()) {
    header('Location: ../index.php');
    exit;
}
if (!mod_tem_tabela()) {
    ch_flash('Execute a migração de modelos antes de usar esta tela.', 'danger');
    header('Location: ../index.php');
    exit;
}

$idModelo = (int)($_POST['id_modelo'] ?? 0);
$turmasIds = array_map('intval', (array)($_POST['turmas'] ?? []));
if (!mod_dados($idModelo)) {
    ch_flash('Modelo não encontrado.', 'danger');
    header('Location: ../index.php');
    exit;
}
if (!$turmasIds) {
    ch_flash('Selecione ao menos uma turma.', 'danger');
    header('Location: ../index.php');
    exit;
}

sqlsrv_begin_transaction(ch_db());
$resultado = mod_aplicar($idModelo, $turmasIds);
if ($resultado['aplicadas'] <= 0) {
    sqlsrv_rollback(ch_db());
    ch_flash('Nenhuma turma foi alterada. Verifique seu acesso às turmas selecionadas.', 'danger');
} else {
    sqlsrv_commit(ch_db());
    $msg = $resultado['aplicadas'] . ' turma(s) atualizada(s) com sucesso.';
    if ($resultado['avisos']) $msg .= ' Avisos: ' . implode(' ', $resultado['avisos']);
    ch_flash($msg, $resultado['avisos'] ? 'warning' : 'success');
}
header('Location: ../index.php');
exit;
