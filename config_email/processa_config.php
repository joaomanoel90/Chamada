<?php
require_once('../config.php');
require_once(DBAPI);
$conn = open_database();

if (!isset($_SESSION['usuarioId']) || $_SESSION['usuarioNiveisAcessoId'] != 1) {
    header("Location: " . BASEURL);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $smtp_host = $_POST['smtp_host'] ?? '';
    $smtp_port = $_POST['smtp_port'] ?? '';
    $smtp_user = $_POST['smtp_user'] ?? '';
    $smtp_pass = $_POST['smtp_pass'] ?? '';
    $smtp_from_email = $_POST['smtp_from_email'] ?? '';
    $smtp_from_name = $_POST['smtp_from_name'] ?? '';

    $check_sql = "SELECT ID FROM [dbo].[TB_CONFIG_EMAIL] WHERE ID = 1";
    $check_stmt = sqlsrv_query($conn, $check_sql);
    $has_row = sqlsrv_fetch_array($check_stmt);

    if ($has_row) {
        if (!empty($smtp_pass)) {
            $sql = "UPDATE [dbo].[TB_CONFIG_EMAIL] 
                    SET SMTP_HOST = ?, SMTP_PORT = ?, SMTP_USER = ?, SMTP_PASS = ?, SMTP_FROM_EMAIL = ?, SMTP_FROM_NAME = ? 
                    WHERE ID = 1";
            $params = array($smtp_host, $smtp_port, $smtp_user, $smtp_pass, $smtp_from_email, $smtp_from_name);
        } else {
            $sql = "UPDATE [dbo].[TB_CONFIG_EMAIL] 
                    SET SMTP_HOST = ?, SMTP_PORT = ?, SMTP_USER = ?, SMTP_FROM_EMAIL = ?, SMTP_FROM_NAME = ? 
                    WHERE ID = 1";
            $params = array($smtp_host, $smtp_port, $smtp_user, $smtp_from_email, $smtp_from_name);
        }
    } else {
        $sql = "SET IDENTITY_INSERT [dbo].[TB_CONFIG_EMAIL] ON; 
                INSERT INTO [dbo].[TB_CONFIG_EMAIL] (ID, SMTP_HOST, SMTP_PORT, SMTP_USER, SMTP_PASS, SMTP_FROM_EMAIL, SMTP_FROM_NAME) 
                VALUES (1, ?, ?, ?, ?, ?, ?); 
                SET IDENTITY_INSERT [dbo].[TB_CONFIG_EMAIL] OFF;";
        $params = array($smtp_host, $smtp_port, $smtp_user, $smtp_pass, $smtp_from_email, $smtp_from_name);
    }

    $stmt = sqlsrv_query($conn, $sql, $params);

    if ($stmt) {
        $_SESSION['message'] = "Configurações atualizadas com sucesso.";
        $_SESSION['type'] = "success";
    } else {
        $errors = sqlsrv_errors();
        $error_msg = !empty($errors) ? $errors[0]['message'] : "Erro desconhecido";
        $_SESSION['message'] = "Erro ao atualizar configurações: " . $error_msg;
        $_SESSION['type'] = "danger";
    }

    header("Location: index.php");
    exit;
}
?>
