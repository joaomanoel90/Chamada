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

$id = (int)($_POST['id'] ?? 0);
$p = ch_row('SELECT ID, NOME, ATIVO FROM TB_PERIODOS WHERE ID = ?', [$id]);

if (!$p) {
    ch_flash('Período não encontrado.', 'danger');
} elseif ((int)$p['ATIVO'] === 1 && ch_val('SELECT ID FROM TB_TURMAS WHERE ID_PERIODO = ? AND ATIVO = 1', [$id])) {
    ch_flash('Este período está em uso por turmas ativas e não pode ser inativado.', 'warning');
} else {
    $novo = (int)$p['ATIVO'] === 1 ? 0 : 1;
    if (ch_exec('UPDATE TB_PERIODOS SET ATIVO = ? WHERE ID = ?', [$novo, $id])) {
        ch_flash('Período ' . ($novo ? 'reativado' : 'inativado') . ' com sucesso.');
    } else {
        ch_flash('Não foi possível alterar a situação do período.', 'danger');
    }
}

header('Location: ../index.php');
exit;
