<?php require_once('processa/edit_processa.php'); ?>
<?php include(HEADER_TEMPLATE); ?>
<?php ch_cabecalho('Editar Turma: ' . $d['NOME'], 'fa-edit'); ?>
<?php $acao = 'edit.php?id=' . (int)$id; $rotuloBotao = 'Salvar Alterações'; include(__DIR__ . '/formulario.php'); ?>
<?php include(FOOTER_TEMPLATE); ?>
