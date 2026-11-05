<?php
require_once(dirname(__DIR__, 2) . '/config.php');
require_once(DBAPI);
require_once(ABSPATH . 'inc/chamada.php');
ch_db();
$filtro = ch_filtro_unidade('t');
$turmas = ch_rows(
    'SELECT t.ID, t.NOME, t.ANO_LETIVO, u.unidade AS UNIDADE, p.NOME AS PERIODO
     FROM TB_TURMAS t
     INNER JOIN TB_UNIDADES u ON u.id = t.ID_UNIDADE
     INNER JOIN TB_PERIODOS p ON p.ID = t.ID_PERIODO
     WHERE t.ATIVO = 1' . $filtro['sql'] . ' ORDER BY u.unidade, t.NOME',
    $filtro['params']
);

$turmaId = (int)($_GET['turma_id'] ?? ($turmas ? (int)$turmas[0]['ID'] : 0));
$data    = ch_data_iso($_GET['data'] ?? '', ch_hoje());

$turma = null;
$bloqueio = null;
$alunos = [];
$jaLancada = false;
$resumo = ['P' => 0, 'F' => 0, 'J' => 0, 'A' => 0];

if ($turmaId > 0) {
    if (!ch_pode_turma($turmaId)) {
        ch_flash('Você não tem acesso a essa turma.', 'danger');
        header('Location: index.php');
        exit;
    }
    $turma = ch_turma($turmaId);
    $bloqueio = ch_bloqueio_chamada($data, $turma);

    $alunos = ch_rows(
        'SELECT a.ID, a.MATRICULA, a.NOME,
                c.STATUS, c.JUSTIFICATIVA,
                CONVERT(varchar(10), a.NASCIMENTO, 23) AS NASCIMENTO
         FROM TB_ALUNOS a
         LEFT JOIN TB_CHAMADA c ON c.ID_ALUNO = a.ID AND c.DATA = ?
         WHERE a.ID_TURMA = ? AND a.ATIVO = 1
         ORDER BY a.NOME',
        [$data, $turmaId]
    );

    foreach ($alunos as $a) {
        if (!empty($a['STATUS'])) {
            $jaLancada = true;
            $resumo[$a['STATUS']] = ($resumo[$a['STATUS']] ?? 0) + 1;
        }
    }
}

$observacoesDoDia = [];
if ($turmaId > 0) {
    $observacoesDoDia = ch_rows(
        'SELECT o.ID_ALUNO, COUNT(*) AS QTDE
         FROM TB_OBSERVACOES o
         INNER JOIN TB_ALUNOS a ON a.ID = o.ID_ALUNO
         WHERE a.ID_TURMA = ? AND o.DATA = ?
         GROUP BY o.ID_ALUNO',
        [$turmaId, $data]
    );
}
$obsPorAluno = [];
foreach ($observacoesDoDia as $o) { $obsPorAluno[(int)$o['ID_ALUNO']] = (int)$o['QTDE']; }
