<?php
require_once('../config.php');
require_once(DBAPI);
$conn = open_database();

if (!isset($_SESSION['usuarioId']) || $_SESSION['usuarioNiveisAcessoId'] != 1) {
    header("Location: " . BASEURL);
    exit;
}

include(HEADER_TEMPLATE);

$sql = "SELECT * FROM TB_ALMOXARIFADOS";
$stmt = sqlsrv_query($conn, $sql);
?>

<header>
    <div class="row">
        <div class="col-sm-6">
            <h2><i class="fas fa-warehouse"></i> Almoxarifados</h2>
        </div>
        <div class="col-sm-6 text-sm-end h2">
            <a class="btn btn-success" href="add.php"><i class="fa fa-plus"></i> Novo Almoxarifado</a>
        </div>
    </div>
</header>
<hr>

<div class="table-responsive-sm">
    <table class="table table-light table-striped table-sm">
        <thead class="text-sm-center">
            <tr>
                <th width="10%">ID</th>
                <th width="60%">Nome</th>
                <th width="15%">Ativo</th>
                <th width="15%">Opções</th>
            </tr>
        </thead>
        <tbody>
            <?php
            if (sqlsrv_has_rows($stmt)) {
                while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
            ?>
                <tr>
                    <td class="text-center"><?php echo $row['ID']; ?></td>
                    <td><?php echo $row['Nome']; ?></td>
                    <td class="text-center"><?php echo $row['Ativo'] ? 'Sim' : 'Não'; ?></td>
                    <td class="actions text-center">
                        <a href="responsaveis.php?id=<?php echo $row['ID']; ?>" class="btn btn-sm btn-info text-white"><i class="fas fa-user-tie"></i> Responsáveis</a>
                        <a href="edit.php?id=<?php echo $row['ID']; ?>" class="btn btn-sm btn-warning"><i class="far fa-edit"></i> Editar</a>
                    </td>
                </tr>
            <?php 
                } 
            } else { 
            ?>
                <tr>
                    <td colspan="4" class="text-center">Nenhum almoxarifado encontrado.</td>
                </tr>
            <?php } ?>
        </tbody>
    </table>
</div>

<?php 
sqlsrv_free_stmt($stmt);
include(FOOTER_TEMPLATE); 
?>
