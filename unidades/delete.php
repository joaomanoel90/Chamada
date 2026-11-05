<?php
require_once('processa/delete_processa.php');

if (isset($_GET['id'])) {
  delete($_GET['id']);
} else {
  die("ERRO: ID não definido.");
}
?>