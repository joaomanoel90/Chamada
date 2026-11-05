<?php
require_once(dirname(__DIR__, 2) . '/config.php');
require_once(DBAPI);
require_once(ABSPATH . 'inc/chamada.php');
ch_db();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

$turmaId = (int)($_POST['turma_id'] ?? 0);
$data    = ch_data_iso($_POST['data'] ?? '');

if (!$data) {
    ch_flash('Data inválida.', 'danger');
    header('Location: index.php');
    exit;
}

if (!ch_pode_turma($turmaId)) {
    ch_flash('Você não tem acesso a essa turma.', 'danger');
    header('Location: index.php');
    exit;
}

$turma = ch_turma($turmaId);
$bloqueio = ch_bloqueio_chamada($data, $turma);
if ($bloqueio) {
    ch_flash($bloqueio, 'warning');
    header('Location: index.php?turma_id=' . $turmaId . '&data=' . $data);
    exit;
}

$alunos = ch_rows('SELECT ID, NOME FROM TB_ALUNOS WHERE ID_TURMA = ? AND ATIVO = 1 ORDER BY NOME', [$turmaId]);
$status     = (array)($_POST['status'] ?? []);
$justificativa = (array)($_POST['justificativa'] ?? []);

$erros = [];
$paraSalvar = [];

foreach ($alunos as $a) {
    $id = (int)$a['ID'];
    $s  = strtoupper(trim((string)($status[$id] ?? '')));
    $j  = strip_tags(trim((string)($justificativa[$id] ?? '')));

    if ($s === '') { $erros[] = $a['NOME'] . ': escolha uma situação.'; continue; }
    if (!in_array($s, ['P', 'F', 'J', 'A'], true)) { $erros[] = $a['NOME'] . ': situação inválida.'; continue; }
    if ($s === 'J' && $j === '') { $erros[] = $a['NOME'] . ': a falta justificada exige justificativa.'; continue; }
    if (mb_strlen($j) > 500) { $erros[] = $a['NOME'] . ': justificativa muito longa (máx. 500).'; continue; }

    $paraSalvar[] = ['id' => $id, 'status' => $s, 'just' => $j !== '' ? $j : null];
}

if ($erros) {
    foreach (array_slice($erros, 0, 8) as $e) { ch_flash($e, 'danger'); }
    if (count($erros) > 8) { ch_flash('E mais ' . (count($erros) - 8) . ' pendências.', 'danger'); }
    header('Location: index.php?turma_id=' . $turmaId . '&data=' . $data);
    exit;
}

if (!$paraSalvar) {
    ch_flash('Não há alunos para lançar chamada.', 'warning');
    header('Location: index.php?turma_id=' . $turmaId . '&data=' . $data);
    exit;
}

sqlsrv_begin_transaction(ch_db());
try {
    $novos = 0;
    $atualizados = 0;

    foreach ($paraSalvar as $item) {
        $existente = ch_val('SELECT ID FROM TB_CHAMADA WHERE ID_ALUNO = ? AND DATA = ?', [$item['id'], $data]);

        if ($existente) {
            $ok = ch_exec(
                'UPDATE TB_CHAMADA SET STATUS = ?, JUSTIFICATIVA = ?, ID_USUARIO = ?, MODIFIED = GETDATE() WHERE ID = ?',
                [$item['status'], $item['just'], ch_uid(), (int)$existente]
            );
            $atualizados++;
        } else {
            $ok = ch_exec(
                'INSERT INTO TB_CHAMADA (ID_ALUNO, DATA, STATUS, JUSTIFICATIVA, ID_USUARIO) VALUES (?, ?, ?, ?, ?)',
                [$item['id'], $data, $item['status'], $item['just'], ch_uid()]
            );
            $novos++;
        }

        if (!$ok) { throw new Exception('Falha ao gravar a chamada.'); }
    }

    sqlsrv_commit(ch_db());
    ch_flash('Chamada salva: ' . $novos . ' novos registros e ' . $atualizados . ' atualizados.');
} catch (Exception $ex) {
    sqlsrv_rollback(ch_db());
    ch_flash('Não foi possível salvar a chamada. Nenhuma alteração foi gravada.', 'danger');
}

header('Location: index.php?turma_id=' . $turmaId . '&data=' . $data);
exit;
