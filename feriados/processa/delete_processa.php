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
$f = ch_row('SELECT ID, DESCRICAO FROM TB_FERIADOS WHERE ID = ?', [$id]);

if (!$f) {
    ch_flash('Feriado não encontrado.', 'danger');
} elseif (ch_exec('DELETE FROM TB_FERIADOS WHERE ID = ?', [$id])) {
    ch_flash('Feriado excluído. As chamadas já lançadas foram mantidas.');
} else {
    ch_flash('Não foi possível excluir o feriado.', 'danger');
}

header('Location: ../index.php');
exit;
