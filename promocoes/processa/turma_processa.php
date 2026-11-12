<?php
require_once(dirname(__DIR__, 2) . '/config.php');
require_once(DBAPI);
require_once(ABSPATH . 'inc/chamada.php');
ch_db();
ch_require_gestor();

$turmaId = (int)($_GET['turma_id'] ?? $_POST['turma_id'] ?? 0);
$origem = ch_row(
    'SELECT t.ID, t.ID_UNIDADE, t.NOME, t.ANO_LETIVO, t.ATIVO, u.unidade AS UNIDADE
     FROM TB_TURMAS t INNER JOIN TB_UNIDADES u ON u.id = t.ID_UNIDADE
     WHERE t.ID = ?',
    [$turmaId]
);
if (!$origem || (int)$origem['ATIVO'] !== 1 || !ch_pode_turma($turmaId)) {
    ch_flash('Turma não encontrada ou fora da sua unidade.', 'danger');
    header('Location: ' . ch_url('promocoes/index.php'));
    exit;
}

$destinos = ch_rows(
    'SELECT t.ID, t.NOME, t.ANO_LETIVO, t.CAPACIDADE, p.NOME AS PERIODO,
            (SELECT COUNT(*) FROM TB_ALUNOS a WHERE a.ID_TURMA = t.ID AND a.ATIVO = 1) AS OCUPADAS
     FROM TB_TURMAS t INNER JOIN TB_PERIODOS p ON p.ID = t.ID_PERIODO
     WHERE t.ATIVO = 1 AND t.ID_UNIDADE = ? AND t.ANO_LETIVO = ?
     ORDER BY t.NOME',
    [(int)$origem['ID_UNIDADE'], (int)$origem['ANO_LETIVO'] + 1]
);
$alunos = ch_rows(
    'SELECT ID, NOME, MATRICULA FROM TB_ALUNOS
     WHERE ID_TURMA = ? AND ATIVO = 1 ORDER BY NOME',
    [$turmaId]
);
