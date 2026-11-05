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

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['almoxarifado'])) {
    $id_almox = (int)$_POST['almoxarifado'];
    
    // Verifies if already exists
    $sql_check = "SELECT ID, Ativo FROM TB_USUARIO_ALMOXARIFADO WHERE ID_Usuario = ? AND ID_Almoxarifado = ?";
    $stmt_check = sqlsrv_query($conn, $sql_check, array($id, $id_almox));
    
    if (sqlsrv_has_rows($stmt_check)) {
        $row = sqlsrv_fetch_array($stmt_check, SQLSRV_FETCH_ASSOC);
        if ($row['Ativo'] == 1) {
            header("Location: acesso_almoxarifado.php?id=$id&erro=duplicado");
            exit;
        } else {
            $sql_update = "UPDATE TB_USUARIO_ALMOXARIFADO SET Ativo = 1 WHERE ID = ?";
            sqlsrv_query($conn, $sql_update, array($row['ID']));
            header("Location: acesso_almoxarifado.php?id=$id");
            exit;
        }
    } else {
        $sql_insert = "INSERT INTO TB_USUARIO_ALMOXARIFADO (ID_Usuario, ID_Almoxarifado, Ativo) VALUES (?, ?, 1)";
        sqlsrv_query($conn, $sql_insert, array($id, $id_almox));
        header("Location: acesso_almoxarifado.php?id=$id");
        exit;
    }
}

if (isset($_GET['delete'])) {
    $id_delete = (int)$_GET['delete'];
    $sql_delete = "UPDATE TB_USUARIO_ALMOXARIFADO SET Ativo = 0 WHERE ID = ?";
    sqlsrv_query($conn, $sql_delete, array($id_delete));
    header("Location: acesso_almoxarifado.php?id=$id");
    exit;
}

// Data for rendering
$sql_usuario = "SELECT NOME FROM TB_USUARIOS WHERE ID = ?";
$stmt_usuario = sqlsrv_query($conn, $sql_usuario, array($id));
$rows_usuario = sqlsrv_has_rows($stmt_usuario);

$sql_almoxarifados = "SELECT * FROM TB_ALMOXARIFADOS WHERE Ativo = 1";
$stmt_almoxarifados = sqlsrv_query($conn, $sql_almoxarifados);

$sql_acessos = "
    SELECT ua.ID as ID_ACESSO, a.Nome as NOME_ALMOX 
    FROM TB_USUARIO_ALMOXARIFADO ua 
    INNER JOIN TB_ALMOXARIFADOS a ON ua.ID_Almoxarifado = a.ID 
    WHERE ua.ID_Usuario = ? AND ua.Ativo = 1
";
$stmt_acessos = sqlsrv_query($conn, $sql_acessos, array($id));
$rows_acessos = sqlsrv_has_rows($stmt_acessos);
?>
