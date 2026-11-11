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
 <div class="card-escola-corpo">
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
          <option value="2" <?= $d['nivel'] === 2 ? 'selected' : '' ?>>Diretor Escolar — unidades liberadas</option>
          <option value="3" <?= $d['nivel'] === 3 ? 'selected' : '' ?>>Professor — turmas da equipe</option>
          <option value="4" <?= $d['nivel'] === 4 ? 'selected' : '' ?>>Secretaria de Educação — toda a rede</option>
          <option value="5" <?= $d['nivel'] === 5 ? 'selected' : '' ?>>Secretário Escolar — unidades liberadas</option>
        </select>
      </div>
      <div class="form-group col-md-4" id="grupoTurno">
        <label class="fw-bold">Turno de trabalho</label>
        <select class="form-select" id="turno" name="turno">
          <option value="">Não definido</option>
<?php foreach (eq_turnos() as $sigla => $rotulo): ?>
          <option value="<?= $sigla ?>" <?= strtoupper((string)$d['turno']) === $sigla ? 'selected' : '' ?>><?= ch_h($rotulo) ?></option>
<?php endforeach; ?>
        </select>
        <small class="text-muted">Só para professor. É o padrão ao montar a equipe da turma — pode ser mudado lá.</small>
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
      <strong>Diretor e Secretário Escolar</strong> precisam das unidades liberadas em <em>Acesso Unidades</em>.
      <strong>Professor</strong> precisa ser incluído na equipe de uma turma. A Secretaria de Educação tem alcance da rede, sem administração de usuários.
    </div>

    <div class="col-auto my-4">
      <button type="submit" class="btn btn-success"><i class="fas fa-save"></i> Salvar</button>
      <a href="index.php" class="btn btn-outline-secondary">Cancelar</a>
    </div>
  </form>
</div>
</div>

<script>
  document.addEventListener('DOMContentLoaded', function () {
    if (window.jQuery && $.fn.select2) { $('#nivel').select2({ theme: 'bootstrap-5' }); }

    /* O turno só aparece para professor. Escondido, o campo continua no
       formulário, mas a validação no servidor já descarta o valor. */
    function mostraTurno() {
      var ehProf = document.getElementById('nivel').value === '3';
      document.getElementById('grupoTurno').style.display = ehProf ? '' : 'none';
    }
    mostraTurno();
    document.getElementById('nivel').addEventListener('change', mostraTurno);
    if (window.jQuery) { $(document).on('select2:select select2:clear', '#nivel', mostraTurno); }

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
