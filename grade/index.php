<?php require_once('processa/index_processa.php'); ?>
<?php include(HEADER_TEMPLATE); ?>
<style nonce="<?= ch_csp_nonce() ?>">
	.grade-tabela { font-size: .85rem; }
	.grade-tabela td, .grade-tabela th { vertical-align: middle; }
	.grade-tabela select { min-width: 150px; }
	.grade-prof { font-size: .72rem; color: #66717e; margin-top: .25rem; line-height: 1.2; }
	/* O CSS global usa overflow:visible em telas largas; aqui a grade precisa
	   rolar sempre, mesmo acima de 992px, para todos os dias ficarem acessíveis. */
	.table-responsive.grade-scroll { overflow-x: auto !important; overflow-y: hidden !important; }
	.grade-tabela thead th:first-child,
	.grade-tabela tbody th { position: sticky; left: 0; background: #fff; z-index: 1; }
	.grade-tabela thead th:first-child { z-index: 2; }
	.grade-ajuda { font-size: .8rem; color: #66717e; }
</style>

<?php
$botaoVoltar = $turmaId > 0
    ? '<a class="btn btn-outline-secondary" href="' . ch_url('chamada/ficha.php?turma_id=' . (int)$turmaId)
        . '"><i class="fas fa-file-lines"></i> Ver ficha</a>'
    : '';
ch_cabecalho('Grade Diária da Turma', 'fas fa-table-cells', $botaoVoltar);
?>

<form method="get" action="index.php" class="row g-3 align-items-end mb-4 no-print">
  <div class="col-md-6">
    <label class="form-label small fw-bold mb-1" for="gradeTurma">Turma</label>
    <select name="turma_id" id="gradeTurma" class="form-select" data-auto-submit required>
      <option value="">Selecione...</option>
      <?php foreach ($turmas as $t): ?>
        <option value="<?= (int)$t['ID'] ?>"<?= (int)$t['ID'] === $turmaId ? ' selected' : '' ?>>
          <?= ch_h($t['UNIDADE']) ?> — Turma <?= ch_h(ch_series_ensino()[$t['SERIE'] ?? '']['rotulo'] ?? 'não classificada') ?>
          · Sala <?= ch_h($t['NOME']) ?> · <?= ch_h($t['PERIODO']) ?> (<?= (int)$t['ANO_LETIVO'] ?>)
        </option>
      <?php endforeach; ?>
    </select>
  </div>
  <div class="col-md-6 grade-ajuda">
    A turma tem <?= (int)ch_turma_aulas_dia($turmaId) ?> aula(s) por dia. Cada matéria pode aparecer
    até o total de aulas atribuídas a ela na turma; o professor vem da Atribuição e não é editável aqui.
  </div>
</form>

<?php if ($turma): ?>
  <?php
  $conflitosGrade = ch_grade_conflitos_da_turma($turmaId);
  if ($conflitosGrade):
    ?>
    <div class="alert alert-warning">
      <strong>Conflitos de professor nesta turma:</strong>
      <ul class="mb-0 mt-1">
        <?php foreach ($conflitosGrade as $msg): ?><li><?= ch_h($msg) ?></li><?php endforeach; ?>
      </ul>
    </div>
  <?php endif; ?>
<?php endif; ?>

<?php if (!$turmas): ?>
  <div class="alert alert-warning">Nenhuma turma disponível para o seu usuário.</div>
<?php elseif (!$turma): ?>
  <div class="alert alert-info">Selecione uma turma para montar a grade.</div>
<?php elseif (!$disciplinas): ?>
  <div class="alert alert-warning">
    Esta turma ainda não tem matérias com professor atribuído. Cadastre as matérias em
    <a href="<?= ch_url('turmas/index.php') ?>">Turmas</a> (editar turma &rarr; Matérias) e defina os
    responsáveis em <a href="<?= ch_url('atribuicoes/index.php') ?>">Atribuição de Professores</a>.
  </div>
<?php else: ?>
  <div class="card card-body mb-3">
    <strong><?= ch_h($turma['UNIDADE']) ?></strong> ·
    Turma <?= ch_h(ch_series_ensino()[$turma['SERIE'] ?? '']['rotulo'] ?? 'não classificada') ?> ·
    Sala <?= ch_h($turma['NOME']) ?> · <?= ch_h($turma['PERIODO']) ?>
    <?php if ($gradeBloqueada): ?><span class="badge bg-secondary ms-2">Grade salva</span><?php endif; ?>
    <div class="small text-muted">
      <?= count($disciplinas) ?> matéria(s) · <?= count($professores) ?> professor(es) vinculado(s) à turma
    </div>
  </div>

  <?php if (!$professores): ?>
    <div class="alert alert-info">
      Nenhum professor vinculado ainda. Você pode salvar as disciplinas sem professor;
      vincule professores em <a href="<?= ch_url('atribuicoes/index.php') ?>">Atribuição de Professores</a>
      ou na equipe da turma.
    </div>
  <?php endif; ?>

  <form method="post" action="index.php?turma_id=<?= (int)$turmaId ?>">
    <?= ch_csrf_campo() ?>
    <input type="hidden" name="turma_id" value="<?= (int)$turmaId ?>">
    <?php $aulasDaTurma = ch_aula_lista_turma($turmaId); ?>
    <?php $desabilitado = $gradeBloqueada ? ' disabled' : ''; ?>
    <div class="grade-ajuda mb-2">Use a barra horizontal para ver os outros dias. Ao escolher uma matéria, o número restante diminui; quando chega a zero, ela sai dos demais selects. O professor vem da Atribuição e aparece abaixo da disciplina.</div>
    <div class="table-responsive grade-scroll">
      <table class="table table-bordered grade-tabela">
        <thead>
          <tr>
            <th style="width: 110px;">Aula</th>
            <?php foreach (ch_grade_dias() as $rotuloDia): ?>
              <th><?= ch_h($rotuloDia) ?></th>
            <?php endforeach; ?>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($aulasDaTurma as $aula => $rotuloAula): ?>
            <tr>
              <th><?= ch_h($rotuloAula) ?></th>
              <?php foreach (ch_grade_dias() as $dia => $rotuloDia): ?>
                <?php
                $slot = $grade[$dia][$aula] ?? null;
                $idDiscSlot = $slot ? (int)$slot['ID_DISCIPLINA'] : 0;
                $atribuida = $idDiscSlot > 0 ? ($professorPadrao[$idDiscSlot] ?? null) : null;
                $idProfFixo = $atribuida ? (int)$atribuida['ID'] : 0;
                $nomeProfFixo = $atribuida ? (string)$atribuida['NOME'] : '';
                ?>
                <td>
                  <select class="form-select form-select-sm" data-disciplina="<?= $dia ?>-<?= $aula ?>" name="grade[<?= $dia ?>][<?= $aula ?>][disciplina]" aria-label="<?= ch_h($rotuloAula) ?> <?= ch_h($rotuloDia) ?> disciplina"<?= $desabilitado ?>>
                    <option value="">Sem aula</option>
                    <?php foreach ($disciplinas as $d): ?>
                      <?php $limiteDisc = (int)($limitesDisciplina[(int)$d['ID']] ?? 0); ?>
                      <option value="<?= (int)$d['ID'] ?>" data-limite="<?= $limiteDisc ?>" data-nome="<?= ch_h($d['NOME']) ?>"<?= $idDiscSlot === (int)$d['ID'] ? ' selected' : '' ?>>
                        <?= ch_h($d['NOME']) ?> (<?= $limiteDisc ?> aula(s))
                      </option>
                    <?php endforeach; ?>
                  </select>
                  <input type="hidden" name="grade[<?= $dia ?>][<?= $aula ?>][professor]" value="<?= $idProfFixo ?>"<?= $desabilitado ?>>
                  <div class="grade-prof" data-professor="<?= $dia ?>-<?= $aula ?>">
                    <?php if ($idDiscSlot === 0): ?>
                      —
                    <?php elseif ($idProfFixo > 0): ?>
                      <?= ch_h($nomeProfFixo) ?>
                    <?php else: ?>
                      Sem professor atribuído
                    <?php endif; ?>
                  </div>
                </td>
              <?php endforeach; ?>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php if ($gradeBloqueada): ?>
      <div class="alert alert-secondary py-2">Grade salva e travada. Para alterar, use o botão abaixo.</div>
      <button class="btn btn-outline-primary" name="acao" value="editar"><i class="fas fa-pen"></i> Editar grade</button>
    <?php else: ?>
      <button class="btn btn-primary"><i class="fas fa-floppy-disk"></i> Salvar grade</button>
    <?php endif; ?>
  </form>
<?php endif; ?>

<script nonce="<?= ch_csp_nonce() ?>">
/* Professor fixo por atribuição + contador decrescente de aulas por matéria. */
(function () {
  var atribuicoes = <?= json_encode($professorPadrao ?? [], JSON_UNESCAPED_UNICODE) ?>;
  var form = document.querySelector('form[method="post"] input[name="turma_id"]');
  form = form ? form.form : null;
  if (!form) return;
  var selects = [].slice.call(form.querySelectorAll('select[data-disciplina]'));

  /* Recalcula o restante de cada matéria e esconde as que já esgotaram a cota. */
  function atualizarDisponibilidade() {
    var usadas = {};
    selects.forEach(function (s) {
      if (s.value) usadas[s.value] = (usadas[s.value] || 0) + 1;
    });
    selects.forEach(function (s) {
      [].forEach.call(s.options, function (o) {
        if (!o.value) return;
        var limite = parseInt(o.dataset.limite, 10) || 0;
        var restante = Math.max(0, limite - (usadas[o.value] || 0));
        var selecionadaAqui = s.value === o.value;
        o.textContent = o.dataset.nome + ' (' + restante + ' aula(s))';
        o.hidden = restante <= 0 && !selecionadaAqui;
        o.disabled = restante <= 0 && !selecionadaAqui;
      });
    });
  }

  form.addEventListener('change', function (ev) {
    var disciplina = ev.target.closest('[data-disciplina]');
    if (!disciplina) return;
    var chave = disciplina.dataset.disciplina.split('-');
    var alvo = form.querySelector('input[type="hidden"][name="grade[' + chave[0] + '][' + chave[1] + '][professor]"]');
    var rotulo = form.querySelector('.grade-prof[data-professor="' + chave[0] + '-' + chave[1] + '"]');
    if (alvo && rotulo) {
      var atribuida = atribuicoes[disciplina.value];
      alvo.value = atribuida && atribuida.ID ? String(atribuida.ID) : '';
      rotulo.textContent = !disciplina.value
        ? '—'
        : (atribuida && atribuida.NOME ? atribuida.NOME : 'Sem professor atribuído');
    }
    atualizarDisponibilidade();
  });

  atualizarDisponibilidade();
})();
</script>

<?php include(FOOTER_TEMPLATE); ?>
