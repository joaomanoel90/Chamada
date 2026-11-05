<?php require_once('processa/ver_processa.php'); ?>
<?php include(ch_gestor() ? HEADER_TEMPLATE : HEADER_TEMPLATE2); ?>

<?php ch_cabecalho('Relatório Anual ' . $ano, 'fa-file-lines',
    '<a class="btn btn-outline-secondary" href="index.php?ano=' . (int)$ano . '"><i class="fas fa-arrow-left"></i> Voltar</a> ' .
    '<a class="btn btn-outline-primary" href="exportar.php?aluno_id=' . (int)$alunoId . '&ano=' . (int)$ano . '"><i class="fas fa-file-csv"></i> CSV</a> ' .
    '<button class="btn btn-primary" onclick="window.print()"><i class="fas fa-print"></i> Imprimir</button>'); ?>

<style>
  @media print {
    .sidebar, .toggle-btn, .footer-custom, .no-print, header .btn, .toast-container { display: none !important; }
    .content { margin: 0 !important; padding: 0 !important; }
    body { background: #fff !important; padding: 0 !important; }
    .card { box-shadow: none !important; border: 1px solid #ccc !important; }
    .relatorio-print { display: block !important; }
  }
  .relatorio-print { display: none; }
</style>

<div class="relatorio-print text-center mb-3">
  <h4>Prefeitura Municipal de Angatuba</h4>
  <h5>Relatório Anual de Desenvolvimento — <?= (int)$ano ?></h5>
  <hr>
</div>

<div class="card border-0 shadow-sm rounded-4 mb-3">
  <div class="card-body">
    <div class="row">
      <div class="col-md-8">
        <h4 class="mb-1"><?= ch_h($aluno['NOME']) ?></h4>
        <p class="text-muted mb-2">
          Matrícula <?= ch_h($aluno['MATRICULA']) ?> &middot;
          Nascimento <?= ch_data_br($aluno['NASCIMENTO']) ?> (<?= ch_h(ch_idade_texto($aluno['NASCIMENTO'], $ano . '-12-31')) ?> ao fim do ano)
        </p>
        <dl class="row mb-0">
          <dt class="col-sm-3">Unidade</dt><dd class="col-sm-9"><?= ch_h($aluno['UNIDADE']) ?></dd>
          <dt class="col-sm-3">Turma</dt><dd class="col-sm-9"><?= ch_h($aluno['TURMA']) ?> — <?= ch_h($aluno['PERIODO']) ?></dd>
          <dt class="col-sm-3">Equipe da turma</dt><dd class="col-sm-9"><?= $professor ? ch_h($professor) : 'sem equipe definida' ?></dd>
          <dt class="col-sm-3">Responsável</dt><dd class="col-sm-9"><?= ch_h($aluno['RESPONSAVEL']) ?> &middot; <?= ch_h($aluno['TELEFONE']) ?></dd>
        </dl>
      </div>
      <div class="col-md-4 text-md-end">
        <?php if ($frequencia === null): ?>
          <p class="text-muted">Sem chamadas lançadas em <?= (int)$ano ?>.</p>
        <?php else: ?>
          <div class="text-muted">Frequência no ano</div>
          <h1 class="mb-0"><?= number_format($frequencia, 1, ',', '.') ?>%</h1>
          <small class="text-muted"><?= $presencas ?> presenças em <?= $dias ?> dias lançados</small>
          <div class="mt-2">
            <span class="badge bg-success">P <?= (int)$totais['P'] ?></span>
            <span class="badge bg-info">A <?= (int)$totais['A'] ?></span>
            <span class="badge bg-danger">F <?= (int)$totais['F'] ?></span>
            <span class="badge bg-warning text-dark">J <?= (int)$totais['J'] ?></span>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<div class="row g-3">
  <div class="col-md-7">
    <div class="card border-0 shadow-sm rounded-4 h-100">
      <div class="card-body">
        <h5 class="mb-3"><i class="fas fa-calendar-days"></i> Frequência mês a mês</h5>
        <?php if (!$meses): ?>
          <p class="text-muted">Nenhum mês com chamada lançada.</p>
        <?php else: ?>
        <table class="table table-sm align-middle">
          <thead><tr><th>Mês</th><th class="text-center">Dias</th><th class="text-center">P</th><th class="text-center">A</th><th class="text-center">F</th><th class="text-center">J</th><th class="text-center">%</th></tr></thead>
          <tbody>
            <?php foreach ($meses as $n => $m):
              $d = (int)$m['DIAS']; $p = (int)$m['P'] + (int)$m['A'];
              $pc = $d > 0 ? round($p * 100 / $d, 1) : 0; ?>
            <tr>
              <td><?= ch_mes_nome($n) ?></td>
              <td class="text-center"><?= $d ?></td>
              <td class="text-center"><?= (int)$m['P'] ?></td>
              <td class="text-center"><?= (int)$m['A'] ?></td>
              <td class="text-center"><?= (int)$m['F'] ?></td>
              <td class="text-center"><?= (int)$m['J'] ?></td>
              <td class="text-center"><strong><?= number_format($pc, 1, ',', '.') ?>%</strong></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <div class="col-md-5">
    <div class="card border-0 shadow-sm rounded-4 h-100">
      <div class="card-body">
        <h5 class="mb-3"><i class="fas fa-tags"></i> Observações por área</h5>
        <?php if (!$porCategoria): ?>
          <p class="text-muted">Nenhuma observação registrada neste ano.</p>
        <?php else: ?>
          <?php foreach ($porCategoria as $cat => $qt): ?>
            <div class="d-flex justify-content-between border-bottom py-1">
              <span><?= ch_h($cat) ?></span><strong><?= (int)$qt ?></strong>
            </div>
          <?php endforeach; ?>
          <p class="mt-2 mb-0"><strong>Total: <?= count($observacoes) ?></strong> observações</p>
        <?php endif; ?>

        <?php if ($faltasDetalhadas): ?>
          <h6 class="mt-4">Faltas no ano (<?= count($faltasDetalhadas) ?>)</h6>
          <div style="max-height:180px;overflow:auto">
            <table class="table table-sm mb-0">
              <tbody>
              <?php foreach ($faltasDetalhadas as $f): ?>
                <tr>
                  <td><?= ch_data_br($f['DATA']) ?></td>
                  <td><span class="badge bg-<?= $f['STATUS'] === 'F' ? 'danger' : 'warning text-dark' ?>"><?= ch_status_rotulo($f['STATUS']) ?></span></td>
                  <td><small><?= ch_h($f['JUSTIFICATIVA']) ?></small></td>
                </tr>
              <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<div class="card border-0 shadow-sm rounded-4 mt-3">
  <div class="card-body">
    <h5 class="mb-3"><i class="far fa-comment-dots"></i> Observações do desenvolvimento (<?= count($observacoes) ?>)</h5>
    <?php if (!$observacoes): ?>
      <p class="text-muted">Nenhuma observação registrada em <?= (int)$ano ?>.</p>
    <?php else: ?>
      <?php foreach ($observacoes as $o): ?>
        <div class="border-start border-3 border-primary ps-3 mb-3">
          <div class="d-flex justify-content-between">
            <strong><?= ch_data_br($o['DATA']) ?></strong>
            <span class="badge bg-light text-dark"><?= ch_h($o['CATEGORIA']) ?></span>
          </div>
          <div><?= nl2br(ch_h($o['TEXTO'])) ?></div>
          <small class="text-muted">Prof. <?= ch_h($o['AUTOR']) ?></small>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>
</div>

<div class="card border-0 shadow-sm rounded-4 mt-3">
  <div class="card-body">
    <h5 class="mb-3"><i class="fas fa-pen-to-square"></i> Parecer final e encaminhamentos</h5>
    <form method="post" action="salvar.php">
      <input type="hidden" name="aluno_id" value="<?= (int)$alunoId ?>">
      <input type="hidden" name="ano" value="<?= (int)$ano ?>">

      <div class="mb-3">
        <label class="fw-bold">Parecer descritivo do desenvolvimento</label>
        <textarea name="parecer" class="form-control" rows="8" maxlength="10000"
                  placeholder="Síntese dos avanços da criança no ano: linguagem, coordenação motora, socialização, autonomia, alimentação, sono..."><?= ch_h($parecer['PARECER'] ?? '') ?></textarea>
      </div>
      <div class="mb-3">
        <label class="fw-bold">Encaminhamentos e recomendações</label>
        <textarea name="encaminhamentos" class="form-control" rows="4" maxlength="5000"
                  placeholder="Orientações à família, encaminhamentos a especialistas, sugestões para o próximo ano..."><?= ch_h($parecer['ENCAMINHAMENTOS'] ?? '') ?></textarea>
      </div>

      <button type="submit" class="btn btn-success no-print"><i class="fas fa-save"></i> Salvar parecer</button>
      <?php if ($parecer): ?>
        <small class="text-muted ms-2">Parecer registrado. Ele aparece na versão impressa.</small>
      <?php endif; ?>
    </form>

    <div class="relatorio-print mt-4">
      <div class="row">
        <div class="col-6 text-center"><hr><small>Professor(a) responsável</small></div>
        <div class="col-6 text-center"><hr><small>Responsável pela criança</small></div>
      </div>
    </div>
  </div>
</div>

<?php include(FOOTER_TEMPLATE); ?>
