<?php
require_once('processa/add_processa.php');
?>

<?php
if (!isset($_SESSION['usuarioId'])) {
    header("Location: " . BASEURL . "login.php");
    exit;
}

if ($_SESSION['usuarioNiveisAcessoId'] != 1 && $_SESSION['usuarioNiveisAcessoId'] != 2) {
  include(HEADER_TEMPLATE2);
} else {
	include(HEADER_TEMPLATE);
}
?>
 <?php if (!empty($erro)): ?>
        <div class="error-banner"><?php echo $erro; ?></div>
    <?php endif; ?>
<h2><i class="fas fa-plus"></i> Novo Pedido</h2>

<form action="add.php" method="post">
  <!-- area de campos do form -->
  <hr />
  <div class="row">
 <div class="form-group col-md-6">
        <label class="fw-bold">Unidade</label>
<select class="form-control" name="unidade" required>
                <option selected disabled value="">Selecione...</option>
                <?php while ($unidade = sqlsrv_fetch_array($stmt_acessos, SQLSRV_FETCH_ASSOC)) : ?>
                    <option value="<?php echo $unidade['ID_UNIDADE']; ?>"><?php echo $unidade['NOME_UNIDADE']; ?></option>
                <?php endwhile; ?>
            </select>    </div>
    
 <div class="form-group col-md-6">
        <label class="fw-bold">Almoxarifado</label>
<select class="form-control" name="almoxarifado" required>
                <option selected disabled value="">Selecione...</option>
                <?php while ($almox = sqlsrv_fetch_array($stmt_almox, SQLSRV_FETCH_ASSOC)) : ?>
                    <option value="<?php echo $almox['ID']; ?>"><?php echo $almox['Nome']; ?></option>
                <?php endwhile; ?>
            </select>    </div>
  </div>
  <div class="row">
     <div class="form-group col-md-12">
      <label class="fw-bold">Objetivo</label>
     <input type="text" class="form-control" id="objetivo" name="objetivo" maxlength="300" required>
    </div>

 


  </div>





  <div class="col-auto my-5">
        <a href="index.php" class="btn btn-outline-success">Cancelar</a>
    <button type="submit" class="btn btn-success"><i class='fas fa-save'></i> Salvar</button>

   


  </div>
</form>

<?php include(FOOTER_TEMPLATE); ?>