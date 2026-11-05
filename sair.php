<?php
require_once('functions.php');

// $baseurl = BASEURL;
// $baseurl_sem_barras = str_replace("/", "", $baseurl);
// session_name($baseurl_sem_barras);
// session_start();

unset(
	$_SESSION['usuarioId'],
	$_SESSION['usuarioNome'],
	$_SESSION['usuarioNiveisAcessoId'],
	$_SESSION['usuarioCPF'],
	$_SESSION['usuarioSenha']
	);

$_SESSION['logindeslogado'] = "Deslogado com sucesso!";

//redirecionar o usuario para a página de login
header("Location: login.php");

$db = close_database($conn);

?>