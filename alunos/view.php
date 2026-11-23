<?php require_once('processa/view_processa.php'); ?>
<?php include(ch_gestao_alunos() ? HEADER_TEMPLATE : HEADER_TEMPLATE2); ?>

<?php
$botoesTopo = ['<a class="btn btn-outline-secondary" href="index.php"><i class="fas fa-arrow-left"></i> Voltar</a>'];
$botoesTopo[] = '<a class="btn btn-outline-primary" target="_blank" rel="noopener" href="cracha.php?id=' . (int)$id . '"><i class="fas fa-id-card"></i> Crachá frente e verso</a>';
if (ch_gestao_alunos() && !$apenasHistorico) {
    $botoesTopo[] = '<a class="btn btn-success" href="edit.php?id=' . (int)$id . '"><i class="far fa-edit"></i> Editar</a>';
}
$botoesAluno = '<div class="d-inline-flex flex-wrap justify-content-sm-end gap-2">' . implode('', $botoesTopo) . '</div>';
ch_cabecalho($aluno['NOME'], 'fa-child-reaching', $botoesAluno);

$acoesMovimentacao = [];
if ($podeTransferir) {
    $acoesMovimentacao[] = '<a class="btn btn-sm btn-outline-success" href="' . ch_url('transferencias/trocar_turma.php?aluno_id=' . (int)$id) . '"><i class="fas fa-people-arrows"></i> Trocar de turma</a>';
    $acoesMovimentacao[] = '<a class="btn btn-sm btn-outline-primary" href="' . ch_url('transferencias/solicitar.php?aluno_id=' . (int)$id) . '"><i class="fas fa-right-left"></i> Transferir de escola</a>';
}
if (ch_gestor() && !$apenasHistorico && (int)$aluno['ATIVO'] === 1
    && !$transferenciaPendente && !$saidaExternaAberta) {
    $acoesMovimentacao[] = '<a class="btn btn-sm btn-outline-success" href="' . ch_url('promocoes/aluno.php?aluno_id=' . (int)$id) . '"><i class="fas fa-arrow-up"></i> Promover ou reter</a>';
}
if (ch_gestao_alunos() && !$apenasHistorico && (int)$aluno['ATIVO'] === 1 && !$transferenciaPendente) {
    $acoesMovimentacao[] = '<a class="btn btn-sm btn-outline-warning" href="' . ch_url('alunos/saida.php?id=' . (int)$id) . '"><i class="fas fa-right-from-bracket"></i> Registrar saída</a>';
}
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

<style>
  @media (min-width: 992px) {
    .acoes-divisor-lg { border-left: 1px solid var(--borda, #dee2e6); padding-left: 1.5rem; }
  }
  @media (max-width: 991.98px) {
    .acoes-divisor-lg { border-top: 1px solid var(--borda, #dee2e6); padding-top: .75rem; }
  }
</style>
<div class="card border-0 shadow-sm rounded-4 mb-3">
  <div class="card-body py-3">
    <div class="row g-3 align-items-start">
      <div class="<?= $acoesMovimentacao ? 'col-lg-5' : 'col-12' ?>">
        <div class="text-uppercase text-muted fw-bold mb-2" style="font-size:.72rem;letter-spacing:.04em">
          <i class="fas fa-book-open-reader me-1"></i> Acompanhamento pedagógico
        </div>
        <div class="d-flex flex-wrap gap-2">
          <?php if (ch_pode_escrever_observacao_aluno($id)): ?>
            <a class="btn btn-sm btn-primary" href="<?= ch_url('observacoes/add.php?aluno_id=' . (int)$id) ?>"><i class="far fa-comment-dots"></i> Registrar observação</a>
          <?php endif; ?>
          <a class="btn btn-sm btn-outline-primary" href="<?= ch_url('relatorios/ver.php?aluno_id=' . (int)$id . '&ano=' . (int)$aluno['ANO_LETIVO'] . '&periodo=' . ch_periodo_atual()) ?>"><i class="fas fa-file-lines"></i> Relatório de desenvolvimento</a>
        </div>
      </div>
      <?php if ($acoesMovimentacao): ?>
        <div class="col-lg-7 acoes-divisor-lg">
          <div class="text-uppercase text-muted fw-bold mb-2" style="font-size:.72rem;letter-spacing:.04em">
            <i class="fas fa-route me-1"></i> Movimentação escolar
          </div>
          <div class="d-flex flex-wrap gap-2">
            <?= implode('', $acoesMovimentacao) ?>
          </div>
        </div>
      <?php endif; ?>
    </div>
  </div>
</div>

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
      <div class="card-body d-flex flex-column justify-content-between">
        <div>
          <h5 class="mb-3"><i class="fas fa-chart-pie"></i> Frequência em <?= (int)$aluno['ANO_LETIVO'] ?></h5>
          <?php if ($frequencia === null): ?>
            <p class="text-muted mb-0">Ainda não há chamada lançada para este aluno no ano letivo.</p>
          <?php else: ?>
            <div class="d-flex align-items-baseline gap-2 mb-1">
              <h2 class="mb-0"><?= number_format($frequencia, 1, ',', '.') ?>%</h2>
              <span class="text-muted small">(<?= $presencas ?> presenças em <?= $dias ?> dias)</span>
            </div>
            <div class="progress rounded-pill mb-3" style="height: 10px;">
              <div class="progress-bar bg-<?= $frequencia >= 75 ? 'success' : 'warning' ?>" role="progressbar"
                   style="width: <?= max(0, min(100, (float)$frequencia)) ?>%"
                   aria-valuenow="<?= (int)round($frequencia) ?>" aria-valuemin="0" aria-valuemax="100"></div>
            </div>
            <div class="d-flex gap-2 flex-wrap">
              <span class="badge bg-success">Presente: <?= (int)$resumo['P'] ?></span>
              <span class="badge bg-info">Atraso: <?= (int)$resumo['A'] ?></span>
              <span class="badge bg-danger">Falta: <?= (int)$resumo['F'] ?></span>
              <span class="badge bg-warning text-dark">Justificada: <?= (int)$resumo['J'] ?></span>
            </div>
          <?php endif; ?>
        </div>
        <p class="text-muted small mb-0 mt-3">
          <i class="fas fa-circle-info me-1"></i>
          Para frequência detalhada por bimestre ou semestre, abra o <b>Relatório de desenvolvimento</b>.
        </p>
      </div>
    </div>
  </div>
</div>

<?php if (!empty($responsaveisFamilia) || !empty($irmaosRede)): ?>
<div class="row g-3 mt-1">
  <div class="col-12">
    <div class="card border-0 shadow-sm rounded-4">
      <div class="card-body">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
          <h5 class="mb-0"><i class="fas fa-people-roof text-success"></i> Família / Responsáveis</h5>
          <?php if (ch_gestao_alunos()): ?>
            <a class="btn btn-sm btn-outline-secondary" href="<?= ch_url('familias/index.php') ?>"><i class="fas fa-magnifying-glass me-1"></i> Buscar família</a>
          <?php endif; ?>
        </div>
        <?php if (!empty($responsaveisFamilia)): ?>
          <div class="row g-3">
            <?php foreach ($responsaveisFamilia as $fr): ?>
              <div class="col-md-4">
                <div class="border rounded-3 p-3 h-100 bg-light bg-opacity-50">
                  <div class="d-flex flex-wrap justify-content-between align-items-center gap-1 mb-1">
                    <span class="badge bg-success"><?= ch_h(fam_parentesco_rotulo($fr['PARENTESCO'], $fr['DESCRICAO_OUTRO'])) ?></span>
                    <?php if (!empty($fr['CONTATO_PRINCIPAL'])): ?>
                      <span class="badge bg-warning text-dark"><i class="fas fa-star me-1"></i> Contato principal</span>
                    <?php endif; ?>
                  </div>
                  <div class="fw-bold"><?= ch_h($fr['NOME']) ?></div>
                  <?php if (ch_gestao_alunos() && !empty($fr['CPF'])): ?>
                    <div class="small text-muted">CPF: <?= ch_h(ch_cpf_mask($fr['CPF'])) ?></div>
                  <?php endif; ?>
                  <?php if (!empty($fr['TELEFONE'])): ?>
                    <div class="small"><i class="fas fa-phone me-1"></i><?= ch_h($fr['TELEFONE']) ?></div>
                  <?php endif; ?>
                  <?php if (!empty($fr['EMAIL'])): ?>
                    <div class="small text-muted"><i class="fas fa-envelope me-1"></i><?= ch_h($fr['EMAIL']) ?></div>
                  <?php endif; ?>
                  <?php if (ch_gestao_alunos() && !empty($fr['CPF'])): ?>
                    <a class="small" href="<?= ch_url('familias/index.php?q=' . rawurlencode((string)$fr['CPF'])) ?>">Ver todos os filhos</a>
                  <?php endif; ?>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
        <?php if (!empty($irmaosRede)): ?>
          <div class="mt-3">
            <div class="small fw-bold text-muted text-uppercase mb-2" style="font-size:.72rem;letter-spacing:.04em">
              <i class="fas fa-children me-1"></i> Irmãos / crianças com os mesmos responsáveis na rede
            </div>
            <div class="d-flex flex-wrap gap-2">
              <?php foreach ($irmaosRede as $irm): ?>
                <a class="btn btn-sm btn-outline-primary"
                   href="<?= ch_url('alunos/view.php?id=' . (int)$irm['ID']) ?>">
                  <?= ch_h($irm['NOME']) ?>
                  <small class="text-muted">· <?= ch_h(ch_series_ensino()[$irm['SERIE'] ?? '']['rotulo'] ?? 'Série') ?> <?= ch_h($irm['TURMA']) ?> (<?= ch_h($irm['UNIDADE']) ?>)</small>
                  <?php if ((int)$irm['ATIVO'] !== 1): ?><span class="badge bg-secondary">Inativo</span><?php endif; ?>
                </a>
              <?php endforeach; ?>
            </div>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>
<?php endif; ?>

<?php $categoriasInclusao = aluno_inclusao_codigos($aluno['DEFICIENCIAS_INCLUSAO'] ?? []); ?>
<?php if ($categoriasInclusao): ?>
<div class="row g-3 mt-1">
  <div class="col-12">
    <div class="card border-0 shadow-sm rounded-4">
      <div class="card-body">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
          <h5 class="mb-0"><i class="fas fa-universal-access text-primary"></i> Necessidades de inclusão</h5>
          <span class="badge bg-light text-secondary border"><i class="fas fa-lock me-1"></i> Uso interno na ficha</span>
        </div>
        <ul class="mb-2">
          <?php $opcoesInclusao = aluno_inclusao_opcoes(); ?>
          <?php foreach ($categoriasInclusao as $codigoInclusao): ?>
            <?php if (isset($opcoesInclusao[$codigoInclusao])): ?>
              <li><?= ch_h($opcoesInclusao[$codigoInclusao]) ?></li>
            <?php endif; ?>
          <?php endforeach; ?>
        </ul>
        <div class="small text-muted">Marcador na frente do crachá: <?= (int)($aluno['AUTORIZA_MARCADOR_INCLUSAO'] ?? 0) === 1 ? 'autorizado' : 'não autorizado' ?></div>
        <?php if (in_array('TEA', $categoriasInclusao, true)): ?>
          <div class="small text-muted">Símbolo escolhido para TEA: <?= ($aluno['SIMBOLO_TEA_INCLUSAO'] ?? 'INFINITO') === 'QUEBRA_CABECA' ? 'quebra-cabeça' : 'infinito colorido' ?></div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>
<?php endif; ?>

<?php if (ch_tem_acompanhamento_especial($aluno)): ?>
<div class="row g-3 mt-1">
  <div class="col-12">
    <div class="card border-0 shadow-sm rounded-4">
      <div class="card-body">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
          <h5 class="mb-0"><i class="fas fa-puzzle-piece text-primary"></i> Educação Especial, AEE e acompanhamentos</h5>
          <span class="badge bg-light text-secondary border"><i class="fas fa-lock me-1"></i> Uso interno na ficha</span>
        </div>
        <div class="row g-3">
          <?php if (trim((string)($aluno['CONDICAO_ESPECIFICA'] ?? '')) !== ''): ?>
            <div class="col-md-4">
              <div class="border rounded-3 p-3 h-100 bg-light bg-opacity-50">
                <div class="small fw-bold text-primary mb-1"><i class="fas fa-notes-medical me-1"></i> Condição / necessidade específica</div>
                <div><?= nl2br(ch_h($aluno['CONDICAO_ESPECIFICA'])) ?></div>
              </div>
            </div>
          <?php endif; ?>
          <?php if ((int)($aluno['PASSA_AEE'] ?? 0) === 1): ?>
            <div class="col-md-4">
              <div class="border rounded-3 p-3 h-100 bg-light bg-opacity-50">
                <div class="small fw-bold text-success mb-1"><i class="fas fa-chalkboard-user me-1"></i> Atendimento Educacional Especializado (AEE)</div>
                <div><span class="badge bg-success mb-1">Em atendimento no AEE</span></div>
                <div class="small mt-1">
                  <strong>Professor(a):</strong>
                  <?= !empty($aluno['PROFESSOR_AEE_NOME'])
                        ? ch_h($aluno['PROFESSOR_AEE_NOME'])
                        : '<span class="text-muted">Não vinculado no sistema</span>' ?>
                </div>
                <?php if (trim((string)($aluno['AEE_OBSERVACOES'] ?? '')) !== ''): ?>
                  <div class="small text-muted mt-1"><i class="fas fa-clock me-1"></i><?= ch_h($aluno['AEE_OBSERVACOES']) ?></div>
                <?php endif; ?>
              </div>
            </div>
          <?php endif; ?>
          <?php if (trim((string)($aluno['OUTROS_ACOMPANHAMENTOS'] ?? '')) !== ''): ?>
            <div class="col-md-4">
              <div class="border rounded-3 p-3 h-100 bg-light bg-opacity-50">
                <div class="small fw-bold text-info mb-1"><i class="fas fa-user-doctor me-1"></i> Outros acompanhamentos</div>
                <div><?= nl2br(ch_h($aluno['OUTROS_ACOMPANHAMENTOS'])) ?></div>
              </div>
            </div>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>
</div>
<?php endif; ?>

<?php if (eq_tem_saude($aluno)): ?>
<div class="row g-3 mt-1">
  <div class="col-12">
    <div class="card border-0 shadow-sm rounded-4">
      <div class="card-body">
        <h5 class="mb-3"><i class="fas fa-heart-pulse text-danger"></i> Saúde e alimentação</h5>
        <?= eq_caixa_saude($aluno) ?>
        <p class="text-muted small mb-0">
          Informação registrada pela escola a partir do que a família informou.
          Aparece também na tela de chamada e no relatório de desenvolvimento.
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
              <div class="d-flex justify-content-between align-items-center">
                <strong><?= ch_data_br($o['DATA']) ?></strong>
                <span class="d-flex gap-1 align-items-center">
                  <?php if (!empty($o['RESTRITA_AEE'])): ?>
                    <span class="badge bg-dark" title="Visível somente para o(a) professor(a) de AEE"><i class="fas fa-lock"></i> Só AEE</span>
                  <?php endif; ?>
                  <span class="badge bg-light text-dark"><?= ch_h($o['CATEGORIA']) ?></span>
                </span>
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
