<?php require_once('processa/index_processa.php'); ?>
<?php include(ch_gestor() ? HEADER_TEMPLATE : HEADER_TEMPLATE2); ?>

<?php ch_cabecalho('Chamada de Alunos', 'fa-clipboard-user'); ?>

<form method="get" action="index.php" class="row g-2 align-items-end mb-3">
  <div class="col-md-6">
    <label class="fw-bold">Turma</label>
    <select name="turma_id" class="form-select" required>
      <option value="">Selecione...</option>
      <?php foreach ($turmas as $t): ?>
        <option value="<?= (int)$t['ID'] ?>" <?= (int)$t['ID'] === $turmaId ? 'selected' : '' ?>>
          <?= ch_h($t['UNIDADE']) ?> — <?= ch_h($t['NOME']) ?> (<?= ch_h($t['PERIODO']) ?>, <?= (int)$t['ANO_LETIVO'] ?>)
        </option>
      <?php endforeach; ?>
    </select>
  </div>
  <div class="col-md-3">
    <label class="fw-bold">Data</label>
    <input type="date" name="data" class="form-control" max="<?= ch_hoje() ?>" value="<?= ch_h($data) ?>" required>
  </div>
  <div class="col-md-3">
    <button class="btn btn-primary w-100"><i class="fas fa-magnifying-glass"></i> Abrir chamada</button>
  </div>
</form>

<?php if (!$turmas): ?>
  <div class="alert alert-warning">
    Nenhuma turma disponível para o seu usuário.
    <?php if (ch_gestor()): ?>Cadastre uma turma em <a href="<?= ch_url('turmas/index.php') ?>">Turmas</a>.
    <?php else: ?>Peça à gestão o vínculo com a sua unidade em <strong>Usuários &rarr; Acesso por Unidade</strong>.<?php endif; ?>
  </div>
<?php elseif (!$turma): ?>
  <div class="alert alert-info">Selecione uma turma e a data para iniciar a chamada.</div>
<?php else: ?>

  <div class="card border-0 shadow-sm rounded-4 mb-3">
    <div class="card-body py-3">
      <div class="row align-items-center">
        <div class="col-md-8">
          <strong><?= ch_h($turma['UNIDADE']) ?></strong> — <?= ch_h($turma['NOME']) ?><br>
          <span class="text-muted">
            <?= ch_h($turma['PERIODO']) ?> (<?= ch_h($turma['HORA_INICIO']) ?>–<?= ch_h($turma['HORA_FIM']) ?>)
            &middot; <?= ch_data_longa($data) ?>
            &middot; Professor(a): <?= $turma['PROFESSOR'] ? ch_h($turma['PROFESSOR']) : 'não definido' ?>
          </span>
        </div>
        <div class="col-md-4 text-md-end">
          <?php if ($jaLancada): ?>
            <span class="badge bg-success">Chamada já lançada</span>
            <div class="mt-1">
              <small class="text-muted">
                P <?= $resumo['P'] ?> &middot; A <?= $resumo['A'] ?> &middot; F <?= $resumo['F'] ?> &middot; J <?= $resumo['J'] ?>
              </small>
            </div>
          <?php else: ?>
            <span class="badge bg-secondary">Ainda não lançada</span>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>

  <?php if ($bloqueio): ?>
    <div class="alert alert-warning">
      <i class="fas fa-triangle-exclamation"></i> <strong>Chamada bloqueada.</strong> <?= ch_h($bloqueio) ?>
    </div>
  <?php endif; ?>

  <?php if (!$alunos): ?>
    <div class="alert alert-info">Não há alunos ativos nesta turma.</div>
  <?php else: ?>

  <form method="post" action="salvar.php" id="form-chamada">
    <input type="hidden" name="turma_id" value="<?= (int)$turmaId ?>">
    <input type="hidden" name="data" value="<?= ch_h($data) ?>">

    <?php if (!$bloqueio): ?>
    <div class="d-flex flex-wrap gap-2 mb-2">
      <button type="button" class="btn btn-sm btn-outline-success" data-marcar-todos="P"><i class="fas fa-check-double"></i> Marcar todos presentes</button>
      <a class="btn btn-sm btn-outline-secondary" href="exportar.php?turma_id=<?= (int)$turmaId ?>&data=<?= ch_h($data) ?>"><i class="fas fa-file-csv"></i> Exportar CSV</a>
    </div>
    <?php endif; ?>

    <div class="table-responsive">
      <table class="table table-light table-striped align-middle">
        <thead>
          <tr>
            <th width="30%">Aluno</th>
            <th width="34%" class="text-center">Situação no dia</th>
            <th width="28%">Justificativa / anotação</th>
            <th width="8%" class="text-center">Obs.</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($alunos as $a): $st = $a['STATUS'] ?: ''; ?>
          <tr>
            <td>
              <strong><?= ch_h($a['NOME']) ?></strong><br>
              <small class="text-muted"><?= ch_h($a['MATRICULA']) ?> &middot; <?= ch_h(ch_idade_texto($a['NASCIMENTO'], $data)) ?></small>
            </td>
            <td class="text-center">
              <div class="btn-group flex-wrap" role="group">
                <?php foreach (ch_status_lista() as $sigla => $info): ?>
                  <input type="radio" class="btn-check" name="status[<?= (int)$a['ID'] ?>]"
                         id="st<?= (int)$a['ID'] ?><?= $sigla ?>" value="<?= $sigla ?>"
                         <?= $st === $sigla ? 'checked' : '' ?> <?= $bloqueio ? 'disabled' : '' ?>>
                  <label class="btn btn-sm btn-outline-<?= $info['cor'] ?>" for="st<?= (int)$a['ID'] ?><?= $sigla ?>"
                         title="<?= ch_h($info['rotulo']) ?>">
                    <i class="fas <?= $info['icone'] ?>"></i> <?= ch_h($info['rotulo']) ?>
                  </label>
                <?php endforeach; ?>
              </div>
            </td>
            <td>
              <input type="text" class="form-control form-control-sm" name="justificativa[<?= (int)$a['ID'] ?>]"
                     maxlength="500" value="<?= ch_h($a['JUSTIFICATIVA'] ?? '') ?>"
                     placeholder="Obrigatória se falta justificada" <?= $bloqueio ? 'disabled' : '' ?>>
            </td>
            <td class="text-center">
              <a class="btn btn-sm btn-outline-primary" title="Registrar observação do aluno"
                 href="<?= ch_url('observacoes/add.php?aluno_id=' . (int)$a['ID'] . '&data=' . ch_h($data) . '&voltar=' . rawurlencode('chamada/index.php?turma_id=' . $turmaId . '&data=' . $data)) ?>">
                <i class="far fa-comment-dots"></i><?= isset($obsPorAluno[(int)$a['ID']]) ? ' ' . $obsPorAluno[(int)$a['ID']] : '' ?>
              </a>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <?php if (!$bloqueio): ?>
    <div class="col-auto my-4">
      <a href="index.php" class="btn btn-outline-success">Cancelar</a>
      <button type="submit" class="btn btn-success btn-lg"><i class="fas fa-save"></i> Salvar chamada</button>
    </div>
    <?php endif; ?>
  </form>

  <script>
    document.querySelectorAll('[data-marcar-todos]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var v = btn.getAttribute('data-marcar-todos');
        document.querySelectorAll('input[type=radio][value="' + v + '"]').forEach(function (r) { r.checked = true; });
      });
    });
    document.getElementById('form-chamada').addEventListener('submit', function (e) {
      var faltando = [];
      document.querySelectorAll('tbody tr').forEach(function (tr) {
        var radios = tr.querySelectorAll('input[type=radio]');
        if (!radios.length) return;
        var marcado = tr.querySelector('input[type=radio]:checked');
        var nome = tr.querySelector('strong').textContent.trim();
        if (!marcado) { faltando.push(nome); return; }
        if (marcado.value === 'J') {
          var j = tr.querySelector('input[type=text]');
          if (!j.value.trim()) { faltando.push(nome + ' (justificativa obrigatória)'); }
        }
      });
      if (faltando.length) {
        e.preventDefault();
        alert('Verifique os alunos a seguir antes de salvar:\n\n' + faltando.join('\n'));
      }
    });
  </script>
  <?php endif; ?>
<?php endif; ?>

<?php include(FOOTER_TEMPLATE); ?>
