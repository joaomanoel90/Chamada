<?php
require_once('processa/encaminha_processa.php');

if (isset($_GET['id'])) {
  delete($_GET['id']);
} else {
  die("ERRO: ID não definido.");
}
?>