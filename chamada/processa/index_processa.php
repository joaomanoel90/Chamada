<?php
require_once(dirname(__DIR__, 2) . '/config.php');
require_once(DBAPI);
require_once(ABSPATH . 'inc/chamada.php');
ch_db();
$filtro = ch_filtro_turma('t');
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
$pendentes = [];
$confirmados = [];
$equipe = [];
$podeRetificar = false;

if ($turmaId > 0) {
    if (!ch_pode_turma($turmaId)) {
        ch_flash('Você não tem acesso a essa turma.', 'danger');
        header('Location: index.php');
        exit;
    }
    $turma = ch_turma($turmaId);
    $equipe = ch_turma_professores($turmaId);
    $bloqueio = ch_bloqueio_chamada($data, $turma);
    $podeRetificar = ch_pode_retificar($turmaId);

    /* A lista é de quem pertencia à turma NAQUELA data, não de quem pertence
       hoje. Sem isto, um aluno transferido apareceria na turma nova em datas
       anteriores à transferência e sumiria do histórico da turma antiga. */
    $naData = tr_filtro_na_data($turmaId, $data, 'a');

    /* CONFIRMADA_EM vira texto no banco: o driver sqlsrv devolveria DateTime. */
    $alunos = ch_rows(
        'SELECT a.ID, a.MATRICULA, a.NOME,
                c.ID AS ID_CHAMADA, c.STATUS, c.JUSTIFICATIVA,
                CONVERT(varchar(16), c.CONFIRMADA_EM, 120) AS CONFIRMADA_EM,
                uc.NOME AS CONFIRMADA_POR_NOME,
                CONVERT(varchar(10), a.NASCIMENTO, 23) AS NASCIMENTO,
                (SELECT COUNT(*) FROM TB_CHAMADA_RETIFICACOES r WHERE r.ID_CHAMADA = c.ID) AS RETIFICACOES
         FROM TB_ALUNOS a
         LEFT JOIN TB_CHAMADA c ON c.ID_ALUNO = a.ID AND c.DATA = ?
         LEFT JOIN TB_USUARIOS uc ON uc.ID = c.CONFIRMADA_POR
         WHERE a.ATIVO = 1' . $naData['sql'] . '
         ORDER BY a.NOME',
        array_merge([$data], $naData['params'])
    );

    foreach ($alunos as $a) {
        if (!empty($a['STATUS'])) {
            $jaLancada = true;
            $resumo[$a['STATUS']] = ($resumo[$a['STATUS']] ?? 0) + 1;
            $confirmados[] = $a;
        } else {
            $pendentes[] = $a;
        }
    }
}

$observacoesDoDia = [];
if ($turmaId > 0) {
    $observacoesDoDia = ch_rows(
        'SELECT o.ID_ALUNO, COUNT(*) AS QTDE
         FROM TB_OBSERVACOES o
         INNER JOIN TB_ALUNOS a ON a.ID = o.ID_ALUNO
         WHERE o.DATA = ?' . $naData['sql'] . '
         GROUP BY o.ID_ALUNO',
        array_merge([$data], $naData['params'])
    );
}
$obsPorAluno = [];
foreach ($observacoesDoDia as $o) { $obsPorAluno[(int)$o['ID_ALUNO']] = (int)$o['QTDE']; }
