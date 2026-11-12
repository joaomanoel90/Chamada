<?php
require_once('config.php');
require_once(DBAPI);

if (session_status() === PHP_SESSION_NONE) {
	session_start();
}
require_once(ABSPATH . 'inc/chamada.php');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
	header("Location: " . BASEURL . "login.php");
	exit;
}
if (!ch_csrf_ok()) {
	header("Location: " . BASEURL . "login.php");
	exit;
}

$cpfEnviado = $_POST['cpf'] ?? '';
$senhaEnviada = $_POST['senha'] ?? '';
$usuario = is_string($cpfEnviado) ? trim($cpfEnviado) : '';
$senha = is_string($senhaEnviada) ? $senhaEnviada : '';
$conn = open_database();

if ($conn === false) {
	ch_auth_log_sql_error('Login: falha ao conectar ao banco');
	$_SESSION['loginErro'] = 'Não foi possível concluir o acesso. Tente novamente mais tarde.';
	header('Location: ' . BASEURL . 'login.php');
	exit;
}

$resultado = ch_auth_login($conn, $usuario, $senha, ch_auth_login_key($usuario));
if ($resultado['status'] !== 'error') {
	ch_auth_login_cleanup($conn);
}

if ($resultado['status'] === 'success') {
	if (!session_regenerate_id(true)) {
		error_log('Login: não foi possível renovar o ID da sessão.');
		$_SESSION = [];
		$_SESSION['loginErro'] = 'Não foi possível concluir o acesso. Tente novamente.';
		header('Location: ' . BASEURL . 'login.php');
		exit;
	}

	$row = $resultado['usuario'];
	$_SESSION['usuarioId'] = $row['ID'];
	$_SESSION['usuarioNome'] = $row['NOME'];
	$_SESSION['usuarioNiveisAcessoId'] = $row['NIVEL'];
	$_SESSION['usuarioCPF'] = $row['CPF'];
	$_SESSION['ch_ultima_atividade'] = time();
	header('Location: ' . BASEURL . 'index.php');
	exit;
}

if ($resultado['status'] === 'blocked') {
	$_SESSION['loginErro'] = 'Muitas tentativas de acesso. Aguarde 15 minutos e tente novamente.';
} elseif ($resultado['status'] === 'password_reset') {
	$_SESSION['loginErro'] = 'A senha precisa ser redefinida pela administração antes do primeiro acesso seguro.';
} elseif ($resultado['status'] === 'error') {
	$_SESSION['loginErro'] = 'Não foi possível concluir o acesso. Tente novamente mais tarde.';
} else {
	$_SESSION['loginErro'] = 'Não foi possível acessar. Verifique o CPF e a senha e tente novamente.';
}

header('Location: ' . BASEURL . 'login.php');
exit;
