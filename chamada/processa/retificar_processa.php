<?php
/* ============================================================================
   RETIFICAÇÃO de presença já confirmada.
   ----------------------------------------------------------------------------
   Exige motivo e grava o antes/depois em TB_CHAMADA_RETIFICACOES, junto com
   quem retificou e quando. Pode retificar a gestão da unidade ou qualquer
   professor da equipe da turma.
   ============================================================================ */
require_once(dirname(__DIR__, 2) . '/config.php');
require_once(DBAPI);
require_once(ABSPATH . 'inc/chamada.php');
ch_db();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

$turmaId   = (int)($_POST['turma_id'] ?? 0);
$data      = ch_data_iso($_POST['data'] ?? '');
$chamadaId = (int)($_POST['chamada_id'] ?? 0);
$novo      = strtoupper(trim((string)($_POST['status'] ?? '')));
$justNova  = strip_tags(trim((string)($_POST['justificativa'] ?? '')));
$motivo    = strip_tags(trim((string)($_POST['motivo'] ?? '')));

$voltar = 'index.php' . ($turmaId && $data ? '?turma_id=' . $turmaId . '&data=' . $data : '');

if (!$data || $chamadaId <= 0) {
    ch_flash('Requisição inválida.', 'danger');
    header('Location: ' . $voltar);
    exit;
}

if (!ch_pode_retificar($turmaId)) {
    ch_flash('Você não tem acesso a essa turma.', 'danger');
    header('Location: ' . $voltar);
    exit;
}

/* A chamada precisa existir, ser deste dia e pertencer a um aluno desta turma. */
$registro = ch_row(
    'SELECT c.ID, c.STATUS, c.JUSTIFICATIVA, c.ID_ALUNO, a.NOME, a.ID_TURMA
     FROM TB_CHAMADA c
     INNER JOIN TB_ALUNOS a ON a.ID = c.ID_ALUNO
     WHERE c.ID = ? AND c.DATA = ?',
    [$chamadaId, $data]
);

if (!$registro || (int)$registro['ID_TURMA'] !== $turmaId) {
    ch_flash('Registro de chamada não encontrado para esta turma e data.', 'danger');
    header('Location: ' . $voltar);
    exit;
}

$turma = ch_turma($turmaId);
$bloqueio = ch_bloqueio_chamada($data, $turma);
if ($bloqueio) {
    ch_flash($bloqueio, 'warning');
    header('Location: ' . $voltar);
    exit;
}

/* ------------------------------------------------------------ validações */
$erros = [];
if (!in_array($novo, ['P', 'F', 'J', 'A'], true)) {
    $erros[] = 'Escolha uma situação válida.';
}
if ($novo === 'J' && $justNova === '') {
    $erros[] = 'A falta justificada exige justificativa.';
}
if (mb_strlen($justNova) > 500) {
    $erros[] = 'Justificativa muito longa (máx. 500 caracteres).';
}
if ($motivo === '') {
    $erros[] = 'Informe o motivo da retificação.';
}
if (mb_strlen($motivo) > 500) {
    $erros[] = 'Motivo muito longo (máx. 500 caracteres).';
}
if (!$erros
    && $novo === $registro['STATUS']
    && $justNova === (string)($registro['JUSTIFICATIVA'] ?? '')) {
    $erros[] = 'Nada mudou: a situação e a justificativa são iguais às já registradas.';
}

if ($erros) {
    foreach ($erros as $e) { ch_flash($e, 'danger'); }
    header('Location: ' . $voltar);
    exit;
}

/* Justificativa só faz sentido para falta justificada. */
if ($novo !== 'J') {
    $justNova = '';
}
$justNovaDb = $justNova !== '' ? $justNova : null;

/* ------------------------------------------------------------ gravação */
sqlsrv_begin_transaction(ch_db());
try {
    $ok = ch_exec(
        'INSERT INTO TB_CHAMADA_RETIFICACOES
            (ID_CHAMADA, STATUS_ANTERIOR, STATUS_NOVO, JUSTIFICATIVA_ANTERIOR, JUSTIFICATIVA_NOVA, MOTIVO, ID_USUARIO)
         VALUES (?, ?, ?, ?, ?, ?, ?)',
        [$chamadaId, $registro['STATUS'], $novo,
         $registro['JUSTIFICATIVA'], $justNovaDb, $motivo, ch_uid()]
    );
    if (!$ok) { throw new Exception('Falha ao registrar o histórico da retificação.'); }

    /* CONFIRMADA_EM e CONFIRMADA_POR preservam quem confirmou originalmente. */
    $ok = ch_exec(
        'UPDATE TB_CHAMADA SET STATUS = ?, JUSTIFICATIVA = ?, ID_USUARIO = ?, MODIFIED = GETDATE() WHERE ID = ?',
        [$novo, $justNovaDb, ch_uid(), $chamadaId]
    );
    if (!$ok) { throw new Exception('Falha ao atualizar a chamada.'); }

    sqlsrv_commit(ch_db());
    ch_flash('Presença de ' . $registro['NOME'] . ' retificada de "'
             . ch_status_rotulo($registro['STATUS']) . '" para "' . ch_status_rotulo($novo)
             . '". O motivo ficou registrado no histórico.');
} catch (Exception $ex) {
    sqlsrv_rollback(ch_db());
    ch_flash('Não foi possível retificar: ' . $ex->getMessage() . ' Nada foi alterado.', 'danger');
}

header('Location: ' . $voltar);
exit;
