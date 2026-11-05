<?php require_once('processa/add_processa.php'); ?>
<?php include(ch_gestor() ? HEADER_TEMPLATE : HEADER_TEMPLATE2); ?>
<?php ch_cabecalho('Nova Observação', 'fa-plus'); ?>
<?php $acao = 'add.php'; $rotuloBotao = 'Salvar'; include(__DIR__ . '/formulario.php'); ?>
<?php include(FOOTER_TEMPLATE); ?>
