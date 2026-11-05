<?php
require_once(dirname(__DIR__, 2) . '/config.php');
require_once(DBAPI);
require_once(ABSPATH . 'inc/chamada.php');
ch_db();
ch_require_gestor();

$anos = ch_rows('SELECT DISTINCT ANO_LETIVO FROM TB_TURMAS ORDER BY ANO_LETIVO DESC');
$unidades = ch_rows('SELECT id, unidade FROM TB_UNIDADES ORDER BY unidade');

$fAno    = (int)($_GET['ano'] ?? (int)date('Y'));
$fUnidade = (int)($_GET['unidade'] ?? 0);
$fSituacao = $_GET['situacao'] ?? 'ativas';

$sql = 'SELECT t.ID, t.NOME, t.ANO_LETIVO, t.CAPACIDADE, t.ATIVO,
               u.unidade AS UNIDADE, p.NOME AS PERIODO, p.HORA_INICIO, p.HORA_FIM,
               pr.NOME AS PROFESSOR,
               (SELECT COUNT(*) FROM TB_ALUNOS a WHERE a.ID_TURMA = t.ID AND a.ATIVO = 1) AS ALUNOS
        FROM TB_TURMAS t
        INNER JOIN TB_UNIDADES u ON u.id = t.ID_UNIDADE
        INNER JOIN TB_PERIODOS p ON p.ID = t.ID_PERIODO
        LEFT  JOIN TB_USUARIOS pr ON pr.ID = t.ID_PROFESSOR
        WHERE t.ANO_LETIVO = ?';
$params = [$fAno];

if ($fUnidade > 0) { $sql .= ' AND t.ID_UNIDADE = ?'; $params[] = $fUnidade; }
if ($fSituacao === 'ativas')   { $sql .= ' AND t.ATIVO = 1'; }
if ($fSituacao === 'inativas') { $sql .= ' AND t.ATIVO = 0'; }
$sql .= ' ORDER BY u.unidade, t.NOME';

$turmas = ch_rows($sql, $params);
