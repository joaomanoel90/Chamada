<?php
require_once('processa/edit_processa.php');


if (!isset($_SESSION['usuarioId'])) {
    header("Location: " . BASEURL . "login.php");
    exit;
}

if ($_SESSION['usuarioNiveisAcessoId'] != 1 && $_SESSION['usuarioNiveisAcessoId'] != 2) {
  include(HEADER_TEMPLATE2);
} else {
	include(HEADER_TEMPLATE);
}


// Mostra "Pedido não encontrado" apenas se não houver erro personalizado
if (!isset($pedido['ID']) && empty($erro)) {
    echo "<p>Pedido não encontrado.</p>";
    include(FOOTER_TEMPLATE);
    exit;
}
?>

<?php if (!empty($erro)): ?>
    <div style="color: red; font-weight: bold;">
        <?= htmlspecialchars($erro) ?>
    </div>
<?php endif; ?>

<?php if (empty($erro) && isset($pedido['ID'])): ?>
    <h2><i class="far fa-edit"></i> Editar Pedido: <?= htmlspecialchars($pedido['ID']) ?></h2>

    <form action="edit.php?id=<?= htmlspecialchars($pedido['ID']) ?>" method="post">
        <input type="hidden" name="id" value="<?= htmlspecialchars($pedido['ID']) ?>">

        <hr />

        <div class="row">
            <div class="form-group col-md-6">
                <label class="fw-bold">Unidade</label>
                <select class="form-control" name="unidade" required>
                    <option selected disabled>Selecione...</option>
                    <?php while ($unidade = sqlsrv_fetch_array($stmt_acessos, SQLSRV_FETCH_ASSOC)) : ?>
                        <option value="<?= htmlspecialchars($unidade['ID_UNIDADE']) ?>"
                            <?= ($pedido['ID_Unidade'] == $unidade['ID_UNIDADE']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($unidade['NOME_UNIDADE']) ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>
            
            <div class="form-group col-md-6">
                <label class="fw-bold">Almoxarifado</label>
                <select class="form-control" name="almoxarifado" required>
                    <option selected disabled value="">Selecione...</option>
                    <?php while ($almox = sqlsrv_fetch_array($stmt_almox, SQLSRV_FETCH_ASSOC)) : ?>
                        <option value="<?= htmlspecialchars($almox['ID']) ?>"
                            <?= ($pedido['ID_Almoxarifado'] == $almox['ID']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($almox['Nome']) ?>
                        </option>
                    <?php endwhile; ?>
                </select>
                <small class="text-danger">Atenção: alterar o almoxarifado limpará os itens atuais do pedido.</small>
            </div>
        </div>

        <div class="row">
            <div class="form-group col-md-12">
                <label class="fw-bold" for="objetivo">Objetivo</label>
                <input type="text" class="form-control" name="objetivo" id="objetivo" maxlength="300"
                    value="<?= htmlspecialchars($pedido['Objetivo']) ?>" required>
            </div>
        </div>

        <div class="col-auto my-5">
            <a href="index.php" class="btn btn-outline-success">Cancelar</a>
            <button type="submit" class="btn btn-success"><i class='fas fa-save'></i> Salvar Alterações</button>
        </div>
    </form>
<?php endif; ?>

<?php include(FOOTER_TEMPLATE); ?>
