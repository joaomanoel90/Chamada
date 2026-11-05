<?php
require_once('processa/add_processa.php');
?>

<?php include(HEADER_TEMPLATE); ?>

<?PHP if ($_SESSION['usuarioNiveisAcessoId'] != 1 && $_SESSION['usuarioNiveisAcessoId'] != 2) {
  header("location:" . BASEURL);
}
?>
 <?php if (!empty($erro)): ?>
        <div class="error-banner"><?php echo $erro; ?></div>
    <?php endif; ?>
<h2><i class="fas fa-plus"></i> Novo Produto</h2>

<form action="add.php" method="post">
  <!-- area de campos do form -->
  <hr />
  <div class="row">
    <div class="form-group col-md-6">
      <label class="fw-bold">Produto</label>
      <input type="text" class="form-control" name="descricao" onkeyup="this.value = this.value.toUpperCase();" required maxlength="150">
    </div>
    <div class="form-group col-md-3">
      <label class="fw-bold">Categoria</label>
      <input type="text" class="form-control" name="categoria" onkeyup="this.value = this.value.toUpperCase();" >
    </div>
  
     <div class="form-group col-md-3">
      <label class="fw-bold">Unidade</label>
     <select class="form-control" id="unidade" name="unidade" >
    <option value="" disabled selected>Selecione...</option>
        <option value="Caixa">Caixa</option>
        <option value="Kit">Kit</option>
        <option value="Litro">Litro</option>
        <option value="Metro">Metro</option>
        <option value="Pacote">Pacote</option>
        <option value="Resma">Resma</option>
        <option value="Unidade">Unidade</option>
        </select>
    </div>

 


<?php
// Fetch Almoxarifados managed by user
$sql_almox = "SELECT a.* FROM TB_ALMOXARIFADOS a 
              INNER JOIN TB_ALMOXARIFADO_RESPONSAVEL ar ON a.ID = ar.ID_Almoxarifado 
              WHERE a.Ativo = 1 AND ar.ID_Usuario = ? AND ar.Ativo = 1";
$stmt_almox = sqlsrv_query($conn, $sql_almox, array($_SESSION['usuarioId']));
?>
    <div class="form-group col-md-12 mt-3">
      <label class="fw-bold">Almoxarifados onde este produto estará disponível</label><br>
      <?php while ($almox = sqlsrv_fetch_array($stmt_almox, SQLSRV_FETCH_ASSOC)) : ?>
        <div class="form-check form-check-inline">
          <input class="form-check-input" type="checkbox" name="almoxarifados[]" value="<?php echo $almox['ID']; ?>" id="almox_<?php echo $almox['ID']; ?>">
          <label class="form-check-label" for="almox_<?php echo $almox['ID']; ?>"><?php echo $almox['Nome']; ?></label>
        </div>
      <?php endwhile; ?>
    </div>
  </div>


  <div class="col-auto my-5">
        <a href="index.php" class="btn btn-outline-success">Cancelar</a>
    <button type="submit" class="btn btn-success"><i class='fas fa-save'></i> Salvar</button>

   


  </div>
</form>

<?php include(FOOTER_TEMPLATE); ?>