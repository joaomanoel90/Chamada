<?php
require_once('processa/add_processa.php');
include(HEADER_TEMPLATE);

// Verifica permissão
if ($_SESSION['usuarioNiveisAcessoId'] != 1) {
  header("location:" . BASEURL);
  exit();
}

// Gera token CSRF
if (session_status() == PHP_SESSION_NONE) session_start();
if (empty($_SESSION['csrf_token'])) {
  $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];
?>

<h2><i class="fas fa-plus"></i> Novo Usuário</h2>

<form action="add.php" method="post" id="userForm">
  <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">

  <hr />
  <div class="row">
    <div class="form-group col-md-3">
      <label class="fw-bold">Nome</label>
      <input type="text" class="form-control" name="nome" required>
    </div>
    <div class="form-group col-md-3">
      <label class="fw-bold">E-mail</label>
      <input type="email" class="form-control" name="email">
    </div>
    <div class="form-group col-md-2">
      <label class="fw-bold">CPF</label>
      <input type="text" class="form-control" name="cpf" id="cpfcnpj" maxlength="14" required>
    </div>
    <div class="form-group col-md-2">
      <label class="fw-bold">Senha</label>
      <input type="password" class="form-control" name="senha" required>
    </div>
    <div class="form-group col-md-2">
      <label class="fw-bold">Nível</label>
      <select class="form-control" id="nivel" name="nivel" required>
        <option>Selecione...</option>
        <option value="1">Administrador</option>
        <option value="2">Coordenador</option>
        <option value="3">Operador</option>
      </select>
    </div>
  </div>

  <div class="col-auto my-5">
    <a href="index.php" class="btn btn-outline-success">Cancelar</a>
    <button type="submit" class="btn btn-success"><i class='fas fa-save'></i> Salvar</button>
  </div>
</form>

<script>
  $(document).ready(function () {
    $('#nivel').select2({ theme: "bootstrap-5" });

    document.getElementById("userForm").addEventListener("submit", function (e) {
  const form = e.target;
  const nome = form.nome.value.trim();
  const cpfOriginal = form.cpf.value.trim();
  const cpf = cpfOriginal.replace(/\D/g, ""); // 🔥 remove máscara
  const senha = form.senha.value.trim();
  const nivel = form.nivel.value;

  if (!nome || !cpf || !senha || nivel === "Selecione...") {
    e.preventDefault();
    alert("Por favor, preencha todos os campos obrigatórios.");
    return;
  }

  if (!/^\d{11}$/.test(cpf)) {
    e.preventDefault();
    alert("O CPF deve conter exatamente 11 dígitos numéricos.");
    return;
  }

  if (senha.length < 6) {
    e.preventDefault();
    alert("A senha deve ter pelo menos 6 caracteres.");
    return;
  }
});
  });
</script>
<script src="<?php echo BASEURL; ?>js/mask.js"></script>

<?php include(FOOTER_TEMPLATE); ?>
