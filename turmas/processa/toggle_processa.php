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

$id = (int)($_POST['id'] ?? 0);
$t = ch_row('SELECT ID, NOME, ATIVO FROM TB_TURMAS WHERE ID = ?', [$id]);

if (!$t) {
    ch_flash('Turma não encontrada.', 'danger');
} else {
    $novo = (int)$t['ATIVO'] === 1 ? 0 : 1;
    if (ch_exec('UPDATE TB_TURMAS SET ATIVO = ? WHERE ID = ?', [$novo, $id])) {
        ch_flash('Turma ' . ($novo ? 'reativada' : 'inativada') . '. O histórico de chamada foi preservado.');
    } else {
        ch_flash('Não foi possível alterar a situação da turma.', 'danger');
    }
}

header('Location: ../index.php');
exit;
