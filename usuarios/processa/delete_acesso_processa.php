<?php
require_once(dirname(__DIR__, 2) . '/config.php');
require_once(DBAPI);
require_once(ABSPATH . 'inc/chamada.php');
ch_db();
ch_require_admin();

$volta = BASEURL . 'usuarios/index.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['id_acesso'])) {
    if (!ch_csrf_ok()) { header('Location: ' . $volta); exit; }

    $idAcesso = (int)$_POST['id_acesso'];
    // Descobre o usuário antes de desativar, para voltar à tela certa.
    $idUsuario = (int)ch_val('SELECT id_usuario FROM TB_ACESSO WHERE ID = ?', [$idAcesso]);

    if ($idUsuario > 0) {
        $ok = ch_exec('UPDATE TB_ACESSO SET ATIVO = 0, DESATIVADO = GETDATE() WHERE ID = ?', [$idAcesso]);
        ch_flash($ok ? 'Acesso à unidade removido.' : 'Não foi possível remover o acesso.',
                 $ok ? 'success' : 'danger');
        $volta = BASEURL . 'usuarios/acesso.php?id=' . $idUsuario;
    } else {
        ch_flash('Acesso não encontrado.', 'warning');
    }
}

// Antes usava HTTP_REFERER direto: além de poder vir vazio, é um valor que
// vem do navegador. Agora o destino é sempre uma tela do próprio sistema.
header('Location: ' . $volta);
exit;
