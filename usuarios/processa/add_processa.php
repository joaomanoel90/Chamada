<?php

require_once('../config.php');
require_once(DBAPI);

$conn = open_database();

// Verifica se o ID foi informado
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: index.php");
    exit;
}

$id = $_GET['id'];

// Busca o usuário com parâmetro SQL
$sql = "SELECT * FROM TB_USUARIOS WHERE ID = ?";
$params = [$id];

$stmt = sqlsrv_query($conn, $sql, $params);

if ($stmt === false) {
    die(print_r(sqlsrv_errors(), true));
}

// Verifica se o usuário existe
if (!sqlsrv_has_rows($stmt)) {
    header("Location: index.php");
    exit;
}


// ======================================================
// ATUALIZAÇÃO DO USUÁRIO
// ======================================================

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Recebe e limpa os dados
    $nome  = strip_tags(trim($_POST['nome'] ?? ''));
    $cpf   = trim($_POST['cpf'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $senha = trim($_POST['senha'] ?? '');

    // Validações básicas
    if (empty($nome)) {
        die('O nome é obrigatório.');
    }

    if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        die('E-mail inválido.');
    }


    // ==================================================
    // COM SENHA
    // ==================================================

    if (!empty($senha)) {

        // Mantendo MD5 para compatibilidade com o sistema atual
        $senha = md5($senha);

        $sql = "UPDATE TB_USUARIOS
                SET
                    NOME = ?,
                    SENHA = ?,
                    CPF = ?,
                    MODIFIED = GETDATE(),
                    EMAIL = ?
                WHERE ID = ?";

        $params = [
            $nome,
            $senha,
            $cpf,
            $email,
            $id
        ];
    } else {

        // Não altera a senha se o campo estiver vazio
        $sql = "UPDATE TB_USUARIOS
                SET
                    NOME = ?,
                    CPF = ?,
                    MODIFIED = GETDATE(),
                    EMAIL = ?
                WHERE ID = ?";

        $params = [
            $nome,
            $cpf,
            $email,
            $id
        ];
    }


    // Executa o UPDATE
    $stmt = sqlsrv_query($conn, $sql, $params);

    // Verifica erro no UPDATE
    if ($stmt === false) {
        die(print_r(sqlsrv_errors(), true));
    }


    // Redireciona após atualizar
    header("Location: index.php");
    exit;
}
