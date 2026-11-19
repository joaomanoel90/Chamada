<?php require_once('processa/index_processa.php'); ?>
<?php include(HEADER_TEMPLATE); ?>

<?php ch_cabecalho('Atribuição de Professores', 'fa-chalkboard-user'); ?>

<div class="alert alert-info">
  <i class="fas fa-circle-info"></i>
  <strong>Como funciona por etapa:</strong>
  na <b>Creche</b> (Berçário I ao Maternal II — período Integral), cada regente <b>PDI — Creche</b> assume <b>um período</b> (Manhã ou Tarde, sem matéria) e o <b>Professor de Apoio — Creche</b> assume <b>o dia todo</b> (Integral).
  No <b>Pré I e Pré II</b>, o professor <b>PEB I</b> é atribuído diretamente como regente da turma (sem matéria).
  Em qualquer etapa, caso a turma tenha <b>matérias específicas</b> cadastradas em <b>Turmas</b> (ex.: <i>Educação Física</i>, <i>Artes</i>, <i>Inglês</i>), é possível atribuir as aulas aos professores que tenham essa matéria no cadastro (teto de <b>36 h/a</b> nas aulas).
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
  <div class="col-md-<?= $ehGestao ? '2' : '4' ?>">
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
  <div class="col-md-<?= $ehGestao ? '2' : '3' ?>">
    <label class="fw-bold">Categoria da turma</label>
    <select name="categoria" class="form-select">
      <option value="">Todas as categorias</option>
      <?php foreach ($categoriasTurma as $cod => $rotulo): ?>
        <option value="<?= ch_h($cod) ?>" <?= $cod === $fCategoria ? 'selected' : '' ?>>
          <?= ch_h($rotulo) ?>
        </option>
      <?php endforeach; ?>
    </select>
  </div>
  <div class="col-md-<?= $ehGestao ? '2' : '3' ?>">
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
    <span><b><?= ch_h($nomeProfessor) ?></b> — <?= $resumoProfessor['turmas'] ?> turma(s) compatível(is):</span>
    <span class="badge bg-success"><i class="fas fa-check"></i> <?= $resumoProfessor['completas'] ?> completa(s)</span>
    <span class="badge bg-warning text-dark"><?= $resumoProfessor['incompletas'] ?> pendente(s)</span>
    <?php if ($resumoProfessor['sem_materias'] > 0): ?>
      <span class="badge bg-secondary"><?= $resumoProfessor['sem_materias'] ?> sem matérias definidas</span>
    <?php endif; ?>
    <span class="badge bg-primary">Carga em matérias: <?= $resumoProfessor['carga'] ?>/<?= $resumoProfessor['carga_max'] ?> h/a</span>
  </div>
  <div class="alert alert-light border py-2">
    <i class="fas fa-id-card text-primary"></i>
    Categoria do concurso: <b><?= ch_h($resumoProfessor['categoria']) ?></b>
    · Matérias cadastradas: <b><?= $resumoProfessor['materias'] ? ch_h(implode(', ', $resumoProfessor['materias'])) : 'atribuição sem matéria (Creche / Apoio / Pré) ou ainda não cadastradas' ?></b>
  </div>
<?php endif; ?>

<?php if (!$turmas): ?>
  <div class="text-center text-muted py-5">
    <i class="fas fa-chalkboard-user fa-2x mb-3 d-block"></i>
    Nenhuma turma encontrada para os filtros escolhidos.
  </div>
<?php endif; ?>

<?php
$camposFiltroPost = '<input type="hidden" name="ano" value="' . (int)$fAno . '">'
    . ($fUnidade ? '<input type="hidden" name="unidade" value="' . (int)$fUnidade . '">' : '')
    . ($fProfessor ? '<input type="hidden" name="professor" value="' . (int)$fProfessor . '">' : '')
    . ($fCategoria ? '<input type="hidden" name="categoria" value="' . ch_h($fCategoria) . '">' : '');
?>

<?php foreach ($grupos as $etapa => $porSerie): ?>
  <h4 class="mt-4 mb-3"><i class="fas fa-layer-group"></i> <?= ch_h($etapas[$etapa] ?? 'Sem etapa definida') ?></h4>

  <?php foreach ($porSerie as $chaveSerie => $listaTurmas): ?>
    <?php $rotuloSerie = explode('|', $chaveSerie, 2)[1]; ?>
    <h6 class="text-muted mt-3 mb-2"><?= ch_h($rotuloSerie) ?></h6>

    <?php foreach ($listaTurmas as $t): ?>
      <?php
      $idTurma   = (int)$t['ID'];
      $discs     = $disciplinasPorTurma[$idTurma] ?? [];
      $st        = $statusPorTurma[$idTurma];
      $ehCreche  = $st['etapa'] === 'INFANTIL_CRECHE';
      $ehPre     = $st['etapa'] === 'INFANTIL_PRE_ESCOLA';
      $vincsDir  = $vinculosDiretosPorTurma[$idTurma] ?? [];
      ?>
      <div class="card border-0 shadow-sm rounded-4 mb-3">
        <div class="card-body">
          <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
            <h5 class="mb-0">
              <?= ch_h($t['NOME']) ?>
              <small class="text-muted">— <?= ch_h($t['ESCOLA']) ?> · <?= ch_h($t['PERIODO']) ?> (<?= (int)$t['ANO_LETIVO'] ?>)</small>
            </h5>
            <span class="d-flex flex-wrap gap-1">
              <?php if ($st['completa']): ?>
                <span class="badge bg-success"><i class="fas fa-check"></i> Completa</span>
              <?php elseif ($ehCreche || $ehPre): ?>
                <?php if (!$st['regencia_ok']): ?>
                  <span class="badge bg-warning text-dark">Regência pendente</span>
                <?php endif; ?>
                <?php if ($ehCreche && !$st['apoio_ok']): ?>
                  <span class="badge bg-warning text-dark">Professor de apoio pendente</span>
                <?php endif; ?>
                <?php if ($st['faltam'] > 0): ?>
                  <span class="badge bg-warning text-dark">Faltam <?= $st['faltam'] ?> aula(s) específicas</span>
                <?php endif; ?>
              <?php elseif ($st['total'] === 0): ?>
                <span class="badge bg-secondary">Sem matérias definidas</span>
              <?php else: ?>
                <span class="badge bg-warning text-dark">Faltam <?= $st['faltam'] ?> aula(s)</span>
              <?php endif; ?>

              <?php if ($st['total'] > 0 || (!$ehCreche && !$ehPre)): ?>
                <span class="badge bg-light text-dark border">
                  <?= $st['total'] ?> <?= $st['total'] === 1 ? 'matéria' : 'matérias' ?> ·
                  <?= $st['total_aulas'] ?> aulas/semana
                </span>
              <?php endif; ?>
            </span>
          </div>

          <?php if ($ehGestao): ?>

            <?php /* === BLOCO 1: CRECHE (Manhã + Tarde + Apoio dia todo, sem matéria) === */ ?>
            <?php if ($ehCreche): ?>
              <?php
                $regManha = null;
                $regTarde = null;
                $regInt   = null;
                foreach ($st['regentes'] as $rg) {
                    if ($rg['TURNO'] === 'MANHA') $regManha = $rg;
                    elseif ($rg['TURNO'] === 'TARDE') $regTarde = $rg;
                    elseif ($rg['TURNO'] === 'INTEGRAL') $regInt = $rg;
                }
              ?>
              <div class="border rounded-3 p-3 mb-2 bg-light">
                <div class="fw-bold mb-2"><i class="fas fa-baby text-success"></i> Professores da Creche (por período — sem matéria)</div>

                <?php if ($regInt): ?>
                  <div class="d-flex align-items-center gap-2 mb-2">
                    <span class="badge bg-primary">Integral (legado)</span>
                    <b><?= ch_h($regInt['PROFESSOR']) ?></b>
                    <form method="post" action="index.php" class="d-inline">
                      <?= ch_csrf_campo() ?><?= $camposFiltroPost ?>
                      <input type="hidden" name="acao" value="remover_direto">
                      <input type="hidden" name="id_turma_prof" value="<?= (int)$regInt['ID'] ?>">
                      <button type="submit" class="btn btn-outline-danger btn-sm" title="Remover"
                              onclick="return confirm('Remover <?= ch_h(addslashes($regInt['PROFESSOR'])) ?> da turma?');">
                        <i class="fas fa-times"></i>
                      </button>
                    </form>
                  </div>
                <?php endif; ?>

                <div class="row g-3">
                  <?php foreach (['MANHA' => 'Período da Manhã', 'TARDE' => 'Período da Tarde'] as $codTurno => $rotTurno): ?>
                    <?php $regAtual = $codTurno === 'MANHA' ? $regManha : $regTarde; ?>
                    <div class="col-md-6">
                      <div class="border rounded-3 p-2 bg-white h-100">
                        <div class="small fw-bold text-muted mb-1"><?= eq_turno_selo($codTurno) ?> <?= ch_h($rotTurno) ?> (PDI — Creche)</div>
                        <?php if ($regAtual): ?>
                          <div class="d-flex justify-content-between align-items-center">
                            <span><i class="fas fa-user-check text-success"></i> <b><?= ch_h($regAtual['PROFESSOR']) ?></b></span>
                            <form method="post" action="index.php" class="d-inline">
                              <?= ch_csrf_campo() ?><?= $camposFiltroPost ?>
                              <input type="hidden" name="acao" value="remover_direto">
                              <input type="hidden" name="id_turma_prof" value="<?= (int)$regAtual['ID'] ?>">
                              <button type="submit" class="btn btn-outline-danger btn-sm" title="Remover deste período"
                                      onclick="return confirm('Remover <?= ch_h(addslashes($regAtual['PROFESSOR'])) ?> deste período?');">
                                <i class="fas fa-times"></i>
                              </button>
                            </form>
                          </div>
                        <?php elseif (!$regInt): ?>
                          <form method="post" action="index.php" class="d-flex gap-2">
                            <?= ch_csrf_campo() ?><?= $camposFiltroPost ?>
                            <input type="hidden" name="acao" value="atribuir_direto">
                            <input type="hidden" name="id_turma" value="<?= $idTurma ?>">
                            <input type="hidden" name="papel" value="REGENTE">
                            <input type="hidden" name="turno" value="<?= $codTurno ?>">
                            <select name="id_professor" class="form-select form-select-sm" required>
                              <option value="">Selecione PDI — Creche...</option>
                              <?php foreach ($profsCrecheRegente as $idP => $nmP): ?>
                                <option value="<?= (int)$idP ?>" <?= $fProfessor === (int)$idP ? 'selected' : '' ?>><?= ch_h($nmP) ?></option>
                              <?php endforeach; ?>
                            </select>
                            <button type="submit" class="btn btn-success btn-sm text-nowrap"><i class="fas fa-plus"></i> Atribuir</button>
                          </form>
                        <?php else: ?>
                          <small class="text-muted">Coberto pelo regente integral acima.</small>
                        <?php endif; ?>
                      </div>
                    </div>
                  <?php endforeach; ?>

                  <div class="col-12">
                    <div class="border rounded-3 p-2 bg-white">
                      <div class="small fw-bold text-muted mb-1">
                        <?= eq_turno_selo('INTEGRAL') ?> Professor(a) de Apoio de Creche — Dia todo (Integral)
                      </div>
                      <?php if ($st['apoios']): ?>
                        <ul class="list-unstyled mb-2">
                          <?php foreach ($st['apoios'] as $ap): ?>
                            <li class="d-flex justify-content-between align-items-center py-1">
                              <span><i class="fas fa-hands-holding-child text-info"></i> <b><?= ch_h($ap['PROFESSOR']) ?></b> <small class="text-muted">(dia todo)</small></span>
                              <form method="post" action="index.php" class="d-inline">
                                <?= ch_csrf_campo() ?><?= $camposFiltroPost ?>
                                <input type="hidden" name="acao" value="remover_direto">
                                <input type="hidden" name="id_turma_prof" value="<?= (int)$ap['ID'] ?>">
                                <button type="submit" class="btn btn-outline-danger btn-sm" title="Remover professor de apoio"
                                        onclick="return confirm('Remover <?= ch_h(addslashes($ap['PROFESSOR'])) ?> do apoio desta turma?');">
                                  <i class="fas fa-times"></i>
                                </button>
                              </form>
                            </li>
                          <?php endforeach; ?>
                        </ul>
                      <?php endif; ?>
                      <form method="post" action="index.php" class="row g-2 align-items-center">
                        <?= ch_csrf_campo() ?><?= $camposFiltroPost ?>
                        <input type="hidden" name="acao" value="atribuir_direto">
                        <input type="hidden" name="id_turma" value="<?= $idTurma ?>">
                        <input type="hidden" name="papel" value="AUXILIAR">
                        <input type="hidden" name="turno" value="INTEGRAL">
                        <div class="col-md-8">
                          <select name="id_professor" class="form-select form-select-sm" required>
                            <option value="">Selecione Professor de Apoio — Creche...</option>
                            <?php foreach ($profsCrecheApoio as $idP => $nmP): ?>
                              <option value="<?= (int)$idP ?>" <?= $fProfessor === (int)$idP ? 'selected' : '' ?>><?= ch_h($nmP) ?></option>
                            <?php endforeach; ?>
                          </select>
                        </div>
                        <div class="col-md-4">
                          <button type="submit" class="btn btn-outline-success btn-sm w-100">
                            <i class="fas fa-plus"></i> Atribuir apoio (dia todo)
                          </button>
                        </div>
                      </form>
                    </div>
                  </div>
                </div>
              </div>
            <?php endif; ?>

            <?php /* === BLOCO 2: PRÉ I e PRÉ II (Regente PEB I sem matéria) === */ ?>
            <?php if ($ehPre): ?>
              <div class="border rounded-3 p-3 mb-2 bg-light">
                <div class="fw-bold mb-2"><i class="fas fa-chalkboard-user text-primary"></i> Professor(a) Regente — PEB I (sem matéria)</div>
                <?php if ($st['regentes']): ?>
                  <ul class="list-unstyled mb-2">
                    <?php foreach ($st['regentes'] as $rg): ?>
                      <li class="d-flex justify-content-between align-items-center py-1">
                        <span><i class="fas fa-user-check text-success"></i> <b><?= ch_h($rg['PROFESSOR']) ?></b> <?= eq_turno_selo($rg['TURNO']) ?></span>
                        <form method="post" action="index.php" class="d-inline">
                          <?= ch_csrf_campo() ?><?= $camposFiltroPost ?>
                          <input type="hidden" name="acao" value="remover_direto">
                          <input type="hidden" name="id_turma_prof" value="<?= (int)$rg['ID'] ?>">
                          <button type="submit" class="btn btn-outline-danger btn-sm" title="Remover regente"
                                  onclick="return confirm('Remover <?= ch_h(addslashes($rg['PROFESSOR'])) ?> desta turma?');">
                            <i class="fas fa-times"></i>
                          </button>
                        </form>
                      </li>
                    <?php endforeach; ?>
                  </ul>
                <?php endif; ?>

                <?php if (!$st['regencia_ok']): ?>
                  <form method="post" action="index.php" class="row g-2 align-items-end">
                    <?= ch_csrf_campo() ?><?= $camposFiltroPost ?>
                    <input type="hidden" name="acao" value="atribuir_direto">
                    <input type="hidden" name="id_turma" value="<?= $idTurma ?>">
                    <input type="hidden" name="papel" value="REGENTE">
                    <div class="col-md-<?= $st['turno_turma'] === 'INTEGRAL' ? '6' : '8' ?>">
                      <label class="small fw-bold">Professor(a) PEB I</label>
                      <select name="id_professor" class="form-select form-select-sm" required>
                        <option value="">Selecione professor PEB I...</option>
                        <?php foreach ($profsPreRegente as $idP => $nmP): ?>
                          <option value="<?= (int)$idP ?>" <?= $fProfessor === (int)$idP ? 'selected' : '' ?>><?= ch_h($nmP) ?></option>
                        <?php endforeach; ?>
                      </select>
                    </div>
                    <?php if ($st['turno_turma'] === 'INTEGRAL'): ?>
                      <div class="col-md-3">
                        <label class="small fw-bold">Período</label>
                        <select name="turno" class="form-select form-select-sm">
                          <?php if (!$st['tem_manha']): ?><option value="MANHA">Manhã</option><?php endif; ?>
                          <?php if (!$st['tem_tarde']): ?><option value="TARDE">Tarde</option><?php endif; ?>
                          <?php if (!$st['tem_manha'] && !$st['tem_tarde']): ?><option value="INTEGRAL">Integral</option><?php endif; ?>
                        </select>
                      </div>
                    <?php else: ?>
                      <input type="hidden" name="turno" value="<?= ch_h($st['turno_turma']) ?>">
                    <?php endif; ?>
                    <div class="col-md-<?= $st['turno_turma'] === 'INTEGRAL' ? '3' : '4' ?>">
                      <button type="submit" class="btn btn-success btn-sm w-100"><i class="fas fa-plus"></i> Atribuir regente</button>
                    </div>
                  </form>
                <?php endif; ?>
              </div>
            <?php endif; ?>

            <?php /* === BLOCO 3: MATÉRIAS (regulares no Fundamental ou específicas na Creche/Pré, ex.: Educação Física) === */ ?>
            <?php if (!$discs && !$ehCreche && !$ehPre): ?>
              <p class="text-muted mb-0">Nenhuma matéria definida para esta turma. Defina as matérias e o total de aulas na área de <strong>Turmas</strong>.</p>
            <?php elseif (!$discs && ($ehCreche || $ehPre)): ?>
              <small class="text-muted d-block">
                <i class="fas fa-person-running"></i> Para vincular professor de matéria específica (ex.: <em>Educação Física</em>), basta adicionar a matéria na turma em <a href="<?= ch_url('turmas/edit.php?id=' . $idTurma) ?>">Editar Turma</a>.
              </small>
            <?php endif; ?>

            <?php if ($discs && ($ehCreche || $ehPre)): ?>
              <div class="small fw-bold text-muted mt-2 mb-1"><i class="fas fa-person-running"></i> Matérias específicas da turma</div>
            <?php endif; ?>

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
                          <?= ch_csrf_campo() ?><?= $camposFiltroPost ?>
                          <input type="hidden" name="acao" value="remover_atribuicao">
                          <input type="hidden" name="id_atribuicao" value="<?= (int)$a['ID'] ?>">
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
                    <?= ch_csrf_campo() ?><?= $camposFiltroPost ?>
                    <input type="hidden" name="acao" value="atribuir">
                    <input type="hidden" name="id_disciplina" value="<?= $idD ?>">
                    <div class="col-12 col-lg-5">
                      <label class="fw-bold small">Professor(a) — só quem tem <?= ch_h($d['NOME']) ?> no cadastro</label>
                      <select name="id_professor" class="form-select" required>
                        <option value="">Selecione...</option>
                        <?php foreach ($elegiveisPorTurma[$idTurma] as $idP => $e): ?>
                          <?php if (!eq_professor_pode_materia($e['MATERIAS'], $d['NOME'])) continue; ?>
                          <option value="<?= (int)$idP ?>" <?= $fProfessor === (int)$idP ? 'selected' : '' ?>><?= ch_h($e['NOME']) ?></option>
                        <?php endforeach; ?>
                      </select>
                      <?php
                      $temQuemPode = false;
                      foreach ($elegiveisPorTurma[$idTurma] as $e) {
                          if (eq_professor_pode_materia($e['MATERIAS'], $d['NOME'])) { $temQuemPode = true; break; }
                      }
                      if (!$temQuemPode): ?>
                        <small class="text-danger d-block mt-1">Nenhum professor ativo tem <?= ch_h($d['NOME']) ?> no cadastro.</small>
                      <?php endif; ?>
                    </div>
                    <div class="col-6 col-lg-2">
                      <label class="fw-bold small">Aulas</label>
                      <input type="number" name="aulas" class="form-control" min="1" max="40" required
                             placeholder="<?= $restam !== null ? $restam : 'Ex.: 2' ?>">
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
            <?php /* Professor: somente leitura, mostrando vínculo direto e/ou aulas de matéria. */ ?>
            <?php if ($vincsDir): ?>
              <div class="mb-2">
                <?php foreach ($vincsDir as $vd): ?>
                  <span class="badge bg-light text-dark border me-1">
                    <i class="fas fa-chalkboard-user text-success"></i>
                    <?= ch_h($vd['PROFESSOR']) ?> — <?= $vd['PAPEL'] === 'REGENTE' ? 'Regente' : 'Apoio' ?> (<?= ch_h(eq_turno_rotulo($vd['TURNO'])) ?>)
                  </span>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>
            <?php if (!$discs && !$vincsDir): ?>
              <p class="text-muted mb-0">Nenhuma atribuição registrada nesta turma.</p>
            <?php elseif ($discs): ?>
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

<?php include(FOOTER_TEMPLATE); ?>
