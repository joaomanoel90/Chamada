<?php require_once('processa/solicitar_processa.php'); ?>
<?php include(HEADER_TEMPLATE); ?>

<?php ch_cabecalho('Transferir aluno', 'fa-right-left',
    '<a class="btn btn-outline-secondary" href="' . ch_url('alunos/view.php?id=' . (int)$idAluno) . '"><i class="fas fa-arrow-left"></i> Voltar ao aluno</a>'); ?>

<div class="row g-3">
  <div class="col-lg-7">
    <div class="card border-0 shadow-sm rounded-4">
      <div class="card-body">
        <h5 class="mb-3"><i class="fas fa-school"></i> Etapa 1 — indicar a escola de destino</h5>

        <div class="alert alert-info">
          <i class="fas fa-circle-info"></i>
          Aqui você indica <b>para qual escola</b> o aluno vai. A <b>turma</b> e a
          <b>data de início</b> são escolhidas pela escola de destino, na etapa 2.
          Até lá o aluno <b>continua na turma atual</b> e a chamada segue normal.
        </div>

        <?php if ($erros): ?>
          <div class="alert alert-danger"><ul class="mb-0"><?php foreach ($erros as $e): ?><li><?= ch_h($e) ?></li><?php endforeach; ?></ul></div>
        <?php endif; ?>

        <?php if (!$unidades): ?>
          <div class="alert alert-warning mb-0">
            Não há outra escola cadastrada na rede. Cadastre a unidade de destino antes de transferir.
          </div>
        <?php else: ?>
          <form method="post" action="solicitar.php">
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
                     value="<?= ch_h($aluno['UNIDADE']) ?> — <?= ch_h($aluno['TURMA']) ?> (<?= (int)$aluno['ANO_LETIVO'] ?>)">
            </div>

            <div class="mb-3">
              <label class="fw-bold" for="id_unidade_destino">Escola de destino</label>
              <select name="id_unidade_destino" id="id_unidade_destino" class="form-select" required>
                <option value="">Selecione...</option>
                <?php foreach ($unidades as $u): ?>
                  <option value="<?= (int)$u['id'] ?>" <?= (int)$d['ID_UNIDADE_DESTINO'] === (int)$u['id'] ? 'selected' : '' ?>>
                    <?= ch_h($u['unidade']) ?>
                  </option>
                <?php endforeach; ?>
              </select>
              <div class="form-text">Aparecem todas as escolas da rede, exceto a atual do aluno.</div>
            </div>

            <div class="mb-3">
              <label class="fw-bold" for="motivo">Motivo da transferência</label>
              <textarea name="motivo" id="motivo" class="form-control" rows="3" maxlength="500" required
                        placeholder="Ex.: mudança de endereço da família; solicitação do responsável."><?= ch_h($d['MOTIVO']) ?></textarea>
              <div class="form-text">Obrigatório, até 500 caracteres. Fica registrado no histórico do aluno.</div>
            </div>

            <div class="mb-3">
              <label class="fw-bold" for="documento">Ofício / protocolo <small class="text-muted">(opcional)</small></label>
              <input type="text" name="documento" id="documento" class="form-control" maxlength="100"
                     value="<?= ch_h($d['DOCUMENTO']) ?>" placeholder="Ex.: Ofício 123/2026">
            </div>

            <div class="d-flex gap-2 mt-4">
              <a href="<?= ch_url('alunos/view.php?id=' . (int)$idAluno) ?>" class="btn btn-outline-secondary">Cancelar</a>
              <button type="submit" class="btn btn-success"><i class="fas fa-paper-plane"></i> Enviar para a escola de destino</button>
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
          <li>A escola de origem continua podendo <b>consultar</b> o histórico de quem foi seu aluno.</li>
          <li>A chamada do dia passa a considerar a data de início: até a véspera o aluno aparece na turma antiga, daquele dia em diante na nova.</li>
          <li>O relatório anual mostra <b>o ano inteiro</b>, com o quadro de escolas e turmas por período.</li>
        </ul>
      </div>
    </div>
  </div>
</div>

<?php include(FOOTER_TEMPLATE); ?>