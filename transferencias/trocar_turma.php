<?php require_once('processa/trocar_turma_processa.php'); ?>
<?php include(HEADER_TEMPLATE); ?>

<?php ch_cabecalho('Trocar de turma', 'fa-people-arrows',
    '<a class="btn btn-outline-secondary" href="' . ch_url('alunos/view.php?id=' . (int)$idAluno) . '"><i class="fas fa-arrow-left"></i> Voltar ao aluno</a>'); ?>

<div class="row g-3">
  <div class="col-lg-7">
    <div class="card border-0 shadow-sm rounded-4">
      <div class="card-body">
        <h5 class="mb-3"><i class="fas fa-people-arrows"></i> Troca de turma na mesma escola</h5>

        <div class="alert alert-info">
          <i class="fas fa-circle-info"></i>
          Aqui o aluno muda de <b>turma dentro da própria escola</b>, em uma única etapa:
          você escolhe a turma e a <b>data de início</b> agora. Até a véspera ele continua
          na turma atual, com chamada normal; daquele dia em diante aparece na nova.
          Para mudar de <b>escola</b>, use <b>Transferir aluno</b>.
        </div>

        <?php if ($erros): ?>
          <div class="alert alert-danger"><ul class="mb-0"><?php foreach ($erros as $e): ?><li><?= ch_h($e) ?></li><?php endforeach; ?></ul></div>
        <?php endif; ?>

        <?php if (!$turmas): ?>
          <div class="alert alert-warning mb-0">
            Não há outra turma ativa nesta escola em <?= (int)$aluno['ANO_LETIVO'] ?> para receber o aluno.
            Cadastre a turma de destino antes de trocar.
          </div>
        <?php else: ?>
          <form method="post" action="trocar_turma.php">
            <?= ch_csrf_campo() ?>
            <input type="hidden" name="aluno_id" value="<?= (int)$idAluno ?>">

            <div class="mb-3">
              <label class="fw-bold">Aluno</label>
              <input type="text" class="form-control" disabled
                     value="<?= ch_h($aluno['NOME']) ?> — matrícula <?= ch_h($aluno['MATRICULA']) ?>">
            </div>

            <div class="mb-3">
              <label class="fw-bold">Escola e turma atuais</label>
              <input type="text" class="form-control" disabled
                     value="<?= ch_h($aluno['UNIDADE']) ?> — <?= ch_h(ch_series_ensino()[$aluno['SERIE']]['rotulo'] ?? 'série não classificada') ?> · <?= ch_h($aluno['TURMA']) ?> (<?= (int)$aluno['ANO_LETIVO'] ?>)">
            </div>

            <div class="mb-3">
              <label class="fw-bold" for="id_turma_destino">Nova turma</label>
              <select name="id_turma_destino" id="id_turma_destino" class="form-select" required>
                <option value="">Selecione...</option>
                <?php foreach ($turmas as $t): ?>
                  <option value="<?= (int)$t['ID'] ?>" <?= (int)$d['ID_TURMA_DESTINO'] === (int)$t['ID'] ? 'selected' : '' ?>>
                    <?= ch_h(ch_series_ensino()[$t['SERIE']]['rotulo'] ?? 'Série não classificada') ?> · <?= ch_h($t['NOME']) ?> — <?= ch_h($t['PERIODO']) ?>
                    (<?= (int)$t['OCUPADAS'] ?>/<?= (int)$t['CAPACIDADE'] ?> vagas)
                  </option>
                <?php endforeach; ?>
              </select>
              <div class="form-text">Aparecem as turmas ativas desta escola em <?= (int)$aluno['ANO_LETIVO'] ?>, exceto a turma atual. Confira a série antes de confirmar.</div>
            </div>

            <div class="mb-3">
              <label class="fw-bold" for="data_inicio">Data de início na nova turma</label>
              <input type="date" name="data_inicio" id="data_inicio" class="form-control" required
                     min="<?= ch_h($dataMin) ?>" value="<?= ch_h($d['DATA_INICIO'] ?: date('Y-m-d')) ?>">
              <div class="form-text">Não pode ser anterior a <?= ch_data_br($dataMin) ?> (matrícula ou dia seguinte ao período anterior).</div>
            </div>

            <div class="mb-3">
              <label class="fw-bold" for="motivo">Motivo da troca</label>
              <textarea name="motivo" id="motivo" class="form-control" rows="3" maxlength="500" required
                        placeholder="Ex.: reequilíbrio das turmas; adaptação da criança; solicitação do responsável."><?= ch_h($d['MOTIVO']) ?></textarea>
              <div class="form-text">Obrigatório, até 500 caracteres. Fica registrado no histórico do aluno.</div>
            </div>

            <div class="mb-3">
              <label class="fw-bold" for="documento">Ofício / protocolo <small class="text-muted">(opcional)</small></label>
              <input type="text" name="documento" id="documento" class="form-control" maxlength="100"
                     value="<?= ch_h($d['DOCUMENTO']) ?>" placeholder="Ex.: Ofício 123/2026">
            </div>

            <div class="d-flex gap-2 mt-4">
              <a href="<?= ch_url('alunos/view.php?id=' . (int)$idAluno) ?>" class="btn btn-outline-secondary">Cancelar</a>
              <button type="submit" class="btn btn-success"><i class="fas fa-people-arrows"></i> Trocar de turma</button>
            </div>
          </form>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <div class="col-lg-5">
    <div class="card border-0 shadow-sm rounded-4">
      <div class="card-body">
        <h5 class="mb-3"><i class="fas fa-circle-question"></i> O que acontece com o histórico</h5>
        <ul class="mb-0">
          <li>A chamada, as observações e os pareceres <b>continuam com o aluno</b>. Nada é apagado nem movido.</li>
          <li>A turma antiga continua podendo <b>consultar</b> o histórico de quem foi seu aluno.</li>
          <li>A chamada do dia passa a considerar a data de início: até a véspera o aluno aparece na turma antiga, daquele dia em diante na nova.</li>
          <li>O relatório anual mostra <b>o ano inteiro</b>, com o quadro de escolas e turmas por período.</li>
        </ul>
      </div>
    </div>
  </div>
</div>

<?php include(FOOTER_TEMPLATE); ?>
