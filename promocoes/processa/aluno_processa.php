<?php
require_once(dirname(__DIR__, 2) . '/config.php');
require_once(DBAPI);
require_once(ABSPATH . 'inc/chamada.php');
ch_db();
ch_require_gestor();

$alunoId = (int)($_GET['aluno_id'] ?? $_POST['aluno_id'] ?? 0);
$aluno = ch_aluno($alunoId);
if (!$aluno || (int)$aluno['ATIVO'] !== 1 || !ch_pode_turma((int)$aluno['ID_TURMA'])) {
    ch_flash('Aluno não encontrado, inativo ou fora da sua unidade.', 'danger');
    header('Location: ' . ch_url('alunos/index.php'));
    exit;
}
if (tr_pendente($alunoId) || tr_saida_externa_aberta($alunoId)) {
    ch_flash('Trate a transferência ou a saída da rede pendente antes de promover o aluno.', 'warning');
    header('Location: ' . ch_url('alunos/view.php?id=' . $alunoId));
    exit;
}
$destinos = ch_rows(
    'SELECT t.ID, t.NOME, t.ANO_LETIVO, t.CAPACIDADE, p.NOME AS PERIODO,
            (SELECT COUNT(*) FROM TB_ALUNOS x WHERE x.ID_TURMA = t.ID AND x.ATIVO = 1) AS OCUPADAS
     FROM TB_TURMAS t INNER JOIN TB_PERIODOS p ON p.ID = t.ID_PERIODO
     WHERE t.ATIVO = 1 AND t.ID_UNIDADE = ? AND t.ANO_LETIVO = ?
     ORDER BY t.NOME',
    [(int)$aluno['ID_UNIDADE'], (int)$aluno['ANO_LETIVO'] + 1]
);
