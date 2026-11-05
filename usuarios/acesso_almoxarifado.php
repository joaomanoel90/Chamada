<?php 
require_once('processa/acesso_almoxarifado_processa.php'); 
include(HEADER_TEMPLATE); 

if ($_SESSION['usuarioNiveisAcessoId'] != 1) {
    header("location:" . BASEURL);
    exit;
}

if (!empty($_GET['id'])) {
    if ($rows_usuario) {
        $usuario = sqlsrv_fetch_array($stmt_usuario, SQLSRV_FETCH_ASSOC);
?>

<h2>
    <i class="fas fa-cart-arrow-down"></i> 
    Almoxarifados permitidos p/ pedido: <?php echo $usuario['NOME']; ?>
</h2>

<?php if (!empty($_GET['erro']) && $_GET['erro'] == 'duplicado'): ?>
<div class="alert alert-warning">
    Este usuário já possui acesso a este almoxarifado.
</div>
<?php endif; ?>

<form action="acesso_almoxarifado.php?id=<?php echo $id; ?>" method="post">
    <hr />
    <div class="row">
        <div class="form-group col-md-12">
            <label for="almoxarifado">Almoxarifado</label>
            <select class="form-control" name="almoxarifado" required>
                <option selected disabled value="">Selecione...</option>
                <?php while ($almox = sqlsrv_fetch_array($stmt_almoxarifados, SQLSRV_FETCH_ASSOC)) : ?>
                    <option value="<?php echo $almox['ID']; ?>">
                        <?php echo $almox['Nome']; ?>
                    </option>
                <?php endwhile; ?>
            </select>
        </div>
    </div>
    <div class="col-auto my-4">
        <button type="submit" name="submit" class="btn btn-success">Salvar</button>
        <a href="index.php" class="btn btn-outline-secondary">Voltar</a>
    </div>
</form>

<hr>

<div class="table-responsive-sm">
    <table class="table table-hover table-striped table-sm">
        <thead>
            <tr>
                <th>ID do Vínculo</th>
                <th>Almoxarifado</th>
                <th class="text-end">Ações</th>
            </tr>
        </thead>
        <tbody>
            <?php if ($rows_acessos): ?>
                <?php while ($acesso = sqlsrv_fetch_array($stmt_acessos, SQLSRV_FETCH_ASSOC)) : ?>
                    <tr>
                        <td><?php echo $acesso['ID_ACESSO']; ?></td>
                        <td><?php echo $acesso['NOME_ALMOX']; ?></td>
                        <td class="text-end">
                            <a href="acesso_almoxarifado.php?id=<?php echo $id; ?>&delete=<?php echo $acesso['ID_ACESSO']; ?>" 
                               class="btn btn-sm btn-danger"
                               onclick="return confirm('Tem certeza que deseja remover este vínculo?');">
                                <i class="far fa-trash-alt"></i>
                            </a>
                        </td>
                    </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr>
                    <td colspan="3">Nenhum almoxarifado vinculado.</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php
        sqlsrv_free_stmt($stmt_usuario);
        sqlsrv_free_stmt($stmt_almoxarifados);
        sqlsrv_free_stmt($stmt_acessos);
    }
} else {
    header("location:index.php");
}

include(FOOTER_TEMPLATE);
?>
