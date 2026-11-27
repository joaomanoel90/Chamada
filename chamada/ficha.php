<?php require_once('processa/ficha_processa.php'); ?>
<?php include(ch_gestor() ? HEADER_TEMPLATE : HEADER_TEMPLATE2); ?>
<style nonce="<?= ch_csp_nonce() ?>">
	/* Largura fixa por coluna: a ficha cabe na largura da página e a rolagem
	   horizontal só aparece em meses com muitas datas letivas. */
	.ficha-tabela { font-size: .72rem; margin-bottom: 0; table-layout: fixed; width: 100%; min-width: 720px; }
	.ficha-tabela th, .ficha-tabela td { padding: .2rem .15rem; text-align: center; vertical-align: middle; white-space: normal; overflow-wrap: anywhere; }
	.ficha-tabela thead > tr > th { padding: .2rem .15rem; }
	.ficha-tabela thead tr:first-child > th { white-space: normal; }
	.ficha-tabela .ficha-aluno { text-align: left !important; width: 150px; position: sticky; left: 0; background: #fff; z-index: 1; }
	.ficha-tabela .ficha-sub span { display: block; }
	.ficha-tabela .ficha-sub span + span { font-size: .85em; color: #555; }
	.ficha-tabela thead .ficha-aluno { background: #e9ecef; z-index: 3; }
	.ficha-tabela tfoot .ficha-aluno { background: #f5f7f9; z-index: 3; }
	.ficha-tabela thead tr:first-child th:nth-last-child(-n+5) { width: 26px; }
	/* 100,0 precisa de mais espaço que os contadores inteiros. */
	.ficha-tabela thead tr:first-child th:last-child { width: 40px; }
	.ficha-data { font-size: .62rem; }
	.ficha-sub { font-size: .5rem; color: #66717e; font-weight: 500; line-height: 1.05; }
	.ficha-sub span { display: block; }
	.ficha-sub b { color: #3b4652; }
	.ficha-sigla { display: inline-block; min-width: 9px; padding: 0 1px; border-radius: 3px; font-weight: 700; }
	.ficha-P { color: #0f6c3b; background: #e7f6ec; }
	.ficha-F { color: #b42318; background: #fdecec; }
	.ficha-J { color: #8a5a00; background: #fff6dc; }
	.ficha-A { color: #0d5f82; background: #e7f3fa; }
	.ficha-vazio { color: #aab2bc; }
	.ficha-total { font-weight: 700; background: #f5f7f9; }
	.ficha-legenda { display: flex; flex-wrap: wrap; gap: 12px; margin-top: 10px; font-size: .78rem; color: #66717e; }
	@page { size: A4 landscape; margin: 6mm; }
	@media print {
		.no-print, .sidebar, .footer-custom { display: none !important; }
		.card, .alert { border: 0 !important; box-shadow: none !important; }
		.table-responsive { overflow: visible !important; }
		.ficha-tabela { font-size: 6.5pt; width: 100%; min-width: 0; }
		.ficha-tabela .ficha-aluno { width: 34mm; position: static; background: transparent; }
		.ficha-tabela th, .ficha-tabela td { padding: 0 1px; line-height: 1.25; }
		.ficha-sigla { min-width: 0; padding: 0; background: transparent !important; }
		.ficha-data { font-size: 6pt; }
		.ficha-sub { font-size: 5.5pt; }
		.ficha-tabela thead { display: table-header-group; }
		.ficha-tabela tfoot { display: table-footer-group; }
		.ficha-tabela tr { break-inside: avoid; }
		.ficha-legenda { font-size: 7pt; }
	}
</style>

<?php
$botaoImprimir = $turma
    ? '<button type="button" class="btn btn-outline-primary no-print" data-print><i class="fas fa-print"></i> Imprimir ficha</button>'
    : '';
ch_cabecalho('Ficha de Chamada da Turma', 'fas fa-file-lines', $botaoImprimir);
?>

<form method="get" action="ficha.php" class="row g-3 align-items-end mb-4 no-print">
  <div class="col-md-5">
    <label class="form-label small fw-bold mb-1" for="fichaTurma">Turma</label>
    <select name="turma_id" id="fichaTurma" class="form-select" required>
      <option value="">Selecione...</option>
      <?php foreach ($turmas as $t): ?>
        <option value="<?= (int)$t['ID'] ?>"<?= (int)$t['ID'] === $turmaId ? ' selected' : '' ?>>
          <?= ch_h($t['UNIDADE']) ?> — Turma <?= ch_h(ch_series_ensino()[$t['SERIE'] ?? '']['rotulo'] ?? 'não classificada') ?>
          · Sala <?= ch_h($t['NOME']) ?> · <?= ch_h($t['PERIODO']) ?> (<?= (int)$t['ANO_LETIVO'] ?>)
        </option>
      <?php endforeach; ?>
    </select>
  </div>
  <div class="col-md-2">
    <label class="form-label small fw-bold mb-1" for="fichaAno">Ano</label>
    <select name="ano" id="fichaAno" class="form-select">
      <?php for ($anoOpcao = min((int)date('Y'), $ano) - 1; $anoOpcao <= max((int)date('Y'), $ano) + 1; $anoOpcao++): ?>
        <option value="<?= $anoOpcao ?>"<?= $anoOpcao === $ano ? ' selected' : '' ?>><?= $anoOpcao ?></option>
      <?php endfor; ?>
    </select>
  </div>
  <div class="col-md-2">
    <label class="form-label small fw-bold mb-1" for="fichaMes">Mês</label>
    <select name="mes" id="fichaMes" class="form-select">
      <?php for ($mesOpcao = 1; $mesOpcao <= 12; $mesOpcao++): ?>
        <option value="<?= $mesOpcao ?>"<?= $mesOpcao === $mes ? ' selected' : '' ?>><?= ch_h(ch_mes_nome($mesOpcao)) ?></option>
      <?php endfor; ?>
    </select>
  </div>
  <div class="col-md-3">
    <button class="btn btn-primary w-100"><i class="fas fa-magnifying-glass"></i> Ver ficha</button>
  </div>
</form>

<?php if (!$turmas): ?>
  <div class="alert alert-warning">
    Nenhuma turma disponível para o seu usuário.
    <?php if (ch_gestor()): ?>Cadastre uma turma em <a href="<?= ch_url('turmas/index.php') ?>">Turmas</a>.
    <?php else: ?>Peça à gestão para incluir você na <strong>equipe da turma</strong> em Turmas &rarr; Editar.<?php endif; ?>
  </div>
<?php elseif (!$turma): ?>
  <div class="alert alert-info">Selecione uma turma para abrir a ficha do mês.</div>
<?php else: ?>

  <div class="card card-body mb-3">
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-3">
      <div>
        <h5 class="mb-1"><?= ch_h($turma['UNIDADE']) ?></h5>
        <div class="small text-muted">
          Turma <?= ch_h(ch_series_ensino()[$turma['SERIE'] ?? '']['rotulo'] ?? 'não classificada') ?>
          · Sala <strong><?= ch_h($turma['NOME']) ?></strong>
          · <?= ch_h($turma['PERIODO']) ?>
          (<?= ch_h($turma['HORA_INICIO']) ?>–<?= ch_h($turma['HORA_FIM']) ?>)
          · Ano letivo <?= (int)$turma['ANO_LETIVO'] ?>
        </div>
      </div>
      <div class="text-sm-end">
        <div class="fw-bold"><?= ch_h(ch_mes_nome($mes)) ?> de <?= $ano ?></div>
        <div class="small text-muted"><?= count($fichaAlunos) ?> aluno(s) · <?= count($fichaDatas) ?> data(s) letiva(s)</div>
      </div>
    </div>
  </div>

  <div class="alert alert-info no-print">
    <i class="fas fa-eye"></i>
    <strong>Ficha somente leitura.</strong>
    Para registrar ou corrigir presenças, use a
    <a href="<?= ch_url('chamada/index.php?turma_id=' . (int)$turmaId) ?>">Chamada do Dia</a>.
    <?php if (ch_gestor()): ?>
      Para informar disciplina e professor de cada aula, abra a
      <a href="<?= ch_url('grade/index.php?turma_id=' . (int)$turmaId) ?>">Grade Diária</a>.
    <?php endif; ?>
  </div>

  <?php if (!$fichaDatas): ?>
    <div class="alert alert-warning">Não há datas letivas neste mês para a turma.</div>
  <?php else: ?>
    <div class="table-responsive">
      <table class="table table-bordered ficha-tabela">
        <thead>
          <tr>
            <th rowspan="2" class="ficha-aluno">Aluno</th>
            <?php foreach ($fichaDatas as $info): ?>
              <th class="ficha-data"><?= ch_h($info['ROTULO']) ?></th>
            <?php endforeach; ?>
            <th rowspan="2">P</th>
            <th rowspan="2">F</th>
            <th rowspan="2">J</th>
            <th rowspan="2">A</th>
            <th rowspan="2">%</th>
          </tr>
          <tr>
            <?php foreach ($fichaDatas as $info): ?>
              <th class="ficha-sub">
                <?php foreach (ch_aula_lista_turma($turmaId) as $aula => $rotuloAula): ?>
                  <?php $linha = $info['GRADE_LINHAS'][$aula] ?? ['DISCIPLINA' => '—', 'PROFESSOR' => '—', 'TITULO' => ch_aula_rotulo($aula)]; ?>
                  <span title="<?= ch_h($linha['TITULO']) ?>"><b><?= $aula ?></b> <?= ch_h($linha['DISCIPLINA']) ?></span>
                  <span title="<?= ch_h($linha['TITULO']) ?>"><?= ch_h($linha['PROFESSOR']) ?></span>
                <?php endforeach; ?>
              </th>
            <?php endforeach; ?>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($fichaAlunos as $aluno): ?>
            <?php
              $id = (int)$aluno['ID'];
              $t = $fichaTotais[$id] ?? ['P' => 0, 'F' => 0, 'J' => 0, 'A' => 0, 'LANCADOS' => 0];
              $freq = $t['LANCADOS'] > 0
                  ? number_format(100 * ($t['P'] + $t['A']) / $t['LANCADOS'], 1, ',', '.')
                  : '—';
            ?>
            <tr>
              <td class="ficha-aluno"><strong><?= ch_h($aluno['NOME']) ?></strong><br><small class="text-muted"><?= ch_h($aluno['MATRICULA']) ?></small></td>
              <?php foreach ($fichaDatas as $iso => $info): ?>
                <td>
                  <?php foreach (ch_aula_lista_turma($turmaId) as $aula => $rotuloAula): ?>
                    <?php
                      $sigla = $fichaMarcas[$id][$iso][$aula] ?? '';
                      $tituloAula = $info['GRADE_LINHAS'][$aula]['TITULO'] ?? ch_aula_rotulo($aula);
                    ?>
                    <span class="ficha-sigla <?= $sigla !== '' ? 'ficha-' . ch_h($sigla) : 'ficha-vazio' ?>" title="<?= ch_h($tituloAula) ?>"><?= $sigla !== '' ? ch_h($sigla) : '·' ?></span>
                  <?php endforeach; ?>
                </td>
              <?php endforeach; ?>
              <td class="ficha-total"><?= (int)$t['P'] ?></td>
              <td class="ficha-total"><?= (int)$t['F'] ?></td>
              <td class="ficha-total"><?= (int)$t['J'] ?></td>
              <td class="ficha-total"><?= (int)$t['A'] ?></td>
              <td class="ficha-total"><?= ch_h($freq) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
        <tfoot>
          <tr>
            <th class="ficha-aluno">Total da turma</th>
            <?php foreach ($fichaDatas as $iso => $info): ?>
              <?php $td = $fichaTotaisPorData[$iso] ?? []; ?>
              <td class="ficha-total">
                <span class="ficha-sigla ficha-P"><?= (int)($td['P'] ?? 0) ?></span>
                <span class="ficha-sigla ficha-F"><?= (int)($td['F'] ?? 0) ?></span>
              </td>
            <?php endforeach; ?>
            <?php foreach (['P', 'F', 'J', 'A'] as $sigla): ?>
              <th class="ficha-total"><?= array_sum(array_column($fichaTotais, $sigla)) ?></th>
            <?php endforeach; ?>
            <th class="ficha-total">—</th>
          </tr>
        </tfoot>
      </table>
    </div>

    <div class="ficha-legenda">
      <?php foreach (ch_status_lista() as $sigla => $info): ?>
        <span><span class="ficha-sigla ficha-<?= ch_h($sigla) ?>"><?= ch_h($sigla) ?></span> <?= ch_h($info['rotulo']) ?></span>
      <?php endforeach; ?>
      <span><span class="ficha-sigla ficha-vazio">·</span> Sem chamada lançada</span>
      <span>Cada data mostra as <?= (int)ch_turma_aulas_dia($turmaId) ?> aula(s) configuradas, nessa ordem; o cabeçalho traz a disciplina e, abaixo dela, o professor de cada aula (<strong>*</strong> = substituto).</span>
    </div>
  <?php endif; ?>
<?php endif; ?>
