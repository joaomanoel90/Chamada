<?php require_once('processa/index_processa.php'); ?>
<?php include(ch_gestor() ? HEADER_TEMPLATE : HEADER_TEMPLATE2); ?>

<?php ch_cabecalho('Relatório Anual de Desenvolvimento', 'fa-file-lines'); ?>

<form method="get" action="index.php" class="row g-2 align-items-end mb-3">
  <div class="col-md-5">
    <label class="fw-bold">Turma</label>
    <select name="turma_id" class="form-select">
      <option value="0">Todas</option>
      <?php foreach ($turmas as $t): ?>
        <option value="<?= (int)$t['ID'] ?>" <?= (int)$t['ID'] === $fTurma ? 'selected' : '' ?>>
          <?= ch_h($t['UNIDADE']) ?> — <?= ch_h($t['NOME']) ?>
        </option>
      <?php endforeach; ?>
    </select>
  </div>
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
  A frequência é calculada <strong>somente sobre os dias com chamada lançada</strong>.
  O relatório reúne a frequência mês a mês e todas as observações registradas no ano.
</div>

<div class="table-responsive-sm">
  <table id="item" data-vazio="Nenhum aluno encontrado para o ano e filtro escolhidos." class="table table-light table-striped table-sm align-middle">
    <thead class="text-sm-center">
      <tr>
        <th width="30%">Aluno</th>
        <th width="26%">Turma / Unidade</th>
        <th width="10%">Dias lançados</th>
        <th width="10%">Observações</th>
        <th width="12%">Parecer</th>
        <th width="12%">Relatório</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($alunos as $row): ?>
      <tr>
        <td><?= ch_h($row['NOME']) ?><br><small class="text-muted"><?= ch_h($row['MATRICULA']) ?></small></td>
        <td><?= ch_h($row['TURMA']) ?><br><small class="text-muted"><?= ch_h($row['UNIDADE']) ?></small></td>
        <td class="text-center"><?= (int)$row['DIAS'] ?></td>
        <td class="text-center"><?= (int)$row['OBSERVACOES'] ?></td>
        <td class="text-center">
          <span class="badge bg-<?= (int)$row['TEM_PARECER'] ? 'success' : 'secondary' ?>">
            <?= (int)$row['TEM_PARECER'] ? 'Registrado' : 'Pendente' ?>
          </span>
        </td>
        <td class="text-center">
          <a class="btn btn-sm btn-primary" href="ver.php?aluno_id=<?= (int)$row['ID'] ?>&ano=<?= (int)$fAno ?>">
            <i class="far fa-eye"></i> Abrir
          </a>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

<script src="<?= BASEURL ?>js/tabela.js"></script>
<?php include(FOOTER_TEMPLATE); ?>
