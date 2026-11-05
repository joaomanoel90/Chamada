<?php
require_once('../config.php');
require_once(DBAPI);

$conn = open_database();
if (!$conn) {
    die("Erro de conexão com o banco de dados.");
}

function adjustIdentitySeed($conn) {
    $sql_max_id = 'SELECT MAX(id) AS max_id FROM TB_PRODUTOS';
    $stmt_max_id = sqlsrv_query($conn, $sql_max_id);

    if ($stmt_max_id === false) {
        die("Erro ao recuperar o maior ID: " . print_r(sqlsrv_errors(), true));
    }

    $max_id = sqlsrv_fetch_array($stmt_max_id, SQLSRV_FETCH_ASSOC)['max_id'];

    if ($max_id === null) {
        $sql_checkident = "DBCC CHECKIDENT ('TB_PRODUTOS', RESEED, 1)";
    } else {
        $sql_checkident = "DBCC CHECKIDENT ('TB_PRODUTOS', RESEED, $max_id)";
    }

    $stmt_checkident = sqlsrv_query($conn, $sql_checkident);

    if ($stmt_checkident === false) {
        die("Erro ao executar DBCC CHECKIDENT: " . print_r(sqlsrv_errors(), true));
    }
}

$erro = ''; // Variável para armazenar a mensagem de erro

if (!empty($_POST)) {
    $descricao = strip_tags(trim($_POST['descricao'] ?? ''));
    $categoria = strip_tags(trim($_POST['categoria'] ?? ''));
    $unidade = strip_tags(trim($_POST['unidade'] ?? ''));
    $ativo = 1;

    $almoxarifados = $_POST['almoxarifados'] ?? array();
    
    sqlsrv_begin_transaction($conn);

    try {
            $sql = 'INSERT INTO TB_PRODUTOS (Descricao, Categoria, Unidade, Ativo) OUTPUT INSERTED.ID VALUES(?,?,?,?)';
            $params = array($descricao, $categoria, $unidade, $ativo);

            $stmt = sqlsrv_query($conn, $sql, $params);

            if ($stmt === false) {
                throw new Exception('Erro ao executar a consulta.');
            }
            
            $inserted = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
            $produto_id = $inserted['ID'];
            
            if (!empty($almoxarifados)) {
                foreach ($almoxarifados as $id_almox) {
                    $sql_almox = "INSERT INTO TB_PRODUTO_ALMOXARIFADO (ID_Produto, ID_Almoxarifado, Ativo) VALUES (?, ?, 1)";
                    sqlsrv_query($conn, $sql_almox, array($produto_id, $id_almox));
                }
            }

            sqlsrv_commit($conn);
            header("location:index.php");
            exit();
        } catch (Exception $e) {
            sqlsrv_rollback($conn);
            adjustIdentitySeed($conn);
            $erro = "Erro ao inserir: " . $e->getMessage();
        }
    }

?>
