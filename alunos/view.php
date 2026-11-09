<?php require_once('processa/view_processa.php'); ?>
<?php include(ch_gestor() ? HEADER_TEMPLATE : HEADER_TEMPLATE2); ?>

<?php
$botoesAluno = '<a class="btn btn-outline-secondary" href="index.php"><i class="fas fa-arrow-left"></i> Voltar</a> ';
if (ch_gestor() && !$apenasHistorico) {
    $botoesAluno .= '<a class="btn btn-success" href="edit.php?id=' . (int)$id . '"><i class="far fa-edit"></i> Editar</a> ';
}
if ($podeTransferir) {
    $botoesAluno .= '<a class="btn btn-outline-primary" href="' . ch_url('transferencias/solicitar.php?aluno_id=' . (int)$id) . '"><i class="fas fa-right-left"></i> Transferir</a>';
}
ch_cabecalho($aluno['NOME'], 'fa-child-reaching', $botoesAluno);
?>

<?php if ($apenasHistorico): ?>
  <div class="alert alert-secondary">
    <i class="fas fa-clock-rotate-left"></i>
    Este aluno foi transferido para <b><?= ch_h($aluno['UNIDADE']) ?></b>. Você está vendo o
    <b>histórico</b> de quando ele estudou na sua escola. A edição e a chamada ficam com a escola atual.
  </div>
<?php endif; ?>

<?php if ($transferenciaPendente): ?>
  <div class="alert alert-warning d-flex flex-wrap justify-content-between align-items-center gap-2">
    <span>
      <i class="fas fa-hourglass-half"></i>
      Transferência para <b><?= ch_h(ch_val('SELECT unidade FROM TB_UNIDADES WHERE id = ?', [(int)$transferenciaPendente['ID_UNIDADE_DESTINO']])) ?></b>
      aguardando a escola de destino escolher a turma e a data de início.
      O aluno continua nesta turma até lá.
    </span>
    <a class="btn btn-sm btn-outline-dark" href="<?= ch_url('transferencias/ver.php?id=' . (int)$transferenciaPendente['ID']) ?>">Ver transferência</a>
  </div>
<?php endif; ?>

<div class="row g-3">
  <div class="col-md-7">
    <div class="card border-0 shadow-sm rounded-4">
      <div class="card-body">
        <h5 class="mb-3"><i class="fas fa-id-card"></i> Dados do aluno</h5>
        <dl class="row mb-0">
          <dt class="col-sm-4">Matrícula</dt><dd class="col-sm-8"><?= ch_h($aluno['MATRICULA']) ?></dd>
          <dt class="col-sm-4">Nascimento</dt><dd class="col-sm-8"><?= ch_data_br($aluno['NASCIMENTO']) ?> <span class="text-muted">(<?= ch_h(ch_idade_texto($aluno['NASCIMENTO'])) ?>)</span></dd>
          <dt class="col-sm-4">Matriculado em</dt><dd class="col-sm-8"><?= ch_data_br($aluno['DATA_MATRICULA']) ?></dd>
          <dt class="col-sm-4">Responsável</dt><dd class="col-sm-8"><?= ch_h($aluno['RESPONSAVEL']) ?></dd>
          <dt class="col-sm-4">Telefone</dt><dd class="col-sm-8"><?= ch_h($aluno['TELEFONE']) ?></dd>
          <dt class="col-sm-4">E-mail</dt><dd class="col-sm-8"><?= $aluno['EMAIL'] ? ch_h($aluno['EMAIL']) : '<span class="text-muted">—</span>' ?></dd>
          <dt class="col-sm-4">Unidade</dt><dd class="col-sm-8"><?= ch_h($aluno['UNIDADE']) ?></dd>
          <dt class="col-sm-4">Turma</dt><dd class="col-sm-8"><?= ch_h($aluno['TURMA']) ?> — <?= ch_h($aluno['PERIODO']) ?> (<?= (int)$aluno['ANO_LETIVO'] ?>)</dd>
        </dl>
      </div>
    </div>
  </div>

  <div class="col-md-5">
    <div class="card border-0 shadow-sm rounded-4 h-100">
      <div class="card-body">
        <h5 class="mb-3"><i class="fas fa-chart-pie"></i> Frequência em <?= (int)$aluno['ANO_LETIVO'] ?></h5>
        <?php if ($frequencia === null): ?>
          <p class="text-muted">Ainda não há chamada lançada para este aluno no ano letivo.</p>
        <?php else: ?>
          <h2 class="mb-0"><?= number_format($frequencia, 1, ',', '.') ?>%</h2>
          <p class="text-muted mb-3"><?= $presencas ?> presenças em <?= $dias ?> dias com chamada lançada.</p>
          <div class="d-flex gap-2 flex-wrap">
            <span class="badge bg-success">Presente: <?= (int)$resumo['P'] ?></span>
            <span class="badge bg-info">Atraso: <?= (int)$resumo['A'] ?></span>
            <span class="badge bg-danger">Falta: <?= (int)$resumo['F'] ?></span>
            <span class="badge bg-warning text-dark">Justificada: <?= (int)$resumo['J'] ?></span>
          </div>
        <?php endif; ?>
        <div class="mt-3 d-grid gap-2">
          <a class="btn btn-primary" href="<?= ch_url('observacoes/add.php?aluno_id=' . (int)$id) ?>"><i class="far fa-comment-dots"></i> Registrar observação</a>
          <a class="btn btn-outline-primary" href="<?= ch_url('relatorios/ver.php?aluno_id=' . (int)$id . '&ano=' . (int)$aluno['ANO_LETIVO']) ?>"><i class="fas fa-file-lines"></i> Relatório anual</a>
        </div>
      </div>
    </div>
  </div>
</div>

<?php if (eq_tem_saude($aluno)): ?>
<div class="row g-3 mt-1">
  <div class="col-12">
    <div class="card border-0 shadow-sm rounded-4">
      <div class="card-body">
        <h5 class="mb-3"><i class="fas fa-heart-pulse text-danger"></i> Saúde e alimentação</h5>
        <?= eq_caixa_saude($aluno) ?>
        <p class="text-muted small mb-0">
          Informação registrada pela escola a partir do que a família informou.
          Aparece também na tela de chamada e no relatório anual.
        </p>
      </div>
    </div>
  </div>
</div>
<?php endif; ?>

<div class="row g-3 mt-1">
  <div class="col-md-6">
    <div class="card border-0 shadow-sm rounded-4">
      <div class="card-body">
        <h5 class="mb-3"><i class="fas fa-clipboard-user"></i> Últimos registros de chamada</h5>
        <?php if (!$ultimas): ?><p class="text-muted">Nenhum registro.</p><?php else: ?>
        <table class="table table-sm align-middle mb-0">
          <thead><tr><th>Data</th><th>Status</th><th>Lançado por</th></tr></thead>
          <tbody>
            <?php foreach ($ultimas as $c): $sl = ch_status_lista(); ?>
            <tr>
              <td><?= ch_data_br($c['DATA']) ?></td>
              <td><span class="badge bg-<?= $sl[$c['STATUS']]['cor'] ?>"><?= ch_status_rotulo($c['STATUS']) ?></span>
                <?php if ($c['JUSTIFICATIVA']): ?><br><small class="text-muted"><?= ch_h($c['JUSTIFICATIVA']) ?></small><?php endif; ?>
              </td>
              <td><small><?= ch_h($c['LANCADO_POR']) ?></small></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <div class="col-md-6">
    <div class="card border-0 shadow-sm rounded-4">
      <div class="card-body">
        <h5 class="mb-3"><i class="far fa-comment-dots"></i> Observações recentes</h5>
        <?php if (!empty($obsOcultas)): ?>
          <div class="alert alert-warning py-2 small">
            <i class="fas fa-eye-slash"></i> <?= ch_h($avisoObs) ?>
          </div>
        <?php endif; ?>
        <?php if (!$observacoes): ?>
          <p class="text-muted">
            <?= !empty($obsOcultas) ? 'Nenhuma observação sua registrada.' : 'Nenhuma observação registrada.' ?>
          </p>
        <?php else: ?>
          <?php foreach ($observacoes as $o): ?>
            <div class="border-bottom pb-2 mb-2">
              <div class="d-flex justify-content-between">
                <strong><?= ch_data_br($o['DATA']) ?></strong>
                <span class="badge bg-light text-dark"><?= ch_h($o['CATEGORIA']) ?></span>
              </div>
              <div><?= nl2br(ch_h($o['TEXTO'])) ?></div>
              <small class="text-muted">por <?= ch_h($o['AUTOR']) ?></small>
            </div>
          <?php endforeach; ?>
          <a href="<?= ch_url('observacoes/index.php?aluno_id=' . (int)$id) ?>" class="btn btn-sm btn-outline-secondary">Ver todas</a>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<?php if (count($vinculos) > 1): ?>
<div class="card border-0 shadow-sm rounded-4 mt-3">
  <div class="card-body">
    <?php $tituloQuadro = 'Escolas e turmas por período'; include(ABSPATH . 'transferencias/quadro_vinculos.php'); ?>
  </div>
</div>
<?php endif; ?>

<?php if ($historicoTransferencias): ?>
<div class="card border-0 shadow-sm rounded-4 mt-3">
  <div class="card-body">
    <?php $historico = $historicoTransferencias; include(ABSPATH . 'transferencias/quadro_historico.php'); ?>
  </div>
</div>
<?php endif; ?>

<?php include(FOOTER_TEMPLATE); ?>
