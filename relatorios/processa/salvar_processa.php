<?php
require_once(dirname(__DIR__, 2) . '/config.php');
require_once(DBAPI);
require_once(ABSPATH . 'inc/chamada.php');
ch_db();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

$alunoId = (int)($_POST['aluno_id'] ?? 0);
$ano     = (int)($_POST['ano'] ?? 0);
$parecer = trim(strip_tags($_POST['parecer'] ?? ''));
$encam   = trim(strip_tags($_POST['encaminhamentos'] ?? ''));

if (!ch_pode_aluno_atual($alunoId)) {
    ch_flash('Você não tem acesso a esse aluno.', 'danger');
    header('Location: index.php');
    exit;
}
if ($ano < 2000 || $ano > 2100) {
    ch_flash('Ano inválido.', 'danger');
    header('Location: index.php');
    exit;
}
if (mb_strlen($parecer) > 10000 || mb_strlen($encam) > 5000) {
    ch_flash('Texto acima do limite permitido.', 'danger');
    header('Location: ver.php?aluno_id=' . $alunoId . '&ano=' . $ano);
    exit;
}

sqlsrv_begin_transaction(ch_db());
try {
    $existente = ch_val('SELECT ID FROM TB_RELATORIOS_ANUAIS WHERE ID_ALUNO = ? AND ANO = ?', [$alunoId, $ano]);

    if ($existente) {
        $ok = ch_exec('UPDATE TB_RELATORIOS_ANUAIS SET PARECER = ?, ENCAMINHAMENTOS = ?, ID_USUARIO = ?, MODIFIED = GETDATE() WHERE ID = ?',
                      [$parecer, $encam, ch_uid(), (int)$existente]);
    } else {
        $ok = ch_exec('INSERT INTO TB_RELATORIOS_ANUAIS (ID_ALUNO, ANO, PARECER, ENCAMINHAMENTOS, ID_USUARIO) VALUES (?, ?, ?, ?, ?)',
                      [$alunoId, $ano, $parecer, $encam, ch_uid()]);
    }
    if (!$ok) { throw new Exception('Falha ao gravar.'); }

    sqlsrv_commit(ch_db());
    ch_flash('Parecer do relatório anual salvo com sucesso.');
} catch (Exception $e) {
    sqlsrv_rollback(ch_db());
    ch_flash('Não foi possível salvar o parecer.', 'danger');
}

header('Location: ver.php?aluno_id=' . $alunoId . '&ano=' . $ano);
exit;
