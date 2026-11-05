<?php
require_once(dirname(__DIR__, 2) . '/config.php');
require_once(DBAPI);
require_once(ABSPATH . 'inc/chamada.php');
ch_db();
$fTurma = (int)($_GET['turma_id'] ?? 0);
$fAno   = (int)($_GET['ano'] ?? (int)date('Y'));
$filtro = ch_filtro_unidade('t');

$turmas = ch_rows(
    'SELECT t.ID, t.NOME, t.ANO_LETIVO, u.unidade AS UNIDADE
     FROM TB_TURMAS t INNER JOIN TB_UNIDADES u ON u.id = t.ID_UNIDADE
     WHERE t.ATIVO = 1' . $filtro['sql'] . ' ORDER BY u.unidade, t.NOME',
    $filtro['params']
);

$sql = 'SELECT a.ID, a.MATRICULA, a.NOME, t.NOME AS TURMA, t.ANO_LETIVO, u.unidade AS UNIDADE,
               (SELECT COUNT(*) FROM TB_CHAMADA c WHERE c.ID_ALUNO = a.ID AND YEAR(c.DATA) = ?) AS DIAS,
               (SELECT COUNT(*) FROM TB_OBSERVACOES o WHERE o.ID_ALUNO = a.ID AND YEAR(o.DATA) = ?) AS OBSERVACOES,
               CASE WHEN r.ID IS NULL THEN 0 ELSE 1 END AS TEM_PARECER
        FROM TB_ALUNOS a
        INNER JOIN TB_TURMAS t ON t.ID = a.ID_TURMA
        INNER JOIN TB_UNIDADES u ON u.id = t.ID_UNIDADE
        LEFT  JOIN TB_RELATORIOS_ANUAIS r ON r.ID_ALUNO = a.ID AND r.ANO = ?
        WHERE a.ATIVO = 1 AND t.ANO_LETIVO = ?' . $filtro['sql'];
$params = array_merge([$fAno, $fAno, $fAno, $fAno], $filtro['params']);

if ($fTurma > 0) { $sql .= ' AND a.ID_TURMA = ?'; $params[] = $fTurma; }
$sql .= ' ORDER BY u.unidade, t.NOME, a.NOME';

$alunos = ch_rows($sql, $params);
