<?php
require_once(dirname(__DIR__) . '/config.php');
require_once(DBAPI);
require_once(ABSPATH . 'inc/chamada.php');
ch_db();
ch_require_relatorios();
$alunoId = (int)($_GET['aluno_id'] ?? 0);
$ano     = (int)($_GET['ano'] ?? (int)date('Y'));
$aluno   = ch_exige_relatorio_aluno($alunoId);

$inicio = $ano . '-01-01';
$fim    = $ano . '-12-31';

$meses = ch_rows(
    "SELECT MONTH(DATA) AS MES, COUNT(*) AS DIAS,
            SUM(CASE WHEN STATUS = 'P' THEN 1 ELSE 0 END) AS P,
            SUM(CASE WHEN STATUS = 'F' THEN 1 ELSE 0 END) AS F,
            SUM(CASE WHEN STATUS = 'J' THEN 1 ELSE 0 END) AS J,
            SUM(CASE WHEN STATUS = 'A' THEN 1 ELSE 0 END) AS A
     FROM TB_CHAMADA WHERE ID_ALUNO = ? AND DATA >= ? AND DATA <= ?
     GROUP BY MONTH(DATA) ORDER BY MONTH(DATA)",
    [$alunoId, $inicio, $fim]
);

$parecer = ch_row('SELECT ISNULL(PARECER, \'\') AS PARECER, ISNULL(ENCAMINHAMENTOS, \'\') AS ENCAMINHAMENTOS
                   FROM TB_RELATORIOS_ANUAIS WHERE ID_ALUNO = ? AND ANO = ?', [$alunoId, $ano]);

$nome = 'relatorio_' . preg_replace('/[^A-Za-z0-9]+/', '_', $aluno['NOME']) . '_' . $ano . '.csv';
header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $nome . '"');

$out = fopen('php://output', 'w');
fwrite($out, "\xEF\xBB\xBF");
fputcsv($out, ['Relatório Anual de Desenvolvimento', $ano], ';', '"', '');
fputcsv($out, ['Aluno', $aluno['NOME']], ';', '"', '');
fputcsv($out, ['Matrícula', $aluno['MATRICULA']], ';', '"', '');
fputcsv($out, ['Unidade', $aluno['UNIDADE']], ';', '"', '');
fputcsv($out, ['Turma', $aluno['TURMA']], ';', '"', '');
fputcsv($out, [], ';', '"', '');
fputcsv($out, ['Mês', 'Dias lançados', 'Presente', 'Atraso', 'Falta', 'Justificada', 'Frequência %'], ';', '"', '');
foreach ($meses as $m) {
    $d = (int)$m['DIAS']; $p = (int)$m['P'] + (int)$m['A'];
    fputcsv($out, [ch_mes_nome((int)$m['MES']), $d, (int)$m['P'], (int)$m['A'], (int)$m['F'], (int)$m['J'],
                   $d > 0 ? number_format($p * 100 / $d, 1, ',', '') : '0,0'], ';', '"', '');
}
fputcsv($out, [], ';', '"', '');
fputcsv($out, ['Parecer', $parecer['PARECER'] ?? ''], ';', '"', '');
fputcsv($out, ['Encaminhamentos', $parecer['ENCAMINHAMENTOS'] ?? ''], ';', '"', '');
fclose($out);
exit;
