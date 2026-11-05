<?php 
require_once('processa/responsaveis_processa.php'); 
include(HEADER_TEMPLATE); 

if ($_SESSION['usuarioNiveisAcessoId'] != 1) {
    header("location:" . BASEURL);
    exit;
}

if (!empty($_GET['id'])) {
    if ($rows_almox) {
        $almox = sqlsrv_fetch_array($stmt_almox, SQLSRV_FETCH_ASSOC);
?>

<h2>
    <i class="fas fa-user-tie"></i> 
    Responsáveis pelo Almoxarifado: <?php echo $almox['Nome']; ?>
</h2>

<?php if (!empty($_GET['erro']) && $_GET['erro'] == 'duplicado'): ?>
<div class="alert alert-warning">
    Este usuário já é responsável por este almoxarifado.
</div>
<?php endif; ?>

<form action="responsaveis.php?id=<?php echo $id; ?>" method="post">
    <hr />
    <div class="row">
        <div class="form-group col-md-12">
            <label for="usuario">Usuário (Responsável)</label>
            <select class="form-control" name="usuario" required>
                <option selected disabled value="">Selecione...</option>
                <?php while ($usr = sqlsrv_fetch_array($stmt_usuarios, SQLSRV_FETCH_ASSOC)) : ?>
                    <option value="<?php echo $usr['ID']; ?>">
                        <?php echo $usr['NOME']; ?>
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
                <th>Responsável</th>
                <th class="text-end">Ações</th>
            </tr>
        </thead>
        <tbody>
            <?php if ($rows_acessos): ?>
                <?php while ($acesso = sqlsrv_fetch_array($stmt_acessos, SQLSRV_FETCH_ASSOC)) : ?>
                    <tr>
                        <td><?php echo $acesso['ID_VINCULO']; ?></td>
                        <td><?php echo $acesso['NOME']; ?></td>
                        <td class="text-end">
                            <a href="responsaveis.php?id=<?php echo $id; ?>&delete=<?php echo $acesso['ID_VINCULO']; ?>" 
                               class="btn btn-sm btn-danger"
                               onclick="return confirm('Tem certeza que deseja remover este responsável?');">
                                <i class="far fa-trash-alt"></i>
                            </a>
                        </td>
                    </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr>
                    <td colspan="3">Nenhum responsável vinculado.</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php
        sqlsrv_free_stmt($stmt_almox);
        sqlsrv_free_stmt($stmt_usuarios);
        sqlsrv_free_stmt($stmt_acessos);
    }
} else {
    header("location:index.php");
}

include(FOOTER_TEMPLATE);
?>
