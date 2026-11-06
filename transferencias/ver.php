<?php require_once('processa/ver_processa.php'); ?>
<?php include(HEADER_TEMPLATE); ?>

<?php
$botoes = '<a class="btn btn-outline-secondary" href="index.php"><i class="fas fa-arrow-left"></i> Voltar</a> ';
if ($podeVerAluno) {
    $botoes .= '<a class="btn btn-outline-primary" href="' . ch_url('alunos/view.php?id=' . (int)$t['ID_ALUNO']) . '"><i class="fas fa-child-reaching"></i> Ficha do aluno</a> ';
}
if ($podeReceber) {
    $botoes .= '<a class="btn btn-success" href="receber.php?id=' . (int)$id . '"><i class="fas fa-door-open"></i> Escolher turma e data</a> ';
}
if ($podeCancelar) {
    $botoes .= '<a class="btn btn-outline-danger" href="cancelar.php?id=' . (int)$id . '"><i class="fas fa-ban"></i> Cancelar</a>';
}
ch_cabecalho('Transferência #' . (int)$id, 'fa-right-left', $botoes);
?>

<div class="mb-3"><?= tr_situacao_badge($t['SITUACAO']) ?></div>

<?php if ($t['SITUACAO'] === 'PENDENTE'): ?>
  <div class="alert alert-warning">
    <i class="fas fa-hourglass-half"></i>
    Aguardando a escola <b><?= ch_h($t['UNIDADE_DESTINO']) ?></b> escolher a turma e a data de início.
    O aluno <b>continua em <?= ch_h($t['TURMA_ORIGEM']) ?></b> e a chamada segue normal nessa turma.
  </div>
<?php elseif ($t['SITUACAO'] === 'CONCLUIDA'): ?>
  <div class="alert alert-success">
    <i class="fas fa-check"></i>
    Concluída. O aluno está em <b><?= ch_h($t['TURMA_DESTINO']) ?></b>, na escola
    <?= ch_h($t['UNIDADE_DESTINO']) ?>, desde <b><?= ch_data_br($t['DATA_INICIO']) ?></b>.
    A chamada anterior a essa data permanece na turma de origem.
  </div>
<?php else: ?>
  <div class="alert alert-secondary">
    <i class="fas fa-ban"></i>
    Cancelada. O aluno permanece em <b><?= ch_h($t['TURMA_ORIGEM']) ?></b>.
  </div>
<?php endif; ?>

<div class="row g-3">
  <div class="col-lg-6">
    <div class="card border-0 shadow-sm rounded-4 h-100">
      <div class="card-body">
        <h5 class="mb-3"><i class="fas fa-child-reaching"></i> Aluno</h5>
        <dl class="row mb-0">
          <dt class="col-sm-4">Nome</dt><dd class="col-sm-8"><?= ch_h($t['ALUNO']) ?></dd>
          <dt class="col-sm-4">Matrícula</dt><dd class="col-sm-8"><?= ch_h($t['MATRICULA']) ?></dd>
          <dt class="col-sm-4">Nascimento</dt>
          <dd class="col-sm-8"><?= ch_data_br($t['NASCIMENTO']) ?> <small class="text-muted">(<?= ch_h(ch_idade_texto($t['NASCIMENTO'])) ?>)</small></dd>
          <dt class="col-sm-4">Responsável</dt><dd class="col-sm-8"><?= ch_h($t['RESPONSAVEL']) ?></dd>
          <dt class="col-sm-4">Telefone</dt><dd class="col-sm-8"><?= ch_h($t['TELEFONE']) ?></dd>
        </dl>
      </div>
    </div>
  </div>

  <div class="col-lg-6">
    <div class="card border-0 shadow-sm rounded-4 h-100">
      <div class="card-body">
        <h5 class="mb-3"><i class="fas fa-right-left"></i> Encaminhamento</h5>
        <dl class="row mb-0">
          <dt class="col-sm-4">De</dt>
          <dd class="col-sm-8"><?= ch_h($t['UNIDADE_ORIGEM']) ?><br><small class="text-muted"><?= ch_h($t['TURMA_ORIGEM']) ?> (<?= (int)$t['ANO_ORIGEM'] ?>)</small></dd>
          <dt class="col-sm-4">Para</dt>
          <dd class="col-sm-8"><?= ch_h($t['UNIDADE_DESTINO']) ?><br>
            <small class="text-muted"><?= $t['TURMA_DESTINO']
                ? ch_h($t['TURMA_DESTINO']) . ' — ' . ch_h($t['PERIODO_DESTINO']) . ' (' . (int)$t['ANO_DESTINO'] . ')'
                : 'turma ainda não escolhida' ?></small></dd>
          <dt class="col-sm-4">Início</dt>
          <dd class="col-sm-8"><?= $t['DATA_INICIO'] ? '<b>' . ch_data_br($t['DATA_INICIO']) . '</b>' : '<span class="text-muted">a definir</span>' ?></dd>
          <dt class="col-sm-4">Motivo</dt><dd class="col-sm-8"><?= ch_h($t['MOTIVO']) ?></dd>
          <?php if ($t['DOCUMENTO']): ?>
            <dt class="col-sm-4">Documento</dt><dd class="col-sm-8"><?= ch_h($t['DOCUMENTO']) ?></dd>
          <?php endif; ?>
          <dt class="col-sm-4">Indicada por</dt>
          <dd class="col-sm-8"><?= ch_h($t['SOLICITADA_POR']) ?><br><small class="text-muted"><?= ch_h(ch_confirma_texto($t['SOLICITADA_EM'])) ?></small></dd>
          <?php if ($t['EFETIVADA_POR']): ?>
            <dt class="col-sm-4">Recebida por</dt>
            <dd class="col-sm-8"><?= ch_h($t['EFETIVADA_POR']) ?><br><small class="text-muted"><?= ch_h(ch_confirma_texto($t['EFETIVADA_EM'])) ?></small></dd>
          <?php endif; ?>
          <?php if ($t['OBSERVACAO_DESTINO']): ?>
            <dt class="col-sm-4">Observação</dt><dd class="col-sm-8"><?= ch_h($t['OBSERVACAO_DESTINO']) ?></dd>
          <?php endif; ?>
          <?php if ($t['CANCELADA_POR']): ?>
            <dt class="col-sm-4">Cancelada por</dt>
            <dd class="col-sm-8"><?= ch_h($t['CANCELADA_POR']) ?><br><small class="text-muted"><?= ch_h(ch_confirma_texto($t['CANCELADA_EM'])) ?></small></dd>
            <dt class="col-sm-4">Motivo</dt><dd class="col-sm-8"><?= ch_h($t['MOTIVO_CANCELAMENTO']) ?></dd>
          <?php endif; ?>
        </dl>
      </div>
    </div>
  </div>
</div>

<div class="card border-0 shadow-sm rounded-4 mt-3">
  <div class="card-body">
    <?php $tituloQuadro = 'Escolas e turmas por período'; include(__DIR__ . '/quadro_vinculos.php'); ?>
  </div>
</div>

<div class="card border-0 shadow-sm rounded-4 mt-3">
  <div class="card-body">
    <?php $historico = tr_historico_aluno($t['ID_ALUNO']); include(__DIR__ . '/quadro_historico.php'); ?>
  </div>
</div>

<?php include(FOOTER_TEMPLATE); ?>