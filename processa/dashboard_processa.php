<?php
/* Dashboard do sistema de Chamada de Alunos.
   Substituiu o antigo dashboard de pedidos do SupriADM. */
require_once(dirname(__DIR__) . '/config.php');
require_once(DBAPI);
require_once(ABSPATH . 'inc/chamada.php');
ch_db();

$hoje      = ch_hoje();
$ano       = (int)date('Y');
$mes       = (int)date('n');
$inicioMes = sprintf('%04d-%02d-01', $ano, $mes);
$filtro    = ch_filtro_turma('t');
$p         = $filtro['params'];

/* ------------------------------------------------------------- totais */
$turmasAtivas = (int)ch_val(
    'SELECT COUNT(*) FROM TB_TURMAS t WHERE t.ATIVO = 1' . $filtro['sql'], $p
);

$alunosAtivos = (int)ch_val(
    'SELECT COUNT(*) FROM TB_ALUNOS a
     INNER JOIN TB_TURMAS t ON t.ID = a.ID_TURMA
     WHERE a.ATIVO = 1' . $filtro['sql'], $p
);

$unidadesAtivas = count(ch_unidades_ids());

/* ------------------------------------------- chamada de hoje por turma */
$turmasHoje = ch_rows(
    'SELECT t.ID, t.NOME, t.ANO_LETIVO, t.ID_UNIDADE,
            u.unidade AS UNIDADE, p.NOME AS PERIODO, p.HORA_INICIO, p.HORA_FIM,
            (SELECT COUNT(*) FROM TB_ALUNOS a WHERE a.ID_TURMA = t.ID AND a.ATIVO = 1) AS ALUNOS,
            (SELECT COUNT(*) FROM TB_CHAMADA c
             INNER JOIN TB_ALUNOS a2 ON a2.ID = c.ID_ALUNO
             WHERE a2.ID_TURMA = t.ID AND c.DATA = ?) AS LANCADAS
     FROM TB_TURMAS t
     INNER JOIN TB_UNIDADES u ON u.id = t.ID_UNIDADE
     INNER JOIN TB_PERIODOS p ON p.ID = t.ID_PERIODO
     WHERE t.ATIVO = 1 AND t.ANO_LETIVO = ?' . $filtro['sql'] . '
     ORDER BY u.unidade, t.NOME',
    array_merge([$hoje, $ano], $p)
);

$pendentes = [];
$bloqueadasHoje = 0;
$feitasHoje = 0;
foreach ($turmasHoje as $t) {
    if ((int)$t['ALUNOS'] === 0) continue;
    if ((int)$t['LANCADAS'] > 0) { $feitasHoje++; continue; }
    $motivo = ch_bloqueio_chamada($hoje, $t);
    if ($motivo) { $bloqueadasHoje++; continue; }
    $pendentes[] = $t;
}

/* ------------------------------------------------- frequência do mês */
$resumoMes = ch_row(
    "SELECT COUNT(*) AS DIAS,
            SUM(CASE WHEN c.STATUS IN ('P','A') THEN 1 ELSE 0 END) AS PRESENCAS
     FROM TB_CHAMADA c
     INNER JOIN TB_ALUNOS a ON a.ID = c.ID_ALUNO
     INNER JOIN TB_TURMAS t ON t.ID = a.ID_TURMA
     WHERE c.DATA >= ? AND c.DATA <= ?" . $filtro['sql'],
    array_merge([$inicioMes, $hoje], $p)
);
$diasMes      = (int)($resumoMes['DIAS'] ?? 0);
$presencasMes = (int)($resumoMes['PRESENCAS'] ?? 0);
$freqMes      = $diasMes > 0 ? round($presencasMes * 100 / $diasMes, 1) : null;

$obsMes = (int)ch_val(
    'SELECT COUNT(*) FROM TB_OBSERVACOES o
     INNER JOIN TB_ALUNOS a ON a.ID = o.ID_ALUNO
     INNER JOIN TB_TURMAS t ON t.ID = a.ID_TURMA
     WHERE o.DATA >= ? AND o.DATA <= ?' . $filtro['sql'],
    array_merge([$inicioMes, $hoje], $p)
);

/* ------------------------------------------- série mensal do gráfico */
$serie = ch_rows(
    "SELECT MONTH(c.DATA) AS MES, COUNT(*) AS DIAS,
            SUM(CASE WHEN c.STATUS IN ('P','A') THEN 1 ELSE 0 END) AS PRESENCAS
     FROM TB_CHAMADA c
     INNER JOIN TB_ALUNOS a ON a.ID = c.ID_ALUNO
     INNER JOIN TB_TURMAS t ON t.ID = a.ID_TURMA
     WHERE c.DATA >= ? AND c.DATA <= ?" . $filtro['sql'] . '
     GROUP BY MONTH(c.DATA) ORDER BY MONTH(c.DATA)',
    array_merge([$ano . '-01-01', $ano . '-12-31'], $p)
);
$porMes = [];
foreach ($serie as $s) { $porMes[(int)$s['MES']] = $s; }

$labelsFreq = [];
$dadosFreq  = [];
for ($m = 1; $m <= 12; $m++) {
    if (!isset($porMes[$m])) continue;
    $d = (int)$porMes[$m]['DIAS'];
    $labelsFreq[] = substr(ch_mes_nome($m), 0, 3);
    $dadosFreq[]  = $d > 0 ? round((int)$porMes[$m]['PRESENCAS'] * 100 / $d, 1) : 0;
}

/* --------------------------------------------------- próximos feriados */
$idsUnidade = ch_unidades_ids();
$proximosFeriados = [];
if ($idsUnidade) {
    $ph = implode(',', array_fill(0, count($idsUnidade), '?'));
    $proximosFeriados = ch_rows(
        'SELECT TOP 5 CONVERT(varchar(10), DATA, 23) AS DATA, DESCRICAO, TIPO, HORA_INICIO, HORA_FIM
         FROM TB_FERIADOS
         WHERE DATA >= ? AND (ID_UNIDADE IS NULL OR ID_UNIDADE IN (' . $ph . '))
         ORDER BY DATA',
        array_merge([$hoje], $idsUnidade)
    );
}

/* -------------------------------------------- observações recentes */
$obsRecentes = ch_rows(
    'SELECT TOP 5 o.ID, CONVERT(varchar(10), o.DATA, 23) AS DATA, o.CATEGORIA, o.TEXTO,
            a.ID AS ID_ALUNO, a.NOME AS ALUNO, t.NOME AS TURMA, us.NOME AS AUTOR
     FROM TB_OBSERVACOES o
     INNER JOIN TB_ALUNOS a ON a.ID = o.ID_ALUNO
     INNER JOIN TB_TURMAS t ON t.ID = a.ID_TURMA
     INNER JOIN TB_USUARIOS us ON us.ID = o.ID_USUARIO
     WHERE 1 = 1' . $filtro['sql'] . '
     ORDER BY o.DATA DESC, o.ID DESC',
    $p
);

/* ------------------------------------------------ relatórios pendentes */
$relatoriosPendentes = (int)ch_val(
    'SELECT COUNT(*) FROM TB_ALUNOS a
     INNER JOIN TB_TURMAS t ON t.ID = a.ID_TURMA
     LEFT  JOIN TB_RELATORIOS_ANUAIS r ON r.ID_ALUNO = a.ID AND r.ANO = ?
     WHERE a.ATIVO = 1 AND t.ANO_LETIVO = ? AND r.ID IS NULL' . $filtro['sql'],
    array_merge([$ano, $ano], $p)
);
