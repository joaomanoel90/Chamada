<?php
if (!defined('ABSPATH')) {
    http_response_code(404);
    exit;
}
?>
<form action="<?= $acao ?>" method="post" id="form-substituicao">
  <?= ch_csrf_campo() ?>
  <?php if (!empty($id)): ?><input type="hidden" name="id" value="<?= (int)$id ?>"><?php endif; ?>

  <?php if ($erros): ?>
    <div class="alert alert-danger"><ul class="mb-0"><?php foreach ($erros as $e): ?><li><?= ch_h($e) ?></li><?php endforeach; ?></ul></div>
  <?php endif; ?>

  <div class="card-escola mb-4">
    <div class="card-escola-titulo"><i class="fas fa-people-arrows"></i> Dados da substituição</div>
    <div class="card-escola-corpo">
      <div class="row g-3">
        <div class="form-group col-md-12">
          <label class="fw-bold">Turma</label>
          <select name="id_turma" id="id_turma" class="form-select" required>
            <option value="">Selecione...</option>
            <?php foreach ($op['turmas'] as $t): ?>
              <option value="<?= (int)$t['ID'] ?>" <?= (int)$d['ID_TURMA'] === (int)$t['ID'] ? 'selected' : '' ?>>
                <?= ch_h($t['UNIDADE']) ?> — <?= ch_h(ch_series_ensino()[$t['SERIE'] ?? '']['rotulo'] ?? 'Não classificada') ?>
                · Sala <?= ch_h($t['NOME']) ?> · <?= ch_h($t['PERIODO']) ?> (<?= (int)$t['ANO_LETIVO'] ?>)
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="form-group col-md-6">
          <label class="fw-bold">Substituindo quem?</label>
          <select name="id_substituido" id="id_substituido" class="form-select" required
                  data-inicial="<?= (int)$d['ID_SUBSTITUIDO'] ?>">
            <option value="">Selecione a turma primeiro...</option>
          </select>
          <small class="text-muted">Aparecem aqui os professores titulares com aulas atribuídas nesta turma.</small>
        </div>

        <div class="form-group col-md-6">
          <label class="fw-bold">Professor(a) substituto(a)</label>
          <select name="id_usuario" id="id_usuario" class="form-select" required>
            <option value="">Selecione...</option>
            <?php foreach ($op['professores'] as $pr): ?>
              <option value="<?= (int)$pr['ID'] ?>" <?= (int)$d['ID_USUARIO'] === (int)$pr['ID'] ? 'selected' : '' ?>>
                <?= ch_h($pr['NOME']) ?> — <?= ch_h(eq_turno_rotulo($pr['TURNO'] ?: 'INTEGRAL')) ?>
              </option>
            <?php endforeach; ?>
          </select>
          <small class="text-muted">O substituto assume o turno do professor substituído durante o período informado.</small>
        </div>

        <div class="form-group col-md-3">
          <label class="fw-bold">De</label>
          <input type="date" name="data_inicio" class="form-control" value="<?= ch_h($d['DATA_INICIO']) ?>" required>
        </div>

        <div class="form-group col-md-3">
          <label class="fw-bold">Até</label>
          <input type="date" name="data_fim" class="form-control" value="<?= ch_h($d['DATA_FIM']) ?>" required>
        </div>

        <div class="form-group col-md-6">
          <label class="fw-bold">Motivo (opcional)</label>
          <input type="text" name="motivo" class="form-control" maxlength="200"
                 value="<?= ch_h($d['MOTIVO']) ?>" placeholder="Ex.: Atestado médico, licença maternidade">
        </div>

        <div class="form-group col-12">
          <div class="form-check form-switch">
            <input class="form-check-input" type="checkbox" role="switch" id="ver-observacoes"
                   name="ver_observacoes" value="1" <?= !empty($d['VER_OBSERVACOES']) ? 'checked' : '' ?>>
            <label class="form-check-label fw-bold" for="ver-observacoes">
              <i class="fas fa-eye"></i> Pode ler as observações dos outros professores da turma
            </label>
          </div>
          <small class="text-muted">
            Desligado, o substituto lê apenas as observações que ele mesmo registrar.
            Ligado, lê também as observações compartilhadas da turma durante esta substituição
            (as observações restritas ao AEE continuam exclusivas do professor de AEE).
          </small>
        </div>
      </div>
    </div>
  </div>

  <div class="col-auto my-4">
    <a href="index.php" class="btn btn-outline-secondary">Cancelar</a>
    <button type="submit" class="btn btn-success"><i class="fas fa-save"></i> <?= ch_h($rotuloBotao) ?></button>
  </div>
</form>

<script>
(function () {
  var mapaTitulares = <?= json_encode($op['titularesPorTurma'], JSON_UNESCAPED_UNICODE) ?>;
  var selTurma = document.getElementById('id_turma');
  var selCob   = document.getElementById('id_substituido');
  if (!selTurma || !selCob) return;

  var valorInicial = selCob.getAttribute('data-inicial') || '';

  function atualizarTitulares() {
    var tid = selTurma.value;
    var atual = selCob.value || valorInicial;
    valorInicial = '';
    selCob.innerHTML = '';

    if (!tid) {
      var op0 = document.createElement('option');
      op0.value = '';
      op0.textContent = 'Selecione a turma primeiro...';
      selCob.appendChild(op0);
      return;
    }

    var lista = mapaTitulares[tid] || [];
    if (!lista.length) {
      var opVazia = document.createElement('option');
      opVazia.value = '';
      opVazia.textContent = 'Nenhum professor atribuído a esta turma (atribua em Atribuição primeiro)';
      selCob.appendChild(opVazia);
      return;
    }

    var opSel = document.createElement('option');
    opSel.value = '';
    opSel.textContent = 'Selecione...';
    selCob.appendChild(opSel);

    lista.forEach(function (t) {
      var o = document.createElement('option');
      o.value = String(t.ID);
      o.textContent = t.NOME;
      if (String(t.ID) === String(atual)) o.selected = true;
      selCob.appendChild(o);
    });
  }

  selTurma.addEventListener('change', atualizarTitulares);
  if (window.jQuery) {
    $(document).on('select2:select select2:clear', '#id_turma', atualizarTitulares);
  }
  atualizarTitulares();
})();
</script>
