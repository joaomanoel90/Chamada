<?php
require_once('processa/cancelar_processa.php');

if (!isset($_SESSION['usuarioId'])) {
    header("Location: " . BASEURL . "login.php");
    exit;
}

$nivel = $_SESSION['usuarioNiveisAcessoId'] ?? 0;
if ($nivel != 1 && $nivel != 2) {
    include(HEADER_TEMPLATE2);
} else {
    include(HEADER_TEMPLATE);
}

if (!isset($pedido['ID'])) {
    echo "<div class='container mt-4'><div class='alert alert-danger'>Pedido não encontrado.</div><a href='index.php' class='btn btn-outline-secondary'>Voltar</a></div>";
    include(FOOTER_TEMPLATE);
    exit;
}

if (strtolower(trim($pedido['Status'])) !== 'aberto') {
    echo "<div class='container mt-4'><div class='alert alert-warning'>Apenas pedidos em status 'Aberto' podem ser cancelados por aqui. Status atual: " . htmlspecialchars($pedido['Status']) . "</div><a href='index.php' class='btn btn-outline-secondary'>Voltar</a></div>";
    include(FOOTER_TEMPLATE);
    exit;
}

$id = $pedido['ID'];
?>

<div class="container-fluid mt-3">
    <h2><i class="fas fa-times-circle text-danger"></i> Cancelar Pedido: <?= htmlspecialchars($pedido['ID']) ?></h2>

    <?php if (!empty($erro)): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($erro) ?></div>
    <?php endif; ?>

    <div class="alert alert-info">
        <strong>Unidade:</strong> <?= htmlspecialchars($pedido['NomeUnidade'] ?? '-') ?><br>
        <strong>Objetivo:</strong> <?= htmlspecialchars($pedido['Objetivo'] ?? '-') ?><br>
        <strong>Status:</strong> <?= htmlspecialchars($pedido['Status']) ?>
    </div>

    <form action="cancelar.php?id=<?= $id ?>" method="post">
        <input type="hidden" name="id" value="<?= $id ?>" required>

        <div class="alert alert-warning">
            <i class="fas fa-exclamation-triangle"></i> Tem certeza que deseja cancelar este pedido? Esta ação não poderá ser desfeita.
        </div>

        <div class="row">
            <div class="form-group col-md-12 mb-3">
                <label class="fw-bold">Motivo do Cancelamento <span class="text-danger">*</span></label>
                <textarea class="form-control" name="motivo_cancelamento" id="motivo_cancelamento" rows="4" required placeholder="Digite detalhadamente o motivo do cancelamento..."></textarea>
            </div>
        </div>

        <div class="my-4">
            <a href="index.php" class="btn btn-outline-secondary me-2">Voltar</a>
            <button type="submit" class="btn btn-danger" onclick="return confirm('Tem certeza que deseja cancelar este pedido?');">
                <i class="fas fa-times"></i> Confirmar Cancelamento
            </button>
        </div>
    </form>
</div>

<?php include(FOOTER_TEMPLATE); ?>
