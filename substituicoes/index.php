<?php require_once('processa/index_processa.php'); ?>
<?php include(HEADER_TEMPLATE); ?>

<?php ch_cabecalho('Substituições de Professores', 'fa-people-arrows',
    '<a class="btn btn-success" href="add.php' . ($fTurma > 0 ? '?turma=' . (int)$fTurma : '') . '"><i class="fa fa-plus"></i> Nova Substituição</a>'); ?>

<div class="alert alert-light border py-2 mb-3 small">
  <i class="fas fa-circle-info text-primary"></i>
  O professor substituto enxerga a turma e lança chamada apenas entre <strong>De</strong> e <strong>Até</strong>.
  Na falta de professor em período <strong>Integral</strong> (ex.: Creche), é possível cadastrar <strong>2 substitutos</strong> nas mesmas datas — um para a <strong>Manhã</strong> e outro para a <strong>Tarde</strong>.
</div>

<form method="get" action="index.php" class="row g-2 align-items-end mb-3">
  <div class="col-md-2">
    <label class="fw-bold">Ano letivo</label>
    <select name="ano" class="form-select">
      <?php for ($a = (int)date('Y') + 1; $a >= 2020; $a--): ?>
        <option value="<?= $a ?>" <?= $a === $fAno ? 'selected' : '' ?>><?= $a ?></option>
      <?php endfor; ?>
    </select>
  </div>
  <div class="col-md-3">
    <label class="fw-bold">Unidade</label>
    <select name="unidade" class="form-select">
      <option value="0">Todas</option>
      <?php foreach ($unidades as $u): ?>
        <option value="<?= (int)$u['id'] ?>" <?= (int)$u['id'] === $fUnidade ? 'selected' : '' ?>><?= ch_h($u['unidade']) ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <div class="col-md-3">
    <label class="fw-bold">Turma</label>
    <select name="turma" class="form-select">
      <option value="0">Todas</option>
      <?php foreach ($turmasOpcoes as $t): ?>
        <option value="<?= (int)$t['ID'] ?>" <?= (int)$t['ID'] === $fTurma ? 'selected' : '' ?>>
          <?= ch_h($t['UNIDADE']) ?> — <?= ch_h(ch_series_ensino()[$t['SERIE'] ?? '']['rotulo'] ?? 'Não classificada') ?> · Sala <?= ch_h($t['NOME']) ?>
        </option>
      <?php endforeach; ?>
    </select>
  </div>
  <div class="col-md-2">
    <label class="fw-bold">Situação</label>
    <select name="situacao" class="form-select">
      <option value="ativas"     <?= $fSituacao === 'ativas' ? 'selected' : '' ?>>Em vigor / agendadas</option>
      <option value="hoje"       <?= $fSituacao === 'hoje' ? 'selected' : '' ?>>Válidas hoje</option>
      <option value="encerradas" <?= $fSituacao === 'encerradas' ? 'selected' : '' ?>>Encerradas</option>
      <option value="todas"      <?= $fSituacao === 'todas' ? 'selected' : '' ?>>Todas</option>
    </select>
  </div>
  <div class="col-md-2">
    <button class="btn btn-primary w-100"><i class="fas fa-filter"></i> Filtrar</button>
  </div>
</form>

<?php if (!$substituicoes): ?>
  <div class="alert alert-info">
    Nenhuma substituição encontrada para os filtros escolhidos.
  </div>
<?php else: ?>
  <div class="table-responsive">
    <table class="table table-light table-striped table-sm align-middle">
      <thead>
        <tr>
          <th>Unidade / Turma</th>
          <th>Substituto(a)</th>
          <th>Substituindo</th>
          <th>Vigência</th>
          <th>Observações</th>
          <th>Situação</th>
          <th class="text-end">Opções</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($substituicoes as $s): ?>
          <?php
            $emVigorHoje = (int)$s['ATIVO'] === 1 && $s['DATA_INICIO'] <= $hoje && $s['DATA_FIM'] >= $hoje;
            $agendada    = (int)$s['ATIVO'] === 1 && $s['DATA_INICIO'] > $hoje;
          ?>
          <tr>
            <td>
              <strong><?= ch_h($s['UNIDADE']) ?></strong><br>
              <small class="text-muted">
                <?= ch_h(ch_series_ensino()[$s['SERIE'] ?? '']['rotulo'] ?? 'Não classificada') ?>
                · Sala <?= ch_h($s['TURMA']) ?> · <?= ch_h($s['PERIODO']) ?>
              </small>
            </td>
            <td>
              <strong><?= ch_h($s['SUBSTITUTO']) ?></strong><br>
              <?= eq_turno_selo($s['TURNO']) ?>
            </td>
            <td>
              <?= ch_h($s['COBERTO'] ?: '—') ?>
              <?php if (!empty($s['MOTIVO'])): ?>
                <br><small class="text-muted"><i class="fas fa-tag"></i> <?= ch_h($s['MOTIVO']) ?></small>
              <?php endif; ?>
            </td>
            <td>
              <?= ch_data_br($s['DATA_INICIO']) ?> a <?= ch_data_br($s['DATA_FIM']) ?>
            </td>
            <td>
              <?php if ((int)$s['VER_OBSERVACOES'] === 1): ?>
                <span class="badge bg-info text-dark"><i class="fas fa-eye"></i> Lê as da turma</span>
              <?php else: ?>
                <span class="badge bg-light text-dark border"><i class="fas fa-eye-slash"></i> Só as próprias</span>
              <?php endif; ?>
            </td>
            <td>
              <?php if ($emVigorHoje): ?>
                <span class="badge bg-success">Em vigor hoje</span>
              <?php elseif ($agendada): ?>
                <span class="badge bg-primary">Agendada</span>
              <?php elseif ((int)$s['ATIVO'] === 1): ?>
                <span class="badge bg-secondary">Prazo concluído</span>
              <?php else: ?>
                <span class="badge bg-secondary">Encerrada</span>
              <?php endif; ?>
            </td>
            <td class="text-end text-nowrap">
              <a class="btn btn-sm btn-outline-success" href="edit.php?id=<?= (int)$s['ID'] ?>" title="Editar substituição">
                <i class="far fa-edit"></i>
              </a>
              <?php if ((int)$s['ATIVO'] === 1): ?>
                <form method="post" action="encerrar.php" class="d-inline"
                      data-confirm="Encerrar esta substituição agora?">
                  <?= ch_csrf_campo() ?>
                  <input type="hidden" name="id" value="<?= (int)$s['ID'] ?>">
                  <button type="submit" class="btn btn-sm btn-outline-danger" title="Encerrar substituição">
                    <i class="fas fa-ban"></i>
                  </button>
                </form>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
<?php endif; ?>

<?php include(FOOTER_TEMPLATE); ?>
