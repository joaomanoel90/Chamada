<?php require_once('processa/index_processa.php'); ?>
<?php include(HEADER_TEMPLATE); ?>

<?php ch_cabecalho('Atribuição de Professores', 'fa-chalkboard-user'); ?>

<div class="alert alert-info">
  <i class="fas fa-circle-info"></i>
  Primeiro escolha o <b>professor</b>: aparecem só as turmas em que ele pode ser
  atribuído (as turmas da equipe dele), agrupadas por <b>etapa</b> (da Creche ao
  Fundamental) e por série. As turmas <b>já completas</b> (todas as matérias com
  professor) vêm marcadas. Em cada turma estão as <b>matérias</b>, com as
  <b>aulas por semana</b> e o <b>professor</b> que as dá. A gestão pode trocar o
  professor, ajustar as aulas e adicionar ou remover matérias; o professor vê
  aqui as turmas e matérias dele.
</div>

<form method="get" action="index.php" class="row g-2 align-items-end mb-3">
  <?php if ($ehGestao): ?>
  <div class="col-md-4">
    <label class="fw-bold">Professor</label>
    <select name="professor" class="form-select">
      <option value="0">Todos os professores</option>
      <?php foreach ($professores as $p): ?>
        <option value="<?= (int)$p['ID'] ?>" <?= (int)$p['ID'] === $fProfessor ? 'selected' : '' ?>>
          <?= ch_h($p['NOME']) ?>
        </option>
      <?php endforeach; ?>
    </select>
  </div>
  <?php endif; ?>
  <div class="col-md-<?= $ehGestao ? '3' : '5' ?>">
    <label class="fw-bold">Escola</label>
    <select name="unidade" class="form-select">
      <option value="0">Todas</option>
      <?php foreach ($unidades as $u): ?>
        <option value="<?= (int)$u['id'] ?>" <?= (int)$u['id'] === $fUnidade ? 'selected' : '' ?>>
          <?= ch_h($u['unidade']) ?>
        </option>
      <?php endforeach; ?>
    </select>
  </div>
  <div class="col-md-3">
    <label class="fw-bold">Ano letivo</label>
    <select name="ano" class="form-select">
      <?php foreach ($anos as $a): ?>
        <option value="<?= (int)$a['ANO_LETIVO'] ?>" <?= (int)$a['ANO_LETIVO'] === $fAno ? 'selected' : '' ?>>
          <?= (int)$a['ANO_LETIVO'] ?>
        </option>
      <?php endforeach; ?>
    </select>
  </div>
  <div class="col-md-2"><button class="btn btn-primary w-100"><i class="fas fa-filter"></i> Filtrar</button></div>
</form>

<?php if ($resumoProfessor): ?>
  <div class="alert alert-light border d-flex flex-wrap align-items-center gap-2">
    <i class="fas fa-user-check text-success"></i>
    <span><b><?= ch_h($nomeProfessor) ?></b> — <?= $resumoProfessor['turmas'] ?> turma(s) na equipe:</span>
    <span class="badge bg-success"><i class="fas fa-check"></i> <?= $resumoProfessor['completas'] ?> completa(s)</span>
    <span class="badge bg-warning text-dark"><?= $resumoProfessor['incompletas'] ?> incompleta(s)</span>
    <span class="badge bg-secondary"><?= $resumoProfessor['sem_materias'] ?> sem matérias definidas</span>
  </div>
<?php endif; ?>

<?php if ($erros): ?>
  <div class="alert alert-danger"><ul class="mb-0"><?php foreach ($erros as $e): ?><li><?= ch_h($e) ?></li><?php endforeach; ?></ul></div>
<?php endif; ?>

<?php if (!$turmas): ?>
  <div class="text-center text-muted py-5">
    <i class="fas fa-chalkboard-user fa-2x mb-3 d-block"></i>
    Nenhuma turma encontrada para os filtros escolhidos.
  </div>
<?php endif; ?>

<?php foreach ($grupos as $etapa => $porSerie): ?>
  <h4 class="mt-4 mb-3"><i class="fas fa-layer-group"></i> <?= ch_h($etapas[$etapa] ?? 'Sem etapa definida') ?></h4>

  <?php foreach ($porSerie as $chaveSerie => $listaTurmas): ?>
    <?php $rotuloSerie = explode('|', $chaveSerie, 2)[1]; ?>
    <h6 class="text-muted mt-3 mb-2"><?= ch_h($rotuloSerie) ?></h6>

    <?php foreach ($listaTurmas as $t): ?>
      <?php
      $idTurma = (int)$t['ID'];
      $discs = $disciplinasPorTurma[$idTurma] ?? [];
      $profs = $professoresPorTurma[$idTurma] ?? [];
      $totalAulas = 0;
      foreach ($discs as $d) { $totalAulas += (int)($d['AULAS'] ?? 0); }
      ?>
      <div class="card border-0 shadow-sm rounded-4 mb-3">
        <div class="card-body">
          <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
            <h5 class="mb-0">
              <?= ch_h($t['NOME']) ?>
              <small class="text-muted">— <?= ch_h($t['ESCOLA']) ?> · <?= ch_h($t['PERIODO']) ?> (<?= (int)$t['ANO_LETIVO'] ?>)</small>
            </h5>
            <span class="d-flex flex-wrap gap-1">
              <?php if ($fProfessor > 0): $st = $statusPorTurma[$idTurma]; ?>
                <?php if ($st['completa']): ?>
                  <span class="badge bg-success"><i class="fas fa-check"></i> Completa</span>
                <?php elseif ($st['total'] === 0): ?>
                  <span class="badge bg-secondary">Sem matérias definidas</span>
                <?php else: ?>
                  <span class="badge bg-warning text-dark">Faltam <?= $st['sem_professor'] ?> matéria(s)</span>
                <?php endif; ?>
              <?php endif; ?>
              <span class="badge bg-light text-dark border">
                <?= count($discs) ?> <?= count($discs) === 1 ? 'matéria' : 'matérias' ?> ·
                <?= $totalAulas ?> aulas/semana
              </span>
            </span>
          </div>

          <?php if ($ehGestao): ?>
            <form method="post" action="index.php" class="form-atribuicao">
              <?= ch_csrf_campo() ?>
              <input type="hidden" name="id_turma" value="<?= $idTurma ?>">
              <input type="hidden" name="ano" value="<?= $fAno ?>">
              <?php if ($fUnidade): ?><input type="hidden" name="unidade" value="<?= $fUnidade ?>"><?php endif; ?>
              <?php if ($fProfessor): ?><input type="hidden" name="professor" value="<?= $fProfessor ?>"><?php endif; ?>
              <div class="lista-disc">
                <?php foreach ($discs as $d): ?>
                  <div class="row g-2 align-items-end mb-2" data-linha-disc>
                    <div class="col-12 col-lg-4">
                      <label class="fw-bold small">Matéria</label>
                      <input type="text" name="disc_nome[]" class="form-control" maxlength="80"
                             value="<?= ch_h($d['NOME']) ?>">
                    </div>
                    <div class="col-6 col-lg-2">
                      <label class="fw-bold small">Aulas/semana</label>
                      <input type="number" name="disc_aulas[]" class="form-control" min="1" max="40"
                             value="<?= ch_h($d['AULAS']) ?>">
                    </div>
                    <div class="col-6 col-lg-5">
                      <label class="fw-bold small">Professor(a)</label>
                      <select name="disc_prof[]" class="form-select">
                        <option value="">Selecione...</option>
                        <?php foreach ($profs as $pr): ?>
                          <option value="<?= (int)$pr['ID'] ?>"
                                  <?= (int)($d['ID_PROFESSOR'] ?? 0) === (int)$pr['ID'] ? 'selected' : '' ?>>
                            <?= ch_h($pr['NOME']) ?>
                          </option>
                        <?php endforeach; ?>
                      </select>
                    </div>
                    <div class="col-12 col-lg-1">
                      <button type="button" class="btn btn-outline-danger btn-sm w-100" data-remover-disc title="Remover matéria">
                        <i class="fas fa-trash"></i>
                      </button>
                    </div>
                  </div>
                <?php endforeach; ?>
              </div>
              <template class="modelo-disc">
                <div class="row g-2 align-items-end mb-2" data-linha-disc>
                  <div class="col-12 col-lg-4">
                    <label class="fw-bold small">Matéria</label>
                    <input type="text" name="disc_nome[]" class="form-control" maxlength="80" placeholder="Ex.: Matemática">
                  </div>
                  <div class="col-6 col-lg-2">
                    <label class="fw-bold small">Aulas/semana</label>
                    <input type="number" name="disc_aulas[]" class="form-control" min="1" max="40" placeholder="Ex.: 4">
                  </div>
                  <div class="col-6 col-lg-5">
                    <label class="fw-bold small">Professor(a)</label>
                    <select name="disc_prof[]" class="form-select">
                      <option value="">Selecione...</option>
                      <?php foreach ($profs as $pr): ?>
                        <option value="<?= (int)$pr['ID'] ?>"><?= ch_h($pr['NOME']) ?></option>
                      <?php endforeach; ?>
                    </select>
                  </div>
                  <div class="col-12 col-lg-1">
                    <button type="button" class="btn btn-outline-danger btn-sm w-100" data-remover-disc title="Remover matéria">
                      <i class="fas fa-trash"></i>
                    </button>
                  </div>
                </div>
              </template>
              <div class="d-flex flex-wrap gap-2 mt-2">
                <button type="button" class="btn btn-outline-success btn-sm" data-add-disc>
                  <i class="fas fa-plus"></i> Adicionar matéria
                </button>
                <button type="submit" class="btn btn-primary btn-sm">
                  <i class="fas fa-save"></i> Salvar atribuição
                </button>
              </div>
            </form>
          <?php else: ?>
            <?php if (!$discs): ?>
              <p class="text-muted mb-0">Nenhuma matéria atribuída a esta turma.</p>
            <?php else: ?>
              <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                  <thead><tr><th>Matéria</th><th class="text-center">Aulas/semana</th><th>Professor(a)</th></tr></thead>
                  <tbody>
                    <?php foreach ($discs as $d): ?>
                      <tr>
                        <td><span class="fw-bold"><?= ch_h($d['NOME']) ?></span></td>
                        <td class="text-center"><?= ch_h($d['AULAS'] ?? '—') ?></td>
                        <td><?= ch_h($d['PROFESSOR'] ?? 'a definir') ?></td>
                      </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
            <?php endif; ?>
          <?php endif; ?>
        </div>
      </div>
    <?php endforeach; ?>
  <?php endforeach; ?>
<?php endforeach; ?>

<script>
(function () {
  /* Linhas de matéria dentro de cada turma: adicionar e remover. */
  document.querySelectorAll('.form-atribuicao').forEach(function (form) {
    var lista = form.querySelector('.lista-disc');
    var modelo = form.querySelector('.modelo-disc');
    if (!lista || !modelo) return;

    function novaLinha() {
      var frag = document.createElement('template');
      frag.innerHTML = modelo.innerHTML.trim();
      lista.appendChild(frag.content.firstElementChild);
      var nome = lista.lastElementChild.querySelector('input[name="disc_nome[]"]');
      if (nome) nome.focus();
    }

    form.querySelector('[data-add-disc]').addEventListener('click', novaLinha);

    lista.addEventListener('click', function (ev) {
      var btn = ev.target.closest('[data-remover-disc]');
      if (!btn) return;
      var linha = btn.closest('[data-linha-disc]');
      if (linha) linha.remove();
    });
  });
})();
</script>

<?php include(FOOTER_TEMPLATE); ?>
