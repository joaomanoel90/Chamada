<?php
require_once(dirname(__DIR__, 2) . '/config.php');
require_once(DBAPI);
require_once(ABSPATH . 'inc/chamada.php');
ch_db();
ch_require_relatorios();
$fTurma = (int)($_GET['turma_id'] ?? 0);
$fAno   = (int)($_GET['ano'] ?? (int)date('Y'));
/* Período do relatório: bimestral (B1..B6) ou semestral (S1/S2). */
$fPeriodo = strtoupper(trim((string)($_GET['periodo'] ?? ch_periodo_atual())));
if (!ch_periodo_valido($fPeriodo)) { $fPeriodo = ch_periodo_atual(); }
$intervalo = ch_periodo_intervalo($fAno, $fPeriodo);
$inicioPeriodo = $intervalo[0];
$fimPeriodo = $intervalo[1];
$rotuloPeriodo = ch_periodo_rotulo($fPeriodo);
$periodos = ch_periodos_relatorio();
$filtro = ch_filtro_turma_relatorios('tv');
$semUnidadesVinculadas = ch_secretario_escolar() && !ch_unidades_relatorios_ids();

$turmas = ch_rows(
    'SELECT tv.ID, tv.NOME, tv.SERIE, tv.ANO_LETIVO, u.unidade AS UNIDADE, p.NOME AS PERIODO
     FROM TB_TURMAS tv
     INNER JOIN TB_UNIDADES u ON u.id = tv.ID_UNIDADE
     INNER JOIN TB_PERIODOS p ON p.ID = tv.ID_PERIODO
     WHERE tv.ANO_LETIVO = ?' . $filtro['sql'] . ' ORDER BY u.unidade, tv.SERIE, tv.NOME, p.NOME',
    array_merge([$fAno], $filtro['params'])
);

/* Dentro do subselect o aluno é "a", então o filtro usa esse alias. */
$fObsIdx = eq_obs_filtro('o', 'a');
$fObsPermissaoIdx = ch_filtro_observacao_aluno('a');
$sql = 'SELECT a.ID, a.MATRICULA, a.NOME, tv.NOME AS TURMA, tv.ANO_LETIVO, u.unidade AS UNIDADE,
               (SELECT COUNT(*) FROM TB_CHAMADA c WHERE c.ID_ALUNO = a.ID AND c.DATA >= ? AND c.DATA <= ?) AS DIAS,
               (SELECT COUNT(*) FROM TB_OBSERVACOES o WHERE o.ID_ALUNO = a.ID AND o.DATA >= ? AND o.DATA <= ?' . $fObsIdx['sql'] . $fObsPermissaoIdx['sql'] . ') AS OBSERVACOES,
               CASE WHEN r.ID IS NULL THEN 0 ELSE 1 END AS TEM_PARECER
        FROM TB_ALUNOS a
        CROSS APPLY (
            SELECT TOP 1 t2.ID, t2.NOME, t2.ANO_LETIVO, t2.ID_UNIDADE
            FROM VW_ALUNO_VINCULO v
            INNER JOIN TB_TURMAS t2 ON t2.ID = v.ID_TURMA
            WHERE v.ID_ALUNO = a.ID AND t2.ANO_LETIVO = ?
            ORDER BY CASE WHEN v.DATA_INICIO IS NULL THEN 0 ELSE 1 END DESC, v.DATA_INICIO DESC
        ) tv
        INNER JOIN TB_UNIDADES u ON u.id = tv.ID_UNIDADE
        LEFT JOIN TB_RELATORIOS_ANUAIS r ON r.ID_ALUNO = a.ID AND r.ANO = ? AND r.PERIODO = ?
        WHERE a.ATIVO = 1' . $filtro['sql'];
/* A ordem importa: os ? do filtro de observação ficam no SELECT, entre as
   datas das observações e o ano do LEFT JOIN. São três datas, o ano do
   vínculo, o ano e o período do parecer, e por fim o filtro de turma. */
$params = array_merge(
    [$inicioPeriodo, $fimPeriodo, $inicioPeriodo, $fimPeriodo],
    $fObsIdx['params'], $fObsPermissaoIdx['params'],
    [$fAno, $fAno, $fPeriodo], $filtro['params']
);

if ($fTurma > 0) { $sql .= ' AND tv.ID = ?'; $params[] = $fTurma; }
$sql .= ' ORDER BY u.unidade, tv.NOME, a.NOME';

$alunos = ch_rows($sql, $params);
