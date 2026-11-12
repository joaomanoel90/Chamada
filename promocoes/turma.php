<?php require_once('processa/turma_processa.php'); ?>
<?php include(HEADER_TEMPLATE); ?>

<?php ch_cabecalho('Promover turma', 'fa-arrow-up',
    '<a class="btn btn-outline-secondary" href="index.php"><i class="fas fa-arrow-left"></i> Voltar</a>'); ?>

<div class="alert alert-info">
  Todos os alunos ativos de <strong><?= ch_h($origem['NOME']) ?></strong>
  (<?= (int)$origem['ANO_LETIVO'] ?>) serão movidos para uma turma de
  <?= (int)$origem['ANO_LETIVO'] + 1 ?>. Para deixar algum aluno retido ou
  escolher outro destino, faça a promoção individual pela ficha do aluno.
</div>

<?php if (!$destinos): ?>
  <div class="alert alert-warning">Cadastre uma turma ativa do ano <?= (int)$origem['ANO_LETIVO'] + 1 ?> nesta unidade antes de promover alunos.</div>
<?php else: ?>
  <form method="post" action="<?= ch_url('promocoes/processa/salvar_processa.php') ?>" class="card border-0 shadow-sm rounded-4">
    <div class="card-body">
      <?= ch_csrf_campo() ?>
      <input type="hidden" name="turma_id" value="<?= (int)$origem['ID'] ?>">
      <div class="row g-3">
        <div class="col-md-6">
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
        <div class="col-md-6">
          <label class="form-label fw-bold" for="data_inicio">Data de início no ano letivo <?= (int)$origem['ANO_LETIVO'] + 1 ?></label>
          <input class="form-control" type="date" id="data_inicio" name="data_inicio"
                 min="<?= (int)$origem['ANO_LETIVO'] + 1 ?>-01-01"
                 max="<?= (int)$origem['ANO_LETIVO'] + 1 ?>-12-31" required>
        </div>
      </div>

      <h5 class="mt-4">Alunos que serão promovidos (<?= count($alunos) ?>)</h5>
      <?php if ($alunos): ?>
        <ul class="mb-0">
          <?php foreach ($alunos as $aluno): ?>
            <li><?= ch_h($aluno['NOME']) ?> <small class="text-muted">· <?= ch_h($aluno['MATRICULA']) ?></small></li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </div>
    <div class="card-footer bg-white text-end">
      <button class="btn btn-primary" type="submit" <?= !$alunos ? 'disabled' : '' ?>
              onclick="return confirm('Promover todos os alunos listados para a turma escolhida?');">
        <i class="fas fa-check"></i> Confirmar promoção
      </button>
    </div>
  </form>
<?php endif; ?>

<?php include(FOOTER_TEMPLATE); ?>
