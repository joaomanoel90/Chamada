<?php
require_once(dirname(__DIR__, 2) . '/config.php');
require_once(DBAPI);
require_once(ABSPATH . 'inc/chamada.php');
ch_db();

$id = (int)($_GET['id'] ?? 0);
$fObs = eq_obs_filtro('o', 'a');
$fPermissao = ch_filtro_observacao_aluno('a');
$observacao = ch_row(
    'SELECT o.ID, o.ID_ALUNO, CONVERT(varchar(10), o.DATA, 23) AS DATA, o.CATEGORIA, o.TEXTO, o.ID_USUARIO,
            CONVERT(varchar(16), o.CREATED, 120) AS CRIADO_EM,
            CONVERT(varchar(16), o.MODIFIED, 120) AS MODIFIED,
            u.NOME AS AUTOR
     FROM TB_OBSERVACOES o
     INNER JOIN TB_ALUNOS a ON a.ID = o.ID_ALUNO
     INNER JOIN TB_USUARIOS u ON u.ID = o.ID_USUARIO
     WHERE o.ID = ?' . $fObs['sql'] . $fPermissao['sql'],
    array_merge([$id], $fObs['params'], $fPermissao['params'])
);

if (!$observacao || !ch_pode_ler_observacao_aluno((int)$observacao['ID_ALUNO'])) {
    ch_flash('Observação não encontrada ou fora da sua permissão de consulta.', 'danger');
    header('Location: ' . ch_url('observacoes/index.php'));
    exit;
}

$aluno = ch_exige_aluno((int)$observacao['ID_ALUNO']);
$historico = ch_rows(
    'SELECT h.CATEGORIA, h.TEXTO,
            CONVERT(varchar(10), h.DATA, 23) AS DATA,
            CONVERT(varchar(16), h.ALTERADA_EM, 120) AS ALTERADA_EM,
            ua.NOME AS AUTOR, ue.NOME AS ALTERADO_POR
     FROM TB_HISTORICO_OBSERVACOES h
     INNER JOIN TB_USUARIOS ua ON ua.ID = h.ID_USUARIO_AUTOR
     INNER JOIN TB_USUARIOS ue ON ue.ID = h.ID_USUARIO_ALTERACAO
     WHERE h.ID_OBSERVACAO = ? ORDER BY h.ID DESC',
    [$id]
);
