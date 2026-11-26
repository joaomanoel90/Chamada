<?php
/* Fragmento de tela: so faz sentido incluido por uma pagina que ja carregou
   o config.php. Aberto direto na URL nao tem ch_h() nem as variaveis que
   espera, e morreria com erro fatal. ABSPATH e o sinal de que vim de dentro. */
if (!defined('ABSPATH')) {
	http_response_code(404);
	exit;
}
?>
<form action="<?= $acao ?>" method="post">
  <?= ch_csrf_campo() ?>
  <?php if (!empty($id)): ?><input type="hidden" name="id" value="<?= (int)$id ?>"><?php endif; ?>
  <?php if (!empty($voltar)): ?><input type="hidden" name="voltar" value="<?= ch_h($voltar) ?>"><?php endif; ?>

  <?php if ($erros): ?>
    <div class="alert alert-danger"><ul class="mb-0"><?php foreach ($erros as $e): ?><li><?= ch_h($e) ?></li><?php endforeach; ?></ul></div>
  <?php endif; ?>

  <div class="row g-3">
    <div class="form-group col-md-8">
      <label class="fw-bold">Aluno</label>
      <select name="aluno_id" class="form-select" required>
        <option value="">Selecione...</option>
        <?php foreach ($alunosDisponiveis as $a): ?>
          <option value="<?= (int)$a['ID'] ?>" <?= (int)$d['ID_ALUNO'] === (int)$a['ID'] ? 'selected' : '' ?>>
            <?= ch_h($a['NOME']) ?> — <?= ch_h($a['TURMA']) ?> (<?= ch_h($a['UNIDADE']) ?>)
          </option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="form-group col-md-4">
      <label class="fw-bold">Data</label>
      <input type="date" class="form-control" name="data" max="<?= ch_hoje() ?>" value="<?= ch_h($d['DATA']) ?>" required>
    </div>

    <div class="form-group col-md-12">
      <label class="fw-bold">Categoria</label>
      <select name="categoria" class="form-select" required>
        <?php foreach (ch_categorias() as $c): ?>
          <option value="<?= ch_h($c) ?>" <?= $d['CATEGORIA'] === $c ? 'selected' : '' ?>><?= ch_h($c) ?></option>
        <?php endforeach; ?>
      </select>
    </div>

    <?php if (ch_pode_marcar_obs_restrita_aee((int)($d['ID_ALUNO'] ?? 0))): ?>
      <div class="form-group col-md-12">
        <label class="fw-bold d-block mb-2"><i class="fas fa-user-lock text-primary"></i> Quem pode ler esta observação do AEE?</label>
        <div class="row g-2">
          <div class="col-md-6">
            <label class="border rounded-3 p-3 d-flex align-items-start gap-2 h-100 w-100" style="cursor:pointer">
              <input class="form-check-input mt-1" type="radio" name="restrita_aee" value="0"
                     <?= empty($d['RESTRITA_AEE']) ? 'checked' : '' ?>>
              <span>
                <strong class="d-block">Compartilhada com os professores da turma</strong>
                <small class="text-muted">Os outros professores da turma podem ler e ela entra no relatório de desenvolvimento.</small>
              </span>
            </label>
          </div>
          <div class="col-md-6">
            <label class="border rounded-3 p-3 d-flex align-items-start gap-2 h-100 w-100" style="cursor:pointer">
              <input class="form-check-input mt-1" type="radio" name="restrita_aee" value="1"
                     <?= !empty($d['RESTRITA_AEE']) ? 'checked' : '' ?>>
              <span>
                <strong class="d-block"><i class="fas fa-lock me-1"></i> Somente professor(a) de AEE pode ler</strong>
                <small class="text-muted">Anotação exclusiva do AEE: outros professores e perfis não enxergam e não sai no relatório.</small>
              </span>
            </label>
          </div>
        </div>
      </div>
    <?php endif; ?>

    <div class="form-group col-md-12">
      <label class="fw-bold">Observação</label>
      <textarea name="texto" class="form-control" rows="7" maxlength="5000" required
                placeholder="Descreva o que foi observado: avanços, dificuldades, interesses, interações, marcos do desenvolvimento..."><?= ch_h($d['TEXTO']) ?></textarea>
      <small class="text-muted" id="ajuda-texto-obs">
        <?= !empty($d['RESTRITA_AEE'])
          ? 'Observação restrita ao AEE: visível apenas ao professor de AEE e não entra no relatório de desenvolvimento.'
          : 'Este texto entra no relatório de desenvolvimento do aluno.' ?>
      </small>
    </div>
  </div>

  <div class="col-auto my-4">
    <a href="<?= $voltar ? ch_h(ch_url($voltar)) : 'index.php' ?>" class="btn btn-outline-success">Cancelar</a>
    <button type="submit" class="btn btn-success"><i class="fas fa-save"></i> <?= $rotuloBotao ?></button>
  </div>
</form>

<script nonce="<?= ch_csp_nonce() ?>">
(function () {
  var radios = document.querySelectorAll('input[name="restrita_aee"]');
  var ajuda = document.getElementById('ajuda-texto-obs');
  if (!radios.length || !ajuda) return;
  function atualizarAjuda() {
    var sel = document.querySelector('input[name="restrita_aee"]:checked');
    ajuda.textContent = (sel && sel.value === '1')
      ? 'Observação restrita ao AEE: visível apenas ao professor de AEE e não entra no relatório de desenvolvimento.'
      : 'Este texto entra no relatório de desenvolvimento do aluno.';
  }
  radios.forEach(function (r) { r.addEventListener('change', atualizarAjuda); });
  atualizarAjuda();
})();
</script>
