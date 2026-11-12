<?php
require_once(__DIR__ . '/config.php');
require_once(ABSPATH . 'inc/chamada.php');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
	header("Location: " . BASEURL . "index.php");
	exit;
}
if (!ch_csrf_ok()) {
	header("Location: " . BASEURL . "index.php");
	exit;
}

$_SESSION = [];
session_regenerate_id(true);

$_SESSION['logindeslogado'] = "Deslogado com sucesso!";

header("Location: " . BASEURL . "login.php");
exit;
?>