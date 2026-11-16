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
        <input type="password" class="form-control" name="senha" minlength="12">
        <small class="text-muted">Deixe em branco para manter a senha atual.</small>
      </div>
      <div class="form-group col-md-4">
        <label class="fw-bold">Nível de acesso</label>
        <select class="form-select" id="nivel" name="nivel" required>
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
      <div class="form-group col-md-4" id="grupoCategoria">
        <label class="fw-bold">Categoria do concurso</label>
        <select class="form-select" id="categoria" name="categoria">
          <option value="">Sem restrição (todas as etapas)</option>
<?php foreach (eq_categorias_concurso() as $sigla => $rotulo): ?>
          <option value="<?= $sigla ?>" <?= strtoupper((string)$d['categoria']) === $sigla ? 'selected' : '' ?>><?= ch_h($rotulo) ?></option>
<?php endforeach; ?>
        </select>
        <small class="text-muted">Só para professor. Diz em quais turmas ele pode ser atribuído no começo do ano, antes de ter equipe.</small>
      </div>
      <div class="col-12" id="grupoMaterias">
        <label class="fw-bold">Matérias que pode lecionar</label>
        <div id="lista-materias-prof">
          <?php foreach ((array)$d['materias'] as $mat): ?>
          <div class="row g-2 align-items-center mb-2" data-linha-mat>
            <div class="col-md-6">
              <input type="text" name="prof_mat_nome[]" class="form-control" maxlength="80"
                     placeholder="Ex.: Matemática" value="<?= ch_h($mat) ?>">
            </div>
            <div class="col-md-2">
              <button type="button" class="btn btn-outline-danger btn-sm" data-remover-mat title="Remover matéria">
                <i class="fas fa-trash"></i>
              </button>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
        <template id="modelo-materia-prof">
          <div class="row g-2 align-items-center mb-2" data-linha-mat>
            <div class="col-md-6">
              <input type="text" name="prof_mat_nome[]" class="form-control" maxlength="80" placeholder="Ex.: Matemática">
            </div>
            <div class="col-md-2">
              <button type="button" class="btn btn-outline-danger btn-sm" data-remover-mat title="Remover matéria">
                <i class="fas fa-trash"></i>
              </button>
            </div>
          </div>
        </template>
        <button type="button" class="btn btn-outline-success btn-sm" id="add-materia-prof">
          <i class="fas fa-plus"></i> Adicionar matéria
        </button>
        <small class="text-muted d-block mt-1">Só para professor. Deixe vazio se ele pode lecionar qualquer matéria.</small>
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
      <?php if (in_array($d['nivel'], [2, 5], true)): ?>
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

    /* Turno, categoria e matérias só aparecem para professor. Escondidos, os
       campos continuam no formulário, mas a validação no servidor já
       descarta os valores. */
    function mostraTurno() {
      var ehProf = document.getElementById('nivel').value === '3';
      document.getElementById('grupoTurno').style.display = ehProf ? '' : 'none';
      document.getElementById('grupoCategoria').style.display = ehProf ? '' : 'none';
      document.getElementById('grupoMaterias').style.display = ehProf ? '' : 'none';
    }
    mostraTurno();
    document.getElementById('nivel').addEventListener('change', mostraTurno);
    if (window.jQuery) { $(document).on('select2:select select2:clear', '#nivel', mostraTurno); }

    /* Linhas de matéria do professor: adicionar e remover. */
    var listaMat = document.getElementById('lista-materias-prof');
    var modeloMat = document.getElementById('modelo-materia-prof');
    document.getElementById('add-materia-prof').addEventListener('click', function () {
      var frag = document.createElement('template');
      frag.innerHTML = modeloMat.innerHTML.trim();
      listaMat.appendChild(frag.content.firstElementChild);
      var campo = listaMat.lastElementChild.querySelector('input[name="prof_mat_nome[]"]');
      if (campo) campo.focus();
    });
    listaMat.addEventListener('click', function (ev) {
      var btn = ev.target.closest('[data-remover-mat]');
      if (!btn) return;
      var linha = btn.closest('[data-linha-mat]');
      if (linha) linha.remove();
    });

    var form = document.getElementById('userForm');
    form.addEventListener('submit', function (e) {
      var cpf = form.cpf.value.replace(/\D/g, '');
      var senha = form.senha.value.trim();
      var avisos = [];
      if (form.nome.value.trim().length < 3) avisos.push('Informe o nome completo.');
      if (!/^\d{11}$/.test(cpf)) avisos.push('O CPF deve ter 11 dígitos.');
      if (senha !== '' && senha.length < 12) avisos.push('A nova senha deve ter pelo menos 12 caracteres.');
      if (avisos.length) {
        e.preventDefault();
        alert('Verifique:\n\n' + avisos.join('\n'));
      }
    });
  });
</script>

<?php include(FOOTER_TEMPLATE); ?>
