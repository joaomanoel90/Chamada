<?php
require_once(dirname(__DIR__) . '/config.php');
require_once(DBAPI);
require_once(ABSPATH . 'inc/chamada.php');
ch_db();
$turmaId = (int)($_GET['turma_id'] ?? 0);
$data    = ch_data_iso($_GET['data'] ?? '', ch_hoje());
$aula    = ch_aula_normalizar($_GET['aula'] ?? 1);

if (!ch_pode_turma($turmaId)) {
    ch_flash('Você não tem acesso a essa turma.', 'danger');
    header('Location: index.php?aula=' . $aula);
    exit;
}

$turma = ch_turma($turmaId);
$naData = tr_filtro_na_data($turmaId, $data, 'a');
$joinAula = ch_chamada_tem_aula() ? ' AND c.AULA_SEQ = ?' : '';
$params = [$data];
if (ch_chamada_tem_aula()) $params[] = $aula;
$linhas = ch_rows(
    'SELECT a.MATRICULA, a.NOME, ' . ch_aula_col('c') . ' AS AULA_SEQ,
            ISNULL(c.STATUS, \'\') AS STATUS, ISNULL(c.JUSTIFICATIVA, \'\') AS JUSTIFICATIVA
     FROM TB_ALUNOS a
     LEFT JOIN TB_CHAMADA c ON c.ID_ALUNO = a.ID AND c.DATA = ?' . $joinAula . '
     WHERE a.ATIVO = 1' . $naData['sql'] . ' ORDER BY a.NOME',
    array_merge($params, $naData['params'])
);

$nomeArquivo = 'chamada_' . preg_replace('/[^A-Za-z0-9]+/', '_', $turma['NOME']) . '_' . $data . '_aula' . (int)$aula . '.csv';
header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $nomeArquivo . '"');

$out = fopen('php://output', 'w');
fwrite($out, "\xEF\xBB\xBF"); // BOM para o Excel
fputcsv($out, ['Unidade', $turma['UNIDADE']], ';', '"', '');
fputcsv($out, ['Turma', $turma['NOME']], ';', '"', '');
fputcsv($out, ['Data', ch_data_br($data)], ';', '"', '');
fputcsv($out, ['Aula', ch_aula_rotulo($aula)], ';', '"', '');
fputcsv($out, [], ';', '"', '');
fputcsv($out, ['Matrícula', 'Aluno', 'Situação', 'Justificativa'], ';', '"', '');
foreach ($linhas as $l) {
    fputcsv($out, [$l['MATRICULA'], $l['NOME'],
                   $l['STATUS'] !== '' ? ch_status_rotulo($l['STATUS']) : 'Não lançado',
                   $l['JUSTIFICATIVA']], ';', '"', '');
}
fclose($out);
exit;
