<?php require_once('processa/receber_processa.php'); ?>
<?php include(HEADER_TEMPLATE); ?>

<?php ch_cabecalho('Receber aluno transferido', 'fa-door-open',
    '<a class="btn btn-outline-secondary" href="ver.php?id=' . (int)$id . '"><i class="fas fa-arrow-left"></i> Voltar</a>'); ?>

<div class="row g-3">
  <div class="col-lg-7">
    <div class="card border-0 shadow-sm rounded-4">
      <div class="card-body">
        <h5 class="mb-3"><i class="fas fa-children"></i> Etapa 2 — escolher a turma e a data de início</h5>

        <?php if ($erros): ?>
          <div class="alert alert-danger"><ul class="mb-0"><?php foreach ($erros as $e): ?><li><?= ch_h($e) ?></li><?php endforeach; ?></ul></div>
        <?php endif; ?>

        <?php if (!$turmas): ?>
          <div class="alert alert-warning mb-0">
            A sua escola não tem turma ativa cadastrada. Cadastre a turma antes de receber o aluno.
          </div>
        <?php else: ?>
          <form method="post" action="receber.php">
            <?= ch_csrf_campo() ?>
            <input type="hidden" name="id" value="<?= (int)$id ?>">

            <div class="mb-3">
              <label class="fw-bold">Aluno</label>
              <input type="text" class="form-control" disabled
                     value="<?= ch_h($aluno['NOME']) ?> — matrícula <?= ch_h($aluno['MATRICULA']) ?>">
            </div>

            <div class="mb-3">
              <label class="fw-bold">Vem de</label>
              <input type="text" class="form-control" disabled
                     value="<?= ch_h($t['UNIDADE_ORIGEM']) ?> — <?= ch_h($t['TURMA_ORIGEM']) ?>">
              <div class="form-text">
                Encaminhado por <?= ch_h($t['SOLICITADA_POR']) ?> em <?= ch_h(ch_confirma_texto($t['SOLICITADA_EM'])) ?>.
                Motivo: <?= ch_h($t['MOTIVO']) ?>
              </div>
            </div>

            <div class="mb-3">
              <label class="fw-bold" for="id_turma_destino">Turma na sua escola</label>
              <select name="id_turma_destino" id="id_turma_destino" class="form-select" required>
                <option value="">Selecione...</option>
                <?php foreach ($turmas as $x): ?>
                  <option value="<?= (int)$x['ID'] ?>" <?= (int)$d['ID_TURMA_DESTINO'] === (int)$x['ID'] ? 'selected' : '' ?>>
                    <?= ch_h($x['NOME']) ?> — <?= ch_h($x['PERIODO']) ?> (<?= (int)$x['ANO_LETIVO'] ?>)
                    [<?= (int)$x['OCUPADAS'] ?>/<?= (int)$x['CAPACIDADE'] ?>]
                  </option>
                <?php endforeach; ?>
              </select>
              <div class="form-text">Entre colchetes, a lotação atual da turma.</div>
            </div>

            <div class="mb-3">
              <label class="fw-bold" for="data_inicio">Data de início na nova turma</label>
              <input type="date" name="data_inicio" id="data_inicio" class="form-control"
                     min="<?= ch_h($dataMin) ?>" required
                     value="<?= ch_h($d['DATA_INICIO'] !== '' ? $d['DATA_INICIO'] : ch_hoje()) ?>">
              <div class="form-text">
                A partir de <b><?= ch_data_br($dataMin) ?></b>. É esta data que separa os dois períodos:
                até a véspera o aluno pertence à turma de origem, deste dia em diante à turma escolhida.
              </div>
            </div>

            <div class="mb-3">
              <label class="fw-bold" for="observacao_destino">Observação <small class="text-muted">(opcional)</small></label>
              <textarea name="observacao_destino" id="observacao_destino" class="form-control" rows="2"
                        maxlength="500" placeholder="Ex.: recebido conforme ofício; documentação conferida."><?= ch_h($d['OBSERVACAO_DESTINO']) ?></textarea>
            </div>

            <div class="alert alert-warning">
              <i class="fas fa-triangle-exclamation"></i>
              Ao confirmar, o aluno <b>passa para a turma escolhida</b>. A chamada de datas
              anteriores continua aparecendo na turma de origem — nenhum registro antigo é movido.
            </div>

            <div class="d-flex gap-2">
              <a href="ver.php?id=<?= (int)$id ?>" class="btn btn-outline-secondary">Voltar</a>
              <button type="submit" class="btn btn-success"><i class="fas fa-check"></i> Confirmar recebimento</button>
            </div>
          </form>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <div class="col-lg-5">
    <div class="card border-0 shadow-sm rounded-4">
      <div class="card-body">
        <h5 class="mb-3"><i class="fas fa-id-card"></i> Dados do aluno</h5>
        <dl class="row mb-0">
          <dt class="col-sm-5">Nascimento</dt>
          <dd class="col-sm-7"><?= ch_data_br($aluno['NASCIMENTO']) ?><br><small class="text-muted"><?= ch_h(ch_idade_texto($aluno['NASCIMENTO'])) ?></small></dd>
          <dt class="col-sm-5">Matriculado em</dt><dd class="col-sm-7"><?= ch_data_br($aluno['DATA_MATRICULA']) ?></dd>
          <dt class="col-sm-5">Responsável</dt><dd class="col-sm-7"><?= ch_h($aluno['RESPONSAVEL']) ?></dd>
          <dt class="col-sm-5">Telefone</dt><dd class="col-sm-7"><?= ch_h($aluno['TELEFONE']) ?></dd>
          <dt class="col-sm-5">E-mail</dt>
          <dd class="col-sm-7"><?= $aluno['EMAIL'] ? ch_h($aluno['EMAIL']) : '<span class="text-muted">—</span>' ?></dd>
        </dl>
        <?php if ($t['DOCUMENTO']): ?>
          <hr>
          <p class="mb-0"><b>Documento:</b> <?= ch_h($t['DOCUMENTO']) ?></p>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<?php include(FOOTER_TEMPLATE); ?>