<?php require_once('processa/edit_processa.php'); ?>
<?php include(HEADER_TEMPLATE); ?>

<?php ch_cabecalho('Editar Período: ' . $nome, 'fa-edit'); ?>

<?php if ($erro): ?><div class="alert alert-danger"><?= ch_h($erro) ?></div><?php endif; ?>

<form action="edit.php?id=<?= (int)$id ?>" method="post">
  <?= ch_csrf_campo() ?>
  <input type="hidden" name="id" value="<?= (int)$id ?>">
  <div class="row g-3">
    <div class="form-group col-md-5">
      <label class="fw-bold">Nome do período</label>
      <input type="text" class="form-control" name="nome" maxlength="60" value="<?= ch_h($nome) ?>" required>
    </div>
    <div class="form-group col-md-4">
      <label class="fw-bold">Turno</label>
      <select class="form-select" name="turno" required>
<?php foreach (eq_turnos() as $sigla => $rotulo): ?>
        <option value="<?= $sigla ?>" <?= strtoupper((string)$turno) === $sigla ? 'selected' : '' ?>><?= ch_h($rotulo) ?></option>
<?php endforeach; ?>
      </select>
      <small class="text-muted">É o turno que a equipe da turma usa como referência.</small>
    </div>
    <div class="form-group col-md-3">
      <label class="fw-bold">Início</label>
      <input type="time" class="form-control" name="hora_inicio" value="<?= ch_h($inicio) ?>" required>
    </div>
    <div class="form-group col-md-3">
      <label class="fw-bold">Fim</label>
      <input type="time" class="form-control" name="hora_fim" value="<?= ch_h($fim) ?>" required>
    </div>
  </div>

  <div class="alert alert-info mt-4 mb-0">
    <i class="fas fa-circle-info"></i>
    Alterar o horário do período muda a faixa usada para conferir feriados parciais.
  </div>

  <div class="col-auto my-4">
    <a href="index.php" class="btn btn-outline-success">Cancelar</a>
    <button type="submit" class="btn btn-success"><i class="fas fa-save"></i> Salvar Alterações</button>
  </div>
</form>

<?php include(FOOTER_TEMPLATE); ?>
