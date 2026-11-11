<?php
require_once('processa/edit_processa.php');
include(HEADER_TEMPLATE);

if (!isset($unidade['id'])) {
  echo "<p>Unidade não encontrada.</p>";
  include(FOOTER_TEMPLATE);
  exit;
}
?>

<h2><i class="far fa-edit"></i> Editar Unidade: <?= htmlspecialchars($unidade['unidade']) ?></h2>

<form action="edit.php?id=<?= $id ?>" method="post">
  <input type="hidden" class="form-control" name="id" id="id" value="<?= $id ?>" required>

  <hr />

  <!-- Dados Pessoais -->
  <div class="row">
    <div class="form-group col-md-12">
      <label class="fw-bold">Unidade</label>
      <input type="text" class="form-control" name="unidade" value="<?= htmlspecialchars($unidade['unidade']) ?>" onkeyup="this.value = this.value.toUpperCase();" id="unidade" maxlength="230" required>
    </div>

  </div>







  <div class="col-auto my-5">
    <a href="index.php" class="btn btn-outline-success">Cancelar</a>

    <button type="submit" class="btn btn-success"><i class='fas fa-save'></i> Salvar Alterações</button>
  </div>
</form>




<?php include(FOOTER_TEMPLATE); ?>