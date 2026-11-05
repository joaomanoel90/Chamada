<?php require_once('processa/historico_processa.php'); ?>
<?php include(ch_gestor() ? HEADER_TEMPLATE : HEADER_TEMPLATE2); ?>

<?php ch_cabecalho('Histórico de retificações', 'fa-clock-rotate-left',
  '<a class="btn btn-outline-secondary" href="index.php?turma_id=' . (int)$turmaId . ($data ? '&data=' . ch_h($data) : '') . '">'
  . '<i class="fas fa-arrow-left"></i> Voltar à chamada</a>'); ?>

<div class="painel-turma mb-4">
  <div class="painel-turma-info">
    <div class="painel-turma-icone"><i class="fas fa-clock-rotate-left"></i></div>
    <div>
      <div class="painel-turma-nome"><?= ch_h($turma['NOME']) ?></div>
      <div class="painel-turma-sub">
        <?= ch_h($turma['UNIDADE']) ?> &middot; <?= (int)$turma['ANO_LETIVO'] ?><br>
        <?= $data ? ch_data_longa($data) : 'Todas as datas' ?>
      </div>
    </div>
  </div>
</div>

<form method="get" action="historico.php" class="row g-2 align-items-end mb-4">
  <input type="hidden" name="turma_id" value="<?= (int)$turmaId ?>">
  <div class="col-md-4">
    <label class="fw-bold">Filtrar por data</label>
    <input type="date" name="data" class="form-control" value="<?= ch_h($data ?? '') ?>">
  </div>
  <div class="col-md-4">
    <button class="btn btn-primary"><i class="fas fa-filter"></i> Filtrar</button>
    <a class="btn btn-outline-secondary" href="historico.php?turma_id=<?= (int)$turmaId ?>">Ver todas</a>
  </div>
</form>

<?php if (!$retificacoes): ?>
  <div class="alert alert-info">
    <i class="fas fa-circle-info"></i>
    Nenhuma retificação registrada<?= $data ? ' nesta data' : ' para esta turma' ?>.
  </div>
<?php else: ?>
  <p class="text-muted"><?= count($retificacoes) ?> retificação(ões) encontrada(s).</p>
  <div class="linha-do-tempo">
    <?php foreach ($retificacoes as $r):
      $de  = ch_status_lista()[$r['STATUS_ANTERIOR']] ?? null;
      $para = ch_status_lista()[$r['STATUS_NOVO']] ?? null;
    ?>
    <div class="evento-retificacao">
      <div class="evento-marcador"><i class="fas fa-pen"></i></div>
      <div class="evento-corpo">
        <div class="evento-topo">
          <strong><?= ch_h($r['ALUNO']) ?></strong>
          <small class="text-muted"><?= ch_h($r['MATRICULA']) ?></small>
          <span class="ms-auto text-muted small">
            <i class="far fa-calendar"></i> <?= ch_data_br($r['DATA_CHAMADA']) ?>
            &nbsp;·&nbsp; retificado em <?= ch_h(ch_confirma_texto($r['QUANDO'])) ?>
          </span>
        </div>
        <div class="evento-mudanca">
          <span class="selo-status status-<?= ch_h($r['STATUS_ANTERIOR']) ?>">
            <i class="fas <?= $de ? $de['icone'] : '' ?>"></i> <?= ch_h(ch_status_rotulo($r['STATUS_ANTERIOR'])) ?>
          </span>
          <i class="fas fa-arrow-right seta-mudanca"></i>
          <span class="selo-status status-<?= ch_h($r['STATUS_NOVO']) ?>">
            <i class="fas <?= $para ? $para['icone'] : '' ?>"></i> <?= ch_h(ch_status_rotulo($r['STATUS_NOVO'])) ?>
          </span>
        </div>
        <?php if (!empty($r['JUSTIFICATIVA_NOVA'])): ?>
          <div class="just-exibida"><i class="fas fa-quote-left"></i> <?= ch_h($r['JUSTIFICATIVA_NOVA']) ?></div>
        <?php endif; ?>
        <div class="evento-motivo">
          <strong>Motivo:</strong> <?= ch_h($r['MOTIVO']) ?>
        </div>
        <div class="evento-autor">
          <span class="avatar-prof avatar-mini"><?= ch_h(ch_iniciais($r['USUARIO'])) ?></span>
          <?= ch_h($r['USUARIO']) ?>
        </div>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

<?php include(FOOTER_TEMPLATE); ?>
