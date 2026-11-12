<?php require_once('processa/index_processa.php'); ?>
<?php include(ch_gestor() ? HEADER_TEMPLATE : HEADER_TEMPLATE2); ?>

<?php ch_cabecalho('Chamada de Alunos', 'fa-clipboard-user'); ?>

<form method="get" action="index.php" class="row g-2 align-items-end mb-4">
  <div class="col-md-6">
    <label class="fw-bold">Turma</label>
    <select name="turma_id" class="form-select" required>
      <option value="">Selecione...</option>
      <?php foreach ($turmas as $t): ?>
        <option value="<?= (int)$t['ID'] ?>" <?= (int)$t['ID'] === $turmaId ? 'selected' : '' ?>>
          <?= ch_h($t['UNIDADE']) ?> — <?= ch_h($t['NOME']) ?> (<?= ch_h($t['PERIODO']) ?>, <?= (int)$t['ANO_LETIVO'] ?>)
        </option>
      <?php endforeach; ?>
    </select>
  </div>
  <div class="col-md-3">
    <label class="fw-bold">Data</label>
    <input type="date" name="data" class="form-control" max="<?= ch_hoje() ?>" value="<?= ch_h($data) ?>" required>
  </div>
  <div class="col-md-3">
    <button class="btn btn-primary w-100"><i class="fas fa-magnifying-glass"></i> Abrir chamada</button>
  </div>
</form>

<?php if (!$turmas): ?>
  <div class="alert alert-warning">
    Nenhuma turma disponível para o seu usuário.
    <?php if (ch_gestor()): ?>Cadastre uma turma em <a href="<?= ch_url('turmas/index.php') ?>">Turmas</a>.
    <?php else: ?>Peça à gestão para incluir você na <strong>equipe da turma</strong> em Turmas &rarr; Editar.<?php endif; ?>
  </div>
<?php elseif (!$turma): ?>
  <div class="alert alert-info">Selecione uma turma e a data para iniciar a chamada.</div>
<?php else: ?>

  <div class="painel-turma mb-4">
    <div class="painel-turma-info">
      <div class="painel-turma-icone"><i class="fas fa-children"></i></div>
      <div>
        <div class="painel-turma-nome"><?= ch_h($turma['NOME']) ?></div>
        <div class="painel-turma-sub">
          <?= ch_h($turma['UNIDADE']) ?><br>
          <i class="far fa-clock"></i> <?= ch_h($turma['PERIODO']) ?>
          (<?= ch_h($turma['HORA_INICIO']) ?>–<?= ch_h($turma['HORA_FIM']) ?>)
          &nbsp;·&nbsp; <i class="far fa-calendar"></i> <?= ch_data_longa($data) ?>
        </div>
        <?php if ($equipe): ?>
        <div class="painel-turma-equipe">
          <?php foreach ($equipe as $m): ?>
            <span class="avatar-prof avatar-mini <?= $m['PAPEL'] === 'REGENTE' ? 'regente' : '' ?>"
                  title="<?= ch_h($m['NOME']) ?> — <?= ch_h(eq_papel_rotulo($m['PAPEL'])) ?> · <?= ch_h(eq_turno_rotulo($m['TURNO'])) ?>">
              <?= ch_h(ch_iniciais($m['NOME'])) ?>
            </span>
          <?php endforeach; ?>
          <small><?= count($equipe) ?> professor(a/es) na equipe</small>
        </div>
        <?php endif; ?>
        <?php if ($substitutos): ?>
        <div class="mt-2">
          <?php foreach ($substitutos as $s): ?>
            <span class="badge bg-danger">
              <i class="fas fa-user-clock"></i>
              <?= ch_h($s['NOME']) ?> substitui <?= ch_h($s['NOME_SUBSTITUIDO']) ?>
              · <?= ch_h(eq_turno_rotulo($s['TURNO'])) ?>
              <?= $s['DATA_FIM'] ? ' até ' . ch_h(ch_data_br($s['DATA_FIM'])) : '' ?>
            </span>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>
      </div>
    </div>
    <div class="painel-turma-placar">
      <?php foreach (ch_status_lista() as $sigla => $info): ?>
        <div class="placar-item placar-<?= $sigla ?>">
          <span class="placar-num"><?= (int)$resumo[$sigla] ?></span>
          <span class="placar-rot"><i class="fas <?= $info['icone'] ?>"></i> <?= ch_h($info['rotulo']) ?></span>
        </div>
      <?php endforeach; ?>
    </div>
  </div>

  <?php if (ch_secretario_escolar()): ?>
    <div class="alert alert-info">
      <i class="fas fa-eye"></i>
      <strong>Somente consulta.</strong>
      O Secretário Escolar não pode confirmar presenças nem retificar a chamada.
    </div>
  <?php endif; ?>

  <?php if ($bloqueio): ?>
    <div class="alert alert-warning">
      <i class="fas fa-triangle-exclamation"></i> <strong>Chamada bloqueada.</strong> <?= ch_h($bloqueio) ?>
    </div>
  <?php endif; ?>

  <?php if ($comSaude): ?>
    <div class="alert alert-danger eq-aviso-turma">
      <h3 class="h6 mb-2"><i class="fas fa-heart-pulse"></i> Atenção à saúde de <?= count($comSaude) ?> aluno(s) desta turma</h3>
      <ul class="mb-0 small">
        <?php foreach ($comSaude as $a): ?>
          <li><strong><?= ch_h($a['NOME']) ?></strong> — <?= ch_h(eq_saude_resumo($a)) ?></li>
        <?php endforeach; ?>
      </ul>
    </div>
  <?php endif; ?>

  <?php if (!$alunos): ?>
    <div class="alert alert-info">Não há alunos ativos nesta turma.</div>
  <?php else: ?>

  <div class="d-flex flex-wrap gap-2 mb-3 align-items-center">
    <?php if ($podeConfirmar && !$bloqueio && $pendentes): ?>
      <button type="button" class="btn btn-sm btn-outline-success" data-marcar-todos="P">
        <i class="fas fa-check-double"></i> Marcar pendentes como presentes
      </button>
    <?php endif; ?>
    <a class="btn btn-sm btn-outline-secondary" href="exportar.php?turma_id=<?= (int)$turmaId ?>&data=<?= ch_h($data) ?>">
      <i class="fas fa-file-csv"></i> Exportar CSV
    </a>
    <a class="btn btn-sm btn-outline-primary" target="_blank" rel="noopener"
       href="<?= ch_url('alunos/crachas_turma.php?turma_id=' . (int)$turmaId) ?>">
      <i class="fas fa-id-card"></i> Imprimir crachás da turma
    </a>
    <?php if ($confirmados): ?>
      <a class="btn btn-sm btn-outline-secondary" href="historico.php?turma_id=<?= (int)$turmaId ?>&data=<?= ch_h($data) ?>">
        <i class="fas fa-clock-rotate-left"></i> Histórico de retificações
      </a>
    <?php endif; ?>
  </div>

  <?php if ($podeConfirmar && !$bloqueio && $pendentes): ?>
  <form method="post" action="salvar.php" id="form-chamada">
    <?= ch_csrf_campo() ?>
    <input type="hidden" name="turma_id" value="<?= (int)$turmaId ?>">
    <input type="hidden" name="data" value="<?= ch_h($data) ?>">

    <div class="secao-chamada">
      <h3 class="secao-titulo">
        <span class="secao-icone pendente"><i class="fas fa-hourglass-half"></i></span>
        Aguardando confirmação
        <span class="badge bg-warning text-dark"><?= count($pendentes) ?></span>
      </h3>
      <p class="text-muted small mb-3">
        A presença é confirmada <strong>uma única vez</strong> por aluno no dia.
        Depois disso, correções são feitas por <strong>retificação</strong>, com motivo registrado.
      </p>

      <div class="grade-alunos">
        <?php foreach ($pendentes as $a): ?>
        <div class="cartao-aluno" data-aluno="<?= (int)$a['ID'] ?>">
          <div class="cartao-aluno-topo">
            <span class="avatar-aluno"><?= ch_h(ch_iniciais($a['NOME'])) ?></span>
            <div class="cartao-aluno-id">
              <strong><?= ch_h($a['NOME']) ?></strong>
              <small><?= ch_h($a['MATRICULA']) ?> · <?= ch_h(ch_idade_texto($a['NASCIMENTO'], $data)) ?></small>
            </div>
            <?php if (ch_pode_escrever_observacao_turma($turmaId, $turma['ID_UNIDADE'])): ?>
            <a class="btn btn-sm btn-outline-primary botao-obs" title="Registrar observação"
               href="<?= ch_url('observacoes/add.php?aluno_id=' . (int)$a['ID'] . '&data=' . ch_h($data) . '&voltar=' . rawurlencode('chamada/index.php?turma_id=' . $turmaId . '&data=' . $data)) ?>">
              <i class="far fa-comment-dots"></i><?= isset($obsPorAluno[(int)$a['ID']]) ? ' ' . $obsPorAluno[(int)$a['ID']] : '' ?>
            </a>
            <?php endif; ?>
          </div>
          <?= eq_caixa_saude($a, true) ?>
          <div class="cartao-aluno-status">
            <?php foreach (ch_status_lista() as $sigla => $info): ?>
              <input type="radio" class="btn-check" name="status[<?= (int)$a['ID'] ?>]"
                     id="st<?= (int)$a['ID'] ?><?= $sigla ?>" value="<?= $sigla ?>">
              <label class="botao-status status-<?= $sigla ?>" for="st<?= (int)$a['ID'] ?><?= $sigla ?>"
                     title="<?= ch_h($info['rotulo']) ?>">
                <i class="fas <?= $info['icone'] ?>"></i><span><?= ch_h($info['rotulo']) ?></span>
              </label>
            <?php endforeach; ?>
          </div>
          <input type="text" class="form-control form-control-sm campo-just" name="justificativa[<?= (int)$a['ID'] ?>]"
                 maxlength="500" value="" placeholder="Justificativa (obrigatória se falta justificada)">
        </div>
        <?php endforeach; ?>
      </div>
    </div>

    <div class="my-4">
      <a href="index.php" class="btn btn-outline-secondary">Cancelar</a>
      <button type="submit" class="btn btn-success btn-lg">
        <i class="fas fa-lock"></i> Confirmar presença de <?= count($pendentes) ?> aluno(s)
      </button>
    </div>
  </form>
  <?php endif; ?>

  <?php if ($confirmados): ?>
  <div class="secao-chamada">
    <h3 class="secao-titulo">
      <span class="secao-icone confirmado"><i class="fas fa-lock"></i></span>
      Presença confirmada
      <span class="badge bg-success"><?= count($confirmados) ?></span>
    </h3>
    <p class="text-muted small mb-3">
      Estes registros estão <strong>travados</strong>. Para corrigir, use
      <strong>Retificar</strong> e informe o motivo — a alteração fica no histórico.
    </p>

    <div class="grade-alunos">
      <?php foreach ($confirmados as $a): $info = ch_status_lista()[$a['STATUS']]; ?>
      <div class="cartao-aluno travado">
        <div class="cartao-aluno-topo">
          <span class="avatar-aluno"><?= ch_h(ch_iniciais($a['NOME'])) ?></span>
          <div class="cartao-aluno-id">
            <strong><?= ch_h($a['NOME']) ?></strong>
            <small><?= ch_h($a['MATRICULA']) ?> · <?= ch_h(ch_idade_texto($a['NASCIMENTO'], $data)) ?></small>
          </div>
          <?php if (ch_pode_escrever_observacao_turma($turmaId, $turma['ID_UNIDADE'])): ?>
          <a class="btn btn-sm btn-outline-primary botao-obs" title="Registrar observação"
             href="<?= ch_url('observacoes/add.php?aluno_id=' . (int)$a['ID'] . '&data=' . ch_h($data) . '&voltar=' . rawurlencode('chamada/index.php?turma_id=' . $turmaId . '&data=' . $data)) ?>">
            <i class="far fa-comment-dots"></i><?= isset($obsPorAluno[(int)$a['ID']]) ? ' ' . $obsPorAluno[(int)$a['ID']] : '' ?>
          </a>
          <?php endif; ?>
        </div>

        <?= eq_caixa_saude($a, true) ?>
        <div class="cartao-aluno-confirmado">
          <span class="selo-status status-<?= ch_h($a['STATUS']) ?>">
            <i class="fas <?= $info['icone'] ?>"></i> <?= ch_h($info['rotulo']) ?>
          </span>
          <?php if (!empty($a['JUSTIFICATIVA'])): ?>
            <div class="just-exibida"><i class="fas fa-quote-left"></i> <?= ch_h($a['JUSTIFICATIVA']) ?></div>
          <?php endif; ?>
          <div class="confirmado-meta">
            <i class="fas fa-lock"></i>
            Confirmada <?= ch_h(ch_confirma_texto($a['CONFIRMADA_EM'])) ?>
            <?php if (!empty($a['CONFIRMADA_POR_NOME'])): ?>
              por <?= ch_h($a['CONFIRMADA_POR_NOME']) ?>
            <?php endif; ?>
            <?php if ((int)$a['RETIFICACOES'] > 0): ?>
              · <span class="badge bg-secondary"><?= (int)$a['RETIFICACOES'] ?> retificação(ões)</span>
            <?php endif; ?>
          </div>
        </div>

        <?php if ($podeRetificar && !$bloqueio): ?>
        <button type="button" class="btn btn-sm btn-outline-warning botao-retificar"
                data-bs-toggle="modal" data-bs-target="#modalRetificar"
                data-chamada="<?= (int)$a['ID_CHAMADA'] ?>"
                data-aluno="<?= ch_h($a['NOME']) ?>"
                data-status="<?= ch_h($a['STATUS']) ?>"
                data-justificativa="<?= ch_h($a['JUSTIFICATIVA'] ?? '') ?>">
          <i class="fas fa-pen-to-square"></i> Retificar
        </button>
        <?php endif; ?>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endif; ?>

  <?php if (!$bloqueio && !$pendentes && $confirmados): ?>
    <div class="alert alert-success mt-4">
      <i class="fas fa-circle-check"></i>
      <strong>Chamada do dia concluída.</strong>
      Todos os <?= count($confirmados) ?> alunos tiveram a presença confirmada.
    </div>
  <?php endif; ?>

  <!-- Modal de retificação -->
  <div class="modal fade" id="modalRetificar" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content conteudo-modal">
        <form method="post" action="retificar.php" id="form-retificar">
          <?= ch_csrf_campo() ?>
          <div class="modal-header cabecalho-modal">
            <h5 class="modal-title"><i class="fas fa-pen-to-square"></i> Retificar presença</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
          </div>
          <div class="modal-body">
            <input type="hidden" name="turma_id" value="<?= (int)$turmaId ?>">
            <input type="hidden" name="data" value="<?= ch_h($data) ?>">
            <input type="hidden" name="chamada_id" id="ret-chamada" value="">

            <div class="alert alert-light border">
              <strong id="ret-aluno"></strong><br>
              <small class="text-muted">Situação atual: <span id="ret-atual"></span></small>
            </div>

            <label class="fw-bold mb-2">Nova situação</label>
            <div class="cartao-aluno-status mb-3">
              <?php foreach (ch_status_lista() as $sigla => $info): ?>
                <input type="radio" class="btn-check" name="status" id="ret<?= $sigla ?>" value="<?= $sigla ?>">
                <label class="botao-status status-<?= $sigla ?>" for="ret<?= $sigla ?>">
                  <i class="fas <?= $info['icone'] ?>"></i><span><?= ch_h($info['rotulo']) ?></span>
                </label>
              <?php endforeach; ?>
            </div>

            <div class="mb-3">
              <label class="fw-bold">Justificativa da falta</label>
              <input type="text" class="form-control" name="justificativa" id="ret-just" maxlength="500"
                     placeholder="Obrigatória se a nova situação for falta justificada">
            </div>

            <div class="mb-0">
              <label class="fw-bold">Motivo da retificação <span class="text-danger">*</span></label>
              <textarea class="form-control" name="motivo" id="ret-motivo" rows="3" maxlength="500" required
                        placeholder="Ex.: responsável informou por telefone que o aluno esteve presente; lançamento inicial incorreto."></textarea>
              <div class="form-text">Obrigatório. Fica registrado no histórico junto com seu nome e a data/hora.</div>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
            <button type="submit" class="btn btn-warning"><i class="fas fa-pen-to-square"></i> Retificar</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <script>
    /* "Marcar pendentes como presentes" só toca nos cartões ainda não confirmados. */
    document.querySelectorAll('[data-marcar-todos]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var v = btn.getAttribute('data-marcar-todos');
        document.querySelectorAll('#form-chamada input[type=radio][value="' + v + '"]')
          .forEach(function (r) { r.checked = true; });
      });
    });

    var formChamada = document.getElementById('form-chamada');
    if (formChamada) {
      formChamada.addEventListener('submit', function (e) {
        /* Alinhado ao servidor: é possível confirmar só parte da turma — quem
           ficar sem marcação continua pendente. Só a falta justificada sem
           justificativa é impedida. */
        var problemas = [], marcados = 0, semMarcacao = 0;
        formChamada.querySelectorAll('.cartao-aluno').forEach(function (card) {
          var marcado = card.querySelector('input[type=radio]:checked');
          var nome = card.querySelector('.cartao-aluno-id strong').textContent.trim();
          if (!marcado) { semMarcacao++; return; }
          marcados++;
          if (marcado.value === 'J') {
            var j = card.querySelector('.campo-just');
            if (!j.value.trim()) { problemas.push(nome + ' (justificativa obrigatória)'); }
          }
        });
        if (problemas.length) {
          e.preventDefault();
          alert('Verifique os alunos a seguir antes de confirmar:\n\n' + problemas.join('\n'));
          return;
        }
        if (marcados === 0) {
          e.preventDefault();
          alert('Marque a situação de pelo menos um aluno antes de confirmar.');
          return;
        }
        var aviso = 'Confirmar a presença de ' + marcados + ' aluno(s)?\n\n'
                  + 'Depois de confirmada, a presença só poderá ser alterada por retificação.';
        if (semMarcacao > 0) {
          aviso += '\n\n' + semMarcacao + ' aluno(s) sem marcação continuarão aguardando confirmação.';
        }
        if (!confirm(aviso)) { e.preventDefault(); }
      });
    }

    /* Preenche o modal com os dados do aluno clicado. */
    var modalRet = document.getElementById('modalRetificar');
    if (modalRet) {
      var rotulos = <?= json_encode(array_map(function ($i) { return $i['rotulo']; }, ch_status_lista()), JSON_UNESCAPED_UNICODE) ?>;
      modalRet.addEventListener('show.bs.modal', function (ev) {
        var b = ev.relatedTarget;
        if (!b) return;
        document.getElementById('ret-chamada').value = b.getAttribute('data-chamada');
        document.getElementById('ret-aluno').textContent = b.getAttribute('data-aluno');
        document.getElementById('ret-atual').textContent = rotulos[b.getAttribute('data-status')] || '';
        document.getElementById('ret-just').value = b.getAttribute('data-justificativa') || '';
        document.getElementById('ret-motivo').value = '';
        modalRet.querySelectorAll('input[name=status]').forEach(function (r) {
          r.checked = (r.value === b.getAttribute('data-status'));
        });
      });
      document.getElementById('form-retificar').addEventListener('submit', function (e) {
        var st = modalRet.querySelector('input[name=status]:checked');
        if (!st) { e.preventDefault(); alert('Escolha a nova situação.'); return; }
        if (st.value === 'J' && !document.getElementById('ret-just').value.trim()) {
          e.preventDefault(); alert('A falta justificada exige justificativa.'); return;
        }
        if (!document.getElementById('ret-motivo').value.trim()) {
          e.preventDefault(); alert('Informe o motivo da retificação.');
        }
      });
    }
  </script>
  <?php endif; ?>
<?php endif; ?>

<?php include(FOOTER_TEMPLATE); ?>
