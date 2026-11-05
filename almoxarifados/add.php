<?php
require_once('../config.php');
require_once(DBAPI);
$conn = open_database();

if (!isset($_SESSION['usuarioId']) || $_SESSION['usuarioNiveisAcessoId'] != 1) {
    header("Location: " . BASEURL);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $nome = strip_tags(trim($_POST['nome'] ?? ''));
    $ativo = isset($_POST['ativo']) ? 1 : 0;
    
    if ($nome) {
        $sql = "INSERT INTO TB_ALMOXARIFADOS (Nome, Ativo) VALUES (?, ?)";
        $stmt = sqlsrv_query($conn, $sql, array($nome, $ativo));
        if ($stmt) {
            header("Location: index.php");
            exit;
        }
    }
}

include(HEADER_TEMPLATE);
?>

<header>
    <div class="row">
        <div class="col-sm-6">
            <h2><i class="fas fa-plus"></i> Novo Almoxarifado</h2>
        </div>
    </div>
</header>
<hr>

<form action="add.php" method="post">
    <div class="row">
        <div class="form-group col-md-6">
            <label class="fw-bold">Nome do Almoxarifado</label>
            <input type="text" class="form-control" name="nome" required>
        </div>
        <div class="form-group col-md-2 mt-4 pt-2">
            <div class="form-check">
                <input class="form-check-input" type="checkbox" name="ativo" id="ativo" checked>
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
