<?php require_once('processa/index_processa.php'); ?>
<?php include(HEADER_TEMPLATE); ?>

<?php ch_cabecalho('Promoção de Alunos', 'fa-arrow-up'); ?>

<div class="alert alert-info">
  <i class="fas fa-circle-info"></i>
  Selecione uma turma para promover todos os alunos de uma vez, ou abra a ficha
  do aluno para promovê-lo individualmente. A data de início é definida pela
  Secretaria de Educação conforme o calendário escolar.
</div>

<?php if (!$turmas): ?>
  <div class="alert alert-secondary">Não há turmas ativas disponíveis para promoção.</div>
<?php else: ?>
  <div class="table-responsive">
    <table class="table table-light table-striped align-middle">
      <thead><tr><th>Unidade</th><th>Turma atual</th><th>Ano</th><th>Alunos</th><th>Turmas do próximo ano</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($turmas as $turma): ?>
        <tr>
          <td><?= ch_h($turma['UNIDADE']) ?></td>
          <td><?= ch_h($turma['NOME']) ?></td>
          <td><?= (int)$turma['ANO_LETIVO'] ?></td>
          <td><?= (int)$turma['ALUNOS'] ?></td>
          <td><?= (int)$turma['DESTINOS'] ?></td>
          <td class="text-end">
            <?php if ((int)$turma['ALUNOS'] > 0 && (int)$turma['DESTINOS'] > 0): ?>
              <a class="btn btn-sm btn-primary" href="turma.php?turma_id=<?= (int)$turma['ID'] ?>">
                <i class="fas fa-users"></i> Promover turma
              </a>
            <?php else: ?>
              <span class="text-muted small">Cadastre a turma do próximo ano</span>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
<?php endif; ?>

<?php include(FOOTER_TEMPLATE); ?>
