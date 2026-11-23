<?php require_once('processa/atribuir_processa.php'); ?>
<?php include(HEADER_TEMPLATE); ?>

<?php
$titulo = $aluno
    ? 'Atendimento AEE — ' . ch_h($aluno['NOME'])
    : 'Novo Atendimento AEE';
ch_cabecalho($titulo, 'fa-hands-holding-child');
?>

<div class="row justify-content-center">
  <div class="col-lg-10">
    <div class="card border-0 shadow-sm rounded-4 mb-4">
      <div class="card-body p-4">

        <?php if ($erros): ?>
          <div class="alert alert-danger">
            <ul class="mb-0">
              <?php foreach ($erros as $e): ?>
                <li><?= ch_h($e) ?></li>
              <?php endforeach; ?>
            </ul>
          </div>
        <?php endif; ?>

        <?php if ($aluno): ?>
          <div class="alert alert-info py-3 mb-4">
            <div class="d-flex align-items-center gap-3">
              <i class="fas fa-child-reaching fa-2x text-primary"></i>
              <div>
                <div class="fw-bold fs-5"><?= ch_h($aluno['NOME']) ?> <small class="text-muted">(Matrícula: <?= ch_h($aluno['MATRICULA']) ?>)</small></div>
                <div class="text-muted">
                  <?= ch_h($aluno['UNIDADE']) ?> · Turma <?= ch_h(ch_series_ensino()[$aluno['SERIE'] ?? '']['rotulo'] ?? 'Turma') ?> <?= ch_h($aluno['TURMA']) ?> (<?= (int)$aluno['ANO_LETIVO'] ?>)
                </div>
                <?php
                  $inclusoes = aluno_inclusao_codigos($aluno['DEFICIENCIAS_INCLUSAO'] ?? []);
                  $opcoesInclusao = aluno_inclusao_opcoes();
                ?>
                <?php if ($inclusoes || !empty($aluno['CONDICAO_ESPECIFICA'])): ?>
                  <div class="mt-2 small">
                    <strong>Necessidades registradas:</strong>
                    <?php foreach ($inclusoes as $ci): ?>
                      <span class="badge bg-light text-dark border me-1"><?= ch_h($opcoesInclusao[$ci] ?? $ci) ?></span>
                    <?php endforeach; ?>
                    <?php if (!empty($aluno['CONDICAO_ESPECIFICA'])): ?>
                      <span class="text-secondary">· <?= ch_h($aluno['CONDICAO_ESPECIFICA']) ?></span>
                    <?php endif; ?>
                  </div>
                <?php endif; ?>
              </div>
            </div>
          </div>
        <?php endif; ?>

        <form method="post" action="atribuir.php<?= $idAluno > 0 ? '?aluno_id=' . $idAluno : '' ?>">
          <?= ch_csrf_campo() ?>
          <input type="hidden" name="origem" value="<?= ch_h($_GET['origem'] ?? '') ?>">

          <div class="row g-3">
            <?php if ($aluno): ?>
              <input type="hidden" name="id_aluno" value="<?= (int)$aluno['ID'] ?>">
            <?php else: ?>
              <div class="col-md-8">
                <label class="fw-bold" for="id_aluno">Selecionar Aluno</label>
                <select name="id_aluno" id="id_aluno" class="form-select" required>
                  <option value="">Selecione o aluno...</option>
                  <?php foreach ($alunosDisponiveis as $al): ?>
                    <option value="<?= (int)$al['ID'] ?>" <?= $d['id_aluno'] === (int)$al['ID'] ? 'selected' : '' ?>>
                      <?= ch_h($al['NOME']) ?> (Matr.: <?= ch_h($al['MATRICULA']) ?>) — <?= ch_h($al['UNIDADE']) ?> [<?= ch_h($al['TURMA']) ?>]
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>
            <?php endif; ?>

            <div class="<?= $aluno ? 'col-md-4' : 'col-md-4' ?>">
              <label class="fw-bold" for="ano_letivo">Ano Letivo de Atendimento</label>
              <select name="ano_letivo" id="ano_letivo" class="form-select" required>
                <?php foreach ($anos as $a): ?>
                  <option value="<?= $a ?>" <?= $a === $d['ano_letivo'] ? 'selected' : '' ?>><?= $a ?><?= $a === $anoAtual ? ' (atual)' : '' ?></option>
                <?php endforeach; ?>
              </select>
            </div>

            <div class="col-12">
              <div class="form-check form-switch p-3 border rounded-3 bg-light">
                <input class="form-check-input ms-0 me-2" type="checkbox" role="switch" id="ativo" name="ativo" value="1" <?= !empty($d['ativo']) ? 'checked' : '' ?>>
                <label class="form-check-label fw-bold" for="ativo">
                  Aluno em atendimento ativo no AEE neste ano letivo
                </label>
                <div class="form-text ms-4 text-muted">
                  Desmarque para indicar suspensão temporária, alta ou transferência do atendimento.
                </div>
              </div>
            </div>

            <div class="col-md-6">
              <label class="fw-bold" for="id_professor_aee">Professor(a) de AEE</label>
              <select name="id_professor_aee" id="id_professor_aee" class="form-select">
                <option value="">Nenhum professor vinculado ainda</option>
                <?php foreach ($professoresAee as $pa): ?>
                  <option value="<?= (int)$pa['ID'] ?>" <?= $d['id_professor_aee'] === (int)$pa['ID'] ? 'selected' : '' ?>>
                    <?= ch_h($pa['NOME']) ?>
                  </option>
                <?php endforeach; ?>
              </select>
              <div class="form-text">
                Docentes cadastrados com a categoria <em>Professor de AEE</em>. Ao vincular, o professor ganha acesso à ficha e ao lançamento de observações deste aluno.
              </div>
            </div>

            <div class="col-md-6">
              <label class="fw-bold" for="sala_recursos">Sala de Recursos Multifuncionais (SRM) / Polo</label>
              <input type="text" class="form-control" name="sala_recursos" id="sala_recursos" maxlength="150"
                     value="<?= ch_h($d['sala_recursos']) ?>" placeholder="Ex.: SRM Polo CMEI Estação, Sala 02">
              <div class="form-text">Local onde ocorre o atendimento especializado.</div>
            </div>

            <div class="col-md-12">
              <label class="fw-bold" for="dias_horarios">Dias da semana e horários de atendimento</label>
              <input type="text" class="form-control" name="dias_horarios" id="dias_horarios" maxlength="500"
                     value="<?= ch_h($d['dias_horarios']) ?>" placeholder="Ex.: Terças e Quintas das 14h às 15h30 (contraturno)">
              <div class="form-text">
                Frequência e turno do atendimento. Aparece na ficha interna e auxilia a escola na rotina.
              </div>
            </div>

            <div class="col-md-12">
              <label class="fw-bold" for="objetivos_plano">Objetivos pedagógicos e metas do PDI / PEI <small class="text-muted">(opcional)</small></label>
              <textarea class="form-control" name="objetivos_plano" id="objetivos_plano" rows="4" maxlength="1000"
                        placeholder="Resumo dos objetivos prioritários para este ano letivo (estimulação cognitiva, comunicação alternativa, recursos de acessibilidade...)"><?= ch_h($d['objetivos_plano']) ?></textarea>
              <div class="form-text">Uso interno para acompanhamento pedagógico entre a equipe e a gestão escolar.</div>
            </div>
          </div>

          <div class="d-flex justify-content-between align-items-center mt-4 pt-3 border-top">
            <?php if ($aluno): ?>
              <a href="<?= ch_url('alunos/view.php?id=' . (int)$aluno['ID']) ?>" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left"></i> Voltar à ficha
              </a>
            <?php else: ?>
              <a href="index.php" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left"></i> Voltar ao painel
              </a>
            <?php endif; ?>
            <button type="submit" class="btn btn-success px-4">
              <i class="fas fa-check"></i> Salvar Atendimento AEE
            </button>
          </div>
        </form>

      </div>
    </div>
  </div>
</div>

<?php include(FOOTER_TEMPLATE); ?>
