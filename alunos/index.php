<?php require_once('processa/index_processa.php'); ?>
<?php include(ch_gestor() ? HEADER_TEMPLATE : HEADER_TEMPLATE2); ?>

<?php ch_cabecalho('Alunos', 'fa-child-reaching',
    ch_gestor() ? '<a class="btn btn-success" href="add.php"><i class="fa fa-plus"></i> Novo Aluno</a>' : ''); ?>

<form method="get" action="index.php" class="row g-2 align-items-end mb-3">
  <div class="col-md-4">
    <label class="fw-bold">Buscar</label>
    <input type="text" name="busca" class="form-control" value="<?= ch_h($fBusca) ?>" placeholder="Nome, matrícula ou responsável">
  </div>
  <div class="col-md-4">
    <label class="fw-bold">Turma</label>
    <select name="turma_id" class="form-select">
      <option value="0">Todas</option>
      <?php foreach ($turmas as $t): ?>
        <option value="<?= (int)$t['ID'] ?>" <?= (int)$t['ID'] === $fTurma ? 'selected' : '' ?>>
          <?= ch_h($t['UNIDADE']) ?> — <?= ch_h($t['NOME']) ?> (<?= (int)$t['ANO_LETIVO'] ?>)
        </option>
      <?php endforeach; ?>
    </select>
  </div>
  <div class="col-md-2">
    <label class="fw-bold">Situação</label>
    <select name="situacao" class="form-select">
      <option value="ativos"   <?= $fSituacao === 'ativos' ? 'selected' : '' ?>>Ativos</option>
      <option value="inativos" <?= $fSituacao === 'inativos' ? 'selected' : '' ?>>Inativos</option>
      <option value="todos"    <?= $fSituacao === 'todos' ? 'selected' : '' ?>>Todos</option>
    </select>
  </div>
  <div class="col-md-2">
    <button class="btn btn-primary w-100"><i class="fas fa-filter"></i> Filtrar</button>
  </div>
</form>

<div class="table-responsive-sm">
  <table id="item" data-vazio="Nenhum aluno encontrado." class="table table-light table-striped table-sm align-middle">
    <thead class="text-sm-center">
      <tr>
        <th width="10%">Matrícula</th>
        <th width="26%">Aluno</th>
        <th width="22%">Turma / Unidade</th>
        <th width="18%">Responsável</th>
        <th width="9%">Idade</th>
        <th width="7%">Situação</th>
        <th width="8%">Opções</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($alunos as $row): ?>
      <tr>
        <td><?= ch_h($row['MATRICULA']) ?></td>
        <td><?= ch_h($row['NOME']) ?></td>
        <td><?= ch_h($row['TURMA']) ?><br><small class="text-muted"><?= ch_h($row['UNIDADE']) ?></small></td>
        <td><?= ch_h($row['RESPONSAVEL']) ?><br><small class="text-muted"><?= ch_h($row['TELEFONE']) ?></small></td>
        <td class="text-center"><?= ch_h(ch_idade_texto($row['NASCIMENTO'])) ?></td>
        <td class="text-center">
          <span class="badge bg-<?= (int)$row['ATIVO'] === 1 ? 'success' : 'secondary' ?>">
            <?= (int)$row['ATIVO'] === 1 ? 'Ativo' : 'Inativo' ?>
          </span>
        </td>
        <td class="text-sm-end">
          <div class="btn-group">
            <button type="button" class="btn btn-success btn-sm dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
              <i class="fa-solid fa-gear"></i>
            </button>
            <ul class="dropdown-menu dropdown-menu-end">
              <li><a class="dropdown-item" href="view.php?id=<?= (int)$row['ID'] ?>"><i class="far fa-eye"></i> Ficha do aluno</a></li>
              <?php if (ch_gestor()): ?>
                <li><a class="dropdown-item" href="edit.php?id=<?= (int)$row['ID'] ?>"><i class="far fa-edit"></i> Editar</a></li>
              <?php endif; ?>
              <li><a class="dropdown-item" href="<?= ch_url('observacoes/add.php?aluno_id=' . (int)$row['ID']) ?>"><i class="far fa-comment-dots"></i> Nova observação</a></li>
              <li><a class="dropdown-item" href="<?= ch_url('relatorios/ver.php?aluno_id=' . (int)$row['ID'] . '&ano=' . (int)$row['ANO_LETIVO']) ?>"><i class="fas fa-file-lines"></i> Relatório anual</a></li>
              <?php if (ch_gestor()): ?>
              <li>
                <form method="post" action="processa/toggle_processa.php" class="px-3 py-1">
                  <input type="hidden" name="id" value="<?= (int)$row['ID'] ?>">
                  <button type="submit" class="btn btn-sm btn-outline-secondary w-100"
                          onclick="return confirm('Alterar a situação deste aluno?');">
                    <?= (int)$row['ATIVO'] === 1 ? 'Inativar' : 'Reativar' ?>
                  </button>
                </form>
              </li>
              <?php endif; ?>
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
