<?php 
  require_once('processa/edit_processa.php'); 
?>

<?php include(HEADER_TEMPLATE); ?>
<?php
  if($_SESSION['usuarioNiveisAcessoId']!=1){
    header( "location:".BASEURL );
  }
?>
<?php if (!empty($_GET['id'])) {
  $id = $_GET['id'];


  if(isset($rows)){
    $row = sqlsrv_fetch_array( $stmt, SQLSRV_FETCH_ASSOC ) ;
  
  


?>


<h2><i class="far fa-edit"></i> Editar Usuário: <?php echo $id; ?></h2>

<form action="edit.php?id=<?php echo $id; ?>" method="post">
  <!-- area de campos do form -->
  <hr />
  <div class="row">
    <div class="form-group col-md-3">
      <label for="name">Nome</label>
      <input type="text" class="form-control" name="nome"  value="<?php echo htmlspecialchars($row['NOME'] ?? '');?>" required>
    </div>
    <div class="form-group col-md-3">
      <label for="email">E-mail</label>
      <input type="email" class="form-control" name="email" value="<?php echo htmlspecialchars($row['EMAIL'] ?? '');?>">
    </div>
  <div class="form-group col-md-3">
      <label for="campo1">CPF</label>
      <input type="text" class="form-control" name="cpf" id="cpfcnpj" maxlength="14"  value="<?php echo htmlspecialchars($row['CPF'] ?? ''); ?>" required>
    </div>

    <div class="form-group col-md-3">
      <label for="campo1">Nova Senha</label>
      <input type="password" class="form-control" name="senha">
      <small class="text-muted">Deixe em branco para não alterar</small>
    </div>
  </div>
  
  
  
  <div class="col-auto my-5">
      <button type="submit" name="submit" class="btn btn-success">Salvar</button>
      
      <a href="index.php" class="btn btn-outline-success">Cancelar</a>


    </div>
</form>
<?php
}
sqlsrv_free_stmt( $stmt); 
} else {
  header( "location:index.php" );
}
?>
<script src="<?php echo BASEURL; ?>js/mask.js"></script>

<?php include(FOOTER_TEMPLATE); ?>