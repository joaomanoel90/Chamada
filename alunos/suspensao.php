<?php require_once('processa/suspensao_processa.php'); ?>
<?php include(HEADER_TEMPLATE); ?>

<?php
$voltar = '<a class="btn btn-outline-secondary" href="' . ch_url('alunos/view.php?id=' . (int)$idAluno) . '"><i class="fas fa-arrow-left"></i> Voltar à ficha</a>';
ch_cabecalho('Suspensão do aluno', 'fa-calendar-xmark', $voltar);
?>

<div class="alert alert-info">
  <i class="fas fa-circle-info me-1"></i>
  Informe o <b>período</b> e o <b>motivo</b>. O aluno fica com <b>falta em todas as aulas letivas</b>
  do intervalo, inclusive as já lançadas (nesse caso, a mudança é registrada como retificação).
  Depois é possível <b>tirar datas específicas</b>, escolhendo se o dia que já passado vira presença
  ou fica pendente. Todo registro mostra quando, quem determinou e por quê.
</div>

<?php if (!susp_pronto()): ?>
  <div class="alert alert-danger">
    <i class="fas fa-triangle-exclamation me-1"></i>
    Este banco ainda não tem a suspensão instalada. Execute
    <code>sql_inicial/ATUALIZAR_SUSPENSAO_ALUNOS.sql</code> no SQL Server e recarregue a página.
  </div>
<?php endif; ?>

<?php if ($erros): ?>
  <div class="alert alert-danger">
    <ul class="mb-0"><?php foreach ($erros as $e): ?><li><?= ch_h($e) ?></li><?php endforeach; ?></ul>
  </div>
<?php endif; ?>

<?php if ($emVigor): ?>
  <div class="alert alert-warning d-flex flex-wrap justify-content-between align-items-center gap-2">
    <span>
      <i class="fas fa-ban me-1"></i>
      <b>Suspenso em vigor</b> de <?= ch_h(ch_data_br($emVigor['DATA_INICIO_TXT'])) ?> a
      <?= ch_h(ch_data_br($emVigor['DATA_FIM_TXT'])) ?> ·
      <?= ch_h($emVigor['MOTIVO']) ?> ·
      registrado por <?= ch_h($emVigor['USUARIO_NOME']) ?>
      (<?= (int)$emVigor['FALTAS_GERADAS'] ?> aula(s) ainda suspensa(s)).
    </span>
    <a class="btn btn-sm btn-outline-dark" href="<?= ch_url('chamada/index.php') ?>">Ver chamada</a>
  </div>
<?php endif; ?>

<div class="row g-3">
  <div class="col-lg-5">
    <div class="card border-0 shadow-sm rounded-4">
      <div class="card-body">
        <h5 class="mb-1"><i class="fas fa-user-slash text-danger me-1"></i> Registrar suspensão</h5>
        <p class="small text-muted mb-3"><?= ch_h($aluno['NOME']) ?> · matrícula <?= ch_h($aluno['MATRICULA']) ?> · <?= ch_h($aluno['UNIDADE']) ?></p>

        <form method="post" action="suspensao.php">
          <?= ch_csrf_campo() ?>
          <input type="hidden" name="aluno_id" value="<?= (int)$idAluno ?>">
          <input type="hidden" name="acao" value="SUSPENDER">
          <div class="row g-2">
            <div class="col-sm-6">
              <label class="form-label fw-bold small" for="data_inicio">Início <span class="text-danger">*</span></label>
              <input type="date" id="data_inicio" name="data_inicio" class="form-control form-control-sm"
                     value="<?= ch_h($d['DATA_INICIO']) ?>" required>
            </div>
            <div class="col-sm-6">
              <label class="form-label fw-bold small" for="data_fim">Fim <span class="text-danger">*</span></label>
              <input type="date" id="data_fim" name="data_fim" class="form-control form-control-sm"
                     value="<?= ch_h($d['DATA_FIM']) ?>" required>
            </div>
          </div>
          <div class="mt-3">
            <label class="form-label fw-bold small" for="motivo_suspensao">Motivo <span class="text-danger">*</span></label>
            <textarea id="motivo_suspensao" name="motivo" class="form-control form-control-sm" rows="3"
                      maxlength="500" required
                      placeholder="Ex.: sanção disciplinar; decisão da direção; afastamento determinado pela secretaria."><?= ch_h($d['MOTIVO']) ?></textarea>
            <div class="form-text">Fica no histórico e aparece na justificativa das faltas geradas.</div>
          </div>
          <div class="mt-3">
            <button type="submit" class="btn btn-danger btn-sm">
              <i class="fas fa-ban me-1"></i> Registrar suspensão e gerar faltas
            </button>
          </div>
        </form>
      </div>
    </div>

    <div class="card border-0 shadow-sm rounded-4 mt-3">
      <div class="card-body">
        <h5 class="mb-2"><i class="fas fa-circle-question me-1"></i> Como as faltas são tratadas</h5>
        <ul class="small text-muted mb-0">
          <li>Só recebem falta os dias com aula no calendário da turma; fins de semana, feriados e exceções não letivas são ignorados.</li>
          <li>Se a turma mudar no meio do período, cada data usa a turma em que o aluno estava naquele dia.</li>
          <li>Uma falta já registrada pelo professor é preservada. Presença, atraso ou falta justificada viram falta com retificação automática.</li>
          <li>Ao tirar dias, você escolhe a data e a aula. Nos dias que já passaram, pode lançar presença ou apenas remover a falta e deixar a chamada pendente.</li>
          <li>As aulas de hoje em diante só voltam a ficar pendentes. Quando todas são liberadas, a suspensão é encerrada.</li>
        </ul>
      </div>
    </div>
  </div>

  <div class="col-lg-7">
    <div class="card border-0 shadow-sm rounded-4">
      <div class="card-body">
        <h5 class="mb-3"><i class="fas fa-clock-rotate-left me-1"></i> Histórico de suspensões</h5>
        <?php if (!$historico): ?>
          <p class="text-muted small mb-0">Nenhuma suspensão registrada para este aluno.</p>
        <?php else: ?>
          <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
              <thead>
                <tr>
                  <th>Período</th>
                  <th>Situação</th>
                  <th>Motivo / registro</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($historico as $s): ?>
                  <?php
                    $hojeIso = ch_hoje();
                    if ($s['SITUACAO'] === 'ENCERRADA') {
                        $situacao = 'Encerrada'; $classe = 'secondary';
                    } elseif ($s['DATA_INICIO_TXT'] > $hojeIso) {
                        $situacao = 'Agendada'; $classe = 'info';
                    } elseif ($s['DATA_FIM_TXT'] >= $hojeIso) {
                        $situacao = 'Em vigor'; $classe = 'danger';
                    } else {
                        $situacao = 'Concluída'; $classe = 'success';
                    }
                  ?>
                  <tr>
                    <td class="text-nowrap small">
                      <?= ch_h(ch_data_br($s['DATA_INICIO_TXT'])) ?> a <?= ch_h(ch_data_br($s['DATA_FIM_TXT'])) ?>
                    </td>
                    <td>
                      <span class="badge bg-<?= $classe ?>"><?= $situacao ?></span>
                      <div class="small text-muted mt-1"><?= (int)$s['FALTAS_GERADAS'] ?> aula(s) suspensa(s)</div>
                      <div class="small text-muted"><?= (int)$s['LIBERACOES'] ?> liberação(ões)</div>
                    </td>
                    <td>
                      <div class="small fw-bold"><?= nl2br(ch_h($s['MOTIVO'])) ?></div>
                      <div class="small text-muted">
                        <?= ch_h($s['CRIADO_EM_TXT']) ?> por <?= ch_h($s['USUARIO_NOME']) ?>
                      </div>
                      <?php if ($s['SITUACAO'] === 'ENCERRADA'): ?>
                        <div class="small text-muted">
                          Encerrada em <?= ch_h($s['ENCERRADA_EM_TXT']) ?> por
                          <?= ch_h($s['USUARIO_ENCERRAMENTO_NOME']) ?>:
                          <?= nl2br(ch_h($s['MOTIVO_ENCERRAMENTO'])) ?>
                        </div>
                      <?php endif; ?>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>
      </div>
    </div>

    <?php foreach ($liberaveis as $lib): ?>
      <?php $mantem = $d['ACAO'] === 'LIBERAR' && $d['SUSPENSAO_ID'] === $lib['ID']; ?>
      <div class="card border-0 shadow-sm rounded-4 mt-3">
        <div class="card-body">
          <h5 class="mb-1"><i class="fas fa-door-open text-success me-1"></i> Tirar dias da suspensão</h5>
          <p class="small text-muted mb-3">
            <?= ch_h(ch_data_br($lib['DATA_INICIO_TXT'])) ?> a <?= ch_h(ch_data_br($lib['DATA_FIM_TXT'])) ?>
            · <?= ch_h($lib['MOTIVO']) ?>
          </p>

          <form method="post" action="suspensao.php">
            <?= ch_csrf_campo() ?>
            <input type="hidden" name="aluno_id" value="<?= (int)$idAluno ?>">
            <input type="hidden" name="acao" value="LIBERAR">
            <input type="hidden" name="suspensao_id" value="<?= (int)$lib['ID'] ?>">

            <label class="form-label fw-bold small">Datas e aulas que serão liberadas <span class="text-danger">*</span></label>
            <div class="row g-1 mb-3">
              <?php foreach ($lib['ITENS'] as $item): ?>
                <div class="col-sm-6">
                  <label class="small d-flex gap-2 align-items-start border rounded px-2 py-1 h-100">
                    <input class="form-check-input mt-1 flex-shrink-0" type="checkbox"
                           name="dias[]" value="<?= ch_h($item['CHAVE']) ?>"
                           <?= $mantem && in_array($item['CHAVE'], $d['DIAS'], true) ? 'checked' : '' ?>>
                    <span>
                      <?= ch_h(ch_data_br($item['DATA_TXT'])) ?> — <?= ch_h(ch_aula_rotulo($item['AULA'])) ?>
                      <span class="text-muted">(<?= $item['PASSOU'] ? 'já passou' : 'futura — ficará pendente' ?>)</span>
                    </span>
                  </label>
                </div>
              <?php endforeach; ?>
            </div>

            <div class="row g-2">
              <div class="col-md-6">
                <label class="form-label fw-bold small mb-1">Nos dias que já passaram</label>
                <div class="form-check small">
                  <input class="form-check-input" type="radio" name="presenca" id="presenca_nao_<?= (int)$lib['ID'] ?>"
                         value="0" <?= !$mantem || !$d['PRESENCA'] ? 'checked' : '' ?>>
                  <label class="form-check-label" for="presenca_nao_<?= (int)$lib['ID'] ?>">
                    Só remover a falta e deixar pendente
                  </label>
                </div>
                <div class="form-check small">
                  <input class="form-check-input" type="radio" name="presenca" id="presenca_sim_<?= (int)$lib['ID'] ?>"
                         value="1" <?= $mantem && $d['PRESENCA'] ? 'checked' : '' ?>>
                  <label class="form-check-label" for="presenca_sim_<?= (int)$lib['ID'] ?>">
                    Lançar presença (com retificação)
                  </label>
                </div>
                <div class="form-text">Só vale para as datas marcadas como “já passou”.</div>
              </div>
              <div class="col-md-6">
                <label class="form-label fw-bold small" for="motivo_liberar_<?= (int)$lib['ID'] ?>">
                  Motivo da liberação <span class="text-danger">*</span>
                </label>
                <textarea id="motivo_liberar_<?= (int)$lib['ID'] ?>" name="motivo"
                          class="form-control form-control-sm" rows="2" maxlength="500" required
                          placeholder="Ex.: retorno autorizado; decisão da direção; erro na suspensão."><?= $mantem ? ch_h($d['MOTIVO']) : '' ?></textarea>
              </div>
            </div>

            <div class="mt-3">
              <button type="submit" class="btn btn-success btn-sm">
                <i class="fas fa-check me-1"></i> Liberar datas selecionadas
              </button>
            </div>
          </form>

        </div>
      </div>
    <?php endforeach; ?>

    <div class="card border-0 shadow-sm rounded-4 mt-3">
      <div class="card-body">
        <h5 class="mb-3"><i class="fas fa-list-check me-1"></i> Datas já liberadas</h5>
        <?php if (!$liberacoes): ?>
          <p class="text-muted small mb-0">Nenhuma data/aula foi liberada até agora.</p>
        <?php else: ?>
          <div class="table-responsive">
            <table class="table table-sm small align-middle mb-0">
              <thead>
                <tr><th>Suspensão</th><th>Data</th><th>Aula</th><th>Resultado</th><th>Motivo / registro</th></tr>
              </thead>
              <tbody>
                <?php foreach ($liberacoes as $l): ?>
                  <tr>
                    <td class="text-nowrap"><?= ch_h($l['PERIODO']) ?></td>
                    <td class="text-nowrap"><?= ch_h(ch_data_br($l['DATA_TXT'])) ?></td>
                    <td><?= ch_h(ch_aula_rotulo((int)$l['AULA_SEQ'])) ?></td>
                    <td>
                      <span class="badge bg-<?= $l['PRESENCA_LANCADA'] ? 'success' : 'secondary' ?>">
                        <?= $l['PRESENCA_LANCADA'] ? 'Presença' : 'Pendente' ?>
                      </span>
                    </td>
                    <td>
                      <div><?= nl2br(ch_h($l['MOTIVO'])) ?></div>
                      <div class="text-muted">
                        <?= ch_h($l['CRIADO_EM_TXT']) ?> por <?= ch_h($l['USUARIO_NOME']) ?>
                      </div>
                    </td>
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

<?php include(FOOTER_TEMPLATE); ?>
