<form action="<?= $acao ?>" method="post">
  <?php if ($id ?? 0): ?><input type="hidden" name="id" value="<?= (int)$id ?>"><?php endif; ?>

  <?php if ($erros): ?>
    <div class="alert alert-danger"><ul class="mb-0"><?php foreach ($erros as $e): ?><li><?= ch_h($e) ?></li><?php endforeach; ?></ul></div>
  <?php endif; ?>

  <div class="row g-3">
    <div class="form-group col-md-8">
      <label class="fw-bold">Unidade (creche / escola)</label>
      <select name="id_unidade" class="form-select" required>
        <option value="">Selecione...</option>
        <?php foreach ($op['unidades'] as $u): ?>
          <option value="<?= (int)$u['id'] ?>" <?= (int)$d['ID_UNIDADE'] === (int)$u['id'] ? 'selected' : '' ?>><?= ch_h($u['unidade']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="form-group col-md-4">
      <label class="fw-bold">Ano letivo</label>
      <input type="number" class="form-control" name="ano_letivo" min="2000" max="2100" value="<?= (int)$d['ANO_LETIVO'] ?>" required>
    </div>

    <div class="form-group col-md-5">
      <label class="fw-bold">Nome da turma</label>
      <input type="text" class="form-control" name="nome" maxlength="80" value="<?= ch_h($d['NOME']) ?>" placeholder="Ex.: Berçário I" required>
    </div>
    <div class="form-group col-md-4">
      <label class="fw-bold">Período</label>
      <select name="id_periodo" class="form-select" required>
        <option value="">Selecione...</option>
        <?php foreach ($op['periodos'] as $p): ?>
          <option value="<?= (int)$p['ID'] ?>" <?= (int)$d['ID_PERIODO'] === (int)$p['ID'] ? 'selected' : '' ?>>
            <?= ch_h($p['NOME']) ?> (<?= ch_h($p['HORA_INICIO']) ?>–<?= ch_h($p['HORA_FIM']) ?>)
          </option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="form-group col-md-3">
      <label class="fw-bold">Capacidade</label>
      <input type="number" class="form-control" name="capacidade" min="1" max="500" value="<?= (int)$d['CAPACIDADE'] ?>" required>
    </div>

    <div class="form-group col-md-12">
      <label class="fw-bold">Professor(a) responsável</label>
      <select name="id_professor" class="form-select">
        <option value="0">Não definido</option>
        <?php foreach ($op['professores'] as $pr): ?>
          <option value="<?= (int)$pr['ID'] ?>" <?= (int)$d['ID_PROFESSOR'] === (int)$pr['ID'] ? 'selected' : '' ?>><?= ch_h($pr['NOME']) ?></option>
        <?php endforeach; ?>
      </select>
      <small class="text-muted">O acesso do professor às turmas também depende do vínculo em Usuários &rarr; Acesso por Unidade.</small>
    </div>
  </div>

  <div class="col-auto my-4">
    <a href="index.php" class="btn btn-outline-success">Cancelar</a>
    <button type="submit" class="btn btn-success"><i class="fas fa-save"></i> <?= $rotuloBotao ?></button>
  </div>
</form>
