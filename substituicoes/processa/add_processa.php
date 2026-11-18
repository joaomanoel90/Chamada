<?php
require_once(dirname(__DIR__, 2) . '/config.php');
require_once(DBAPI);
require_once(ABSPATH . 'inc/chamada.php');
ch_db();
ch_require_gestor();
require_once(__DIR__ . '/salvar_processa.php');

$erros = [];
$op = subst_opcoes_form((int)($_GET['turma'] ?? 0));
$d = [
    'ID_TURMA'        => (int)($_GET['turma'] ?? 0),
    'ID_SUBSTITUIDO'  => (int)($_GET['coberto'] ?? 0),
    'ID_USUARIO'      => 0,
    'DATA_INICIO'     => ch_hoje(),
    'DATA_FIM'        => ch_hoje(),
    'MOTIVO'          => '',
    'VER_OBSERVACOES' => 0,
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!ch_csrf_ok()) {
        header('Location: add.php');
        exit;
    }
    [$d, $erros] = subst_validar($_POST, $op);
    if (!$erros) {
        sqlsrv_begin_transaction(ch_db());
        $ok = subst_salvar($d);
        if ($ok) {
            sqlsrv_commit(ch_db());
            ch_flash('Substituição cadastrada com sucesso.');
            header('Location: index.php');
            exit;
        }
        sqlsrv_rollback(ch_db());
        $erros[] = 'Não foi possível salvar a substituição.';
    }
}
