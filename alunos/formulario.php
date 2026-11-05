<form action="<?= $acao ?>" method="post">
  <?php if (!empty($id)): ?><input type="hidden" name="id" value="<?= (int)$id ?>"><?php endif; ?>

  <?php if ($erros): ?>
    <div class="alert alert-danger"><ul class="mb-0"><?php foreach ($erros as $e): ?><li><?= ch_h($e) ?></li><?php endforeach; ?></ul></div>
  <?php endif; ?>

  <div class="row g-3">
    <div class="form-group col-md-8">
      <label class="fw-bold">Turma</label>
      <select name="id_turma" class="form-select" required>
        <option value="">Selecione...</option>
        <?php foreach ($turmasDisponiveis as $t): ?>
          <option value="<?= (int)$t['ID'] ?>" <?= (int)$d['ID_TURMA'] === (int)$t['ID'] ? 'selected' : '' ?>>
            <?= ch_h($t['UNIDADE']) ?> — <?= ch_h($t['NOME']) ?> (<?= (int)$t['ANO_LETIVO'] ?>)
            [<?= (int)$t['OCUPADAS'] ?>/<?= (int)$t['CAPACIDADE'] ?>]
          </option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="form-group col-md-4">
      <label class="fw-bold">Matrícula</label>
      <input type="text" class="form-control" name="matricula" maxlength="30" value="<?= ch_h($d['MATRICULA']) ?>" required>
    </div>

    <div class="form-group col-md-8">
      <label class="fw-bold">Nome completo do aluno</label>
      <input type="text" class="form-control" name="nome" maxlength="150" value="<?= ch_h($d['NOME']) ?>" required>
    </div>
    <div class="form-group col-md-4">
      <label class="fw-bold">Data de nascimento</label>
      <input type="date" class="form-control" name="nascimento" min="1900-01-01" max="<?= ch_hoje() ?>" value="<?= ch_h($d['NASCIMENTO']) ?>" required>
    </div>

    <div class="form-group col-md-5">
      <label class="fw-bold">Responsável</label>
      <input type="text" class="form-control" name="responsavel" maxlength="150" value="<?= ch_h($d['RESPONSAVEL']) ?>" required>
    </div>
    <div class="form-group col-md-3">
      <label class="fw-bold">Telefone</label>
      <input type="text" class="form-control" name="telefone" maxlength="30" value="<?= ch_h($d['TELEFONE']) ?>" placeholder="(15) 99999-9999" required>
    </div>
    <div class="form-group col-md-4">
      <label class="fw-bold">Data de matrícula</label>
      <input type="date" class="form-control" name="data_matricula" value="<?= ch_h($d['DATA_MATRICULA']) ?>" required>
    </div>

    <div class="form-group col-md-12">
      <label class="fw-bold">E-mail do responsável <small class="text-muted">(opcional)</small></label>
      <input type="email" class="form-control" name="email" maxlength="150" value="<?= ch_h($d['EMAIL']) ?>">
    </div>
  </div>

  <div class="col-auto my-4">
    <a href="index.php" class="btn btn-outline-success">Cancelar</a>
    <button type="submit" class="btn btn-success"><i class="fas fa-save"></i> <?= $rotuloBotao ?></button>
  </div>
</form>
