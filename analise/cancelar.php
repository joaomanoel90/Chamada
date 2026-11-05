<?php
require_once('processa/cancelar_processa.php');

if (isset($_SESSION['usuarioId']) && 
    (isset($_SESSION['usuarioNiveisAcessoId']) && 
    ($_SESSION['usuarioNiveisAcessoId'] == 1 || $_SESSION['usuarioNiveisAcessoId'] == 2))) {
    
    include(HEADER_TEMPLATE);

    if (!isset($pedido['ID'])) {
        echo "<p>Pedido não encontrado.</p>";
        include(FOOTER_TEMPLATE);
        exit;
    }

    if (strtolower($pedido['Status']) === 'cancelado') {
        echo "<p>Este pedido já foi cancelado.</p>";
        include(FOOTER_TEMPLATE);
        exit;
    }

    $id = $pedido['ID'];
    ?>

    <h2><i class="fas fa-times-circle"></i> Cancelar Pedido: <?= htmlspecialchars($pedido['ID']) ?></h2>

    <?php if (!empty($erro)): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($erro) ?></div>
    <?php endif; ?>

    <form action="cancelar.php?id=<?= $id ?>" method="post">
        <input type="hidden" class="form-control" name="id" id="id" value="<?= $id ?>" required>

        <hr />

        <div class="alert alert-warning">
            Ao cancelar este pedido, todos os itens voltarão para o estoque e a movimentação será registrada.
        </div>

        <div class="row">
            <div class="form-group col-md-12">
                <label class="fw-bold">Motivo do Cancelamento</label>
                <textarea class="form-control" name="motivo_cancelamento" id="motivo_cancelamento" rows="4" required></textarea>
            </div>
        </div>

        <div class="col-auto my-5">
           <a href="index.php" class="btn btn-outline-secondary">Voltar</a>
           <button type="submit" class="btn btn-danger" onclick="return confirm('Tem certeza que deseja cancelar este pedido?');"><i class='fas fa-times'></i> Confirmar Cancelamento</button>
        </div>
    </form>

    <?php include(FOOTER_TEMPLATE); ?>

<?php
} else {
    header("Location: " . BASEURL);
    exit;
}
?>
