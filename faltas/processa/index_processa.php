<?php
/* ============================================================================
   RELATÓRIO DE FALTAS DE PROFESSORES — com base nas substituições
   ----------------------------------------------------------------------------
   Cada substituição registrada na equipe da turma (PAPEL = 'SUBSTITUTO')
   representa um período em que o professor titular faltou e foi coberto.
   O relatório lista essas faltas com quem cobriu, a turma, o período e o
   motivo, além de um resumo por professor.

   O período filtrado é o de sobreposição: entram as substituições que
   intersectam o intervalo escolhido (início <= até E fim >= de).
   ============================================================================ */
require_once(dirname(__DIR__, 2) . '/config.php');
require_once(DBAPI);
require_once(ABSPATH . 'inc/chamada.php');
ch_db();
ch_require_gestor();

$anoAtual = (int)date('Y');
$fDe   = ch_data_iso($_GET['de'] ?? '', sprintf('%04d-01-01', $anoAtual));
$fAte  = ch_data_iso($_GET['ate'] ?? '', sprintf('%04d-12-31', $anoAtual));
$fUnidade   = (int)($_GET['unidade'] ?? 0);
$fProfessor = (int)($_GET['professor'] ?? 0);
if ($fDe > $fAte) { $tmp = $fDe; $fDe = $fAte; $fAte = $tmp; }

$unidades = ch_unidades_opcoes();

/* Escopo: diretor só vê as unidades dele; rede vê tudo. */
$filtro = ch_filtro_turma('t');

/* Condições comuns (escopo + período + escola). O filtro por professor
   entra só na consulta principal, para a lista de opções do filtro
   continuar mostrando todos os professores com substituição. */
$baseWhere  = ["tp.PAPEL = 'SUBSTITUTO'", 'tp.ATIVO = 1',
               'tp.DATA_INICIO <= ?', 'tp.DATA_FIM >= ?'];
$baseParams = [$fAte, $fDe];
if ($filtro['sql'] !== '') {
    $baseWhere[]  = substr($filtro['sql'], 5); /* tira o " AND " inicial */
    $baseParams   = array_merge($baseParams, $filtro['params']);
}
if ($fUnidade > 0) {
    $baseWhere[]  = 't.ID_UNIDADE = ?';
    $baseParams[] = $fUnidade;
}
$baseWhere = ' WHERE ' . implode(' AND ', $baseWhere);

$where  = $baseWhere;
$params = $baseParams;
if ($fProfessor > 0) {
    $where  .= ' AND tp.ID_SUBSTITUIDO = ?';
    $params[] = $fProfessor;
}

$substituicoes = ch_rows(
    'SELECT tp.ID, tp.ID_TURMA, tp.ID_USUARIO, tp.ID_SUBSTITUIDO, tp.TURNO AS TURNO_SUBST,
            CONVERT(varchar(10), tp.DATA_INICIO, 23) AS DATA_INICIO,
            CONVERT(varchar(10), tp.DATA_FIM, 23)    AS DATA_FIM,
            tp.MOTIVO,
            DATEDIFF(DAY, tp.DATA_INICIO, tp.DATA_FIM) + 1 AS DIAS,
            aus.NOME AS PROFESSOR, sub.NOME AS SUBSTITUTO,
            t.NOME AS TURMA, t.SERIE, t.ANO_LETIVO, p.NOME AS PERIODO,
            u.unidade AS ESCOLA
     FROM TB_TURMA_PROFESSORES tp
     INNER JOIN TB_TURMAS t    ON t.ID = tp.ID_TURMA
     INNER JOIN TB_PERIODOS p  ON p.ID = t.ID_PERIODO
     INNER JOIN TB_UNIDADES u  ON u.id = t.ID_UNIDADE
     INNER JOIN TB_USUARIOS aus ON aus.ID = tp.ID_SUBSTITUIDO
     INNER JOIN TB_USUARIOS sub ON sub.ID = tp.ID_USUARIO'
    . $where .
    ' ORDER BY tp.DATA_INICIO DESC, aus.NOME, t.NOME',
    $params
) ?: [];

/* Resumo geral e por professor: quando uma falta em creche integral tem 2
   substitutos (Manhã + Tarde), as linhas compartilham o mesmo intervalo.
   Unimos períodos sobrepostos por professor ausente para não contar esses
   dias duas vezes, inclusive se a falta cobre mais de uma turma. */
$resumo = [
    'total'       => count($substituicoes),
    'dias'        => 0,
    'professores' => [],
    'substitutos' => [],
];
$intervalosPorAusente = [];
$porProfessor = [];
foreach ($substituicoes as $s) {
    $idAus = (int)$s['ID_SUBSTITUIDO'];
    $resumo['professores'][$idAus] = $s['PROFESSOR'];
    $resumo['substitutos'][(int)$s['ID_USUARIO']] = $s['SUBSTITUTO'];
    $intervalosPorAusente[$idAus][] = [$s['DATA_INICIO'], $s['DATA_FIM']];
    if (!isset($porProfessor[$idAus])) {
        $porProfessor[$idAus] = ['nome' => $s['PROFESSOR'], 'vezes' => 0, 'dias' => 0];
    }
}
foreach ($intervalosPorAusente as $idAus => $listaInt) {
    $idAus = (int)$idAus;
    usort($listaInt, fn($a, $b) => strcmp($a[0], $b[0]) ?: strcmp($a[1], $b[1]));
    $unidos = [];
    foreach ($listaInt as [$ini, $fim]) {
        if (!$unidos) {
            $unidos[] = [$ini, $fim];
        } else {
            $ult = &$unidos[count($unidos) - 1];
            if ($ini <= $ult[1]) {
                if ($fim > $ult[1]) $ult[1] = $fim;
            } else {
                $unidos[] = [$ini, $fim];
            }
            unset($ult);
        }
    }
    foreach ($unidos as [$ini, $fim]) {
        $d = (int)round((strtotime($fim) - strtotime($ini)) / 86400) + 1;
        $porProfessor[$idAus]['vezes']++;
        $porProfessor[$idAus]['dias'] += $d;
        $resumo['dias'] += $d;
    }
}
uasort($porProfessor, function ($a, $b) {
    return $b['dias'] <=> $a['dias'] ?: $b['vezes'] <=> $a['vezes'];
});

/* Professores com substituição no escopo (para o filtro). */
$professores = ch_rows(
    'SELECT DISTINCT aus.ID, aus.NOME
     FROM TB_TURMA_PROFESSORES tp
     INNER JOIN TB_TURMAS t    ON t.ID = tp.ID_TURMA
     INNER JOIN TB_USUARIOS aus ON aus.ID = tp.ID_SUBSTITUIDO'
    . $baseWhere .
    ' ORDER BY aus.NOME',
    $baseParams
) ?: [];
