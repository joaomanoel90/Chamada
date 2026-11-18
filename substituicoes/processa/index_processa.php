<?php
require_once(dirname(__DIR__, 2) . '/config.php');
require_once(DBAPI);
require_once(ABSPATH . 'inc/chamada.php');
ch_db();
ch_require_gestor();

$fAno      = (int)($_GET['ano'] ?? date('Y'));
$fUnidade  = (int)($_GET['unidade'] ?? 0);
$fTurma    = (int)($_GET['turma'] ?? 0);
$fSituacao = $_GET['situacao'] ?? 'ativas';
if (!in_array($fSituacao, ['hoje', 'ativas', 'encerradas', 'todas'], true)) {
    $fSituacao = 'ativas';
}

$unidades = ch_unidades_opcoes();
$filtro = ch_filtro_turma('t');
$hoje = ch_hoje();

$turmasOpcoes = ch_rows(
    'SELECT t.ID, t.NOME, t.SERIE, t.ANO_LETIVO, u.unidade AS UNIDADE, p.NOME AS PERIODO
       FROM TB_TURMAS t
       INNER JOIN TB_UNIDADES u ON u.id = t.ID_UNIDADE
       INNER JOIN TB_PERIODOS p ON p.ID = t.ID_PERIODO
      WHERE t.ANO_LETIVO = ?' . $filtro['sql'] . '
      ORDER BY u.unidade, t.SERIE, t.NOME, p.NOME',
    array_merge([$fAno], $filtro['params'])
) ?: [];

$sql = "SELECT tp.ID, tp.ID_TURMA, tp.ID_USUARIO, tp.TURNO, tp.ID_SUBSTITUIDO,
               CONVERT(varchar(10), tp.DATA_INICIO, 23) AS DATA_INICIO,
               CONVERT(varchar(10), tp.DATA_FIM, 23) AS DATA_FIM,
               tp.MOTIVO, tp.VER_OBSERVACOES, tp.ATIVO,
               us.NOME AS SUBSTITUTO,
               uc.NOME AS COBERTO,
               t.NOME AS TURMA, t.SERIE, t.ANO_LETIVO, t.ID_UNIDADE,
               u.unidade AS UNIDADE, p.NOME AS PERIODO
          FROM TB_TURMA_PROFESSORES tp
          INNER JOIN TB_TURMAS t ON t.ID = tp.ID_TURMA
          INNER JOIN TB_UNIDADES u ON u.id = t.ID_UNIDADE
          INNER JOIN TB_PERIODOS p ON p.ID = t.ID_PERIODO
          INNER JOIN TB_USUARIOS us ON us.ID = tp.ID_USUARIO
          LEFT  JOIN TB_USUARIOS uc ON uc.ID = tp.ID_SUBSTITUIDO
         WHERE tp.PAPEL = 'SUBSTITUTO' AND t.ANO_LETIVO = ?" . $filtro['sql'];
$params = array_merge([$fAno], $filtro['params']);

if ($fUnidade > 0) {
    $sql .= ' AND t.ID_UNIDADE = ?';
    $params[] = $fUnidade;
}
if ($fTurma > 0) {
    $sql .= ' AND t.ID = ?';
    $params[] = $fTurma;
}
if ($fSituacao === 'hoje') {
    $sql .= ' AND tp.ATIVO = 1 AND tp.DATA_INICIO <= ? AND tp.DATA_FIM >= ?';
    $params[] = $hoje;
    $params[] = $hoje;
} elseif ($fSituacao === 'ativas') {
    $sql .= ' AND tp.ATIVO = 1 AND tp.DATA_FIM >= ?';
    $params[] = $hoje;
} elseif ($fSituacao === 'encerradas') {
    $sql .= ' AND (tp.ATIVO = 0 OR tp.DATA_FIM < ?)';
    $params[] = $hoje;
}

$sql .= ' ORDER BY tp.ATIVO DESC, tp.DATA_FIM DESC, u.unidade, t.SERIE, t.NOME';
$substituicoes = ch_rows($sql, $params) ?: [];
