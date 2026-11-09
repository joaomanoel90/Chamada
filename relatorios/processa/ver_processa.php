<?php
require_once(dirname(__DIR__, 2) . '/config.php');
require_once(DBAPI);
require_once(ABSPATH . 'inc/chamada.php');
ch_db();
$alunoId = (int)($_POST['aluno_id'] ?? $_GET['aluno_id'] ?? 0);
$ano     = (int)($_POST['ano'] ?? $_GET['ano'] ?? (int)date('Y'));

$aluno = ch_exige_aluno($alunoId);

/* Transferências: o relatório é do ANO INTEIRO do aluno, mesmo que ele tenha
   mudado de escola no meio do ano. A chamada, as observações e o parecer já são
   consultados por ID_ALUNO, então nada precisa ser somado — só explicitar em
   que escola e turma ele estava em cada período. */
$vinculosAno = tr_vinculos_no_ano($alunoId, $ano);
$houveTransferencia = count($vinculosAno) > 1;

$inicio = $ano . '-01-01';
$fim    = $ano . '-12-31';

$totais = ch_row(
    "SELECT COUNT(*) AS DIAS,
            SUM(CASE WHEN STATUS = 'P' THEN 1 ELSE 0 END) AS P,
            SUM(CASE WHEN STATUS = 'F' THEN 1 ELSE 0 END) AS F,
            SUM(CASE WHEN STATUS = 'J' THEN 1 ELSE 0 END) AS J,
            SUM(CASE WHEN STATUS = 'A' THEN 1 ELSE 0 END) AS A
     FROM TB_CHAMADA WHERE ID_ALUNO = ? AND DATA >= ? AND DATA <= ?",
    [$alunoId, $inicio, $fim]
);

$porMes = ch_rows(
    "SELECT MONTH(DATA) AS MES, COUNT(*) AS DIAS,
            SUM(CASE WHEN STATUS = 'P' THEN 1 ELSE 0 END) AS P,
            SUM(CASE WHEN STATUS = 'F' THEN 1 ELSE 0 END) AS F,
            SUM(CASE WHEN STATUS = 'J' THEN 1 ELSE 0 END) AS J,
            SUM(CASE WHEN STATUS = 'A' THEN 1 ELSE 0 END) AS A
     FROM TB_CHAMADA WHERE ID_ALUNO = ? AND DATA >= ? AND DATA <= ?
     GROUP BY MONTH(DATA) ORDER BY MONTH(DATA)",
    [$alunoId, $inicio, $fim]
);
$meses = [];
foreach ($porMes as $m) { $meses[(int)$m['MES']] = $m; }

/* Relatório anual, inclusive na impressão: o substituto sem liberação não
   pode receber o texto dos outros professores por este caminho. */
$fObsRel = eq_obs_filtro_aluno($alunoId, 'o');
$observacoes = ch_rows(
    'SELECT o.ID, CONVERT(varchar(10), o.DATA, 23) AS DATA, o.CATEGORIA, o.TEXTO, u.NOME AS AUTOR
     FROM TB_OBSERVACOES o INNER JOIN TB_USUARIOS u ON u.ID = o.ID_USUARIO
     WHERE o.ID_ALUNO = ? AND o.DATA >= ? AND o.DATA <= ?' . $fObsRel['sql'] . '
     ORDER BY o.DATA, o.ID',
    array_merge([$alunoId, $inicio, $fim], $fObsRel['params'])
);
$obsOcultas = $fObsRel['sql'] !== '';
$avisoObs   = eq_obs_aviso_bloqueio();

$porCategoria = [];
foreach ($observacoes as $o) {
    $porCategoria[$o['CATEGORIA']] = ($porCategoria[$o['CATEGORIA']] ?? 0) + 1;
}

$faltasDetalhadas = ch_rows(
    "SELECT CONVERT(varchar(10), DATA, 23) AS DATA, STATUS, ISNULL(JUSTIFICATIVA, '') AS JUSTIFICATIVA
     FROM TB_CHAMADA WHERE ID_ALUNO = ? AND DATA >= ? AND DATA <= ? AND STATUS IN ('F', 'J')
     ORDER BY DATA",
    [$alunoId, $inicio, $fim]
);

$parecer = ch_row(
    'SELECT ID, ISNULL(PARECER, \'\') AS PARECER, ISNULL(ENCAMINHAMENTOS, \'\') AS ENCAMINHAMENTOS, ID_USUARIO
     FROM TB_RELATORIOS_ANUAIS WHERE ID_ALUNO = ? AND ANO = ?',
    [$alunoId, $ano]
);

$dias       = (int)($totais['DIAS'] ?? 0);
$presencas  = (int)($totais['P'] ?? 0) + (int)($totais['A'] ?? 0);
$frequencia = $dias > 0 ? round($presencas * 100 / $dias, 1) : null;
/* A turma pode ter vários professores: mostra a equipe inteira. */
$professor  = ch_turma_equipe_texto((int)$aluno['ID_TURMA']);
