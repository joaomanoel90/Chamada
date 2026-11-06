<?php
require_once(dirname(__DIR__, 2) . '/config.php');
require_once(DBAPI);
require_once(ABSPATH . 'inc/chamada.php');
ch_db();
$anoAtual = (int)date('Y');
$fTurma    = (int)($_GET['turma_id'] ?? 0);
$fBusca    = trim($_GET['busca'] ?? '');
$fSituacao = $_GET['situacao'] ?? 'ativos';

$filtro = ch_filtro_turma('t');

$turmas = ch_rows(
    'SELECT t.ID, t.NOME, t.ANO_LETIVO, u.unidade AS UNIDADE
     FROM TB_TURMAS t INNER JOIN TB_UNIDADES u ON u.id = t.ID_UNIDADE
     WHERE t.ATIVO = 1' . $filtro['sql'] . ' ORDER BY u.unidade, t.NOME',
    $filtro['params']
);

$sql = 'SELECT a.ID, a.MATRICULA, a.NOME, a.RESPONSAVEL, a.TELEFONE, a.ATIVO,
               CONVERT(varchar(10), a.NASCIMENTO, 23) AS NASCIMENTO,
               t.NOME AS TURMA, t.ANO_LETIVO, t.ID_UNIDADE, u.unidade AS UNIDADE,
               (SELECT COUNT(*) FROM TB_TRANSFERENCIAS tr
                 WHERE tr.ID_ALUNO = a.ID AND tr.SITUACAO = \'PENDENTE\') AS TRANSF_PENDENTE
        FROM TB_ALUNOS a
        INNER JOIN TB_TURMAS t ON t.ID = a.ID_TURMA
        INNER JOIN TB_UNIDADES u ON u.id = t.ID_UNIDADE
        WHERE 1 = 1' . $filtro['sql'];
$params = $filtro['params'];

if ($fTurma > 0) { $sql .= ' AND a.ID_TURMA = ?'; $params[] = $fTurma; }
if ($fBusca !== '') {
    $sql .= ' AND (a.NOME LIKE ? OR a.MATRICULA LIKE ? OR a.RESPONSAVEL LIKE ?)';
    $like = '%' . $fBusca . '%';
    array_push($params, $like, $like, $like);
}
if ($fSituacao === 'ativos')   { $sql .= ' AND a.ATIVO = 1'; }
if ($fSituacao === 'inativos') { $sql .= ' AND a.ATIVO = 0'; }
$sql .= ' ORDER BY a.NOME';

$alunos = ch_rows($sql, $params);
