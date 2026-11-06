<?php require_once('processa/edit_processa.php'); ?>
<?php include(HEADER_TEMPLATE); ?>

<?php ch_cabecalho('Editar Usuário', 'fa-user-pen',
    '<a class="btn btn-outline-secondary" href="index.php"><i class="fas fa-arrow-left"></i> Voltar</a>'); ?>

<?= usuario_erros_html($erros) ?>

<div class="card-escola">
 <div class="card-escola-corpo">
  <div class="d-flex align-items-center gap-3 mb-3">
    <span class="avatar-prof"><?= ch_h(ch_iniciais($d['nome'])) ?></span>
    <div>
      <div class="fw-bold"><?= ch_h($usuario['NOME']) ?></div>
      <small class="text-muted">
        Cadastrado em <?= ch_h(ch_data_br(substr((string)$usuario['CREATED'], 0, 10))) ?>
        <?php if (!empty($usuario['MODIFIED'])): ?>
          · última alteração em <?= ch_h(ch_data_br(substr((string)$usuario['MODIFIED'], 0, 10))) ?>
        <?php endif; ?>
      </small>
    </div>
  </div>

  <form action="edit.php?id=<?= $id ?>" method="post" id="userForm">
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
      </div>
      <div class="form-group col-md-4">
        <label class="fw-bold">CPF</label>
        <input type="text" class="form-control" name="cpf" id="cpfcnpj" maxlength="14"
               inputmode="numeric" value="<?= ch_h($d['cpf']) ?>" required>
      </div>
      <div class="form-group col-md-4">
        <label class="fw-bold">Nova senha</label>
        <input type="password" class="form-control" name="senha">
        <small class="text-muted">Deixe em branco para manter a senha atual.</small>
      </div>
      <div class="form-group col-md-4">
        <label class="fw-bold">Nível de acesso</label>
        <select class="form-select" id="nivel" name="nivel" required>
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
        <?php if ($id === ch_uid()): ?>
          <small class="text-muted">Este é o seu usuário: o nível e a situação não podem ser alterados aqui.</small>
        <?php endif; ?>
      </div>
    </div>

    <div class="col-auto my-4">
      <button type="submit" class="btn btn-success"><i class="fas fa-save"></i> Salvar</button>
      <a href="index.php" class="btn btn-outline-secondary">Cancelar</a>
      <?php if ($d['nivel'] === 2): ?>
        <a href="acesso.php?id=<?= $id ?>" class="btn btn-outline-primary">
          <i class="far fa-check-square"></i> Acesso Unidades
        </a>
      <?php endif; ?>
    </div>
  </form>
</div>
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
      if (senha !== '' && senha.length < 6) avisos.push('A nova senha deve ter pelo menos 6 caracteres.');
      if (avisos.length) {
        e.preventDefault();
        alert('Verifique:\n\n' + avisos.join('\n'));
      }
    });
  });
</script>

<?php include(FOOTER_TEMPLATE); ?>
