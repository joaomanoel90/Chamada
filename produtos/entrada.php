<?php
require_once('processa/entrada_processa.php');
include(HEADER_TEMPLATE);

// 🔒 Permissão
if ($_SESSION['usuarioNiveisAcessoId'] != 1 && $_SESSION['usuarioNiveisAcessoId'] != 2) {
    header("location:" . BASEURL);
    exit;
}
?>

<h2><i class="fas fa-plus"></i> Nova Entrada de Produto</h2>

<?php if (!empty($erro)): ?>
    <div class="alert alert-danger"><?php echo $erro; ?></div>
<?php endif; ?>

<?php if ($produto): ?>
    <div class="alert alert-info">
        <strong>Produto:</strong> <?php echo $produto['Descricao']; ?><br>
        <strong>Categoria:</strong> <?php echo $produto['Categoria']; ?><br>
        <strong>Unidade:</strong> <?php echo $produto['Unidade']; ?>
    </div>
<?php endif; ?>

<?php if ($produto): ?>

    <form action="entrada.php" method="post">

        <input type="hidden" name="id_produto" value="<?php echo $produto['ID']; ?>">

        <div class="row">

            <div class="form-group col-md-3">
                <label class="fw-bold">Fornecedor</label>
                <select class="form-control" name="id_fornecedor" required>
                    <option value="">Selecione...</option>
                    <?php foreach ($fornecedores as $f): ?>
                        <option value="<?php echo $f['ID']; ?>">
                            <?php echo $f['Fornecedor']; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group col-md-3">
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

            <div class="form-group col-md-3">
                <label class="fw-bold">Nº da Nota</label>
                <input type="text"
                    class="form-control"
                    name="nota"
                    inputmode="numeric"
                    pattern="[0-9]*"
                    maxlength="20"
                    required>
            </div>

            <div class="form-group col-md-3">
                <label class="fw-bold">Quantidade</label>
                <input type="number" step="0.01" class="form-control" name="quantidade" required>
            </div>

        </div>

        <div class="mt-4">
            <a href="index.php" class="btn btn-outline-secondary">Cancelar</a>
            <button type="submit" class="btn btn-success">
                <i class="fas fa-save"></i> Salvar
            </button>
        </div>

    </form>

<?php endif; ?>

<?php include(FOOTER_TEMPLATE); ?>