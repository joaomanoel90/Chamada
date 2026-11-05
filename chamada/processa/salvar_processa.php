<?php
/* ============================================================================
   CONFIRMAÇÃO da chamada — grava apenas alunos AINDA SEM registro no dia.
   ----------------------------------------------------------------------------
   A presença é confirmada uma única vez por aluno/dia. Se o registro já existe,
   este script NÃO sobrescreve: a correção acontece em retificar.php, que exige
   motivo e grava histórico em TB_CHAMADA_RETIFICACOES.
   ============================================================================ */
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

/* Só alunos ativos desta turma. */
$alunos = ch_rows('SELECT ID, NOME FROM TB_ALUNOS WHERE ID_TURMA = ? AND ATIVO = 1 ORDER BY NOME', [$turmaId]);
$validos = [];
foreach ($alunos as $a) { $validos[(int)$a['ID']] = $a['NOME']; }

$status        = (array)($_POST['status'] ?? []);
$justificativa = (array)($_POST['justificativa'] ?? []);

$erros = [];
$paraSalvar = [];
$jaConfirmados = [];

foreach ($status as $idBruto => $sBruto) {
    $id = (int)$idBruto;
    if (!isset($validos[$id])) { continue; }          /* aluno de outra turma: ignora */

    $nome = $validos[$id];
    $s = strtoupper(trim((string)$sBruto));
    $j = strip_tags(trim((string)($justificativa[$id] ?? '')));

    if ($s === '') { continue; }                      /* não marcado: fica pendente */
    if (!in_array($s, ['P', 'F', 'J', 'A'], true)) { $erros[] = $nome . ': situação inválida.'; continue; }
    if ($s === 'J' && $j === '') { $erros[] = $nome . ': a falta justificada exige justificativa.'; continue; }
    if (mb_strlen($j) > 500) { $erros[] = $nome . ': justificativa muito longa (máx. 500).'; continue; }

    /* Trava principal: já existe registro no dia -> não confirma de novo. */
    if (ch_val('SELECT ID FROM TB_CHAMADA WHERE ID_ALUNO = ? AND DATA = ?', [$id, $data])) {
        $jaConfirmados[] = $nome;
        continue;
    }

    $paraSalvar[] = ['id' => $id, 'status' => $s, 'just' => $j !== '' ? $j : null];
}

/* Quem ficou sem marcar continua pendente — avisa, mas não bloqueia o resto. */
$semMarcacao = [];
foreach ($validos as $id => $nome) {
    $enviado = strtoupper(trim((string)($status[$id] ?? '')));
    if ($enviado !== '') continue;
    if (ch_val('SELECT ID FROM TB_CHAMADA WHERE ID_ALUNO = ? AND DATA = ?', [$id, $data])) continue;
    $semMarcacao[] = $nome;
}

if ($erros) {
    foreach (array_slice($erros, 0, 8) as $e) { ch_flash($e, 'danger'); }
    if (count($erros) > 8) { ch_flash('E mais ' . (count($erros) - 8) . ' pendências.', 'danger'); }
    header('Location: index.php?turma_id=' . $turmaId . '&data=' . $data);
    exit;
}

if (!$paraSalvar) {
    if ($jaConfirmados) {
        ch_flash('A presença destes alunos já estava confirmada. Use Retificar para corrigir: '
                 . implode(', ', array_slice($jaConfirmados, 0, 5))
                 . (count($jaConfirmados) > 5 ? ' e outros.' : ''), 'warning');
    } else {
        ch_flash('Nenhum aluno marcado para confirmar.', 'warning');
    }
    header('Location: index.php?turma_id=' . $turmaId . '&data=' . $data);
    exit;
}

sqlsrv_begin_transaction(ch_db());
try {
    $novos = 0;
    foreach ($paraSalvar as $item) {
        /* Reconfere dentro da transação contra gravação simultânea. */
        if (ch_val('SELECT ID FROM TB_CHAMADA WHERE ID_ALUNO = ? AND DATA = ?', [$item['id'], $data])) {
            throw new Exception('A presença de um aluno foi confirmada por outro usuário enquanto você preenchia.');
        }
        $ok = ch_exec(
            'INSERT INTO TB_CHAMADA (ID_ALUNO, DATA, STATUS, JUSTIFICATIVA, ID_USUARIO, CONFIRMADA_EM, CONFIRMADA_POR)
             VALUES (?, ?, ?, ?, ?, GETDATE(), ?)',
            [$item['id'], $data, $item['status'], $item['just'], ch_uid(), ch_uid()]
        );
        if (!$ok) { throw new Exception('Falha ao gravar a chamada.'); }
        $novos++;
    }
    sqlsrv_commit(ch_db());

    ch_flash('Presença confirmada para ' . $novos . ' aluno(s).'
             . ' Para corrigir, use Retificar.');
    if ($jaConfirmados) {
        ch_flash(count($jaConfirmados) . ' aluno(s) já tinham presença confirmada e não foram alterados.', 'warning');
    }
    if ($semMarcacao) {
        ch_flash(count($semMarcacao) . ' aluno(s) continuam aguardando confirmação.', 'warning');
    }
} catch (Exception $ex) {
    sqlsrv_rollback(ch_db());
    ch_flash('Não foi possível confirmar: ' . $ex->getMessage() . ' Nenhuma alteração foi gravada.', 'danger');
}

header('Location: index.php?turma_id=' . $turmaId . '&data=' . $data);
exit;
