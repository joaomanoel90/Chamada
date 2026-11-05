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
$a = ch_row('SELECT ID, NOME, ATIVO FROM TB_ALUNOS WHERE ID = ?', [$id]);

if (!$a) {
    ch_flash('Aluno não encontrado.', 'danger');
} else {
    $novo = (int)$a['ATIVO'] === 1 ? 0 : 1;
    if (ch_exec('UPDATE TB_ALUNOS SET ATIVO = ? WHERE ID = ?', [$novo, $id])) {
        ch_flash('Aluno ' . ($novo ? 'reativado' : 'inativado') . '. O histórico foi preservado.');
    } else {
        ch_flash('Não foi possível alterar a situação do aluno.', 'danger');
    }
}

header('Location: ../index.php');
exit;
