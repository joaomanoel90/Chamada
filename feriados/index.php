<?php require_once('processa/index_processa.php'); ?>
<?php include(ch_gestor() ? HEADER_TEMPLATE : HEADER_TEMPLATE2); ?>

<?php ch_cabecalho('Feriados e dias sem aula', 'fa-calendar-day',
    ch_gestor() ? '<a class="btn btn-success" href="add.php"><i class="fa fa-plus"></i> Novo Feriado</a>' : ''); ?>

<form method="get" action="index.php" class="row g-2 align-items-end mb-3">
  <div class="col-md-3">
    <label class="fw-bold">Ano</label>
    <select name="ano" class="form-select">
      <?php for ($a = (int)date('Y') + 1; $a >= 2020; $a--): ?>
        <option value="<?= $a ?>" <?= $a === $fAno ? 'selected' : '' ?>><?= $a ?></option>
      <?php endfor; ?>
    </select>
  </div>
  <div class="col-md-2"><button class="btn btn-primary w-100"><i class="fas fa-filter"></i> Filtrar</button></div>
</form>

<div class="alert alert-info">
  <i class="fas fa-circle-info"></i>
  <strong>Integral</strong> bloqueia a chamada de todas as turmas do dia.
  <strong>Parcial</strong> guarda a faixa <em>sem aula</em> e bloqueia apenas as turmas cujo período cruza essa faixa.
</div>

<div class="table-responsive-sm">
  <table id="item" data-vazio="Nenhum feriado cadastrado neste ano." class="table table-light table-striped table-sm align-middle">
    <thead class="text-sm-center">
      <tr>
        <th width="12%">Data</th>
        <th width="32%">Descrição</th>
        <th width="12%">Tipo</th>
        <th width="16%">Faixa sem aula</th>
        <th width="20%">Unidade</th>
        <th width="8%">Opções</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($feriados as $row): ?>
      <tr>
        <td><?= ch_data_br($row['DATA']) ?></td>
        <td><?= ch_h($row['DESCRICAO']) ?></td>
        <td class="text-center">
          <span class="badge bg-<?= $row['TIPO'] === 'INTEGRAL' ? 'danger' : 'warning text-dark' ?>">
            <?= $row['TIPO'] === 'INTEGRAL' ? 'Integral' : 'Parcial' ?>
          </span>
        </td>
        <td class="text-center">
          <?= $row['TIPO'] === 'PARCIAL' ? ch_h($row['HORA_INICIO']) . ' às ' . ch_h($row['HORA_FIM']) : '—' ?>
        </td>
        <td><?= $row['UNIDADE'] ? ch_h($row['UNIDADE']) : '<span class="badge bg-secondary">Todas</span>' ?></td>
        <td class="text-sm-end">
          <?php if (ch_gestor()): ?>
          <div class="btn-group">
            <button type="button" class="btn btn-success btn-sm dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
              <i class="fa-solid fa-gear"></i>
            </button>
            <ul class="dropdown-menu dropdown-menu-end">
              <li><a class="dropdown-item" href="edit.php?id=<?= (int)$row['ID'] ?>"><i class="far fa-edit"></i> Editar</a></li>
              <li>
                <form method="post" action="processa/delete_processa.php" class="px-3 py-1">
                  <input type="hidden" name="id" value="<?= (int)$row['ID'] ?>">
                  <button type="submit" class="btn btn-sm btn-outline-danger w-100"
                          onclick="return confirm('Excluir este feriado? As chamadas já lançadas serão mantidas.');">
                    Excluir
                  </button>
                </form>
              </li>
            </ul>
          </div>
          <?php else: ?><span class="text-muted">—</span><?php endif; ?>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

<script src="<?= BASEURL ?>js/tabela.js"></script>
<?php include(FOOTER_TEMPLATE); ?>
