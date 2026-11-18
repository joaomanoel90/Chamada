<?php require_once('processa/index_processa.php'); ?>
<?php include(HEADER_TEMPLATE2); ?>

<?php ch_cabecalho('Diário de Classe', 'fa-book-open'); ?>

<?php if ($erros): ?>
  <div class="alert alert-danger"><ul class="mb-0"><?php foreach ($erros as $erro): ?><li><?= ch_h($erro) ?></li><?php endforeach; ?></ul></div>
<?php endif; ?>

<?php if (!$turmas): ?>
  <div class="alert alert-info">
    Nenhuma turma com lançamento de notas está disponível.
    <?php if (ch_gestor()): ?>
      Habilite as notas em <a href="<?= ch_url('turmas/index.php') ?>">Turmas → Editar</a>, somente para turmas escolares.
    <?php else: ?>
      Peça à gestão para habilitar as notas na turma escolar.
    <?php endif; ?>
  </div>
  <a class="btn btn-outline-primary" href="<?= ch_url('chamada/index.php') ?>"><i class="fas fa-clipboard-user"></i> Abrir chamada</a>
<?php else: ?>
  <form method="get" action="index.php" class="row g-2 align-items-end mb-4 no-print">
    <div class="col-md-6">
      <label class="fw-bold" for="turma">Turma escolar</label>
      <select class="form-select" id="turma" name="turma_id" required>
        <?php foreach ($turmas as $opcao): ?>
          <option value="<?= (int)$opcao['ID'] ?>" <?= (int)$opcao['ID'] === $turmaId ? 'selected' : '' ?>>
              <?= ch_h($opcao['UNIDADE']) ?> — Turma <?= ch_h(ch_series_ensino()[$opcao['SERIE'] ?? '']['rotulo'] ?? 'não classificada') ?>
              · Sala <?= ch_h($opcao['NOME']) ?> · <?= ch_h($opcao['PERIODO']) ?>
              (<?= ch_h(ch_etapa_ensino_rotulo($opcao['ETAPA_ENSINO'])) ?>, <?= (int)$opcao['ANO_LETIVO'] ?>)
          </option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-md-3">
      <label class="fw-bold" for="bimestre">Bimestre</label>
      <select class="form-select" id="bimestre" name="bimestre">
        <?php for ($i = 1; $i <= 4; $i++): ?>
          <option value="<?= $i ?>" <?= $i === $bimestre ? 'selected' : '' ?>><?= $i ?>º bimestre</option>
        <?php endfor; ?>
      </select>
    </div>
    <div class="col-md-3">
      <button class="btn btn-primary w-100"><i class="fas fa-filter"></i> Abrir diário</button>
    </div>
  </form>

  <?php if ($turma): ?>
    <div class="d-flex flex-wrap gap-2 mb-3 no-print">
      <a class="btn btn-outline-primary" href="<?= ch_url('chamada/index.php?turma_id=' . $turmaId) ?>">
        <i class="fas fa-clipboard-user"></i> Chamada da turma
      </a>
      <button type="button" class="btn btn-outline-secondary" onclick="window.print()">
        <i class="fas fa-print"></i> Imprimir diário
      </button>
    </div>

    <div class="card-escola mb-4">
      <div class="card-escola-titulo"><i class="fas fa-book-open"></i> Disciplinas da turma</div>
      <div class="card-escola-corpo">
        <form method="get" action="index.php" class="row g-2 align-items-end mb-3 no-print">
          <input type="hidden" name="turma_id" value="<?= $turmaId ?>">
          <input type="hidden" name="bimestre" value="<?= $bimestre ?>">
          <div class="col-md-8">
            <label class="fw-bold" for="disciplina">Disciplina</label>
            <select class="form-select" id="disciplina" name="disciplina_id" onchange="this.form.submit()">
              <option value="0">Selecione uma disciplina...</option>
              <?php foreach ($disciplinas as $disciplina): ?>
                <option value="<?= (int)$disciplina['ID'] ?>" <?= (int)$disciplina['ID'] === $disciplinaId ? 'selected' : '' ?>>
                  <?= ch_h($disciplina['NOME']) ?> — <?= ch_h($disciplina['PROFESSOR'] ?: 'responsável pendente') ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
        </form>

        <?php if (ch_gestor() && !$disciplinas): ?>
          <div class="alert alert-warning mb-0 no-print">Esta turma ainda não tem matérias. Defina as matérias e o total de aulas na área de <strong>Turmas</strong>.</div>
        <?php endif; ?>
      </div>
    </div>

    <?php if ($disciplinaId > 0): ?>
      <?php
        $nomeDisciplina = '';
        foreach ($disciplinas as $disciplina) {
            if ((int)$disciplina['ID'] === $disciplinaId) {
                $nomeDisciplina = $disciplina['NOME'];
                break;
            }
        }
      ?>
      <?php
        $professorAtualDisciplina = (int)($disciplinaSelecionada['ID_PROFESSOR'] ?? 0);
        $responsavelDisciplina = $disciplinaSelecionada['PROFESSOR'] ?? '';
      ?>
      <div class="card-escola">
        <div class="card-escola-titulo">
          <i class="fas fa-list-ol"></i> <?= ch_h($nomeDisciplina) ?> — <?= $bimestre ?>º bimestre
          <span class="ms-2 small">Professores: <?= ch_h($responsavelDisciplina ?: 'não atribuído') ?></span>
          <span class="ms-auto badge bg-light text-dark"><?= count($alunos) ?> aluno(s)</span>
        </div>
        <div class="card-escola-corpo">
          <?php if (!$responsavelDisciplina): ?>
            <div class="alert alert-warning no-print">
              Esta disciplina ainda não tem professor atribuído.
              <?php if (ch_gestor()): ?>
                Faça a atribuição no menu <a href="<?= ch_url('atribuicoes/index.php?ano=' . (int)$turma['ANO_LETIVO'] . '&unidade=' . (int)$turma['ID_UNIDADE']) ?>"><strong>Atribuição</strong></a> antes do lançamento das notas.
              <?php else: ?>
                A gestão deve atribuir um professor antes do lançamento das notas.
              <?php endif; ?>
            </div>
          <?php elseif (!$podeLancar && !ch_gestor()): ?>
            <div class="alert alert-info no-print">As notas desta disciplina são lançadas pelos professores com aulas atribuídas. Você pode consultar os registros.</div>
          <?php endif; ?>
          <?php if ($alunos): ?>
            <form method="post" action="index.php">
              <?= ch_csrf_campo() ?>
              <input type="hidden" name="acao" value="salvar_notas">
              <input type="hidden" name="turma_id" value="<?= $turmaId ?>">
              <input type="hidden" name="disciplina_id" value="<?= $disciplinaId ?>">
              <input type="hidden" name="bimestre" value="<?= $bimestre ?>">
              <div class="table-responsive">
                <table class="table table-striped table-hover align-middle">
                  <thead><tr><th>Aluno</th><th>Matrícula</th><th style="width: 180px">Nota (0 a 10)</th></tr></thead>
                  <tbody>
                    <?php foreach ($alunos as $aluno):
                      $alunoId = (int)$aluno['ID'];
                      $notaExibida = $notasPost[$alunoId] ?? $notaPorAluno[$alunoId] ?? '';
                      if (!is_scalar($notaExibida)) $notaExibida = '';
                    ?>
                      <tr>
                        <td><?= ch_h($aluno['NOME']) ?></td>
                        <td>
                          <?= ch_h($aluno['MATRICULA']) ?>
                          <?php if ((int)$aluno['ID_TURMA'] !== $turmaId || (int)$aluno['ATIVO'] !== 1): ?>
                            <span class="badge bg-secondary">Histórico</span>
                          <?php endif; ?>
                        </td>
                        <td>
                          <?php if ($podeLancar && (int)$aluno['ID_TURMA'] === $turmaId && (int)$aluno['ATIVO'] === 1): ?>
                          <input type="number" class="form-control" name="notas[<?= $alunoId ?>]"
                                 min="0" max="10" step="0.01" inputmode="decimal"
                                 value="<?= ch_h($notaExibida) ?>"
                                 aria-label="Nota de <?= ch_h($aluno['NOME']) ?>">
                          <?php else: ?>
                          <?= isset($notaPorAluno[$alunoId]) ? ch_h(number_format((float)$notaPorAluno[$alunoId], 2, ',', '.')) : '<span class="text-muted">—</span>' ?>
                          <?php endif; ?>
                        </td>
                      </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
              <?php if ($podeLancar): ?>
                <p class="form-text">Deixe sem nota quem ainda não foi avaliado. Apagar uma nota já lançada e salvar remove esse registro.</p>
                <button class="btn btn-success"><i class="fas fa-save"></i> Salvar notas</button>
              <?php endif; ?>
            </form>
          <?php else: ?>
            <p class="text-muted mb-0">Não há alunos ativos nesta turma.</p>
          <?php endif; ?>
        </div>
      </div>
    <?php elseif ($disciplinas): ?>
      <div class="alert alert-info">Selecione uma disciplina para lançar ou consultar as notas.</div>
    <?php else: ?>
      <div class="alert alert-info">Defina as matérias da turma na área de <strong>Turmas</strong> para começar o lançamento de notas.</div>
    <?php endif; ?>
  <?php endif; ?>
<?php endif; ?>

<style>
  @media print {
    body { background: #fff !important; padding: 0 !important; }
    .content { margin: 0 !important; padding: 0 !important; }
      .sidebar, .toggle-btn, .footer-custom { display: none !important; }
      .card-escola { break-inside: avoid; }
    }
</style>

<?php include(FOOTER_TEMPLATE); ?>
