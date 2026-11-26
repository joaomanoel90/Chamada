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
        <input type="password" class="form-control" name="senha" minlength="12" required>
        <small class="text-muted">Mínimo de 12 caracteres.</small>
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
      <div class="col-12" id="avisoSemMateria" style="display:none">
        <div class="alert alert-light border py-2 mb-0 small" id="avisoSemMateriaTexto"></div>
      </div>
      <div class="col-12" id="grupoMaterias">
        <label class="fw-bold">Matérias que pode lecionar</label>
        <select class="form-select" id="materias-prof" name="prof_mat_nome[]" multiple>
          <?php foreach (eq_materias_opcoes() as $mat): ?>
            <option value="<?= ch_h($mat) ?>"
                    <?= in_array($mat, (array)$d['materias'], true) ? 'selected' : '' ?>>
              <?= ch_h($mat) ?>
            </option>
          <?php endforeach; ?>
        </select>
        <small class="text-muted d-block mt-1">No Pré I e Pré II o professor PEB I é atribuído diretamente na turma sem matéria. Marque matérias para quem leciona nos Anos Iniciais/Finais ou matérias específicas (ex.: Educação Física, Artes, Inglês).</small>
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
      <strong>Professor</strong>: informe a <strong>categoria do concurso</strong> e as <strong>matérias que ele pode lecionar</strong> — no painel <em>Atribuição de Professores</em> ele já aparece nas turmas compatíveis, mesmo antes de ter equipe. A Secretaria de Educação tem alcance da rede, sem administração de usuários.
    </div>

    <div class="col-auto my-4">
      <button type="submit" class="btn btn-success"><i class="fas fa-save"></i> Salvar</button>
      <a href="index.php" class="btn btn-outline-secondary">Cancelar</a>
    </div>
  </form>
</div>
</div>

<script nonce="<?= ch_csp_nonce() ?>">
  document.addEventListener('DOMContentLoaded', function () {
    if (window.jQuery && $.fn.select2) { $('#nivel').select2({ theme: 'bootstrap-5' }); }

    /* Turno, categoria e matérias só aparecem para professor. Escondidos, os
       campos continuam no formulário, mas a validação no servidor já
       descarta os valores. */
    function mostraTurno() {
      var ehProf = document.getElementById('nivel').value === '3';
      var cat = ehProf ? document.getElementById('categoria').value : '';
      var msg = '';
      if (cat === 'AEE') {
        msg = '<i class="fas fa-puzzle-piece text-primary"></i> <strong>Professor de AEE:</strong> atua no Atendimento Educacional Especializado e é vinculado diretamente na ficha dos alunos (não recebe matérias de turma).';
      } else if (cat === 'PDI_CRECHE') {
        msg = '<i class="fas fa-baby text-success"></i> <strong>PDI — Creche:</strong> atua na Creche (Berçário I ao Maternal II) assumindo um período da turma integral (Manhã ou Tarde), sem matéria.';
      } else if (cat === 'APOIO_CRECHE') {
        msg = '<i class="fas fa-hands-holding-child text-info"></i> <strong>Professor de Apoio — Creche:</strong> atua na Creche assumindo o dia todo (período Integral), sem matéria.';
      }
      document.getElementById('grupoTurno').style.display = ehProf ? '' : 'none';
      document.getElementById('grupoCategoria').style.display = ehProf ? '' : 'none';
      document.getElementById('grupoMaterias').style.display = (ehProf && !msg) ? '' : 'none';
      document.getElementById('avisoSemMateria').style.display = msg ? '' : 'none';
      document.getElementById('avisoSemMateriaTexto').innerHTML = msg;
    }
    mostraTurno();
    document.getElementById('nivel').addEventListener('change', mostraTurno);
    document.getElementById('categoria').addEventListener('change', mostraTurno);
    if (window.jQuery) { $(document).on('select2:select select2:clear', '#nivel', mostraTurno); }

    /* Matérias do professor: seleção múltipla da lista. */
    if (window.jQuery && $.fn.select2) {
      $('#materias-prof').select2({ theme: 'bootstrap-5', placeholder: 'Escolha as matérias...' });
    }

    var form = document.getElementById('userForm');
    form.addEventListener('submit', function (e) {
      var cpf = form.cpf.value.replace(/\D/g, '');
      var senha = form.senha.value.trim();
      var avisos = [];
      if (form.nome.value.trim().length < 3) avisos.push('Informe o nome completo.');
      if (!/^\d{11}$/.test(cpf)) avisos.push('O CPF deve ter 11 dígitos.');
      if (senha.length < 12) avisos.push('A senha deve ter pelo menos 12 caracteres.');
      if (!form.nivel.value) avisos.push('Escolha o nível de acesso.');
      if (avisos.length) {
        e.preventDefault();
        alert('Verifique:\n\n' + avisos.join('\n'));
      }
    });
  });
</script>

<?php include(FOOTER_TEMPLATE); ?>
