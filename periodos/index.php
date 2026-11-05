<?php require_once('processa/index_processa.php'); ?>
<?php include(ch_gestor() ? HEADER_TEMPLATE : HEADER_TEMPLATE2); ?>

<?php ch_cabecalho('Períodos', 'fa-clock',
    '<a class="btn btn-success" href="add.php"><i class="fa fa-plus"></i> Novo Período</a>'); ?>

<div class="table-responsive-sm">
  <table id="item" data-vazio="Nenhum período cadastrado." class="table table-light table-striped table-sm align-middle">
    <thead class="text-sm-center">
      <tr>
        <th width="8%">ID</th>
        <th width="32%">Período</th>
        <th width="15%">Início</th>
        <th width="15%">Fim</th>
        <th width="12%">Turmas</th>
        <th width="10%">Situação</th>
        <th width="8%">Opções</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($periodos as $row): ?>
      <tr>
        <td class="text-center"><?= (int)$row['ID'] ?></td>
        <td><?= ch_h($row['NOME']) ?></td>
        <td class="text-center"><?= ch_h($row['HORA_INICIO']) ?></td>
        <td class="text-center"><?= ch_h($row['HORA_FIM']) ?></td>
        <td class="text-center"><?= (int)$row['TURMAS'] ?></td>
        <td class="text-center">
          <?php if ((int)$row['ATIVO'] === 1): ?>
            <span class="badge bg-success">Ativo</span>
          <?php else: ?>
            <span class="badge bg-secondary">Inativo</span>
          <?php endif; ?>
        </td>
        <td class="text-sm-end">
          <div class="btn-group">
            <button type="button" class="btn btn-success btn-sm dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
              <i class="fa-solid fa-gear"></i>
            </button>
            <ul class="dropdown-menu dropdown-menu-end">
              <li><a class="dropdown-item" href="edit.php?id=<?= (int)$row['ID'] ?>"><i class="far fa-edit"></i> Editar</a></li>
              <li>
                <form method="post" action="processa/toggle_processa.php" class="px-3 py-1">
                  <input type="hidden" name="id" value="<?= (int)$row['ID'] ?>">
                  <button type="submit" class="btn btn-sm btn-outline-secondary w-100"
                          onclick="return confirm('Confirmar a alteração de situação deste período?');">
                    <?= (int)$row['ATIVO'] === 1 ? 'Inativar' : 'Reativar' ?>
                  </button>
                </form>
              </li>
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
