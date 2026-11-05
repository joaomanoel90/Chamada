<?php
require_once('processa/edit_processa.php');
include(HEADER_TEMPLATE);

if ($_SESSION['usuarioNiveisAcessoId'] != 1 && $_SESSION['usuarioNiveisAcessoId'] != 2) {
  header("location:" . BASEURL);
}

if (!isset($fornecedor['ID'])) {
    echo "<p>Fornecedor não encontrada.</p>";
    include(FOOTER_TEMPLATE);
    exit;
}
?>

<h2><i class="far fa-edit"></i> Editar Fornecedor: <?= htmlspecialchars($fornecedor['Fornecedor']) ?></h2>

<form action="edit.php?id=<?= $id ?>" method="post">
  <input type="hidden" class="form-control" name="id" id="id" value="<?= $id ?>" required>

  <hr />

  <!-- Dados Pessoais -->
  <div class="row">
    <div class="form-group col-md-8">
      <label class="fw-bold">Fornecedor</label>
      <input type="text" class="form-control" name="fornecedor" value="<?= htmlspecialchars($fornecedor['Fornecedor']) ?>" onkeyup="this.value = this.value.toUpperCase();" id="fornecedor" maxlength="230" required>
    </div>
    <div class="form-group col-md-4">
      <label class="fw-bold">CNPJ</label>
      <input type="text" class="form-control" name="cnpj" value="<?= htmlspecialchars($fornecedor['CNPJ']) ?>" id="cpfcnpj" maxlength="18" required>
    </div>

  </div>







  <div class="col-auto my-5">
    <a href="index.php" class="btn btn-outline-success">Cancelar</a>

    <button type="submit" class="btn btn-success"><i class='fas fa-save'></i> Salvar Alterações</button>
  </div>
</form>



<script src="<?php echo BASEURL; ?>js/mask.js"></script>

<?php include(FOOTER_TEMPLATE); ?>
