<?php require_once('processa/saida_processa.php'); ?>
<?php include(HEADER_TEMPLATE); ?>
<?php ch_cabecalho('Registrar saída da rede', 'fa-right-from-bracket'); ?>

<div class="alert alert-info">
  O cadastro do aluno não será apagado. Informe a escola externa e o último dia
  frequentado; o aluno deixará de aparecer nas chamadas ativas.
</div>
<?php if ($erros): ?>
  <div class="alert alert-danger"><ul class="mb-0"><?php foreach ($erros as $erro): ?><li><?= ch_h($erro) ?></li><?php endforeach; ?></ul></div>
<?php endif; ?>

<div class="card-escola">
  <div class="card-escola-corpo">
    <p><strong>Aluno:</strong> <?= ch_h($aluno['NOME']) ?> —
      <?= ch_h($aluno['UNIDADE']) ?> / <?= ch_h($aluno['TURMA']) ?></p>
    <form method="post">
      <?= ch_csrf_campo() ?>
      <input type="hidden" name="id" value="<?= (int)$id ?>">
      <div class="row g-3">
        <div class="col-md-4">
          <label class="fw-bold" for="data-saida">Último dia na escola</label>
          <input class="form-control" type="date" id="data-saida" name="data_saida"
                 min="<?= ch_h($dataMinima) ?>" max="<?= ch_hoje() ?>"
                 value="<?= ch_h($d['DATA_SAIDA']) ?>" required>
        </div>
        <div class="col-md-8">
          <label class="fw-bold" for="escola-destino">Escola de destino</label>
          <input class="form-control" id="escola-destino" name="escola_destino" maxlength="150"
                 value="<?= ch_h($d['ESCOLA_DESTINO']) ?>" required>
        </div>
        <div class="col-md-8">
          <label class="fw-bold" for="municipio-destino">Município</label>
          <input class="form-control" id="municipio-destino" name="municipio_destino" maxlength="100"
                 value="<?= ch_h($d['MUNICIPIO_DESTINO']) ?>" required>
        </div>
        <div class="col-md-4">
          <label class="fw-bold" for="uf-destino">Estado (UF)</label>
          <input class="form-control text-uppercase" id="uf-destino" name="uf_destino" maxlength="2"
                 value="<?= ch_h($d['UF_DESTINO']) ?>" required>
        </div>
        <div class="col-12">
          <label class="fw-bold" for="motivo">Motivo / observação</label>
          <textarea class="form-control" id="motivo" name="motivo" maxlength="500" rows="3" required><?= ch_h($d['MOTIVO']) ?></textarea>
        </div>
      </div>
      <div class="mt-4">
        <a class="btn btn-outline-secondary" href="<?= ch_url('alunos/view.php?id=' . (int)$id) ?>">Cancelar</a>
        <button class="btn btn-primary" type="submit"
                onclick="return confirm('Registrar a saída e retirar o aluno das chamadas ativas?')">
          <i class="fas fa-right-from-bracket"></i> Registrar saída
        </button>
      </div>
    </form>
  </div>
</div>

<?php include(FOOTER_TEMPLATE); ?>
