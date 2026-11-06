<?php require_once('processa/cancelar_processa.php'); ?>
<?php include(HEADER_TEMPLATE); ?>

<?php ch_cabecalho('Cancelar transferência', 'fa-ban',
    '<a class="btn btn-outline-secondary" href="ver.php?id=' . (int)$id . '"><i class="fas fa-arrow-left"></i> Voltar</a>'); ?>

<div class="row g-3">
  <div class="col-lg-7">
    <div class="card border-0 shadow-sm rounded-4">
      <div class="card-body">
        <h5 class="mb-3"><i class="fas fa-ban"></i> Cancelar o encaminhamento</h5>

        <div class="alert alert-info">
          O aluno <b><?= ch_h($t['ALUNO']) ?></b> permanece em
          <b><?= ch_h($t['TURMA_ORIGEM']) ?></b>, na escola <?= ch_h($t['UNIDADE_ORIGEM']) ?>.
          Cancelar não altera a matrícula nem a chamada.
        </div>

        <?php if ($erros): ?>
          <div class="alert alert-danger"><ul class="mb-0"><?php foreach ($erros as $e): ?><li><?= ch_h($e) ?></li><?php endforeach; ?></ul></div>
        <?php endif; ?>

        <form method="post" action="cancelar.php">
          <?= ch_csrf_campo() ?>
          <input type="hidden" name="id" value="<?= (int)$id ?>">

          <div class="mb-3">
            <label class="fw-bold">Escola de destino indicada</label>
            <input type="text" class="form-control" disabled value="<?= ch_h($t['UNIDADE_DESTINO']) ?>">
          </div>

          <div class="mb-3">
            <label class="fw-bold" for="motivo_cancelamento">Motivo do cancelamento</label>
            <textarea name="motivo_cancelamento" id="motivo_cancelamento" class="form-control" rows="3"
                      maxlength="500" required
                      placeholder="Ex.: família desistiu da mudança; escola de destino sem vaga."><?= ch_h($motivo) ?></textarea>
            <div class="form-text">Obrigatório, até 500 caracteres. Fica registrado no histórico.</div>
          </div>

          <div class="d-flex gap-2 mt-4">
            <a href="ver.php?id=<?= (int)$id ?>" class="btn btn-outline-secondary">Voltar</a>
            <button type="submit" class="btn btn-danger"><i class="fas fa-ban"></i> Cancelar transferência</button>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>

<?php include(FOOTER_TEMPLATE); ?>