<?php
require_once('processa/finalizar_processa.php');
include(HEADER_TEMPLATE);
?>

<h2><i class="fas fa-check"></i> Finalizar Pedido</h2>

<?php if (!empty($erro)): ?>
    <div class="alert alert-danger">
        <?php echo $erro; ?>
    </div>
<?php endif; ?>

<form method="post" action="finalizar.php">

    <input type="hidden" name="id" value="<?php echo intval($id_pedido); ?>">

    <div class="row">

        <div class="col-md-6 mb-3">
            <label class="fw-bold">Nome do Recebedor *</label>
            <input type="text" name="nome_recebedor" class="form-control" value="<?php echo htmlspecialchars($pedido['NomeUsuario'] ?? ''); ?>" required>
        </div>

        <div class="col-md-6 mb-3">
            <label class="fw-bold">Documento (CPF)</label>
            <input type="text" name="documento_recebedor" class="form-control" id="cpfcnpj" value="<?php echo htmlspecialchars($pedido['CPFUsuario'] ?? ''); ?>" maxlength="14">

        </div>

    </div>

    <div class="mt-4">
        <a href="index.php" class="btn btn-outline-secondary">Cancelar</a>

        <button type="submit" class="btn btn-success">
            <i class="fas fa-check"></i> Finalizar Pedido
        </button>
    </div>

</form>
<script src="<?php echo BASEURL; ?>js/mask.js"></script>

<?php include(FOOTER_TEMPLATE); ?>