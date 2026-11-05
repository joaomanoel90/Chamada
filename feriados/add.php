<?php require_once('processa/add_processa.php'); ?>
<?php include(HEADER_TEMPLATE); ?>
<?php ch_cabecalho('Novo Feriado', 'fa-plus'); ?>
<?php $acao = 'add.php'; $rotuloBotao = 'Salvar'; include(__DIR__ . '/formulario.php'); ?>
<?php include(FOOTER_TEMPLATE); ?>
