<?php require_once('processa/historico_processa.php'); ?>
<?php include(ch_gestor() ? HEADER_TEMPLATE : HEADER_TEMPLATE2); ?>

<?php ch_cabecalho('Histórico da observação', 'fa-clock-rotate-left',
    '<a class="btn btn-outline-secondary" href="' . ch_url('observacoes/index.php?aluno_id=' . (int)$aluno['ID']) . '"><i class="fas fa-arrow-left"></i> Voltar</a>'); ?>

<div class="card border-0 shadow-sm rounded-4 mb-3">
  <div class="card-body">
    <h5><?= ch_h($aluno['NOME']) ?> · <?= ch_data_br($observacao['DATA']) ?></h5>
    <span class="badge bg-light text-dark"><?= ch_h($observacao['CATEGORIA']) ?></span>
    <p class="mt-2 mb-1"><?= nl2br(ch_h($observacao['TEXTO'])) ?></p>
    <small class="text-muted">
      <?php if ($historico): ?>
        Versão atual registrada por <?= ch_h($observacao['AUTOR']) ?>
      <?php else: ?>
        Autor conhecido: <?= ch_h($observacao['AUTOR']) ?>
      <?php endif; ?>
      <?php if ($observacao['MODIFIED']): ?>
        · última alteração em <?= ch_h($observacao['MODIFIED']) ?>
        <?php if (!$historico): ?> (autor não registrado antes da implantação do histórico)<?php endif; ?>
      <?php endif; ?>
    </small>
  </div>
</div>

<h5 class="mb-3">Versões anteriores</h5>
<?php if (!$historico): ?>
  <div class="alert alert-info">Ainda não há alterações registradas para esta observação.</div>
<?php else: ?>
  <?php foreach ($historico as $versao): ?>
    <div class="card border-0 shadow-sm rounded-4 mb-3">
      <div class="card-body">
        <div class="d-flex flex-wrap justify-content-between gap-2 mb-2">
          <strong><?= ch_h($versao['CATEGORIA']) ?> · <?= ch_data_br($versao['DATA']) ?></strong>
          <small class="text-muted">Alterada em <?= ch_h($versao['ALTERADA_EM']) ?> por <?= ch_h($versao['ALTERADO_POR']) ?></small>
        </div>
        <p class="mb-1"><?= nl2br(ch_h($versao['TEXTO'])) ?></p>
        <small class="text-muted">Versão registrada por <?= ch_h($versao['AUTOR']) ?></small>
      </div>
    </div>
  <?php endforeach; ?>
<?php endif; ?>

<?php include(FOOTER_TEMPLATE); ?>
