<?php require_once('processa/index_processa.php'); ?>
<?php include(HEADER_TEMPLATE); ?>

<?php ch_cabecalho('Turmas', 'fa-children',
    '<a class="btn btn-success" href="add.php"><i class="fa fa-plus"></i> Nova Turma</a>'); ?>

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
  <table id="item" class="table table-light table-striped table-sm align-middle">
    <thead class="text-sm-center">
      <tr>
        <th width="26%">Unidade</th>
        <th width="18%">Turma</th>
        <th width="14%">Período</th>
        <th width="20%">Professor(a)</th>
        <th width="8%">Alunos</th>
        <th width="7%">Situação</th>
        <th width="7%">Opções</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($turmas as $row): ?>
      <tr>
        <td><?= ch_h($row['UNIDADE']) ?></td>
        <td><?= ch_h($row['NOME']) ?></td>
        <td><?= ch_h($row['PERIODO']) ?> <small class="text-muted"><?= ch_h($row['HORA_INICIO']) ?>–<?= ch_h($row['HORA_FIM']) ?></small></td>
        <td><?= $row['PROFESSOR'] ? ch_h($row['PROFESSOR']) : '<span class="text-muted">não definido</span>' ?></td>
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
              <li><a class="dropdown-item" href="edit.php?id=<?= (int)$row['ID'] ?>"><i class="far fa-edit"></i> Editar</a></li>
              <li><a class="dropdown-item" href="<?= ch_url('chamada/index.php?turma_id=' . (int)$row['ID']) ?>"><i class="fas fa-clipboard-user"></i> Chamada</a></li>
              <li>
                <form method="post" action="processa/toggle_processa.php" class="px-3 py-1">
                  <input type="hidden" name="id" value="<?= (int)$row['ID'] ?>">
                  <button type="submit" class="btn btn-sm btn-outline-secondary w-100"
                          onclick="return confirm('Alterar a situação desta turma?');">
                    <?= (int)$row['ATIVO'] === 1 ? 'Inativar' : 'Reativar' ?>
                  </button>
                </form>
              </li>
            </ul>
          </div>
        </td>
      </tr>
      <?php endforeach; ?>
      <?php if (!$turmas): ?>
        <tr><td colspan="7" class="text-center py-3">Nenhuma turma encontrada para o filtro escolhido.</td></tr>
      <?php endif; ?>
    </tbody>
  </table>
</div>

<script src="<?= BASEURL ?>js/tabela.js"></script>
<?php include(FOOTER_TEMPLATE); ?>
