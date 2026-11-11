<?php
require_once('processa/add_processa.php');
?>

<?php include(HEADER_TEMPLATE); ?>

<h2><i class="fas fa-plus"></i> Nova Unidade</h2>

<form action="add.php" method="post">
  <!-- area de campos do form -->
  <hr />
  <div class="row">
    <div class="form-group col-md-12">
      <label class="fw-bold">Unidade</label>
      <input type="text" class="form-control" name="unidade" onkeyup="this.value = this.value.toUpperCase();" required>
    </div>



  </div>





  <div class="col-auto my-5">
    <a href="index.php" class="btn btn-outline-success">Cancelar</a>
    <button type="submit" class="btn btn-success"><i class='fas fa-save'></i> Salvar</button>




  </div>
</form>

<?php include(FOOTER_TEMPLATE); ?>