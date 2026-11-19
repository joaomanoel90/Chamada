<?php require_once('processa/index_processa.php'); ?>
<?php include(HEADER_TEMPLATE); ?>

<?php ch_cabecalho('Faltas de Professores', 'fa-user-clock',
    '<button class="btn btn-primary" onclick="window.print()"><i class="fas fa-print"></i> Imprimir</button>'); ?>

<style>
  /* Versão impressa: só o relatório, sem menu, filtros e botões. */
  @media print {
    @page { size: A4 landscape; margin: 12mm 12mm 14mm; }
    .sidebar, .toggle-btn, #sidebarToggle, .footer-custom, .toast-container,
    .no-print, header .btn, form.filtros { display: none !important; }
    html, body { background: #fff !important; }
    body { font-size: 10pt !important; color: #000 !important; }
    .content { margin: 0 !important; padding: 0 !important; width: 100% !important; max-width: none !important; }
    .container, .container-fluid { width: 100% !important; max-width: none !important; padding: 0 !important; margin: 0 !important; }
    header, header + hr { display: none !important; }
    .card { box-shadow: none !important; border: 1px solid #bbb !important; border-radius: 0 !important; height: auto !important; }
    .table { font-size: 9pt !important; }
    .table th, .table td { border: 1px solid #999 !important; padding: 3px 5px !important; }
    tr { page-break-inside: avoid; }
  }
</style>

<div class="alert alert-info">
  <i class="fas fa-circle-info"></i>
  Este relatório é montado a partir das <b>substituições</b> registradas na equipe das turmas:
  cada substituição representa um período em que o professor faltou e foi coberto.
  Entram no relatório as substituições que <b>intersectam</b> o período escolhido.
</div>

<form method="get" action="index.php" class="row g-2 align-items-end mb-3 filtros">
  <div class="col-md-3">
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
  <div class="col-md-2">
    <label class="fw-bold">De</label>
    <input type="date" name="de" class="form-control" value="<?= ch_h($fDe) ?>">
  </div>
  <div class="col-md-2">
    <label class="fw-bold">Até</label>
    <input type="date" name="ate" class="form-control" value="<?= ch_h($fAte) ?>">
  </div>
  <div class="col-md-3">
    <label class="fw-bold">Professor (ausente)</label>
    <select name="professor" class="form-select">
      <option value="0">Todos</option>
      <?php foreach ($professores as $p): ?>
        <option value="<?= (int)$p['ID'] ?>" <?= (int)$p['ID'] === $fProfessor ? 'selected' : '' ?>>
          <?= ch_h($p['NOME']) ?>
        </option>
      <?php endforeach; ?>
    </select>
  </div>
  <div class="col-md-2"><button class="btn btn-primary w-100"><i class="fas fa-filter"></i> Filtrar</button></div>
</form>

<div class="row g-3 mb-3">
  <div class="col-md-3">
    <div class="card border-0 shadow-sm rounded-4 h-100"><div class="card-body text-center">
      <div class="fs-3 fw-bold"><?= (int)$resumo['total'] ?></div>
      <div class="text-muted">Substituições no período</div>
    </div></div>
  </div>
  <div class="col-md-3">
    <div class="card border-0 shadow-sm rounded-4 h-100"><div class="card-body text-center">
      <div class="fs-3 fw-bold"><?= (int)$resumo['dias'] ?></div>
      <div class="text-muted">Dias de substituição</div>
    </div></div>
  </div>
  <div class="col-md-3">
    <div class="card border-0 shadow-sm rounded-4 h-100"><div class="card-body text-center">
      <div class="fs-3 fw-bold"><?= count($resumo['professores']) ?></div>
      <div class="text-muted">Professores que faltaram</div>
    </div></div>
  </div>
  <div class="col-md-3">
    <div class="card border-0 shadow-sm rounded-4 h-100"><div class="card-body text-center">
      <div class="fs-3 fw-bold"><?= count($resumo['substitutos']) ?></div>
      <div class="text-muted">Substitutos que atuaram</div>
    </div></div>
  </div>
</div>

<div class="card border-0 shadow-sm rounded-4 mb-3">
  <div class="card-body">
    <h5 class="mb-3"><i class="fas fa-list"></i> Faltas por substituição</h5>
    <div class="table-responsive">
      <table id="item" data-vazio="Nenhuma substituição encontrada no período e filtros escolhidos." class="table table-light table-striped table-sm align-middle">
        <thead>
          <tr>
            <th>Professor (ausente)</th>
            <th>Substituto</th>
            <th>Turma</th>
            <th>Escola</th>
            <th>Início</th>
            <th>Fim</th>
            <th class="text-center">Dias</th>
            <th>Motivo</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($substituicoes as $s): ?>
            <tr>
              <td><span class="fw-bold"><?= ch_h($s['PROFESSOR']) ?></span></td>
              <td><?= ch_h($s['SUBSTITUTO']) ?> <?= eq_turno_selo($s['TURNO_SUBST'] ?? 'INTEGRAL') ?></td>
              <td>
                <?= ch_h(ch_series_ensino()[$s['SERIE'] ?? '']['rotulo'] ?? 'Série não classificada') ?> ·
                <?= ch_h($s['TURMA']) ?> — <?= ch_h($s['PERIODO']) ?>
                <small class="text-muted">(<?= (int)$s['ANO_LETIVO'] ?>)</small>
              </td>
              <td><?= ch_h($s['ESCOLA']) ?></td>
              <td><?= ch_data_br($s['DATA_INICIO']) ?></td>
              <td><?= ch_data_br($s['DATA_FIM']) ?></td>
              <td class="text-center fw-bold"><?= (int)$s['DIAS'] ?></td>
              <td><?= ch_h($s['MOTIVO']) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<?php if ($porProfessor): ?>
<div class="card border-0 shadow-sm rounded-4">
  <div class="card-body">
    <h5 class="mb-3"><i class="fas fa-ranking-star"></i> Resumo por professor</h5>
    <div class="table-responsive">
      <table class="table table-light table-striped table-sm align-middle mb-0">
        <thead>
          <tr>
            <th>Professor</th>
            <th class="text-center">Substituições</th>
            <th class="text-center">Dias de falta</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($porProfessor as $p): ?>
            <tr>
              <td><span class="fw-bold"><?= ch_h($p['nome']) ?></span></td>
              <td class="text-center"><?= (int)$p['vezes'] ?></td>
              <td class="text-center fw-bold"><?= (int)$p['dias'] ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
<?php endif; ?>

<?php include(FOOTER_TEMPLATE); ?>
