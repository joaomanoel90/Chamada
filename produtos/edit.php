<?php
require_once('processa/edit_processa.php');

if ($_SESSION['usuarioNiveisAcessoId'] != 1 && $_SESSION['usuarioNiveisAcessoId'] != 2) {
	header("location:" . BASEURL);
     exit();
} else {
	include(HEADER_TEMPLATE);
}
if (!isset($produto['ID'])) {
    echo "<p>Produto não encontrado.</p>";
    include(FOOTER_TEMPLATE);
    exit;
}
?>
<?php if (!empty($erro)): ?>
    <div style="color: red; font-weight: bold;">
        <?php echo htmlspecialchars($erro); ?>
    </div>
<?php endif; ?>


<h2><i class="far fa-edit"></i> Editar Produto: <?= htmlspecialchars($produto['Descricao']) ?></h2>

<form action="edit.php?id=<?= $id ?>" method="post">
  <input type="hidden" class="form-control" name="id" id="id" value="<?= $id ?>" required>

  <hr />

  <!-- Dados Pessoais -->
  <div class="row">
    <div class="form-group col-md-6">
      <label class="fw-bold">Produto</label>
      <input type="text" class="form-control" name="descricao" value="<?= htmlspecialchars($produto['Descricao']) ?>" onkeyup="this.value = this.value.toUpperCase();" id="descricao" maxlength="150" required>
    </div>
<div class="form-group col-md-3">
         <label class="fw-bold">Categoria</label>
      <input type="text" class="form-control" name="categoria" value="<?= htmlspecialchars($produto['Categoria']) ?>" onkeyup="this.value = this.value.toUpperCase();" id="categoria" required>

</div>

    <div class="form-group col-md-3">
   
     <label class="fw-bold" for="unidade">Unidade</label>
    <select class="form-control" id="unidade" name="unidade" required>
        <option value="" disabled <?= empty($produto['Unidade']) ? 'selected' : '' ?>>Selecione...</option>
                <option value="Caixa"<?= ($produto['Unidade'] === 'Caixa') ? 'selected' : '' ?>>Caixa</option>
        <option value="Kit"<?= ($produto['Unidade'] === 'Kit') ? 'selected' : '' ?>>Kit</option>
        <option value="Litro"<?= ($produto['Unidade'] === 'Litro') ? 'selected' : '' ?>>Litro</option>
        <option value="Metro"<?= ($produto['Unidade'] === 'Metro') ? 'selected' : '' ?>>Metro</option>
        <option value="Pacote"<?= ($produto['Unidade'] === 'Pacote') ? 'selected' : '' ?>>Pacote</option>
        <option value="Resma"<?= ($produto['Unidade'] === 'Resma') ? 'selected' : '' ?>>Resma</option>
        <option value="Unidade"<?= ($produto['Unidade'] === 'Unidade') ? 'selected' : '' ?>>Unidade</option>


    </select>
    </div>
  </div>

<?php
// Fetch Almoxarifados managed by user
$sql_almox = "SELECT a.* FROM TB_ALMOXARIFADOS a 
              INNER JOIN TB_ALMOXARIFADO_RESPONSAVEL ar ON a.ID = ar.ID_Almoxarifado 
              WHERE a.Ativo = 1 AND ar.ID_Usuario = ? AND ar.Ativo = 1";
$stmt_almox = sqlsrv_query($conn, $sql_almox, array($_SESSION['usuarioId']));

// Fetch current Almoxarifados for this product
$sql_prod_almox = "SELECT ID_Almoxarifado FROM TB_PRODUTO_ALMOXARIFADO WHERE ID_Produto = ? AND Ativo = 1";
$stmt_prod_almox = sqlsrv_query($conn, $sql_prod_almox, array($id));
$current_almox = array();
while($row_almox = sqlsrv_fetch_array($stmt_prod_almox, SQLSRV_FETCH_ASSOC)) {
    $current_almox[] = $row_almox['ID_Almoxarifado'];
}
?>
  <div class="row">
    <div class="form-group col-md-12 mt-3">
      <label class="fw-bold">Almoxarifados onde este produto estará disponível</label><br>
      <?php while ($almox = sqlsrv_fetch_array($stmt_almox, SQLSRV_FETCH_ASSOC)) : ?>
        <div class="form-check form-check-inline">
          <input class="form-check-input" type="checkbox" name="almoxarifados[]" value="<?php echo $almox['ID']; ?>" id="almox_<?php echo $almox['ID']; ?>" <?php echo in_array($almox['ID'], $current_almox) ? 'checked' : ''; ?>>
          <label class="form-check-label" for="almox_<?php echo $almox['ID']; ?>"><?php echo $almox['Nome']; ?></label>
        </div>
      <?php endwhile; ?>
    </div>
  </div>



  <div class="col-auto my-5">
    <a href="index.php" class="btn btn-outline-success">Cancelar</a>

    <button type="submit" class="btn btn-success"><i class='fas fa-save'></i> Salvar Alterações</button>
  </div>
</form>




<?php include(FOOTER_TEMPLATE); ?>
