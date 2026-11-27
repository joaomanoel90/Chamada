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
require_once(ABSPATH . 'inc/suspensao.php');
ch_db();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}
if (!ch_csrf_ok()) {
    header('Location: index.php');
    exit;
}

$turmaId   = (int)($_POST['turma_id'] ?? 0);
$data      = ch_data_iso($_POST['data'] ?? '');
$aula      = ch_aula_normalizar($_POST['aula'] ?? 1, ch_turma_aulas_dia($turmaId));
$chamadaId = (int)($_POST['chamada_id'] ?? 0);
$novo      = strtoupper(trim((string)($_POST['status'] ?? '')));
$justNova  = strip_tags(trim((string)($_POST['justificativa'] ?? '')));
$motivo    = strip_tags(trim((string)($_POST['motivo'] ?? '')));

$voltar = 'index.php' . ($turmaId && $data
    ? '?turma_id=' . $turmaId . '&data=' . $data . '&aula=' . $aula
    : '?aula=' . $aula);

if (!$data || $chamadaId <= 0) {
    ch_flash('Requisição inválida.', 'danger');
    header('Location: ' . $voltar);
    exit;
}

if ($aula >= 2 && !ch_chamada_tem_aula()) {
    ch_flash('Este banco ainda não tem chamada por aula. Rode sql_inicial/ATUALIZAR_CHAMADA_POR_AULA.sql.', 'danger');
    header('Location: ' . $voltar);
    exit;
}

if (!ch_pode_retificar($turmaId)) {
    ch_flash(ch_secretario_escolar()
        ? 'O perfil Secretário Escolar não pode retificar chamadas.'
        : 'Você não tem acesso a essa turma.', 'danger');
    header('Location: ' . $voltar);
    exit;
}

/* A chamada precisa existir, ser deste dia, desta aula e pertencer a um aluno
   desta turma. O ID sozinho não bastaria: alguém poderia trocar o aula no POST
   e retificar o registro errado. */
$registro = ch_row(
    'SELECT c.ID, c.STATUS, c.JUSTIFICATIVA, c.ID_ALUNO, a.NOME, a.ID_TURMA,
            ' . ch_aula_col('c') . ' AS AULA_SEQ
     FROM TB_CHAMADA c
     INNER JOIN TB_ALUNOS a ON a.ID = c.ID_ALUNO
     WHERE c.ID = ? AND c.DATA = ?',
    [$chamadaId, $data]
);

if (!$registro || (int)$registro['ID_TURMA'] !== $turmaId
    || ch_aula_normalizar($registro['AULA_SEQ'], ch_turma_aulas_dia($turmaId)) !== $aula) {
    ch_flash('Registro de chamada não encontrado para esta turma, data e aula.', 'danger');
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

    /* CONFIRMADA_EM e CONFIRMADA_POR preservam quem confirmou originalmente.
       Se a falta vinha de suspensão, a retificação manual assume o registro e
       o desliga da suspensão, para não ser removida num encerramento futuro. */
    $limparSuspensao = susp_tem_coluna_chamada() ? ', ID_SUSPENSAO = NULL' : '';
    $ok = ch_exec(
        'UPDATE TB_CHAMADA SET STATUS = ?, JUSTIFICATIVA = ?, ID_USUARIO = ?'
        . $limparSuspensao . ', MODIFIED = GETDATE() WHERE ID = ?',
        [$novo, $justNovaDb, ch_uid(), $chamadaId]
    );
    if (!$ok) { throw new Exception('Falha ao atualizar a chamada.'); }

    sqlsrv_commit(ch_db());
    ch_flash(ch_aula_rotulo($aula) . ' de ' . $registro['NOME'] . ' retificada de "'
             . ch_status_rotulo($registro['STATUS']) . '" para "' . ch_status_rotulo($novo)
             . '". O motivo ficou registrado no histórico.');
} catch (Exception $ex) {
    sqlsrv_rollback(ch_db());
    ch_flash('Não foi possível retificar: ' . $ex->getMessage() . ' Nada foi alterado.', 'danger');
}

header('Location: ' . $voltar);
exit;
