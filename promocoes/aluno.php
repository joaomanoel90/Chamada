<?php require_once('processa/aluno_processa.php'); ?>
<?php include(HEADER_TEMPLATE); ?>

<?php ch_cabecalho('Promover aluno', 'fa-arrow-up',
    '<a class="btn btn-outline-secondary" href="' . ch_url('alunos/view.php?id=' . (int)$aluno['ID']) . '"><i class="fas fa-arrow-left"></i> Ficha do aluno</a>'); ?>

<div class="alert alert-info">
  Promover <strong><?= ch_h($aluno['NOME']) ?></strong> de
  <?= ch_h(ch_series_ensino()[$aluno['SERIE'] ?? '']['rotulo'] ?? 'série não classificada') ?> · <?= ch_h($aluno['TURMA']) ?>
  — <?= ch_h($aluno['UNIDADE']) ?> (<?= (int)$aluno['ANO_LETIVO'] ?>) para uma turma autorizada no ano
  <?= (int)$aluno['ANO_LETIVO'] + 1 ?>. Escolha promoção ou retenção; a data de início
  segue o calendário escolar definido pela Secretaria de Educação.
</div>

<?php if (!$destinos): ?>
  <div class="alert alert-warning">Cadastre e classifique uma turma ativa do próximo ano em uma unidade autorizada antes de movimentar este aluno.</div>
<?php elseif (empty($aluno['SERIE'])): ?>
  <div class="alert alert-warning">Classifique a série da turma atual em Turmas → Editar antes de promover ou reter este aluno.</div>
<?php else: ?>
  <form method="post" action="<?= ch_url('promocoes/processa/salvar_processa.php') ?>" class="card border-0 shadow-sm rounded-4">
    <div class="card-body">
      <?= ch_csrf_campo() ?>
      <input type="hidden" name="aluno_id" value="<?= (int)$aluno['ID'] ?>">
      <div class="mb-3">
        <label class="form-label fw-bold" for="resultado">Resultado escolar</label>
        <select class="form-select" id="resultado" name="resultado" required>
          <option value="PROMOVIDO" <?= ch_serie_seguinte($aluno['SERIE']) === null ? 'disabled' : '' ?>>Promovido — avançar uma série</option>
          <option value="RETIDO" <?= ch_serie_seguinte($aluno['SERIE']) === null ? 'selected' : '' ?>>Retido — manter a mesma série</option>
        </select>
      </div>
      <div class="mb-3" id="motivo-retencao-bloco" hidden>
        <label class="form-label fw-bold" for="motivo_resultado">Motivo da retenção</label>
        <select class="form-select" id="motivo_resultado" name="motivo_resultado" disabled>
          <option value="">Selecione...</option>
          <?php foreach (ch_motivos_retencao() as $codigo => $rotulo): ?>
            <option value="<?= ch_h($codigo) ?>"><?= ch_h($rotulo) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="mb-3">
        <label class="form-label fw-bold" for="turma_destino">Turma de destino</label>
        <select class="form-select" id="turma_destino" name="id_turma_destino" required>
          <option value="">Selecione</option>
          <?php foreach ($destinos as $destino): ?>
            <option value="<?= (int)$destino['ID'] ?>" data-serie="<?= ch_h($destino['SERIE']) ?>">
              <?= ch_h($destino['UNIDADE']) ?> — <?= ch_h(ch_series_ensino()[$destino['SERIE'] ?? '']['rotulo'] ?? '') ?>
              · Turma/sala <?= ch_h($destino['NOME']) ?> · <?= ch_h($destino['PERIODO']) ?>
              (<?= (int)$destino['OCUPADAS'] ?>/<?= (int)$destino['CAPACIDADE'] ?>)
            </option>
          <?php endforeach; ?>
        </select>
        <div class="small text-danger" id="aviso-destino-incompativel" hidden>Não há turma classificada para esta série e resultado. Cadastre a turma de destino antes de continuar.</div>
      </div>
      <div class="mb-3">
        <label class="form-label fw-bold" for="data_inicio">Data de início no novo ano letivo</label>
        <input class="form-control" type="date" id="data_inicio" name="data_inicio"
               min="<?= (int)$aluno['ANO_LETIVO'] + 1 ?>-01-01"
               max="<?= (int)$aluno['ANO_LETIVO'] + 1 ?>-12-31" required>
      </div>
    </div>
    <div class="card-footer bg-white text-end">
      <button class="btn btn-primary" type="submit"><i class="fas fa-check"></i> Confirmar movimentação</button>
    </div>
  </form>
<?php endif; ?>

<script>
(function () {
  var resultado = document.getElementById('resultado');
  var destino = document.getElementById('turma_destino');
  var blocoMotivo = document.getElementById('motivo-retencao-bloco');
  var motivo = document.getElementById('motivo_resultado');
  var avisoDestino = document.getElementById('aviso-destino-incompativel');
  if (!resultado || !destino) return;
  var ordemOrigem = <?= (int)(ch_serie_ordem($aluno['SERIE'] ?? '') ?: 0) ?>;
  var ordens = <?= json_encode(array_map(function ($s) { return (int)$s['ordem']; }, ch_series_ensino()), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
  function filtrar() {
    var ordemEsperada = resultado.value === 'RETIDO' ? ordemOrigem : ordemOrigem + 1;
    var compativeis = 0;
    if (blocoMotivo && motivo) {
      blocoMotivo.hidden = resultado.value !== 'RETIDO';
      motivo.disabled = resultado.value !== 'RETIDO';
      motivo.required = resultado.value === 'RETIDO';
    }
    Array.prototype.forEach.call(destino.options, function (opcao) {
      if (!opcao.value) return;
      opcao.hidden = ordens[opcao.getAttribute('data-serie')] !== ordemEsperada;
      if (!opcao.hidden) compativeis++;
      if (opcao.hidden && opcao.selected) destino.value = '';
    });
    if (avisoDestino) avisoDestino.hidden = compativeis > 0;
  }
  resultado.addEventListener('change', filtrar);
  filtrar();
})();
</script>
<?php include(FOOTER_TEMPLATE); ?>
