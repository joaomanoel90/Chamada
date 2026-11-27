<?php
/* Endpoint somente leitura do card de calendário do dashboard.
   Devolve o HTML do card para o filtro AJAX, sem renderizar o restante da página. */
require_once('config.php');
if (!isset($_SESSION['usuarioId'])) {
    http_response_code(401);
    exit;
}
require_once(DBAPI);
require_once(ABSPATH . 'inc/chamada.php');
ch_db();

$hoje = ch_hoje();
$ano  = (int)date('Y');
$mes  = (int)date('n');
require_once(ABSPATH . 'processa/dashboard_calendario_processa.php');

header('Content-Type: text/html; charset=UTF-8');
header('Cache-Control: no-store');
include(ABSPATH . 'inc/dashboard_calendario.php');
