<?php
require_once(dirname(__DIR__, 2) . '/config.php');
require_once(DBAPI);
require_once(ABSPATH . 'inc/chamada.php');
ch_db();

$volta = ch_url(ch_secretaria_educacao() ? 'usuarios/vinculos.php' : 'usuarios/index.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['id_acesso'])) {
    if (!ch_csrf_ok()) { header('Location: ' . $volta); exit; }

    $idAcesso = (int)$_POST['id_acesso'];
    // Descobre o usuário antes de desativar, para voltar à tela certa.
    $idUsuario = (int)ch_val('SELECT id_usuario FROM TB_ACESSO WHERE ID = ?', [$idAcesso]);

    if (!$idUsuario || !ch_pode_gerenciar_vinculos_usuario($idUsuario)) {
        ch_flash('Você não pode remover este vínculo de unidade.', 'danger');
    } else {
        $ok = ch_exec('UPDATE TB_ACESSO SET ATIVO = 0, DESATIVADO = GETDATE() WHERE ID = ?', [$idAcesso]);
        ch_flash($ok ? 'Acesso à unidade removido.' : 'Não foi possível remover o acesso.',
                 $ok ? 'success' : 'danger');
        $volta = ch_url('usuarios/acesso.php?id=' . $idUsuario);
    }
}

// Antes usava HTTP_REFERER direto: além de poder vir vazio, é um valor que
// vem do navegador. Agora o destino é sempre uma tela do próprio sistema.
header('Location: ' . $volta);
exit;
