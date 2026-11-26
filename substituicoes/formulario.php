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

        <div class="form-group col-md-4">
          <label class="fw-bold">Substituindo quem?</label>
          <select name="id_substituido" id="id_substituido" class="form-select" required
                  data-inicial="<?= (int)$d['ID_SUBSTITUIDO'] ?>">
            <option value="">Selecione a turma primeiro...</option>
          </select>
          <small class="text-muted">Professores titulares atribuídos a esta turma.</small>
        </div>

        <div class="form-group col-md-4">
          <label class="fw-bold">Período da substituição</label>
          <select name="turno" id="turno" class="form-select" required
                  data-inicial="<?= ch_h($d['TURNO'] ?? 'INTEGRAL') ?>">
            <option value="INTEGRAL">Integral (dia todo)</option>
            <option value="MANHA">Manhã</option>
            <option value="TARDE">Tarde</option>
          </select>
          <small class="text-muted" id="ajuda-turno">
            Em falta de professor integral (ex.: creche), é possível cadastrar 2 substitutos: um na Manhã e outro na Tarde.
          </small>
        </div>

        <div class="form-group col-md-4">
          <label class="fw-bold">Professor(a) substituto(a)</label>
          <select name="id_usuario" id="id_usuario" class="form-select" required>
            <option value="">Selecione...</option>
            <?php foreach ($op['professores'] as $pr): ?>
              <option value="<?= (int)$pr['ID'] ?>" <?= (int)$d['ID_USUARIO'] === (int)$pr['ID'] ? 'selected' : '' ?>>
                <?= ch_h($pr['NOME']) ?> — <?= ch_h(eq_turno_rotulo($pr['TURNO'] ?: 'INTEGRAL')) ?>
              </option>
            <?php endforeach; ?>
          </select>
          <small class="text-muted">Assume o período selecionado acima durante o prazo.</small>
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

<script nonce="<?= ch_csp_nonce() ?>">
(function () {
  var mapaTitulares = <?= json_encode($op['titularesPorTurma'], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
  var selTurma = document.getElementById('id_turma');
  var selCob   = document.getElementById('id_substituido');
  var selTurno = document.getElementById('turno');
  var ajudaT   = document.getElementById('ajuda-turno');
  if (!selTurma || !selCob || !selTurno) return;

  var valorInicial = selCob.getAttribute('data-inicial') || '';
  var turnoInicial = selTurno.getAttribute('data-inicial') || 'INTEGRAL';

  function atualizarTurnos() {
    var tid = selTurma.value;
    var cid = selCob.value;
    var lista = mapaTitulares[tid] || [];
    var escolhido = null;
    for (var i = 0; i < lista.length; i++) {
      if (String(lista[i].ID) === String(cid)) { escolhido = lista[i]; break; }
    }
    var turnoTitular = escolhido ? escolhido.TURNO : 'INTEGRAL';
    var preferido = turnoInicial || selTurno.value || turnoTitular;
    turnoInicial = '';

    selTurno.innerHTML = '';
    if (turnoTitular === 'MANHA') {
      selTurno.innerHTML = '<option value="MANHA" selected>Manhã</option>';
      if (ajudaT) ajudaT.textContent = 'O professor substituído atua no período da Manhã.';
    } else if (turnoTitular === 'TARDE') {
      selTurno.innerHTML = '<option value="TARDE" selected>Tarde</option>';
      if (ajudaT) ajudaT.textContent = 'O professor substituído atua no período da Tarde.';
    } else {
      [
        ['INTEGRAL', 'Integral — Dia todo (1 substituto)'],
        ['MANHA',    'Manhã (permite 2º substituto à Tarde)'],
        ['TARDE',    'Tarde (permite 2º substituto na Manhã)']
      ].forEach(function (par) {
        var o = document.createElement('option');
        o.value = par[0];
        o.textContent = par[1];
        if (par[0] === preferido) o.selected = true;
        selTurno.appendChild(o);
      });
      if (ajudaT) ajudaT.textContent = 'Na falta de professor integral (ex.: creche), você pode usar 1 substituto Integral ou 2 substitutos (Manhã e Tarde).';
    }
  }

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
      atualizarTurnos();
      return;
    }

    var lista = mapaTitulares[tid] || [];
    if (!lista.length) {
      var opVazia = document.createElement('option');
      opVazia.value = '';
      opVazia.textContent = 'Nenhum professor atribuído a esta turma (atribua em Atribuição primeiro)';
      selCob.appendChild(opVazia);
      atualizarTurnos();
      return;
    }

    var opSel = document.createElement('option');
    opSel.value = '';
    opSel.textContent = 'Selecione...';
    selCob.appendChild(opSel);

    lista.forEach(function (t) {
      var o = document.createElement('option');
      o.value = String(t.ID);
      o.textContent = t.NOME + ' (' + t.TURNO_ROT + ')';
      if (String(t.ID) === String(atual)) o.selected = true;
      selCob.appendChild(o);
    });
    atualizarTurnos();
  }

  selTurma.addEventListener('change', atualizarTitulares);
  selCob.addEventListener('change', atualizarTurnos);
  if (window.jQuery) {
    $(document).on('select2:select select2:clear', '#id_turma', atualizarTitulares);
    $(document).on('select2:select select2:clear', '#id_substituido', atualizarTurnos);
  }
  atualizarTitulares();
})();
</script>
