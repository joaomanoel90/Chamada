<?php
/* Histórico de transferências do aluno. Espera $historico (tr_historico_aluno()).
   Usado no detalhe da transferência e na ficha do aluno. */
if (!isset($historico)) { $historico = []; }
?>
<h5 class="mb-3"><i class="fas fa-clock-rotate-left"></i> Histórico de transferências</h5>
<?php if (!$historico): ?>
  <p class="text-muted mb-0">Este aluno nunca foi transferido.</p>
<?php else: ?>
  <?php foreach ($historico as $h): ?>
    <div class="observacao-item border rounded-3 p-3 mb-2">
      <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-1">
        <div>
          <?= tr_situacao_badge($h['SITUACAO']) ?>
          <?php if ($h['DATA_INICIO']): ?>
            <span class="badge bg-light text-dark border">início em <?= ch_data_br($h['DATA_INICIO']) ?></span>
          <?php endif; ?>
        </div>
        <a class="btn btn-sm btn-outline-secondary" href="<?= ch_url('transferencias/ver.php?id=' . (int)$h['ID']) ?>">
          <i class="far fa-eye"></i> Detalhes
        </a>
      </div>
      <p class="mb-1">
        <b><?= ch_h($h['UNIDADE_ORIGEM']) ?></b> — <?= ch_h($h['TURMA_ORIGEM']) ?>
        <i class="fas fa-arrow-right mx-1 text-muted"></i>
        <b><?= ch_h($h['UNIDADE_DESTINO']) ?></b><?php if ($h['TURMA_DESTINO']): ?> — <?= ch_h($h['TURMA_DESTINO']) ?><?php endif; ?>
      </p>
      <p class="mb-1"><small><b>Motivo:</b> <?= ch_h($h['MOTIVO']) ?></small></p>
      <?php if ($h['DOCUMENTO']): ?>
        <p class="mb-1"><small><b>Documento:</b> <?= ch_h($h['DOCUMENTO']) ?></small></p>
      <?php endif; ?>
      <?php if ($h['SITUACAO'] === 'CANCELADA' && $h['MOTIVO_CANCELAMENTO']): ?>
        <p class="mb-1"><small><b>Cancelamento:</b> <?= ch_h($h['MOTIVO_CANCELAMENTO']) ?></small></p>
      <?php endif; ?>
      <p class="mb-0 text-muted"><small>
        Indicada por <?= ch_h($h['SOLICITADA_POR']) ?> em <?= ch_h(ch_confirma_texto($h['SOLICITADA_EM'])) ?>.
        <?php if ($h['EFETIVADA_POR']): ?>
          Recebida por <?= ch_h($h['EFETIVADA_POR']) ?> em <?= ch_h(ch_confirma_texto($h['EFETIVADA_EM'])) ?>.
        <?php endif; ?>
      </small></p>
    </div>
  <?php endforeach; ?>
<?php endif; ?>