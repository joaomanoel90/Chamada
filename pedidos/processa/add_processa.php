<?php
require_once('../config.php');
require_once(DBAPI);

$conn = open_database();
if (!$conn) {
    die("Erro de conexão com o banco de dados.");
}

function adjustIdentitySeed($conn)
{
    $sql_max_id = 'SELECT MAX(id) AS max_id FROM TB_PEDIDOS';
    $stmt_max_id = sqlsrv_query($conn, $sql_max_id);

    if ($stmt_max_id === false) {
        die("Erro ao recuperar o maior ID: " . print_r(sqlsrv_errors(), true));
    }

    $max_id = sqlsrv_fetch_array($stmt_max_id, SQLSRV_FETCH_ASSOC)['max_id'];

    if ($max_id === null) {
        $sql_checkident = "DBCC CHECKIDENT ('TB_PEDIDOS', RESEED, 1)";
    } else {
        $sql_checkident = "DBCC CHECKIDENT ('TB_PEDIDOS', RESEED, $max_id)";
    }

    $stmt_checkident = sqlsrv_query($conn, $sql_checkident);

    if ($stmt_checkident === false) {
        die("Erro ao executar DBCC CHECKIDENT: " . print_r(sqlsrv_errors(), true));
    }
}

$erro = ''; // Variável para armazenar a mensagem de erro

if (!empty($_POST)) {
    $objetivo = strip_tags(trim($_POST['objetivo'] ?? ''));
    $unidade = strip_tags(trim($_POST['unidade'] ?? ''));
    $almoxarifado = strip_tags(trim($_POST['almoxarifado'] ?? ''));
    $ativo = 1;
    $status = 'Aberto';

    try {
        $sql = 'INSERT INTO TB_PEDIDOS (Objetivo, ID_Unidade, Ativo, Status, ID_Usuario, ID_Almoxarifado) OUTPUT INSERTED.ID VALUES(?,?,?,?,?,?)';
        $params = array($objetivo, $unidade, $ativo, $status, $_SESSION['usuarioId'], $almoxarifado);

        $stmt = sqlsrv_query($conn, $sql, $params);

        if ($stmt === false) {
            throw new Exception('Erro ao executar a consulta.');
        }

        $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
        $new_id = $row['ID'];

        sqlsrv_commit($conn);
        header("location:lista.php?id=" . $new_id);
        exit();
    } catch (Exception $e) {
        sqlsrv_rollback($conn);
        adjustIdentitySeed($conn);
        $erro = "Erro ao inserir: " . $e->getMessage();
    }
}
//Buscar Acessos do Usuário
$sql_acessos = "SELECT ID_UNIDADE, NOME_UNIDADE FROM VIEW_ACESSOS_USUARIOS WHERE ID_USUARIO = ? AND ATIVO = 1";
$stmt_acessos = sqlsrv_query($conn, $sql_acessos, array($_SESSION['usuarioId']));
$rows_acessos = sqlsrv_has_rows($stmt_acessos);

//Buscar Almoxarifados do Usuário
$sql_almox = "SELECT a.* FROM TB_ALMOXARIFADOS a 
              INNER JOIN TB_USUARIO_ALMOXARIFADO ua ON a.ID = ua.ID_Almoxarifado 
              WHERE a.Ativo = 1 AND ua.ID_Usuario = ? AND ua.Ativo = 1";
$stmt_almox = sqlsrv_query($conn, $sql_almox, array($_SESSION['usuarioId']));

