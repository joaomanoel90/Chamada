<?php
require_once(dirname(__DIR__, 2) . '/config.php');
require_once(DBAPI);
require_once(ABSPATH . 'inc/chamada.php');
ch_db();

$turmaId = (int)($_GET['turma_id'] ?? 0);
$data    = ch_data_iso($_GET['data'] ?? '', null);

if (!$turmaId || !ch_pode_turma($turmaId)) {
    ch_flash('Você não tem acesso a essa turma.', 'danger');
    header('Location: index.php');
    exit;
}

$turma = ch_turma($turmaId);

$sql = 'SELECT r.ID, r.STATUS_ANTERIOR, r.STATUS_NOVO,
               r.JUSTIFICATIVA_ANTERIOR, r.JUSTIFICATIVA_NOVA, r.MOTIVO,
               CONVERT(varchar(16), r.CREATED, 120) AS QUANDO,
               CONVERT(varchar(10), c.DATA, 23)     AS DATA_CHAMADA,
               a.NOME AS ALUNO, a.MATRICULA,
               u.NOME AS USUARIO
        FROM TB_CHAMADA_RETIFICACOES r
        INNER JOIN TB_CHAMADA c ON c.ID = r.ID_CHAMADA
        INNER JOIN TB_ALUNOS a  ON a.ID = c.ID_ALUNO
        INNER JOIN TB_USUARIOS u ON u.ID = r.ID_USUARIO
        /* A retificação pertence à turma em que o aluno estava no dia da
           chamada, não à turma em que ele está hoje. */
        WHERE EXISTS (SELECT 1 FROM VW_ALUNO_VINCULO v
                      WHERE v.ID_ALUNO = a.ID AND v.ID_TURMA = ?
                        AND (v.DATA_INICIO IS NULL OR v.DATA_INICIO <= c.DATA)
                        AND (v.DATA_FIM    IS NULL OR v.DATA_FIM    >= c.DATA))';
$params = [$turmaId];

if ($data) {
    $sql .= ' AND c.DATA = ?';
    $params[] = $data;
}
$sql .= ' ORDER BY r.CREATED DESC, r.ID DESC';

$retificacoes = ch_rows($sql, $params);
