<?php require_once('processa/view_processa.php'); ?>
<?php include(ch_gestao_alunos() ? HEADER_TEMPLATE : HEADER_TEMPLATE2); ?>

<?php
$botoesAluno = '<a class="btn btn-outline-secondary" href="index.php"><i class="fas fa-arrow-left"></i> Voltar</a> ';
if (ch_gestao_alunos() && !$apenasHistorico) {
    $botoesAluno .= '<a class="btn btn-success" href="edit.php?id=' . (int)$id . '"><i class="far fa-edit"></i> Editar</a> ';
}
$botoesAluno .= '<a class="btn btn-outline-primary" target="_blank" rel="noopener" href="cracha.php?id=' . (int)$id . '"><i class="fas fa-id-card"></i> Gerar crachá</a> ';
if (ch_gestao_alunos() && !$apenasHistorico && (int)$aluno['ATIVO'] === 1 && !$transferenciaPendente) {
    $botoesAluno .= '<a class="btn btn-outline-warning" href="' . ch_url('alunos/saida.php?id=' . (int)$id) . '"><i class="fas fa-right-from-bracket"></i> Registrar saída</a> ';
}
if ($podeTransferir) {
    $botoesAluno .= '<a class="btn btn-outline-primary" href="' . ch_url('transferencias/solicitar.php?aluno_id=' . (int)$id) . '"><i class="fas fa-right-left"></i> Transferir</a>';
}
if (ch_gestor() && !$apenasHistorico && (int)$aluno['ATIVO'] === 1
    && !$transferenciaPendente && !$saidaExternaAberta) {
    $botoesAluno .= ' <a class="btn btn-outline-success" href="' . ch_url('promocoes/aluno.php?aluno_id=' . (int)$id) . '"><i class="fas fa-arrow-up"></i> Promover ou reter</a>';
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
<?php if ($saidaExternaAberta): ?>
  <div class="alert alert-secondary">
    <i class="fas fa-right-from-bracket"></i>
    Cadastro com saída da rede em <?= ch_data_br($saidaExternaAberta['DATA_SAIDA_ISO']) ?> para
    <b><?= ch_h($saidaExternaAberta['ESCOLA_DESTINO']) ?></b>,
    <?= ch_h($saidaExternaAberta['MUNICIPIO_DESTINO']) ?>/<?= ch_h($saidaExternaAberta['UF_DESTINO']) ?>.
    Para matricular novamente, use <b>Alunos → Retorno à rede</b> e localize pelo CPF.
  </div>
<?php endif; ?>

<div class="row g-3">
  <div class="col-md-7">
    <div class="card border-0 shadow-sm rounded-4">
      <div class="card-body">
        <div class="d-flex align-items-start gap-3 mb-3">
          <?php if ($fotoSrc = aluno_foto_src($aluno['TEM_FOTO'] ?? false, $id)): ?>
            <img src="<?= ch_h($fotoSrc) ?>" alt="Foto de <?= ch_h($aluno['NOME']) ?>"
                 style="width:92px;height:112px;object-fit:cover;border-radius:12px">
          <?php endif; ?>
          <h5 class="mb-0"><i class="fas fa-id-card"></i> Dados do aluno</h5>
        </div>
        <dl class="row mb-0">
          <dt class="col-sm-4">Matrícula</dt><dd class="col-sm-8"><?= ch_h($aluno['MATRICULA']) ?></dd>
          <?php if (ch_gestao_alunos()): ?>
            <dt class="col-sm-4">CPF</dt><dd class="col-sm-8"><?= !empty($aluno['CPF']) ? ch_h(ch_cpf_mask($aluno['CPF'])) : '<span class="text-muted">Não informado</span>' ?></dd>
          <?php endif; ?>
          <dt class="col-sm-4">Nascimento</dt><dd class="col-sm-8"><?= ch_data_br($aluno['NASCIMENTO']) ?> <span class="text-muted">(<?= ch_h(ch_idade_texto($aluno['NASCIMENTO'])) ?>)</span></dd>
          <dt class="col-sm-4">Matriculado em</dt><dd class="col-sm-8"><?= ch_data_br($aluno['DATA_MATRICULA']) ?></dd>
          <dt class="col-sm-4">Responsável</dt><dd class="col-sm-8"><?= ch_h($aluno['RESPONSAVEL']) ?></dd>
          <dt class="col-sm-4">Telefone</dt><dd class="col-sm-8"><?= ch_h($aluno['TELEFONE']) ?></dd>
          <dt class="col-sm-4">E-mail</dt><dd class="col-sm-8"><?= $aluno['EMAIL'] ? ch_h($aluno['EMAIL']) : '<span class="text-muted">—</span>' ?></dd>
          <dt class="col-sm-4">Unidade</dt><dd class="col-sm-8"><?= ch_h($aluno['UNIDADE']) ?></dd>
          <dt class="col-sm-4">Turma</dt><dd class="col-sm-8"><?= ch_h(ch_series_ensino()[$aluno['SERIE'] ?? '']['rotulo'] ?? 'Série não classificada') ?> · <?= ch_h($aluno['TURMA']) ?> — <?= ch_h($aluno['PERIODO']) ?> (<?= (int)$aluno['ANO_LETIVO'] ?>)</dd>
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
          <?php if (ch_pode_escrever_observacao_aluno($id)): ?>
            <a class="btn btn-primary" href="<?= ch_url('observacoes/add.php?aluno_id=' . (int)$id) ?>"><i class="far fa-comment-dots"></i> Registrar observação</a>
          <?php endif; ?>
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
            <?= ch_secretario_escolar() && !ch_pode_ler_observacao_aluno($id)
              ? 'A Direção não autorizou a consulta de observações nesta unidade.'
              : (!empty($obsOcultas) ? 'Nenhuma observação sua registrada.' : 'Nenhuma observação registrada.') ?>
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

<?php if ($saidasExternas): ?>
<div class="card border-0 shadow-sm rounded-4 mt-3">
  <div class="card-body">
    <h5 class="mb-3"><i class="fas fa-right-left"></i> Saídas e retornos de fora da rede</h5>
    <?php foreach ($saidasExternas as $saida): ?>
      <div class="border rounded-3 p-3 mb-2">
        <p class="mb-1">
          Saiu em <b><?= ch_data_br($saida['DATA_SAIDA_ISO']) ?></b> de
          <?= ch_h($saida['UNIDADE_ORIGEM']) ?> (<?= ch_h($saida['TURMA_ORIGEM']) ?>)
          para <b><?= ch_h($saida['ESCOLA_DESTINO']) ?></b>,
          <?= ch_h($saida['MUNICIPIO_DESTINO']) ?>/<?= ch_h($saida['UF_DESTINO']) ?>.
        </p>
        <p class="mb-1"><small><b>Motivo:</b> <?= ch_h($saida['MOTIVO']) ?></small></p>
        <?php if ($saida['DATA_RETORNO']): ?>
          <p class="mb-0 text-muted"><small>
            Retornou em <?= ch_data_br($saida['DATA_RETORNO']) ?>
            para <?= ch_h($saida['UNIDADE_RETORNO']) ?> (<?= ch_h($saida['TURMA_RETORNO']) ?>).
          </small></p>
        <?php else: ?>
          <p class="mb-0 text-muted"><small>Ainda não retornou à rede.</small></p>
        <?php endif; ?>
      </div>
    <?php endforeach; ?>
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

<?php if ($historicoPromocoes): ?>
<div class="card border-0 shadow-sm rounded-4 mt-3">
  <div class="card-body">
    <h5 class="mb-3"><i class="fas fa-arrow-up"></i> Histórico de promoções</h5>
    <?php foreach ($historicoPromocoes as $promocao): ?>
      <div class="border-bottom pb-2 mb-2">
        <strong><?= ch_h(ch_series_ensino()[$promocao['SERIE_ORIGEM'] ?? '']['rotulo'] ?? '') ?> · <?= ch_h($promocao['TURMA_ORIGEM']) ?> (<?= (int)$promocao['ANO_ORIGEM'] ?>)</strong>
        <i class="fas fa-arrow-right mx-1"></i>
        <strong><?= ch_h(ch_series_ensino()[$promocao['SERIE_DESTINO'] ?? '']['rotulo'] ?? '') ?> · <?= ch_h($promocao['TURMA_DESTINO']) ?> (<?= (int)$promocao['ANO_DESTINO'] ?>)</strong>
        <div class="small text-muted">
          <?= ch_h(ch_resultado_escolar_rotulo($promocao['RESULTADO'])) ?> · vigência em <?= ch_data_br($promocao['DATA_INICIO']) ?> · <?= ch_h($promocao['UNIDADE']) ?>
          <?php if ($promocao['MOTIVO_RESULTADO']): ?> · retenção por <?= ch_h(ch_motivo_retencao_rotulo($promocao['MOTIVO_RESULTADO'])) ?><?php endif; ?>
          · registrada por <?= ch_h($promocao['REGISTRADA_POR']) ?> em <?= ch_h($promocao['REGISTRADA_EM']) ?>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
</div>
<?php endif; ?>

<?php include(FOOTER_TEMPLATE); ?>
