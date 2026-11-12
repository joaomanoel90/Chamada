<?php
require_once('../config.php');
require_once(DBAPI);
require_once(ABSPATH . 'inc/chamada.php');

if (empty($_SESSION['usuarioId'])) {
    header('Location: ' . BASEURL . 'login.php');
    exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    return;
}
if (!ch_csrf_ok()) {
    header('Location: index.php');
    exit;
}

$conn = ch_db();
$id = (int)$_SESSION['usuarioId'];
$senhaEnviada = $_POST['senha'] ?? '';
$senha = is_string($senhaEnviada) ? $senhaEnviada : '';
$email = $_POST['email'] ?? '';

if (!is_string($senhaEnviada)) {
    ch_flash('A senha informada é inválida.', 'danger');
    header('Location: ' . BASEURL . 'conta/index.php');
    exit;
}

if ($senha !== '') {
    if (mb_strlen($senha, 'UTF-8') < 12) {
        ch_flash('A senha deve ter pelo menos 12 caracteres.', 'danger');
        header('Location: ' . BASEURL . 'conta/index.php');
        exit;
    }
    if (PASSWORD_DEFAULT === PASSWORD_BCRYPT && strlen($senha) > 72) {
        ch_flash('A senha não pode exceder 72 bytes.', 'danger');
        header('Location: ' . BASEURL . 'conta/index.php');
        exit;
    }
    $hash = ch_auth_password_hash($senha);
    if ($hash === false) {
        error_log('Falha ao gerar hash para atualização da conta do usuário #' . $id . '.');
        ch_flash('Não foi possível atualizar os dados da conta.', 'danger');
        header('Location: ' . BASEURL . 'conta/index.php');
        exit;
    }

    $sql = 'UPDATE TB_USUARIOS SET SENHA = ?, MODIFIED = GETDATE(), EMAIL = ? WHERE ID = ?';
    $params = [$hash, $email, $id];
} else {
    $sql = 'UPDATE TB_USUARIOS SET EMAIL = ?, MODIFIED = GETDATE() WHERE ID = ?';
    $params = [$email, $id];
}

$stmt = sqlsrv_query($conn, $sql, $params);
if ($stmt === false) {
    error_log('Falha ao atualizar a conta do usuário #' . $id . ': ' . print_r(sqlsrv_errors(), true));
    ch_flash('Não foi possível atualizar os dados da conta.', 'danger');
} else {
    ch_flash('Dados da conta atualizados com sucesso.');
}

header('Location: ' . BASEURL . 'conta/index.php');
exit;
