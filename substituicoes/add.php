<?php require_once('processa/add_processa.php'); ?>
<?php include(HEADER_TEMPLATE); ?>

<?php ch_cabecalho('Nova Substituição', 'fa-people-arrows',
    '<a class="btn btn-outline-secondary" href="index.php"><i class="fas fa-arrow-left"></i> Voltar</a>'); ?>

<?php $acao = 'add.php'; $rotuloBotao = 'Cadastrar substituição'; include('formulario.php'); ?>

<?php include(FOOTER_TEMPLATE); ?>
