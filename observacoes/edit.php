<?php require_once('processa/edit_processa.php'); ?>
<?php include(ch_gestor() ? HEADER_TEMPLATE : HEADER_TEMPLATE2); ?>
<?php ch_cabecalho('Editar Observação', 'fa-edit'); ?>
<?php $acao = 'edit.php?id=' . (int)$id; $rotuloBotao = 'Salvar Alterações'; include(__DIR__ . '/formulario.php'); ?>
<?php include(FOOTER_TEMPLATE); ?>
