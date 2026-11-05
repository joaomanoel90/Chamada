<?php
$_SESSION['usuarioId'] = 1;
$_SESSION['usuarioNiveisAcessoId'] = 1;
$_GET['id'] = 1009;
$_SERVER['REQUEST_METHOD'] = 'GET';
require '../config.php';
require 'processa/lista_processa.php';
echo "Pedido:\n";
print_r($pedido);
echo "\nItens:\n";
print_r($itens_pedido);
