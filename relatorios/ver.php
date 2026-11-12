<?php require_once('processa/ver_processa.php'); ?>
<?php include(ch_gestor() ? HEADER_TEMPLATE : HEADER_TEMPLATE2); ?>

<?php ch_cabecalho('Relatório Anual ' . $ano, 'fa-file-lines',
    '<a class="btn btn-outline-secondary" href="index.php?ano=' . (int)$ano . '"><i class="fas fa-arrow-left"></i> Voltar</a> ' .
    '<a class="btn btn-outline-primary" href="exportar.php?aluno_id=' . (int)$alunoId . '&ano=' . (int)$ano . '"><i class="fas fa-file-csv"></i> CSV</a> ' .
    '<button class="btn btn-primary" onclick="window.print()"><i class="fas fa-print"></i> Imprimir</button>'); ?>

<style>
  /* ============================================================
     VERSÃO IMPRESSA DO RELATÓRIO
     ------------------------------------------------------------
     O que estava errado antes:
     1. O <body> tinha a classe no-print e o inc/tema.php esconde
        .no-print na impressão -> a folha saía INTEIRA em branco.
        Corrigido nos dois headers.
     2. O parecer e os encaminhamentos só existiam dentro de
        <textarea>, que imprime só as linhas visíveis e corta o
        resto. Agora há uma versão em texto, só de impressão.
     3. A lista de faltas tinha max-height:180px com rolagem; no
        papel saía cortada. A rolagem é desfeita na impressão.
     4. Os selos (P/A/F/J) usam texto branco sobre fundo colorido.
        Sem "imprimir plano de fundo" o texto sumia. Na impressão
        viram contorno com texto preto.
     ============================================================ */
  .relatorio-print { display: none; }
  .lista-rolavel { max-height: 180px; overflow: auto; }

  @media print {
    @page { size: A4 portrait; margin: 14mm 14mm 15mm; }

    /* nada de interface no papel */
    .sidebar, .toggle-btn, #sidebarToggle, .footer-custom, .toast-container,
    .no-print, header .btn, .alert, .dt-buttons,
    form[action="salvar.php"] { display: none !important; }

    html, body { background: #fff !important; }
    body { font-size: 10.5pt !important; color: #000 !important; }
    .content { margin: 0 !important; padding: 0 !important; width: 100% !important; max-width: none !important; }
    .container, .container-fluid { width: 100% !important; max-width: none !important; padding: 0 !important; margin: 0 !important; }

    /* o cabeçalho da tela vira o título do documento, sem ícone */
    header h2 { font-size: 14pt !important; margin: 0 !important; }
    header h2 i, h5 i, h6 i { display: none !important; }
    hr { border-color: #999 !important; opacity: 1 !important; }

    .relatorio-print { display: block !important; }
    /* <img> é conteúdo, não gráfico de fundo: sai mesmo com a opção
       "gráficos de fundo" desligada no diálogo de impressão. */
    .brasao-print { display: inline-block !important; width: 54px; height: auto; margin-bottom: 4pt; }

    /* o título da tela é redundante com o timbre do documento */
    header, header + hr { display: none !important; }

    /* cartões sem sombra e nunca partidos ao meio.
       O height:auto é essencial: os cartões usam .h-100 do Bootstrap, que na
       impressão fixava a altura e CORTAVA o conteúdo — o texto continuava no
       PDF, mas fora da moldura, invisível. */
    .card {
      box-shadow: none !important; border: 1px solid #bbb !important; border-radius: 0 !important;
      break-inside: avoid; page-break-inside: avoid; margin-bottom: 8pt !important;
      height: auto !important; min-height: 0 !important;
    }
    .card.h-100 { height: auto !important; }

    /* A saúde do aluno é conteúdo do relatório, não aviso de interface.
       Moldura preta e texto preto: sai legível mesmo sem "gráficos de fundo". */
    .eq-saude-ficha {
      border: 1.2pt solid #000 !important; background: #fff !important;
      padding: 6pt 8pt !important; margin-top: 6pt !important;
      break-inside: avoid; page-break-inside: avoid;
    }
    .eq-saude-ficha-titulo {
      font-weight: 700 !important; font-size: 10.5pt !important;
      text-transform: uppercase; margin-bottom: 3pt !important; color: #000 !important;
    }
    .eq-saude-ficha-item { font-size: 10pt !important; color: #000 !important; margin-bottom: 2pt !important; }

    /* Os dois cartões-resumo empilham, cada um em toda a largura da folha.
       Tentar mantê-los lado a lado não funciona: container flex não fragmenta
       entre páginas e o cartão mais alto era cortado na borda do papel, além de
       ficar estreito demais para a justificativa das faltas. */
    .linha-resumo { display: block !important; }
    .linha-resumo > [class*="col-"] {
      width: 100% !important; max-width: 100% !important; flex: none !important;
    }

    /* Cartões que podem crescer muito (lista de observações, parecer) precisam
       poder continuar na página seguinte. Se ficassem indivisíveis, um ano com
       muitas observações abriria uma página nova e ainda transbordaria.
       Cada observação individual continua indivisível. */
    .cartao-longo { break-inside: auto !important; page-break-inside: auto !important; }
    .card-body { padding: 8pt 10pt !important; }
    h4 { font-size: 13pt !important; }
    h5, h6 { font-size: 11pt !important; break-after: avoid; page-break-after: avoid; }
    h1 { font-size: 20pt !important; }
    dl.row dt, dl.row dd { font-size: 10pt; margin-bottom: 2pt; }

    /* tabelas: cabeçalho repete a cada página, linha não se parte.
       O cabeçalho azul da tela usa texto branco; sem "imprimir plano de fundo"
       ele sairia branco no branco. No papel vira texto preto com régua embaixo. */
    table { width: 100% !important; font-size: 9.5pt !important; }
    thead { display: table-header-group; }
    .table > thead > tr { background: #fff !important; background-image: none !important; }
    .table > thead > tr > th, .table-light > thead > tr > th {
      background: #fff !important; background-image: none !important;
      color: #000 !important; --bs-table-color: #000; --bs-table-bg: #fff;
      border-bottom: 1.2pt solid #000 !important; font-weight: 700;
      white-space: normal !important;
    }
    .table > tbody > tr > td { color: #000 !important; }
    tr, .observacao-item { break-inside: avoid; page-break-inside: avoid; }
    th, td { border-color: #bbb !important; padding: 3pt 5pt !important; }

    /* selos legíveis mesmo sem impressão de fundo */
    .badge {
      background: #fff !important; color: #000 !important;
      border: 1px solid #666 !important; font-weight: 600; font-size: 8.5pt !important;
    }

    /* lista que rola na tela sai inteira no papel */
    .lista-rolavel { max-height: none !important; overflow: visible !important; height: auto !important; }

    /* parecer como texto corrido, não como caixa de formulário */
    .parecer-texto { white-space: pre-wrap; text-align: justify; line-height: 1.5; }

    .observacao-item { border-left: 2px solid #888 !important; padding-left: 8pt; margin-bottom: 8pt; }
    /* O aviso de relatório parcial tem de sair no papel: sem ele, quem recebe
       a folha impressa não tem como saber que faltam observações. */
    .aviso-obs-parcial { border: 1.2pt solid #444 !important; padding: 8pt !important;
                         margin-bottom: 10pt; break-inside: avoid; page-break-inside: avoid; }
    .assinaturas { break-inside: avoid; page-break-inside: avoid; margin-top: 20pt; }
    .assinaturas hr { border-top: 1px solid #000 !important; margin-bottom: 3pt; opacity: 1 !important; }
    .rodape-impresso { font-size: 8.5pt; color: #444 !important; margin-top: 12pt; }

    a[href]::after { content: "" !important; }   /* não imprimir a URL dos links */
  }
</style>

<?php
/* Na impressão, bloco curto tem de sair inteiro; bloco que pode passar de uma
   página precisa poder continuar, senão o navegador o corta na borda. Por isso
   .cartao-longo só entra quando o conteúdo é realmente longo. */
$classeFaltas = count($faltasDetalhadas) > 8 ? ' cartao-longo' : '';
$classeObs    = count($observacoes) > 4 ? ' cartao-longo' : '';
$textoParecer = trim((string)($parecer['PARECER'] ?? '')) . trim((string)($parecer['ENCAMINHAMENTOS'] ?? ''));
$classeParecer = mb_strlen($textoParecer) > 1200 ? ' cartao-longo' : '';
?>

<div class="relatorio-print text-center mb-3">
  <img src="<?= BASEURL ?>images/brasao.png" alt="Brasão de Angatuba" class="brasao-print">
  <h4 class="mb-0">Prefeitura Municipal de Angatuba</h4>
  <div>Secretaria Municipal de Educação</div>
  <h5 class="mt-2 mb-0">Relatório Anual de Desenvolvimento — <?= (int)$ano ?></h5>
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
          <dt class="col-sm-3">Unidade</dt><dd class="col-sm-9">
            <?= count($vinculosAno) === 1 ? ch_h($vinculosAno[0]['UNIDADE']) : ch_h($aluno['UNIDADE']) ?>
          </dd>
          <dt class="col-sm-3">Turma</dt><dd class="col-sm-9">
            <?php if (count($vinculosAno) === 1): ?>
              <?= ch_h($vinculosAno[0]['TURMA']) ?> — <?= ch_h($vinculosAno[0]['PERIODO']) ?>
            <?php elseif ($vinculosAno): ?>
              <?= count($vinculosAno) ?> vínculos no ano (quadro abaixo)
            <?php else: ?>
              <?= ch_h($aluno['TURMA']) ?> — <?= ch_h($aluno['PERIODO']) ?>
            <?php endif; ?>
          </dd>
          <dt class="col-sm-3">Equipe da turma</dt><dd class="col-sm-9"><?= $professor ? ch_h($professor) : 'sem equipe definida' ?></dd>
          <dt class="col-sm-3">Responsável</dt><dd class="col-sm-9"><?= ch_h($aluno['RESPONSAVEL']) ?> &middot; <?= ch_h($aluno['TELEFONE']) ?></dd>
        </dl>
        <?= eq_saude_ficha($aluno) ?>
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

<div class="row g-3 linha-resumo">
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
          <p class="text-muted">
            <?= ch_secretario_escolar() && !$obsLeituraPermitida
                  ? 'A Direção não autorizou a consulta de observações nesta unidade.'
                  : (!empty($obsOcultas)
                  ? 'Nenhuma observação sua registrada neste ano.'
                  : 'Nenhuma observação registrada neste ano.') ?>
          </p>
        <?php else: ?>
          <?php foreach ($porCategoria as $cat => $qt): ?>
            <div class="d-flex justify-content-between border-bottom py-1">
              <span><?= ch_h($cat) ?></span><strong><?= (int)$qt ?></strong>
            </div>
          <?php endforeach; ?>
          <p class="mt-2 mb-0"><strong>Total: <?= count($observacoes) ?></strong> observações</p>
        <?php endif; ?>

      </div>
    </div>
  </div>
</div>

<?php if ($vinculosAno): ?>
<div class="card border-0 shadow-sm rounded-4 mt-3">
  <div class="card-body">
    <?php $vinculos = $vinculosAno; $tituloQuadro = 'Escolas e turmas em ' . (int)$ano;
          include(ABSPATH . 'transferencias/quadro_vinculos.php'); ?>
    <p class="text-muted mt-2 mb-0"><small>
      Os totais deste relatório são do ano inteiro do aluno, somando os períodos acima.
    </small></p>
  </div>
</div>
<?php endif; ?>

<?php /* Cartão próprio: dentro do resumo ele deixava aquele bloco alto demais
         para caber na 1ª página, e a lista podia ser longa num ano inteiro.
         Aqui é um cartão que pode continuar na página seguinte. */ ?>
<?php if ($faltasDetalhadas): ?>
<div class="card<?= $classeFaltas ?> border-0 shadow-sm rounded-4 mt-3">
  <div class="card-body">
    <h5 class="mb-3"><i class="fas fa-calendar-xmark"></i> Faltas e faltas justificadas no ano (<?= count($faltasDetalhadas) ?>)</h5>
    <div class="lista-rolavel">
      <table class="table table-sm mb-0">
        <thead><tr><th>Data</th><th>Situação</th><th>Justificativa</th></tr></thead>
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
  </div>
</div>
<?php endif; ?>

<?php if (!empty($obsOcultas)): ?>
  <?php /* Não uso .alert: a folha de impressão esconde .alert, e quem ler o
           papel precisa saber que o relatório está parcial. */ ?>
  <div class="aviso-obs-parcial border border-2 rounded-3 p-3 mt-3">
    <strong><i class="fas fa-eye-slash no-print"></i> Relatório parcial</strong>
    <div class="small mb-0"><?= ch_h($avisoObs) ?></div>
  </div>
<?php endif; ?>

<div class="card<?= $classeObs ?> border-0 shadow-sm rounded-4 mt-3">
  <div class="card-body">
    <h5 class="mb-3"><i class="far fa-comment-dots"></i> Observações do desenvolvimento (<?= count($observacoes) ?>)</h5>
    <?php if (!$observacoes): ?>
      <p class="text-muted">
        <?= ch_secretario_escolar() && !$obsLeituraPermitida
              ? 'A Direção não autorizou a consulta de observações nesta unidade.'
              : (!empty($obsOcultas)
              ? 'Nenhuma observação sua registrada em ' . (int)$ano . '.'
              : 'Nenhuma observação registrada em ' . (int)$ano . '.') ?>
      </p>
    <?php else: ?>
      <?php foreach ($observacoes as $o): ?>
        <div class="observacao-item border-start border-3 border-primary ps-3 mb-3">
          <div class="d-flex justify-content-between">
            <strong><?= ch_data_br($o['DATA']) ?></strong>
            <span class="badge bg-light text-dark"><?= ch_h($o['CATEGORIA']) ?></span>
          </div>
          <div><?= nl2br(ch_h($o['TEXTO'])) ?></div>
          <small class="text-muted">
            Prof. <?= ch_h($o['AUTOR']) ?>
            <a class="no-print ms-2" href="<?= ch_url('observacoes/historico.php?id=' . (int)$o['ID']) ?>">Histórico de alterações</a>
          </small>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>
</div>

<div class="card<?= $classeParecer ?> border-0 shadow-sm rounded-4 mt-3">
  <div class="card-body">
    <h5 class="mb-3"><i class="fas fa-pen-to-square"></i> Parecer final e encaminhamentos</h5>
    <?php if ($parecer): ?>
      <p class="small text-muted no-print">
        Última versão salva por <?= ch_h($parecer['AUTOR']) ?>
        <?php if ($parecer['MODIFIED']): ?> em <?= ch_h($parecer['MODIFIED']) ?>
        <?php else: ?> em <?= ch_h($parecer['CRIADO_EM']) ?><?php endif; ?>.
      </p>
    <?php endif; ?>
    <?php if ($podeEditarParecer): ?>
    <form method="post" action="salvar.php">
      <?= ch_csrf_campo() ?>
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
    <?php else: ?>
      <?php if (!ch_secretario_escolar()): ?>
      <div class="alert alert-warning no-print">
        Aluno inativo após saída da rede. O relatório anual está disponível somente para consulta.
      </div>
      <?php endif; ?>
      <div class="mb-3">
        <strong>Parecer descritivo do desenvolvimento</strong>
        <p class="parecer-texto mb-0"><?= $parecer && trim((string)$parecer['PARECER']) !== '' ? nl2br(ch_h($parecer['PARECER'])) : '<span class="text-muted">Parecer ainda não preenchido.</span>' ?></p>
      </div>
      <div class="mb-3">
        <strong>Encaminhamentos e recomendações</strong>
        <p class="parecer-texto mb-0"><?= $parecer && trim((string)$parecer['ENCAMINHAMENTOS']) !== '' ? nl2br(ch_h($parecer['ENCAMINHAMENTOS'])) : '<span class="text-muted">Nenhum encaminhamento registrado.</span>' ?></p>
      </div>
    <?php endif; ?>

    <?php /* Versão impressa: o textarea imprime só as linhas visíveis e corta o
             resto do parecer. Aqui o texto sai inteiro, justificado. */ ?>
    <div class="relatorio-print">
      <h6 class="fw-bold mb-1">Parecer descritivo do desenvolvimento</h6>
      <?php if (trim((string)($parecer['PARECER'] ?? '')) !== ''): ?>
        <div class="parecer-texto"><?= ch_h($parecer['PARECER']) ?></div>
      <?php else: ?>
        <p class="fst-italic">Parecer ainda não preenchido.</p>
      <?php endif; ?>

      <h6 class="fw-bold mt-3 mb-1">Encaminhamentos e recomendações</h6>
      <?php if (trim((string)($parecer['ENCAMINHAMENTOS'] ?? '')) !== ''): ?>
        <div class="parecer-texto"><?= ch_h($parecer['ENCAMINHAMENTOS']) ?></div>
      <?php else: ?>
        <p class="fst-italic">Nenhum encaminhamento registrado.</p>
      <?php endif; ?>
    </div>
  </div>
</div>

<?php if ($historicoParecer): ?>
<div class="card border-0 shadow-sm rounded-4 mt-3 no-print">
  <div class="card-body">
    <h5 class="mb-3"><i class="fas fa-clock-rotate-left"></i> Histórico de alterações do parecer</h5>
    <?php foreach ($historicoParecer as $versao): ?>
      <details class="border-bottom py-2">
        <summary>
          Alterado em <?= ch_h($versao['ALTERADA_EM']) ?> por <?= ch_h($versao['ALTERADO_POR']) ?>
          · versão registrada por <?= ch_h($versao['AUTOR']) ?>
        </summary>
        <div class="mt-3">
          <strong>Parecer</strong>
          <p class="parecer-texto"><?= trim($versao['PARECER']) !== '' ? nl2br(ch_h($versao['PARECER'])) : '<span class="text-muted">Vazio</span>' ?></p>
          <strong>Encaminhamentos</strong>
          <p class="parecer-texto"><?= trim($versao['ENCAMINHAMENTOS']) !== '' ? nl2br(ch_h($versao['ENCAMINHAMENTOS'])) : '<span class="text-muted">Vazio</span>' ?></p>
        </div>
      </details>
    <?php endforeach; ?>
  </div>
</div>
<?php endif; ?>

<div class="relatorio-print assinaturas">
  <div class="row">
    <div class="col-6 text-center px-4"><hr><small>Professor(a) responsável</small></div>
    <div class="col-6 text-center px-4"><hr><small>Responsável pela criança</small></div>
  </div>
  <div class="rodape-impresso text-center">
    Documento emitido pelo Sistema de Chamada em <?= ch_data_br(ch_hoje()) ?>.
    <?= ch_h($aluno['NOME']) ?> — matrícula <?= ch_h($aluno['MATRICULA']) ?> — <?= (int)$ano ?>.
  </div>
</div>

<?php include(FOOTER_TEMPLATE); ?>
