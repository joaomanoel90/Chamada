<?php
require_once(dirname(__DIR__, 2) . '/config.php');
require_once(DBAPI);
require_once(ABSPATH . 'inc/chamada.php');
ch_db();
$fAluno = (int)($_GET['aluno_id'] ?? 0);
$fTurma = (int)($_GET['turma_id'] ?? 0);
$fCategoria = trim($_GET['categoria'] ?? '');
$fInicio = ch_data_iso($_GET['inicio'] ?? '', date('Y-m-01'));
$fFim    = ch_data_iso($_GET['fim'] ?? '', ch_hoje());

$filtro = ch_filtro_unidade('t');

$turmas = ch_rows(
    'SELECT t.ID, t.NOME, t.ANO_LETIVO, u.unidade AS UNIDADE
     FROM TB_TURMAS t INNER JOIN TB_UNIDADES u ON u.id = t.ID_UNIDADE
     WHERE t.ATIVO = 1' . $filtro['sql'] . ' ORDER BY u.unidade, t.NOME',
    $filtro['params']
);

$sql = 'SELECT o.ID, o.CATEGORIA, o.TEXTO, o.ID_USUARIO,
               CONVERT(varchar(10), o.DATA, 23) AS DATA,
               a.ID AS ID_ALUNO, a.NOME AS ALUNO,
               t.NOME AS TURMA, u.unidade AS UNIDADE, us.NOME AS AUTOR
        FROM TB_OBSERVACOES o
        INNER JOIN TB_ALUNOS a ON a.ID = o.ID_ALUNO
        INNER JOIN TB_TURMAS t ON t.ID = a.ID_TURMA
        INNER JOIN TB_UNIDADES u ON u.id = t.ID_UNIDADE
        INNER JOIN TB_USUARIOS us ON us.ID = o.ID_USUARIO
        WHERE o.DATA >= ? AND o.DATA <= ?' . $filtro['sql'];
$params = array_merge([$fInicio, $fFim], $filtro['params']);

if ($fAluno > 0) { $sql .= ' AND o.ID_ALUNO = ?'; $params[] = $fAluno; }
if ($fTurma > 0) { $sql .= ' AND a.ID_TURMA = ?'; $params[] = $fTurma; }
if ($fCategoria !== '') { $sql .= ' AND o.CATEGORIA = ?'; $params[] = $fCategoria; }
$sql .= ' ORDER BY o.DATA DESC, o.ID DESC';

$observacoes = ch_rows($sql, $params);
$alunoFiltro = $fAluno > 0 ? ch_aluno($fAluno) : null;
