<?php
require_once('processa/add_processa.php');
?>

<?php include(HEADER_TEMPLATE); ?>

<?PHP if ($_SESSION['usuarioNiveisAcessoId'] != 1 && $_SESSION['usuarioNiveisAcessoId'] != 2) {
  header("location:" . BASEURL);
}
?>
<h2><i class="fas fa-plus"></i> Novo Fornecedor</h2>

<form action="add.php" method="post">
  <!-- area de campos do form -->
  <hr />
  <div class="row">
    <div class="form-group col-md-8">
      <label class="fw-bold">Fornecedor</label>
      <input type="text" class="form-control" name="fornecedor" onkeyup="this.value = this.value.toUpperCase();" required>
    </div>
    <div class="form-group col-md-4">
  <label class="fw-bold">CNPJ</label>
<input type="text" class="form-control" name="cnpj" id="cpfcnpj" maxlength="18"  required>
</div>
 


  </div>





  <div class="col-auto my-5">
        <a href="index.php" class="btn btn-outline-success">Cancelar</a>
    <button type="submit" class="btn btn-success"><i class='fas fa-save'></i> Salvar</button>

   


  </div>
</form>
<script src="<?php echo BASEURL; ?>js/mask.js"></script>


<?php include(FOOTER_TEMPLATE); ?>