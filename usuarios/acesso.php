<?php 
require_once('processa/acesso_processa.php'); 
include(HEADER_TEMPLATE); 

if ($_SESSION['usuarioNiveisAcessoId'] != 1) {
    header("location:" . BASEURL);
    exit;
}

if (!empty($_GET['id'])) {
    $id = $_GET['id'];

    if ($rows_usuario) {
        $usuario = sqlsrv_fetch_array($stmt_usuario, SQLSRV_FETCH_ASSOC);
?>

<h2>
    <i class="far fa-check-square"></i> 
    Acessos do usuário: <?php echo $usuario['NOME']; ?>
</h2>

<?php if (!empty($_GET['erro']) && $_GET['erro'] == 'duplicado'): ?>
<div class="alert alert-warning">
    Este usuário já possui acesso à unidade selecionada.
</div>
<?php endif; ?>

<!-- 🔹 FORMULÁRIO NORMAL -->
<form action="acesso.php?id=<?php echo $id; ?>" method="post">
    <hr />

    <div class="row">
        <div class="form-group col-md-12">
            <label for="unidade">Unidades</label>
            <select class="form-control" name="unidade">
                <option selected disabled>Selecione...</option>
                <?php while ($unidade = sqlsrv_fetch_array($stmt_unidades, SQLSRV_FETCH_ASSOC)) : ?>
                    <option value="<?php echo $unidade['id']; ?>">
                        <?php echo $unidade['unidade']; ?>
                    </option>
                <?php endwhile; ?>
            </select>
        </div>
    </div>

    <div class="col-auto my-4">
        <button type="submit" name="submit" class="btn btn-success">
            Salvar
        </button>

        <a href="index.php" class="btn btn-outline-secondary">
            Cancelar
        </a>
    </div>
</form>

<!-- 🔴 AÇÃO SEPARADA (SEGURO) -->
<hr>

<div class="alert alert-warning d-flex justify-content-between align-items-center">
    <div>
        <strong>Atenção:</strong> Esta ação irá adicionar <b>TODAS</b> as unidades ao usuário.
    </div>

    <form action="acesso.php?id=<?php echo $id; ?>" method="post" style="margin:0;">
        <button type="submit" name="add_all" class="btn btn-danger"
            onclick="return confirm('Tem certeza que deseja adicionar TODAS as unidades? Essa ação pode gerar muitos acessos.')">
            Adicionar Todas
        </button>
    </form>
</div>

<!-- 🔹 TABELA -->
<hr>

<div class="table-responsive-sm">
    <table class="table table-hover table-striped table-sm">
        <thead>
            <tr>
                <th>ID</th>
                <th>Unidade</th>
                <th class="text-end">Ações</th>
            </tr>
        </thead>
        <tbody>
            <?php if ($rows_acessos > 0): ?>
                <?php while ($acesso = sqlsrv_fetch_array($stmt_acessos, SQLSRV_FETCH_ASSOC)) : ?>
                    <tr>
                        <td><?php echo $acesso['ID_ACESSO']; ?></td>
                        <td><?php echo $acesso['NOME_UNIDADE']; ?></td>
                        <td class="text-end">
                            <a href="#" class="btn btn-sm btn-danger"
                               data-bs-toggle="modal"
                               data-bs-target="#acesso-modal"
                               data-id="<?php echo $acesso['ID_ACESSO']; ?>">
                                <i class="far fa-trash-alt"></i>
                            </a>
                        </td>
                    </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr>
                    <td colspan="3">Nenhum registro encontrado.</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php
        sqlsrv_free_stmt($stmt_usuario);
        sqlsrv_free_stmt($stmt_unidades);
        sqlsrv_free_stmt($stmt_acessos);
    }
} else {
    header("location:index.php");
}

include('modal_acesso.php'); 
include(FOOTER_TEMPLATE);
?>