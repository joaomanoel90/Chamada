<?php
require_once(dirname(__DIR__, 2) . '/config.php');
require_once(DBAPI);
require_once(ABSPATH . 'inc/chamada.php');
ch_db();
ch_require_gestao_alunos();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../index.php');
    exit;
}

if (!ch_csrf_ok()) {
    header('Location: ../index.php');
    exit;
}

$id = (int)($_POST['id'] ?? 0);
$a = ch_row('SELECT ID, NOME, ATIVO FROM TB_ALUNOS WHERE ID = ?', [$id]);

/* Faltava a conferência de alcance: qualquer gestor conseguia inativar aluno
   de outra escola mandando o ID no POST. */
if (!$a) {
    ch_flash('Aluno não encontrado.', 'danger');
} elseif (!ch_pode_aluno_atual($id)) {
    ch_flash('Este aluno não está na sua escola.', 'danger');
} else {
    $novo = (int)$a['ATIVO'] === 1 ? 0 : 1;
    if ($novo === 1 && tr_saida_externa_aberta($id)) {
        ch_flash('O aluno tem saída da rede registrada. Use Retorno à rede para matriculá-lo novamente.', 'warning');
    } elseif (ch_exec('UPDATE TB_ALUNOS SET ATIVO = ? WHERE ID = ?', [$novo, $id])) {
        ch_flash('Aluno ' . ($novo ? 'reativado' : 'inativado') . '. O histórico foi preservado.');
    } else {
        ch_flash('Não foi possível alterar a situação do aluno.', 'danger');
    }
}

header('Location: ../index.php');
exit;
