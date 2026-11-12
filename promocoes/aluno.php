<?php require_once('processa/aluno_processa.php'); ?>
<?php include(HEADER_TEMPLATE); ?>

<?php ch_cabecalho('Promover aluno', 'fa-arrow-up',
    '<a class="btn btn-outline-secondary" href="' . ch_url('alunos/view.php?id=' . (int)$aluno['ID']) . '"><i class="fas fa-arrow-left"></i> Ficha do aluno</a>'); ?>

<div class="alert alert-info">
  Promover <strong><?= ch_h($aluno['NOME']) ?></strong> de
  <?= ch_h($aluno['TURMA']) ?> (<?= (int)$aluno['ANO_LETIVO'] ?>) para uma turma
  da mesma unidade no ano <?= (int)$aluno['ANO_LETIVO'] + 1 ?>. A data de início
  segue o calendário escolar definido pela Secretaria de Educação.
</div>

<?php if (!$destinos): ?>
  <div class="alert alert-warning">Cadastre uma turma ativa do próximo ano nesta unidade antes de promover este aluno.</div>
<?php else: ?>
  <form method="post" action="<?= ch_url('promocoes/processa/salvar_processa.php') ?>" class="card border-0 shadow-sm rounded-4">
    <div class="card-body">
      <?= ch_csrf_campo() ?>
      <input type="hidden" name="aluno_id" value="<?= (int)$aluno['ID'] ?>">
      <div class="mb-3">
        <label class="form-label fw-bold" for="turma_destino">Turma de destino</label>
        <select class="form-select" id="turma_destino" name="id_turma_destino" required>
          <option value="">Selecione</option>
          <?php foreach ($destinos as $destino): ?>
            <option value="<?= (int)$destino['ID'] ?>">
              <?= ch_h($destino['NOME']) ?> · <?= ch_h($destino['PERIODO']) ?>
              (<?= (int)$destino['OCUPADAS'] ?>/<?= (int)$destino['CAPACIDADE'] ?>)
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="mb-3">
        <label class="form-label fw-bold" for="data_inicio">Data de início no novo ano letivo</label>
        <input class="form-control" type="date" id="data_inicio" name="data_inicio"
               min="<?= (int)$aluno['ANO_LETIVO'] + 1 ?>-01-01"
               max="<?= (int)$aluno['ANO_LETIVO'] + 1 ?>-12-31" required>
      </div>
    </div>
    <div class="card-footer bg-white text-end">
      <button class="btn btn-primary" type="submit"><i class="fas fa-check"></i> Confirmar promoção</button>
    </div>
  </form>
<?php endif; ?>

<?php include(FOOTER_TEMPLATE); ?>
