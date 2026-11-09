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
  <?php if (!empty($id)): ?><input type="hidden" name="id" value="<?= (int)$id ?>"><?php endif; ?>

  <?php if ($erros): ?>
    <div class="alert alert-danger"><ul class="mb-0"><?php foreach ($erros as $e): ?><li><?= ch_h($e) ?></li><?php endforeach; ?></ul></div>
  <?php endif; ?>

  <div class="row g-3">
    <div class="form-group col-md-3">
      <label class="fw-bold">Data</label>
      <input type="date" class="form-control" name="data" value="<?= ch_h($d['DATA']) ?>" required>
    </div>
    <div class="form-group col-md-9">
      <label class="fw-bold">Descrição</label>
      <input type="text" class="form-control" name="descricao" maxlength="150" value="<?= ch_h($d['DESCRICAO']) ?>" placeholder="Ex.: Independência do Brasil" required>
    </div>

    <div class="form-group col-md-4">
      <label class="fw-bold">Tipo</label>
      <select name="tipo" id="tipo" class="form-select" required>
        <option value="INTEGRAL" <?= $d['TIPO'] === 'INTEGRAL' ? 'selected' : '' ?>>Dia inteiro (integral)</option>
        <option value="PARCIAL"  <?= $d['TIPO'] === 'PARCIAL'  ? 'selected' : '' ?>>Parte do dia (parcial)</option>
      </select>
    </div>
    <div class="form-group col-md-4 faixa-parcial">
      <label class="fw-bold">Sem aula a partir de</label>
      <input type="time" class="form-control" name="hora_inicio" value="<?= ch_h($d['HORA_INICIO'] ?: '12:00') ?>">
    </div>
    <div class="form-group col-md-4 faixa-parcial">
      <label class="fw-bold">Sem aula até</label>
      <input type="time" class="form-control" name="hora_fim" value="<?= ch_h($d['HORA_FIM'] ?: '18:00') ?>">
    </div>

    <div class="form-group col-md-12">
      <label class="fw-bold">Abrangência</label>
      <select name="id_unidade" class="form-select">
        <option value="0">Todas as unidades</option>
        <?php foreach ($unidades as $u): ?>
          <option value="<?= (int)$u['id'] ?>" <?= (int)$d['ID_UNIDADE'] === (int)$u['id'] ? 'selected' : '' ?>><?= ch_h($u['unidade']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
  </div>

  <div class="alert alert-info mt-3 mb-0">
    No feriado parcial, informe a faixa em que <strong>não haverá aula</strong>.
    Ex.: das 12:00 às 18:00 bloqueia as turmas da Tarde e Integral, mas libera as da Manhã.
  </div>

  <div class="col-auto my-4">
    <a href="index.php" class="btn btn-outline-success">Cancelar</a>
    <button type="submit" class="btn btn-success"><i class="fas fa-save"></i> <?= $rotuloBotao ?></button>
  </div>
</form>

<script>
  (function () {
    var tipo = document.getElementById('tipo');
    function sync() {
      var parcial = tipo.value === 'PARCIAL';
      document.querySelectorAll('.faixa-parcial').forEach(function (el) {
        el.style.display = parcial ? '' : 'none';
        el.querySelectorAll('input').forEach(function (i) { i.required = parcial; });
      });
    }
    tipo.addEventListener('change', sync);
    sync();
  })();
</script>
