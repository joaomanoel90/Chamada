<?php
require_once('../config.php');
require_once(DBAPI);
$conn = open_database();

// Tente carregar o autoloader do composer, se existir. 
// Caso não, certifique-se de baixar o PHPMailer e colocar na pasta inc/PHPMailer/
if (file_exists('../vendor/autoload.php')) {
    require '../vendor/autoload.php';
} elseif (file_exists('../inc/PHPMailer/src/PHPMailer.php')) {
    require '../inc/PHPMailer/src/Exception.php';
    require '../inc/PHPMailer/src/PHPMailer.php';
    require '../inc/PHPMailer/src/SMTP.php';
} else {
    die("PHPMailer não encontrado. Instale via composer ou coloque em inc/PHPMailer/src/");
}

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

if (!isset($_SESSION['usuarioId']) || $_SESSION['usuarioNiveisAcessoId'] != 1 && $_SESSION['usuarioNiveisAcessoId'] != 2) {
    header("Location: " . BASEURL);
    exit;
}

$id_pedido = $_GET['id'] ?? null;
if (!$id_pedido) {
    $_SESSION['message'] = "ID do pedido não informado.";
    $_SESSION['type'] = "danger";
    header("Location: index.php");
    exit;
}

// 1. Buscar o e-mail do usuário que abriu o pedido
$sql_pedido = "
    SELECT p.ID, p.Objetivo, u.EMAIL, u.NOME 
    FROM [dbo].[TB_PEDIDOS] p
    LEFT JOIN [dbo].[TB_USUARIOS] u ON p.ID_Usuario = u.ID
    WHERE p.ID = ?
";
$stmt_pedido = sqlsrv_query($conn, $sql_pedido, array($id_pedido));
$pedido = sqlsrv_fetch_array($stmt_pedido, SQLSRV_FETCH_ASSOC);

if (!$pedido) {
    $_SESSION['message'] = "Pedido não encontrado.";
    $_SESSION['type'] = "danger";
    header("Location: index.php");
    exit;
}

if (empty($pedido['EMAIL'])) {
    $_SESSION['message'] = "O usuário que abriu o pedido não possui e-mail cadastrado.";
    $_SESSION['type'] = "warning";
    header("Location: index.php");
    exit;
}

// 2. Buscar as configurações de e-mail (SMTP)
$sql_config = "SELECT * FROM [dbo].[TB_CONFIG_EMAIL] WHERE ID = 1";
$stmt_config = sqlsrv_query($conn, $sql_config);
$config = sqlsrv_fetch_array($stmt_config, SQLSRV_FETCH_ASSOC);

if (!$config || empty($config['SMTP_HOST'])) {
    $_SESSION['message'] = "Configurações de E-mail não definidas. Configure o SMTP primeiro.";
    $_SESSION['type'] = "danger";
    header("Location: index.php");
    exit;
}

// 3. Configurar o PHPMailer e Enviar
$mail = new PHPMailer(true);

try {
    // Configurações do Servidor SMTP
    $mail->isSMTP();
    $mail->Host       = $config['SMTP_HOST'];
    $mail->SMTPAuth   = true;
    $mail->Username   = $config['SMTP_USER'];
    $mail->Password   = $config['SMTP_PASS'];
    // Ajuste SMTPSecure de acordo com a porta (465 -> ssl, 587 -> tls)
    $mail->SMTPSecure = ($config['SMTP_PORT'] == 465) ? PHPMailer::ENCRYPTION_SMTPS : PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port       = $config['SMTP_PORT'];
    $mail->CharSet    = 'UTF-8';

    // Remetente e Destinatário
    $mail->setFrom($config['SMTP_FROM_EMAIL'], $config['SMTP_FROM_NAME']);
    $mail->addAddress($pedido['EMAIL'], $pedido['NOME']);

    // Conteúdo
    $mail->isHTML(true);
    $mail->Subject = "Pedido #{$pedido['ID']} Finalizado";
    $mail->Body    = "Olá <b>{$pedido['NOME']}</b>,<br><br>Seu pedido <b>#{$pedido['ID']} ({$pedido['Objetivo']})</b> foi finalizado com sucesso!<br><br>O pedido está pronto para a retirada.";
    $mail->AltBody = "Olá {$pedido['NOME']},\n\nSeu pedido #{$pedido['ID']} ({$pedido['Objetivo']}) foi finalizado com sucesso!\n\nO pedido está pronto para a retirada.";

    $mail->send();

    $_SESSION['message'] = "E-mail enviado com sucesso para {$pedido['EMAIL']}!";
    $_SESSION['type'] = "success";
} catch (Exception $e) {
    $_SESSION['message'] = "Erro ao enviar e-mail. Mailer Error: {$mail->ErrorInfo}";
    $_SESSION['type'] = "danger";
}

header("Location: index.php");
exit;
