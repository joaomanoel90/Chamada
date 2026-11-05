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
    header("Location: ../index.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['usuario'])) {
    $id_usuario = (int)$_POST['usuario'];
    
    // Verifies if already exists
    $sql_check = "SELECT ID, Ativo FROM TB_ALMOXARIFADO_RESPONSAVEL WHERE ID_Almoxarifado = ? AND ID_Usuario = ?";
    $stmt_check = sqlsrv_query($conn, $sql_check, array($id, $id_usuario));
    
    if (sqlsrv_has_rows($stmt_check)) {
        $row = sqlsrv_fetch_array($stmt_check, SQLSRV_FETCH_ASSOC);
        if ($row['Ativo'] == 1) {
            header("Location: responsaveis.php?id=$id&erro=duplicado");
            exit;
        } else {
            $sql_update = "UPDATE TB_ALMOXARIFADO_RESPONSAVEL SET Ativo = 1 WHERE ID = ?";
            sqlsrv_query($conn, $sql_update, array($row['ID']));
            header("Location: responsaveis.php?id=$id");
            exit;
        }
    } else {
        $sql_insert = "INSERT INTO TB_ALMOXARIFADO_RESPONSAVEL (ID_Almoxarifado, ID_Usuario, Ativo) VALUES (?, ?, 1)";
        sqlsrv_query($conn, $sql_insert, array($id, $id_usuario));
        header("Location: responsaveis.php?id=$id");
        exit;
    }
}

if (isset($_GET['delete'])) {
    $id_delete = (int)$_GET['delete'];
    $sql_delete = "UPDATE TB_ALMOXARIFADO_RESPONSAVEL SET Ativo = 0 WHERE ID = ?";
    sqlsrv_query($conn, $sql_delete, array($id_delete));
    header("Location: responsaveis.php?id=$id");
    exit;
}

// Data for rendering
$sql_almox = "SELECT Nome FROM TB_ALMOXARIFADOS WHERE ID = ?";
$stmt_almox = sqlsrv_query($conn, $sql_almox, array($id));
$rows_almox = sqlsrv_has_rows($stmt_almox);

$sql_usuarios = "SELECT ID, NOME FROM TB_USUARIOS WHERE ATIVO = 1 OR ATIVO IS NULL ORDER BY NOME";
$stmt_usuarios = sqlsrv_query($conn, $sql_usuarios);

$sql_acessos = "
    SELECT ar.ID as ID_VINCULO, u.NOME 
    FROM TB_ALMOXARIFADO_RESPONSAVEL ar 
    INNER JOIN TB_USUARIOS u ON ar.ID_Usuario = u.ID 
    WHERE ar.ID_Almoxarifado = ? AND ar.Ativo = 1
";
$stmt_acessos = sqlsrv_query($conn, $sql_acessos, array($id));
$rows_acessos = sqlsrv_has_rows($stmt_acessos);
