<?php
/* ============================================================================
   CANCELAR uma transferência que ainda está aguardando
   ----------------------------------------------------------------------------
   Pode cancelar a gestão de qualquer uma das duas pontas: a escola de origem
   desiste do pedido, ou a de destino não pode receber o aluno. Motivo
   obrigatório, como na retificação da chamada. O aluno não sai do lugar.
   ============================================================================ */
require_once(dirname(__DIR__, 2) . '/config.php');
require_once(DBAPI);
require_once(ABSPATH . 'inc/chamada.php');
ch_db();
ch_require_gestao_alunos();

$id = (int)($_POST['id'] ?? $_GET['id'] ?? 0);
$t  = tr_transferencia($id);

if (!$t || !tr_pode_ver($t)) {
    ch_flash('Transferência não encontrada ou fora do seu alcance.', 'danger');
    header('Location: ' . ch_url('transferencias/index.php'));
    exit;
}
if ($t['SITUACAO'] !== 'PENDENTE') {
    ch_flash('Só é possível cancelar uma transferência que ainda está aguardando.', 'warning');
    header('Location: ' . ch_url('transferencias/ver.php?id=' . $id));
    exit;
}
if (!tr_pode_cancelar($t)) {
    ch_flash('Você não tem acesso a nenhuma das duas escolas desta transferência.', 'danger');
    header('Location: ' . ch_url('transferencias/index.php'));
    exit;
}

$erros = [];
$motivo = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!ch_csrf_ok()) {
        header('Location: ' . ch_url('transferencias/cancelar.php?id=' . $id));
        exit;
    }
    $motivo = strip_tags(trim((string)($_POST['motivo_cancelamento'] ?? '')));
    if ($motivo === '') {
        $erros[] = 'Informe o motivo do cancelamento.';
    } elseif (mb_strlen($motivo) > 500) {
        $erros[] = 'O motivo deve ter no máximo 500 caracteres.';
    }

    if (!$erros) {
        $n = ch_afetadas(
            "UPDATE TB_TRANSFERENCIAS
             SET SITUACAO = 'CANCELADA', MOTIVO_CANCELAMENTO = ?,
                 ID_USUARIO_CANCEL = ?, CANCELADA_EM = GETDATE()
             WHERE ID = ? AND SITUACAO = 'PENDENTE'",
            [$motivo, ch_uid(), $id]
        );
        if ($n === 1) {
            ch_flash('Transferência cancelada. O aluno permanece em ' . $t['TURMA_ORIGEM'] . '.');
            header('Location: ' . ch_url('transferencias/ver.php?id=' . $id));
            exit;
        }
        $erros[] = $n === 0
            ? 'Esta transferência acabou de ser tratada por outra pessoa. Recarregue a página.'
            : 'Não foi possível cancelar a transferência.';
        if (ch_admin() && $n !== 0) { $erros[] = ch_erro_sql(); }
    }
}