<?php require_once('processa/index_processa.php'); ?>
<?php include(HEADER_TEMPLATE); ?>

<?php $podeGerirTurmas = ch_gestor(); ?>
<?php ch_cabecalho('Turmas', 'fa-children',
    $podeGerirTurmas ? '<a class="btn btn-success" href="add.php"><i class="fa fa-plus"></i> Nova Turma</a>' : ''); ?>

<form method="get" action="index.php" class="row g-2 align-items-end mb-3">
  <div class="col-md-3">
    <label class="fw-bold">Ano letivo</label>
    <select name="ano" class="form-select">
      <?php for ($a = (int)date('Y') + 1; $a >= 2020; $a--): ?>
        <option value="<?= $a ?>" <?= $a === $fAno ? 'selected' : '' ?>><?= $a ?></option>
      <?php endfor; ?>
    </select>
  </div>
  <div class="col-md-5">
    <label class="fw-bold">Unidade</label>
    <select name="unidade" class="form-select">
      <option value="0">Todas</option>
      <?php foreach ($unidades as $u): ?>
        <option value="<?= (int)$u['id'] ?>" <?= (int)$u['id'] === $fUnidade ? 'selected' : '' ?>><?= ch_h($u['unidade']) ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <div class="col-md-2">
    <label class="fw-bold">Situação</label>
    <select name="situacao" class="form-select">
      <option value="ativas"   <?= $fSituacao === 'ativas' ? 'selected' : '' ?>>Ativas</option>
      <option value="inativas" <?= $fSituacao === 'inativas' ? 'selected' : '' ?>>Inativas</option>
      <option value="todas"    <?= $fSituacao === 'todas' ? 'selected' : '' ?>>Todas</option>
    </select>
  </div>
  <div class="col-md-2">
    <button class="btn btn-primary w-100"><i class="fas fa-filter"></i> Filtrar</button>
  </div>
</form>

<div class="table-responsive-sm">
  <table id="item" data-vazio="Nenhuma turma encontrada para o filtro escolhido." class="table table-light table-striped table-sm align-middle">
    <thead class="text-sm-center">
      <tr>
        <th width="26%">Unidade</th>
        <th width="18%">Turma</th>
        <th width="14%">Período</th>
        <th width="22%">Equipe</th>
        <th width="8%">Alunos</th>
        <th width="7%">Situação</th>
        <th width="7%">Opções</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($turmas as $row): ?>
      <tr>
        <td><?= ch_h($row['UNIDADE']) ?></td>
        <td><?= ch_h($row['NOME']) ?>
          <br><small class="text-muted"><?= ch_h(ch_etapa_ensino_rotulo($row['ETAPA_ENSINO'])) ?></small>
          <?php if (!empty($row['NOTAS_ATIVAS']) && $row['ETAPA_ENSINO'] !== 'INFANTIL_CRECHE'): ?>
            <br><span class="badge bg-primary"><i class="fas fa-book-open"></i> Diário escolar</span>
          <?php endif; ?>
        </td>
        <td><?= ch_h($row['PERIODO']) ?> <small class="text-muted"><?= ch_h($row['HORA_INICIO']) ?>–<?= ch_h($row['HORA_FIM']) ?></small></td>
        <td>
          <?php $eq = $equipePorTurma[(int)$row['ID']] ?? []; ?>
          <?php if (!$eq): ?>
            <span class="text-muted">sem equipe</span>
          <?php else: ?>
            <div class="equipe-mini">
              <?php foreach ($eq as $m): ?>
                <span class="avatar-prof avatar-mini <?= $m['PAPEL'] === 'REGENTE' ? 'regente' : '' ?>"
                      title="<?= ch_h($m['NOME']) ?> — <?= ch_h(eq_papel_rotulo($m['PAPEL'])) ?> · <?= ch_h(eq_turno_rotulo($m['TURNO'])) ?>">
                  <?= ch_h(ch_iniciais($m['NOME'])) ?>
                </span>
              <?php endforeach; ?>
              <small class="text-muted ms-1">
                <?= count($eq) ?> prof<?= count($eq) > 1 ? 's' : '' ?>.
              </small>
            </div>
            <?php /* Quem responde em cada turno, em vez de só o primeiro nome. */ ?>
            <?php foreach ($eq as $m): if ($m['PAPEL'] !== 'REGENTE') continue; ?>
              <div><small class="text-muted"><?= ch_h($m['NOME']) ?> · <?= eq_turno_selo($m['TURNO']) ?></small></div>
            <?php endforeach; ?>
            <?php foreach ($eq as $m): if ($m['PAPEL'] !== 'SUBSTITUTO') continue; ?>
              <div><small class="text-danger"><i class="fas fa-user-clock"></i> <?= ch_h($m['NOME']) ?> substituto<?= $m['DATA_FIM'] ? ' até ' . ch_h(ch_data_br($m['DATA_FIM'])) : '' ?></small></div>
            <?php endforeach; ?>
          <?php endif; ?>
        </td>
        <td class="text-center"><?= (int)$row['ALUNOS'] ?> / <?= (int)$row['CAPACIDADE'] ?></td>
        <td class="text-center">
          <span class="badge bg-<?= (int)$row['ATIVO'] === 1 ? 'success' : 'secondary' ?>">
            <?= (int)$row['ATIVO'] === 1 ? 'Ativa' : 'Inativa' ?>
          </span>
        </td>
        <td class="text-sm-end">
          <div class="btn-group">
            <button type="button" class="btn btn-success btn-sm dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
              <i class="fa-solid fa-gear"></i>
            </button>
            <ul class="dropdown-menu dropdown-menu-end">
              <?php if ($podeGerirTurmas): ?>
                <li><a class="dropdown-item" href="edit.php?id=<?= (int)$row['ID'] ?>"><i class="far fa-edit"></i> Editar</a></li>
              <?php endif; ?>
              <li><a class="dropdown-item" href="<?= ch_url('chamada/index.php?turma_id=' . (int)$row['ID']) ?>"><i class="fas fa-clipboard-user"></i> Chamada</a></li>
              <?php if (!empty($row['NOTAS_ATIVAS']) && $row['ETAPA_ENSINO'] !== 'INFANTIL_CRECHE'): ?>
                <li><a class="dropdown-item" href="<?= ch_url('diario/index.php?turma_id=' . (int)$row['ID']) ?>"><i class="fas fa-book-open"></i> Diário de classe</a></li>
              <?php endif; ?>
              <li><a class="dropdown-item" target="_blank" rel="noopener" href="<?= ch_url('alunos/crachas_turma.php?turma_id=' . (int)$row['ID']) ?>"><i class="fas fa-id-card"></i> Imprimir crachás da turma</a></li>
              <?php if ($podeGerirTurmas): ?><li>
                <form method="post" action="processa/toggle_processa.php" class="px-3 py-1">
                  <?= ch_csrf_campo() ?>
                  <input type="hidden" name="id" value="<?= (int)$row['ID'] ?>">
                  <button type="submit" class="btn btn-sm btn-outline-secondary w-100"
                          onclick="return confirm('Alterar a situação desta turma?');">
                    <?= (int)$row['ATIVO'] === 1 ? 'Inativar' : 'Reativar' ?>
                  </button>
                </form>
              </li><?php endif; ?>
            </ul>
          </div>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

<script src="<?= BASEURL ?>js/tabela.js"></script>
<?php include(FOOTER_TEMPLATE); ?>
