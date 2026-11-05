<?php
require_once('../config.php');
require_once(DBAPI);

$conn = open_database();

if (isset($_GET['id']) && !empty($_GET['id'])) {

    $id = $_GET['id'];

    // Busca o usuário
    $sql = "SELECT * FROM TB_USUARIOS WHERE ID = ?";
    $params = array($id);

    $stmt = sqlsrv_query($conn, $sql, $params);

    if ($stmt === false) {
        die(print_r(sqlsrv_errors(), true));
    }

    $rows = sqlsrv_has_rows($stmt);

    if (!$rows) {
        header("Location: index.php");
        exit;
    }

    // Atualização
    if (!empty($_POST)) {

        $nome  = $_POST['nome'] ?? '';
        $senha = $_POST['senha'] ?? '';
        $cpf   = $_POST['cpf'] ?? '';
        $email = $_POST['email'] ?? '';

        if (!empty($senha)) {

            $senha = md5($senha);

            $sql = "UPDATE TB_USUARIOS 
                    SET NOME = ?, 
                        SENHA = ?, 
                        CPF = ?, 
                        MODIFIED = GETDATE(), 
                        EMAIL = ? 
                    WHERE ID = ?";

            $params = array(
                $nome,
                $senha,
                $cpf,
                $email,
                $id
            );
        } else {

            $sql = "UPDATE TB_USUARIOS 
                    SET NOME = ?, 
                        CPF = ?, 
                        MODIFIED = GETDATE(), 
                        EMAIL = ? 
                    WHERE ID = ?";

            $params = array(
                $nome,
                $cpf,
                $email,
                $id
            );
        }

        $stmt = sqlsrv_query($conn, $sql, $params);

        if ($stmt === false) {
            die(print_r(sqlsrv_errors(), true));
        }

        header("Location: index.php");
        exit;
    }
}
