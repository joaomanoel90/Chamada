<?php
require_once(dirname(__DIR__, 2) . '/config.php');
require_once(DBAPI);
require_once(ABSPATH . 'inc/chamada.php');
ch_db();
ch_require_gestor();

$anos = ch_rows('SELECT DISTINCT ANO_LETIVO FROM TB_TURMAS ORDER BY ANO_LETIVO DESC');
$unidades = ch_unidades_opcoes();

$fAno    = (int)($_GET['ano'] ?? (int)date('Y'));
$fUnidade = (int)($_GET['unidade'] ?? 0);
$fSituacao = $_GET['situacao'] ?? 'ativas';

/* Diretor só vê as turmas da sua unidade. */
$filtro = ch_filtro_turma('t');

$sql = 'SELECT t.ID, t.NOME, t.ANO_LETIVO, t.CAPACIDADE, t.ATIVO,
               u.unidade AS UNIDADE, p.NOME AS PERIODO, p.HORA_INICIO, p.HORA_FIM,
               (SELECT COUNT(*) FROM TB_ALUNOS a WHERE a.ID_TURMA = t.ID AND a.ATIVO = 1) AS ALUNOS
        FROM TB_TURMAS t
        INNER JOIN TB_UNIDADES u ON u.id = t.ID_UNIDADE
        INNER JOIN TB_PERIODOS p ON p.ID = t.ID_PERIODO
        WHERE t.ANO_LETIVO = ?';
$params = array_merge([$fAno], $filtro['params']);
$sql .= $filtro['sql'];

if ($fUnidade > 0) { $sql .= ' AND t.ID_UNIDADE = ?'; $params[] = $fUnidade; }
if ($fSituacao === 'ativas')   { $sql .= ' AND t.ATIVO = 1'; }
if ($fSituacao === 'inativas') { $sql .= ' AND t.ATIVO = 0'; }
$sql .= ' ORDER BY u.unidade, t.NOME';

$turmas = ch_rows($sql, $params);

/* Equipe de todas as turmas listadas, em uma única consulta.
   Agrupado em PHP para não depender de STRING_AGG (SQL Server 2017+). */
$equipePorTurma = [];
if ($turmas) {
    $ids = array_map(function ($t) { return (int)$t['ID']; }, $turmas);
    $ph = implode(',', array_fill(0, count($ids), '?'));
    $membros = ch_rows(
        "SELECT tp.ID_TURMA, tp.PAPEL, u.NOME
         FROM TB_TURMA_PROFESSORES tp
         INNER JOIN TB_USUARIOS u ON u.ID = tp.ID_USUARIO
         WHERE tp.ID_TURMA IN ({$ph}) AND tp.ATIVO = 1 AND ISNULL(u.ATIVO, 1) = 1
         ORDER BY CASE tp.PAPEL WHEN 'REGENTE' THEN 0 ELSE 1 END, u.NOME",
        $ids
    );
    foreach ($membros as $m) {
        $equipePorTurma[(int)$m['ID_TURMA']][] = $m;
    }
}
