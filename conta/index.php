<?php
require_once('../config.php');

if (!isset($_SESSION['usuarioId'])) {
    header("Location: " . BASEURL . "login.php");
    exit;
}

require_once('processa/add_processa.php');

$nivel = $_SESSION['usuarioNiveisAcessoId'] ?? 0;
if ($nivel != 1 && $nivel != 2) {
    include(HEADER_TEMPLATE2);
} else {
    include(HEADER_TEMPLATE);
}
?>
<header>
  <div class="row">
    <div class="col-sm-6">
      <h2><i class="fas fa-users"></i> Alterar Senha</h2>
    </div>


</header>

<hr>


<form action="index.php" method="post">
  <!-- area de campos do form -->


  <input type="hidden" class="form-control" name="id" value="<?php echo ($_SESSION['usuarioId']); ?>">




<?php
$conn_conta = open_database();
$sql_conta = "SELECT EMAIL FROM TB_USUARIOS WHERE ID = ?";
$stmt_conta = sqlsrv_query($conn_conta, $sql_conta, array($_SESSION['usuarioId']));
$userRow = sqlsrv_fetch_array($stmt_conta, SQLSRV_FETCH_ASSOC);
$currentEmail = $userRow['EMAIL'] ?? '';
?>
  <div class="row">
    <div class="form-group col-md-3">
      <label for="name">Nome</label>
      <input type="text" class="form-control" value="<?php echo $_SESSION['usuarioNome']; ?>" disabled>
    </div>

    <div class="form-group col-md-3">
      <label for="email">E-mail</label>
      <input type="email" class="form-control" name="email" value="<?php echo htmlspecialchars($currentEmail); ?>">
    </div>

    <div class="form-group col-md-3">
      <label for="campo1">CPF</label>
      <input type="text" class="form-control" value="<?php echo $_SESSION['usuarioCPF']; ?>" disabled>
    </div>

    <div class="form-group col-md-3">
      <label for="campo1">Nova Senha</label>
      <input type="password" class="form-control" name="senha">
      <small class="text-muted">Deixe em branco se não quiser alterar a senha</small>
    </div>
  </div>



  <div class="col-auto my-5">
    <button type="submit" name="submit" class="btn btn-success">Salvar</button>


      <a href="<?php echo BASEURL. 'index.php' ?>" class="btn btn-outline-success">Cancelar</a>
   


  </div>
</form>




</body>

</html>

<script src="<?php echo BASEURL; ?>js/tabela.js"></script>

<?php include(FOOTER_TEMPLATE); ?>