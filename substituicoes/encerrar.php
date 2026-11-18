<?php
require_once(dirname(__DIR__) . '/config.php');
require_once(DBAPI);
require_once(ABSPATH . 'inc/chamada.php');
ch_db();
ch_require_gestor();

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !ch_csrf_ok()) {
    header('Location: index.php');
    exit;
}

$id = (int)($_POST['id'] ?? 0);
$linha = ch_row(
    "SELECT tp.ID, t.ID_UNIDADE
       FROM TB_TURMA_PROFESSORES tp
       INNER JOIN TB_TURMAS t ON t.ID = tp.ID_TURMA
      WHERE tp.ID = ? AND tp.PAPEL = 'SUBSTITUTO'",
    [$id]
);
if (!$linha || !ch_pode_unidade((int)$linha['ID_UNIDADE'])) {
    ch_flash('Substituição não encontrada ou fora da sua unidade.', 'danger');
    header('Location: index.php');
    exit;
}

if (ch_exec('UPDATE TB_TURMA_PROFESSORES SET ATIVO = 0 WHERE ID = ?', [$id])) {
    ch_flash('Substituição encerrada.');
} else {
    ch_flash('Não foi possível encerrar a substituição.', 'danger');
}
header('Location: index.php');
exit;
