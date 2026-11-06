<?php
/* O processador roda ANTES do cabeçalho: é ele quem trava o acesso, valida o
   CSRF e redireciona. Antes o include do header vinha primeiro, e o header()
   de permissão já não tinha efeito porque a página tinha começado a sair. */
require_once('processa/add_processa.php');
include(HEADER_TEMPLATE);

ch_cabecalho('Novo Usuário', 'fa-user-plus',
    '<a class="btn btn-outline-secondary" href="index.php"><i class="fas fa-arrow-left"></i> Voltar</a>');
?>

<?php if ($erros): ?>
  <div class="alert alert-danger">
    <strong>Não foi possível salvar:</strong>
    <ul class="mb-0 mt-1">
      <?php foreach ($erros as $e): ?><li><?= ch_h($e) ?></li><?php endforeach; ?>
    </ul>
  </div>
<?php endif; ?>

<div class="card-escola">
  <form action="add.php" method="post" id="userForm">
    <?= ch_csrf_campo() ?>

    <div class="row g-3">
      <div class="form-group col-md-6">
        <label class="fw-bold">Nome completo</label>
        <input type="text" class="form-control" name="nome" maxlength="255"
               value="<?= ch_h($d['nome']) ?>" required>
      </div>
      <div class="form-group col-md-6">
        <label class="fw-bold">E-mail</label>
        <input type="email" class="form-control" name="email" maxlength="150"
               value="<?= ch_h($d['email']) ?>">
        <small class="text-muted">Opcional.</small>
      </div>
      <div class="form-group col-md-4">
        <label class="fw-bold">CPF</label>
        <input type="text" class="form-control" name="cpf" id="cpfcnpj" maxlength="14"
               inputmode="numeric" value="<?= ch_h($d['cpf']) ?>" required>
        <small class="text-muted">É com o CPF que a pessoa entra no sistema.</small>
      </div>
      <div class="form-group col-md-4">
        <label class="fw-bold">Senha</label>
        <input type="password" class="form-control" name="senha" minlength="6" required>
        <small class="text-muted">Mínimo de 6 caracteres.</small>
      </div>
      <div class="form-group col-md-4">
        <label class="fw-bold">Nível de acesso</label>
        <select class="form-select" id="nivel" name="nivel" required>
          <option value="">Selecione...</option>
          <option value="1" <?= $d['nivel'] === 1 ? 'selected' : '' ?>>Administrador — toda a rede</option>
          <option value="2" <?= $d['nivel'] === 2 ? 'selected' : '' ?>>Diretor — unidades liberadas</option>
          <option value="3" <?= $d['nivel'] === 3 ? 'selected' : '' ?>>Professor — turmas da equipe</option>
        </select>
      </div>
      <div class="col-12">
        <div class="form-check">
          <input class="form-check-input" type="checkbox" name="ativo" id="ativo" value="1"
                 <?= $d['ativo'] ? 'checked' : '' ?>>
          <label class="form-check-label" for="ativo">Usuário ativo (pode entrar no sistema)</label>
        </div>
      </div>
    </div>

    <div class="alert alert-info mt-3 mb-0">
      <i class="fas fa-circle-info"></i>
      <strong>Diretor</strong> precisa das unidades liberadas em <em>Acesso Unidades</em>.
      <strong>Professor</strong> precisa ser incluído na equipe de uma turma.
    </div>

    <div class="col-auto my-4">
      <button type="submit" class="btn btn-success"><i class="fas fa-save"></i> Salvar</button>
      <a href="index.php" class="btn btn-outline-secondary">Cancelar</a>
    </div>
  </form>
</div>

<script>
  document.addEventListener('DOMContentLoaded', function () {
    if (window.jQuery && $.fn.select2) { $('#nivel').select2({ theme: 'bootstrap-5' }); }

    var form = document.getElementById('userForm');
    form.addEventListener('submit', function (e) {
      var cpf = form.cpf.value.replace(/\D/g, '');
      var senha = form.senha.value.trim();
      var avisos = [];
      if (form.nome.value.trim().length < 3) avisos.push('Informe o nome completo.');
      if (!/^\d{11}$/.test(cpf)) avisos.push('O CPF deve ter 11 dígitos.');
      if (senha.length < 6) avisos.push('A senha deve ter pelo menos 6 caracteres.');
      if (!form.nivel.value) avisos.push('Escolha o nível de acesso.');
      if (avisos.length) {
        e.preventDefault();
        alert('Verifique:\n\n' + avisos.join('\n'));
      }
    });
  });
</script>

<?php include(FOOTER_TEMPLATE); ?>
