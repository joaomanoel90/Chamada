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

[$d, $erros] = mod_validar($_POST, (int)($_POST['id'] ?? 0));
if ($erros) {
    ch_flash(implode(' ', $erros), 'danger');
    header('Location: ../index.php' . ($d['ID'] ? '?id=' . (int)$d['ID'] : ''));
    exit;
}

sqlsrv_begin_transaction(ch_db());
$id = mod_salvar($d);
if ($id <= 0) {
    sqlsrv_rollback(ch_db());
    ch_flash('Não foi possível salvar o modelo.', 'danger');
} else {
    sqlsrv_commit(ch_db());
    ch_auditar('MODELO_TURMA', 'SALVAR', 'MODELO', $id, ['nome' => $d['NOME'], 'aulas_dia' => $d['AULAS_DIA']]);
    ch_flash('Modelo salvo.', 'success');
}
header('Location: ../index.php');
exit;
