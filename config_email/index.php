<?php
require_once('../config.php');
require_once(DBAPI);
$conn = open_database();

if (!isset($_SESSION['usuarioId']) || $_SESSION['usuarioNiveisAcessoId'] != 1) {
    header("Location: " . BASEURL);
    exit;
}

include(HEADER_TEMPLATE);

// Fetch current settings
$sql = "SELECT * FROM [dbo].[TB_CONFIG_EMAIL] WHERE ID = 1";
$stmt = sqlsrv_query($conn, $sql);
$config = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);

if (!$config) {
    $config = [
        'SMTP_HOST' => '',
        'SMTP_PORT' => 587,
        'SMTP_USER' => '',
        'SMTP_PASS' => '',
        'SMTP_FROM_EMAIL' => '',
        'SMTP_FROM_NAME' => ''
    ];
}
?>

<header>
    <div class="row">
        <div class="col-sm-6">
            <h2><i class="fas fa-envelope"></i> Configuração de E-mail (SMTP)</h2>
        </div>
    </div>
</header>
<hr>

<?php if (isset($_SESSION['message'])): ?>
    <div class="alert alert-<?php echo $_SESSION['type']; ?> alert-dismissible fade show" role="alert">
        <?php echo $_SESSION['message']; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php unset($_SESSION['message'], $_SESSION['type']);
endif; ?>

<form action="processa_config.php" method="post">
    <div class="row mb-3">
        <div class="col-md-6">
            <label for="smtp_host" class="form-label">Servidor SMTP</label>
            <input type="text" class="form-control" name="smtp_host" id="smtp_host" value="<?php echo htmlspecialchars((string)$config['SMTP_HOST']); ?>" required>
        </div>
        <div class="col-md-3">
            <label for="smtp_port" class="form-label">Porta SMTP</label>
            <input type="number" class="form-control" name="smtp_port" id="smtp_port" value="<?php echo htmlspecialchars((string)$config['SMTP_PORT']); ?>" required>
        </div>
    </div>

    <div class="row mb-3">
        <div class="col-md-6">
            <label for="smtp_user" class="form-label">Usuário SMTP (E-mail)</label>
            <input type="text" class="form-control" name="smtp_user" id="smtp_user" value="<?php echo htmlspecialchars((string)$config['SMTP_USER']); ?>" required>
        </div>
        <div class="col-md-6">
            <label for="smtp_pass" class="form-label">Senha SMTP</label>
            <input type="password" class="form-control" name="smtp_pass" id="smtp_pass" value="<?php echo htmlspecialchars((string)$config['SMTP_PASS']); ?>">
            <small class="text-muted">Preencha apenas se desejar alterar a senha atual.</small>
        </div>
    </div>

    <div class="row mb-4">
        <div class="col-md-6">
            <label for="smtp_from_email" class="form-label">E-mail do Remetente</label>
            <input type="email" class="form-control" name="smtp_from_email" id="smtp_from_email" value="<?php echo htmlspecialchars((string)$config['SMTP_FROM_EMAIL']); ?>" required>
        </div>
        <div class="col-md-6">
            <label for="smtp_from_name" class="form-label">Nome do Remetente</label>
            <input type="text" class="form-control" name="smtp_from_name" id="smtp_from_name" value="<?php echo htmlspecialchars((string)$config['SMTP_FROM_NAME']); ?>" required>
        </div>
    </div>

    <div class="row">
        <div class="col-md-12">
            <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Salvar Configurações</button>
            <a href="<?php echo BASEURL; ?>" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Voltar</a>
        </div>
    </div>
</form>

<?php include(FOOTER_TEMPLATE); ?>