<?php require_once('processa/edit_processa.php'); ?>
<?php include(HEADER_TEMPLATE); ?>

<?php ch_cabecalho('Editar Substituição', 'fa-people-arrows',
    '<a class="btn btn-outline-secondary" href="index.php"><i class="fas fa-arrow-left"></i> Voltar</a>'); ?>

<?php $acao = 'edit.php?id=' . (int)$id; $rotuloBotao = 'Salvar alterações'; include('formulario.php'); ?>

<?php include(FOOTER_TEMPLATE); ?>
