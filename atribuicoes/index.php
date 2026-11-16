<?php require_once('processa/index_processa.php'); ?>
<?php include(HEADER_TEMPLATE); ?>

<?php ch_cabecalho('Atribuição de Professores', 'fa-chalkboard-user'); ?>

<div class="alert alert-info">
  <i class="fas fa-circle-info"></i>
  Primeiro escolha o <b>professor</b>: aparecem só as turmas em que ele pode ser
  atribuído — as turmas da equipe dele <b>e</b> as turmas compatíveis com a
  <b>categoria do concurso</b> dele (PDI / PEB I / PEB II, definida no cadastro
  do professor), agrupadas por <b>etapa</b> (da Creche ao Fundamental) e por série.
  No começo do ano ele ainda não tem equipe, e a categoria é o que permite
  atribuir. A turma define o <b>total de aulas</b> de cada matéria; as aulas são
  <b>divididas entre os professores</b> e o painel mostra <b>quantas aulas ainda
  faltam</b>. Cada professor tem teto de <b>36 h/a</b> por semana. Só é possível
  atribuir a ele uma matéria que esteja <b>no cadastro dele</b>. Quem recebe
  aulas entra na equipe da turma automaticamente. O professor vê aqui as
  turmas e matérias dele.
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

<?php if ($erros): ?>
  <div class="alert alert-danger"><ul class="mb-0"><?php foreach ($erros as $e): ?><li><?= ch_h($e) ?></li><?php endforeach; ?></ul></div>
<?php endif; ?>

<?php if ($resumoProfessor): ?>
  <div class="alert alert-light border d-flex flex-wrap align-items-center gap-2">
    <i class="fas fa-user-check text-success"></i>
    <span><b><?= ch_h($nomeProfessor) ?></b> — <?= $resumoProfessor['turmas'] ?> turma(s) na equipe:</span>
    <span class="badge bg-success"><i class="fas fa-check"></i> <?= $resumoProfessor['completas'] ?> completa(s)</span>
    <span class="badge bg-warning text-dark"><?= $resumoProfessor['incompletas'] ?> incompleta(s)</span>
    <span class="badge bg-secondary"><?= $resumoProfessor['sem_materias'] ?> sem matérias definidas</span>
    <span class="badge bg-primary">Carga: <?= $resumoProfessor['carga'] ?>/<?= $resumoProfessor['carga_max'] ?> h/a</span>
  </div>
  <div class="alert alert-light border py-2">
    <i class="fas fa-id-card text-primary"></i>
    Categoria do concurso: <b><?= ch_h($resumoProfessor['categoria']) ?></b>
    · Pode lecionar: <b><?= $resumoProfessor['materias'] ? ch_h(implode(', ', $resumoProfessor['materias'])) : 'nenhuma matéria cadastrada' ?></b>
  </div>
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
      $st = $statusPorTurma[$idTurma];
      ?>
      <div class="card border-0 shadow-sm rounded-4 mb-3">
        <div class="card-body">
          <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
            <h5 class="mb-0">
              <?= ch_h($t['NOME']) ?>
              <small class="text-muted">— <?= ch_h($t['ESCOLA']) ?> · <?= ch_h($t['PERIODO']) ?> (<?= (int)$t['ANO_LETIVO'] ?>)</small>
            </h5>
            <span class="d-flex flex-wrap gap-1">
              <?php if ($fProfessor > 0): ?>
                <?php if ($st['completa']): ?>
                  <span class="badge bg-success"><i class="fas fa-check"></i> Completa</span>
                <?php elseif ($st['total'] === 0): ?>
                  <span class="badge bg-secondary">Sem matérias definidas</span>
                <?php else: ?>
                  <span class="badge bg-warning text-dark">Faltam <?= $st['faltam'] ?> aula(s)</span>
                <?php endif; ?>
              <?php endif; ?>
              <span class="badge bg-light text-dark border">
                <?= $st['total'] ?> <?= $st['total'] === 1 ? 'matéria' : 'matérias' ?> ·
                <?= $st['total_aulas'] ?> aulas/semana
              </span>
            </span>
          </div>

          <?php if ($ehGestao): ?>
            <?php /* Matérias da turma: nome (da lista) + total de aulas. */ ?>
            <form method="post" action="index.php" class="form-materias mb-3">
              <?= ch_csrf_campo() ?>
              <input type="hidden" name="acao" value="salvar_materias">
              <input type="hidden" name="id_turma" value="<?= $idTurma ?>">
              <input type="hidden" name="ano" value="<?= $fAno ?>">
              <?php if ($fUnidade): ?><input type="hidden" name="unidade" value="<?= $fUnidade ?>"><?php endif; ?>
              <?php if ($fProfessor): ?><input type="hidden" name="professor" value="<?= $fProfessor ?>"><?php endif; ?>
              <div class="lista-disc">
                <?php foreach ($discs as $d): ?>
                  <div class="row g-2 align-items-end mb-2" data-linha-disc>
                    <div class="col-12 col-lg-5">
                      <label class="fw-bold small">Matéria</label>
                      <select name="disc_nome[]" class="form-select">
                        <option value="">Selecione...</option>
                        <?php foreach ($materiasOpcoes as $mat): ?>
                          <option value="<?= ch_h($mat) ?>"
                                  <?= mb_strtolower((string)$d['NOME'], 'UTF-8') === mb_strtolower($mat, 'UTF-8') ? 'selected' : '' ?>>
                            <?= ch_h($mat) ?>
                          </option>
                        <?php endforeach; ?>
                      </select>
                    </div>
                    <div class="col-6 col-lg-3">
                      <label class="fw-bold small">Aulas/semana (total)</label>
                      <input type="number" name="disc_aulas[]" class="form-control" min="1" max="40"
                             value="<?= ch_h($d['AULAS']) ?>">
                    </div>
                    <div class="col-6 col-lg-4">
                      <button type="button" class="btn btn-outline-danger btn-sm w-100" data-remover-disc title="Remover matéria">
                        <i class="fas fa-trash"></i> Remover matéria
                      </button>
                    </div>
                  </div>
                <?php endforeach; ?>
              </div>
              <template class="modelo-disc">
                <div class="row g-2 align-items-end mb-2" data-linha-disc>
                  <div class="col-12 col-lg-5">
                    <label class="fw-bold small">Matéria</label>
                    <select name="disc_nome[]" class="form-select">
                      <option value="">Selecione...</option>
                      <?php foreach ($materiasOpcoes as $mat): ?>
                        <option value="<?= ch_h($mat) ?>"><?= ch_h($mat) ?></option>
                      <?php endforeach; ?>
                    </select>
                  </div>
                  <div class="col-6 col-lg-3">
                    <label class="fw-bold small">Aulas/semana (total)</label>
                    <input type="number" name="disc_aulas[]" class="form-control" min="1" max="40" placeholder="Ex.: 10">
                  </div>
                  <div class="col-6 col-lg-4">
                    <button type="button" class="btn btn-outline-danger btn-sm w-100" data-remover-disc title="Remover matéria">
                      <i class="fas fa-trash"></i> Remover matéria
                    </button>
                  </div>
                </div>
              </template>
              <div class="d-flex flex-wrap gap-2 mt-2">
                <button type="button" class="btn btn-outline-success btn-sm" data-add-disc>
                  <i class="fas fa-plus"></i> Adicionar matéria
                </button>
                <button type="submit" class="btn btn-primary btn-sm">
                  <i class="fas fa-save"></i> Salvar matérias
                </button>
              </div>
            </form>

            <?php /* Aulas atribuídas por matéria. */ ?>
            <?php foreach ($discs as $d): ?>
              <?php
              $idD = (int)$d['ID'];
              $atribs = $atribuicoesPorDisc[$idD] ?? [];
              $restam = $d['RESTAM'];
              ?>
              <div class="border rounded-3 p-2 mb-2 bg-light">
                <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
                  <span class="fw-bold"><?= ch_h($d['NOME']) ?>
                    <small class="text-muted">— <?= ch_h($d['AULAS'] ?? '—') ?> aulas/semana</small>
                  </span>
                  <?php if ($restam === null): ?>
                    <span class="badge bg-secondary">total não definido</span>
                  <?php elseif ($restam === 0): ?>
                    <span class="badge bg-success"><i class="fas fa-check"></i> Completa</span>
                  <?php else: ?>
                    <span class="badge bg-warning text-dark">Faltam <?= $restam ?> aula(s)</span>
                  <?php endif; ?>
                </div>

                <?php if ($atribs): ?>
                  <ul class="list-unstyled mb-2 mt-1">
                    <?php foreach ($atribs as $a): ?>
                      <li class="d-flex flex-wrap align-items-center gap-2 py-1">
                        <span><i class="fas fa-user-check text-success"></i> <b><?= ch_h($a['PROFESSOR']) ?></b> — <?= (int)$a['AULAS'] ?> aula(s)/semana</span>
                        <form method="post" action="index.php" class="d-inline">
                          <?= ch_csrf_campo() ?>
                          <input type="hidden" name="acao" value="remover_atribuicao">
                          <input type="hidden" name="id_atribuicao" value="<?= (int)$a['ID'] ?>">
                          <input type="hidden" name="ano" value="<?= $fAno ?>">
                          <?php if ($fUnidade): ?><input type="hidden" name="unidade" value="<?= $fUnidade ?>"><?php endif; ?>
                          <?php if ($fProfessor): ?><input type="hidden" name="professor" value="<?= $fProfessor ?>"><?php endif; ?>
                          <button type="submit" class="btn btn-outline-danger btn-sm" title="Remover atribuição"
                                  onclick="return confirm('Remover a atribuição de <?= ch_h(addslashes($a['PROFESSOR'])) ?>?')">
                            <i class="fas fa-times"></i>
                          </button>
                        </form>
                      </li>
                    <?php endforeach; ?>
                  </ul>
                <?php endif; ?>

                <?php if ($restam === null || $restam > 0): ?>
                  <form method="post" action="index.php" class="row g-2 align-items-end">
                    <?= ch_csrf_campo() ?>
                    <input type="hidden" name="acao" value="atribuir">
                    <input type="hidden" name="id_disciplina" value="<?= $idD ?>">
                    <input type="hidden" name="ano" value="<?= $fAno ?>">
                    <?php if ($fUnidade): ?><input type="hidden" name="unidade" value="<?= $fUnidade ?>"><?php endif; ?>
                    <?php if ($fProfessor): ?><input type="hidden" name="professor" value="<?= $fProfessor ?>"><?php endif; ?>
                    <div class="col-12 col-lg-5">
                      <label class="fw-bold small">Professor(a) — só quem tem <?= ch_h($d['NOME']) ?> no cadastro</label>
                      <select name="id_professor" class="form-select" required>
                        <option value="">Selecione...</option>
                        <?php foreach ($elegiveisPorTurma[$idTurma] as $idP => $e): ?>
                          <?php if (!eq_professor_pode_materia($e['MATERIAS'], $d['NOME'])) continue; ?>
                          <option value="<?= (int)$idP ?>"><?= ch_h($e['NOME']) ?></option>
                        <?php endforeach; ?>
                      </select>
                      <?php
                      $temQuemPode = false;
                      foreach ($elegiveisPorTurma[$idTurma] as $e) {
                          if (eq_professor_pode_materia($e['MATERIAS'], $d['NOME'])) { $temQuemPode = true; break; }
                      }
                      if (!$temQuemPode): ?>
                        <small class="text-danger d-block mt-1">Nenhum professor elegível tem <?= ch_h($d['NOME']) ?> no cadastro.</small>
                      <?php endif; ?>
                    </div>
                    <div class="col-6 col-lg-2">
                      <label class="fw-bold small">Aulas</label>
                      <input type="number" name="aulas" class="form-control" min="1" max="40" required
                             placeholder="<?= $restam !== null ? $restam : 'Ex.: 4' ?>">
                    </div>
                    <div class="col-6 col-lg-2">
                      <button type="submit" class="btn btn-success btn-sm w-100">
                        <i class="fas fa-plus"></i> Atribuir
                      </button>
                    </div>
                  </form>
                <?php endif; ?>
              </div>
            <?php endforeach; ?>

          <?php else: ?>
            <?php /* Professor: somente leitura, com as aulas dele. */ ?>
            <?php if (!$discs): ?>
              <p class="text-muted mb-0">Nenhuma matéria atribuída a esta turma.</p>
            <?php else: ?>
              <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                  <thead><tr><th>Matéria</th><th class="text-center">Aulas/semana</th><th>Suas aulas</th><th>Professor(a)</th></tr></thead>
                  <tbody>
                    <?php foreach ($discs as $d): ?>
                      <?php
                      $idD = (int)$d['ID'];
                      $minhas = 0;
                      $nomes = [];
                      foreach ($atribuicoesPorDisc[$idD] ?? [] as $a) {
                          $nomes[] = $a['PROFESSOR'] . ' (' . (int)$a['AULAS'] . ')';
                          if ((int)$a['ID_PROFESSOR'] === ch_uid()) { $minhas += (int)$a['AULAS']; }
                      }
                      ?>
                      <tr>
                        <td><span class="fw-bold"><?= ch_h($d['NOME']) ?></span></td>
                        <td class="text-center"><?= ch_h($d['AULAS'] ?? '—') ?></td>
                        <td><?= $minhas ? '<b>' . $minhas . '</b>' : '—' ?></td>
                        <td><?= ch_h(implode(', ', $nomes) ?: 'a definir') ?></td>
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
  document.querySelectorAll('.form-materias').forEach(function (form) {
    var lista = form.querySelector('.lista-disc');
    var modelo = form.querySelector('.modelo-disc');
    if (!lista || !modelo) return;

    function novaLinha() {
      var frag = document.createElement('template');
      frag.innerHTML = modelo.innerHTML.trim();
      lista.appendChild(frag.content.firstElementChild);
      var nome = lista.lastElementChild.querySelector('select[name="disc_nome[]"]');
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
