<?php
require_once('../config.php');
require_once(DBAPI);
$conn = open_database();

if (!isset($_SESSION['usuarioId']) || $_SESSION['usuarioNiveisAcessoId'] != 1) {
    header("Location: " . BASEURL);
    exit;
}

$id = $_GET['id'] ?? null;
if (!$id) {
    header("Location: index.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $nome = strip_tags(trim($_POST['nome'] ?? ''));
    $ativo = isset($_POST['ativo']) ? 1 : 0;
    
    if ($nome) {
        $sql = "UPDATE TB_ALMOXARIFADOS SET Nome = ?, Ativo = ? WHERE ID = ?";
        $stmt = sqlsrv_query($conn, $sql, array($nome, $ativo, $id));
        if ($stmt) {
            header("Location: index.php");
            exit;
        }
    }
}

$sql = "SELECT * FROM TB_ALMOXARIFADOS WHERE ID = ?";
$stmt = sqlsrv_query($conn, $sql, array($id));
$row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);

if (!$row) {
    header("Location: index.php");
    exit;
}

include(HEADER_TEMPLATE);
?>

<header>
    <div class="row">
        <div class="col-sm-6">
            <h2><i class="far fa-edit"></i> Editar Almoxarifado: <?php echo $id; ?></h2>
        </div>
    </div>
</header>
<hr>

<form action="edit.php?id=<?php echo $id; ?>" method="post">
    <div class="row">
        <div class="form-group col-md-6">
            <label class="fw-bold">Nome do Almoxarifado</label>
            <input type="text" class="form-control" name="nome" value="<?php echo htmlspecialchars($row['Nome']); ?>" required>
        </div>
        <div class="form-group col-md-2 mt-4 pt-2">
            <div class="form-check">
                <input class="form-check-input" type="checkbox" name="ativo" id="ativo" <?php echo $row['Ativo'] ? 'checked' : ''; ?>>
                <label class="form-check-label fw-bold" for="ativo">Ativo</label>
            </div>
        </div>
    </div>
    
    <div class="col-auto my-5">
        <button type="submit" class="btn btn-success"><i class='fas fa-save'></i> Salvar</button>
        <a href="index.php" class="btn btn-outline-success">Cancelar</a>
    </div>
</form>

<?php include(FOOTER_TEMPLATE); ?>
