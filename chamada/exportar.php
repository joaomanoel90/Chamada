<?php
require_once(dirname(__DIR__) . '/config.php');
require_once(DBAPI);
require_once(ABSPATH . 'inc/chamada.php');
ch_db();
$turmaId = (int)($_GET['turma_id'] ?? 0);
$data    = ch_data_iso($_GET['data'] ?? '', ch_hoje());

if (!ch_pode_turma($turmaId)) {
    ch_flash('Você não tem acesso a essa turma.', 'danger');
    header('Location: index.php');
    exit;
}

$turma = ch_turma($turmaId);
$linhas = ch_rows(
    'SELECT a.MATRICULA, a.NOME, ISNULL(c.STATUS, \'\') AS STATUS, ISNULL(c.JUSTIFICATIVA, \'\') AS JUSTIFICATIVA
     FROM TB_ALUNOS a
     LEFT JOIN TB_CHAMADA c ON c.ID_ALUNO = a.ID AND c.DATA = ?
     WHERE a.ID_TURMA = ? AND a.ATIVO = 1 ORDER BY a.NOME',
    [$data, $turmaId]
);

$nomeArquivo = 'chamada_' . preg_replace('/[^A-Za-z0-9]+/', '_', $turma['NOME']) . '_' . $data . '.csv';
header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $nomeArquivo . '"');

$out = fopen('php://output', 'w');
fwrite($out, "\xEF\xBB\xBF"); // BOM para o Excel
fputcsv($out, ['Unidade', $turma['UNIDADE']], ';', '"', '');
fputcsv($out, ['Turma', $turma['NOME']], ';', '"', '');
fputcsv($out, ['Data', ch_data_br($data)], ';', '"', '');
fputcsv($out, [], ';', '"', '');
fputcsv($out, ['Matrícula', 'Aluno', 'Situação', 'Justificativa'], ';', '"', '');
foreach ($linhas as $l) {
    fputcsv($out, [$l['MATRICULA'], $l['NOME'],
                   $l['STATUS'] !== '' ? ch_status_rotulo($l['STATUS']) : 'Não lançado',
                   $l['JUSTIFICATIVA']], ';', '"', '');
}
fclose($out);
exit;
