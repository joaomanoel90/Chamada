<?php
/* Detalhe de uma transferência, com a linha do tempo do aluno. */
require_once(dirname(__DIR__, 2) . '/config.php');
require_once(DBAPI);
require_once(ABSPATH . 'inc/chamada.php');
ch_db();
ch_require_gestao_alunos();

$id = (int)($_GET['id'] ?? 0);
$t  = tr_transferencia($id);

if (!$t || !tr_pode_ver($t)) {
    ch_flash('Transferência não encontrada ou fora do seu alcance.', 'danger');
    header('Location: ' . ch_url('transferencias/index.php'));
    exit;
}

$aluno    = ch_aluno($t['ID_ALUNO']);
$vinculos = tr_vinculos($t['ID_ALUNO']);
$podeReceber  = tr_pode_receber($t);
$podeCancelar = tr_pode_cancelar($t);
$podeVerAluno = ch_pode_aluno($t['ID_ALUNO']);