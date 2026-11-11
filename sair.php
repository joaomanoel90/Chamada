<?php
require_once(__DIR__ . '/config.php');

unset(
	$_SESSION['usuarioId'],
	$_SESSION['usuarioNome'],
	$_SESSION['usuarioNiveisAcessoId'],
	$_SESSION['usuarioCPF'],
	$_SESSION['usuarioSenha']
	);

$_SESSION['logindeslogado'] = "Deslogado com sucesso!";

header("Location: " . BASEURL . "login.php");
exit;
?>