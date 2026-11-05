<?php
require_once('processa/saldo_processa.php');
include(HEADER_TEMPLATE);

if (
    $_SESSION['usuarioNiveisAcessoId'] != 1 &&
    $_SESSION['usuarioNiveisAcessoId'] != 2
) {
    header("location:" . BASEURL);
    exit;
}
?>

<h2>
    <i class="fas fa-balance-scale"></i>
    Ajuste de Saldo
</h2>

<?php if (!empty($erro)): ?>
    <div class="alert alert-danger">
        <?php echo $erro; ?>
    </div>
<?php endif; ?>

<?php if (!empty($sucesso)): ?>
    <div class="alert alert-success">
        <?php echo $sucesso; ?>
    </div>
<?php endif; ?>

<?php if ($produto): ?>

    <div class="card shadow-sm border-0 rounded-4">

        <div class="card-body">

            <form method="post">

                <input type="hidden"
                    name="id_produto"
                    value="<?php echo intval($produto['ID']); ?>">

                <div class="row">

                    <div class="col-md-3 mb-3">
                        <label class="fw-bold">Almoxarifado</label>
                        <select class="form-control" name="almoxarifado" required>
                            <option value="">Selecione...</option>
                            <?php foreach ($almoxarifados as $a): ?>
                                <option value="<?php echo $a['ID']; ?>">
                                    <?php echo $a['Nome']; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-3 mb-3">

                        <label class="fw-bold">
                            Produto
                        </label>

                        <input type="text"
                            class="form-control"
                            value="<?php echo htmlspecialchars($produto['Descricao']); ?>"
                            readonly>

                    </div>

                    <div class="col-md-3 mb-3">

                        <label class="fw-bold">
                            Saldo Atual
                        </label>

                        <input type="text"
                            class="form-control"
                            value="<?php echo number_format($saldoAtual, 2, ',', '.'); ?>"
                            readonly>

                    </div>

                    <div class="col-md-3 mb-3">

                        <label class="fw-bold">
                            Novo Saldo
                        </label>

                        <input type="number"
                            step="0.01"
                            min="0"
                            name="novo_saldo"
                            class="form-control"
                            required>

                    </div>

                </div>

                <div class="mb-3">

                    <label class="fw-bold">
                        Motivo do Ajuste
                    </label>

                    <textarea name="motivo"
                        class="form-control"
                        rows="3"
                        required></textarea>

                </div>

                <div class="mt-4">

                    <a href="index.php"
                        class="btn btn-outline-success">

                        Cancelar

                    </a>

                    <button type="submit"
                        class="btn btn-success">

                        <i class="fas fa-save"></i>
                        Salvar Ajuste

                    </button>

                </div>

            </form>

        </div>

    </div>

<?php endif; ?>

<?php include(FOOTER_TEMPLATE); ?>