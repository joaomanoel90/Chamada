<?php require_once('processa/turma_processa.php'); ?>
<?php include(HEADER_TEMPLATE); ?>

<?php ch_cabecalho('Promover turma', 'fa-arrow-up',
    '<a class="btn btn-outline-secondary" href="index.php"><i class="fas fa-arrow-left"></i> Voltar</a>'); ?>

<div class="alert alert-info">
  Todos os alunos ativos de <strong><?= ch_h($origem['NOME']) ?></strong>
  — <?= ch_h(ch_series_ensino()[$origem['SERIE'] ?? '']['rotulo'] ?? 'série não classificada') ?>
  — <?= ch_h($origem['UNIDADE']) ?> (<?= (int)$origem['ANO_LETIVO'] ?>) serão encaminhados para uma turma de
  <?= (int)$origem['ANO_LETIVO'] + 1 ?>. Se os alunos tiverem resultados diferentes,
  faça a movimentação individual pela ficha de cada um.
</div>

<?php if (!$destinos): ?>
  <div class="alert alert-warning">Cadastre e classifique uma turma ativa do ano <?= (int)$origem['ANO_LETIVO'] + 1 ?> em uma unidade autorizada antes de movimentar alunos.</div>
<?php elseif (empty($origem['SERIE'])): ?>
  <div class="alert alert-warning">Classifique a série da turma de origem em Turmas → Editar antes de promover ou reter estes alunos.</div>
<?php else: ?>
  <form method="post" action="<?= ch_url('promocoes/processa/salvar_processa.php') ?>" class="card border-0 shadow-sm rounded-4">
    <div class="card-body">
      <?= ch_csrf_campo() ?>
      <input type="hidden" name="turma_id" value="<?= (int)$origem['ID'] ?>">
      <div class="row g-3">
        <div class="col-md-6">
          <label class="form-label fw-bold" for="resultado">Resultado escolar</label>
          <select class="form-select" id="resultado" name="resultado" required>
            <option value="PROMOVIDO" <?= ch_serie_seguinte($origem['SERIE']) === null ? 'disabled' : '' ?>>Promovido — avançar uma série</option>
            <option value="RETIDO" <?= ch_serie_seguinte($origem['SERIE']) === null ? 'selected' : '' ?>>Retido — manter a mesma série</option>
          </select>
        </div>
        <div class="col-md-6" id="motivo-retencao-bloco" hidden>
          <label class="form-label fw-bold" for="motivo_resultado">Motivo da retenção</label>
          <select class="form-select" id="motivo_resultado" name="motivo_resultado" disabled>
            <option value="">Selecione...</option>
            <?php foreach (ch_motivos_retencao() as $codigo => $rotulo): ?>
              <option value="<?= ch_h($codigo) ?>"><?= ch_h($rotulo) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-6">
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
        <div class="col-md-6">
          <label class="form-label fw-bold" for="data_inicio">Data de início no ano letivo <?= (int)$origem['ANO_LETIVO'] + 1 ?></label>
          <input class="form-control" type="date" id="data_inicio" name="data_inicio"
                 min="<?= (int)$origem['ANO_LETIVO'] + 1 ?>-01-01"
                 max="<?= (int)$origem['ANO_LETIVO'] + 1 ?>-12-31" required>
        </div>
      </div>

      <h5 class="mt-4">Alunos na turma (<?= count($alunos) ?>)</h5>
      <?php if ($alunos): ?>
        <ul class="mb-0">
          <?php foreach ($alunos as $aluno): ?>
            <li><?= ch_h($aluno['NOME']) ?> <small class="text-muted">· <?= ch_h($aluno['MATRICULA']) ?></small></li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </div>
    <div class="card-footer bg-white text-end">
      <button class="btn btn-primary" type="submit" <?= !$alunos ? 'disabled' : '' ?>
              onclick="return confirm('Registrar o resultado escolar e movimentar todos os alunos listados para a turma escolhida?');">
        <i class="fas fa-check"></i> Confirmar movimentação
      </button>
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
  var ordemOrigem = <?= (int)(ch_serie_ordem($origem['SERIE'] ?? '') ?: 0) ?>;
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
