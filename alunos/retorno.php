<?php require_once('processa/retorno_processa.php'); ?>
<?php include(HEADER_TEMPLATE); ?>
<?php ch_cabecalho('Retorno à rede', 'fa-right-to-bracket'); ?>

<div class="alert alert-info">
  Localize o cadastro pelo CPF. O retorno reutiliza o registro do aluno e seu
  histórico; não crie outro cadastro para o mesmo aluno.
</div>
<?php if (!ch_rede()): ?>
  <div class="alert alert-secondary">
    A lista de turmas mostra somente as unidades vinculadas ao seu usuário.
    A escolha também é validada pelo sistema antes de registrar a matrícula.
  </div>
<?php else: ?>
  <div class="alert alert-secondary">
    Como usuário com acesso à rede, você pode escolher uma turma ativa de qualquer unidade.
  </div>
<?php endif; ?>
<?php if ($erros): ?>
  <div class="alert alert-danger"><ul class="mb-0"><?php foreach ($erros as $erro): ?><li><?= ch_h($erro) ?></li><?php endforeach; ?></ul></div>
<?php endif; ?>

<div class="card-escola mb-3">
  <div class="card-escola-corpo">
    <form method="post" class="row g-3 align-items-end">
      <?= ch_csrf_campo() ?>
      <input type="hidden" name="acao" value="buscar">
      <div class="col-md-8">
        <label class="fw-bold" for="cpf-retorno">CPF do aluno</label>
        <input class="form-control" id="cpf-retorno" name="cpf" maxlength="14" inputmode="numeric"
               value="<?= ch_h($cpf) ?>" placeholder="000.000.000-00" required>
      </div>
      <div class="col-md-4">
        <button class="btn btn-primary w-100" type="submit"><i class="fas fa-search"></i> Localizar aluno</button>
      </div>
    </form>
  </div>
</div>

<?php if ($aluno): ?>
  <?php $dataMinima = date('Y-m-d', strtotime($aluno['DATA_SAIDA'] . ' +1 day')); ?>
  <div class="card-escola">
    <div class="card-escola-corpo">
      <h5><?= ch_h($aluno['NOME']) ?></h5>
      <p class="mb-1">Matrícula: <?= ch_h($aluno['MATRICULA']) ?></p>
      <p class="text-muted">
        Última escola: <?= ch_h($aluno['ESCOLA_DESTINO']) ?> —
        <?= ch_h($aluno['MUNICIPIO_DESTINO']) ?>/<?= ch_h($aluno['UF_DESTINO']) ?>.
        Último dia na rede: <?= ch_data_br($aluno['DATA_SAIDA']) ?>.
      </p>
      <?php if (!$turmasDisponiveis): ?>
        <div class="alert alert-warning mb-0">
          Aluno localizado, mas não há turmas ativas nas unidades vinculadas ao seu usuário.
          Peça à gestão que vincule uma unidade com turma ativa para concluir o retorno.
        </div>
      <?php else: ?>
      <form method="post" class="row g-3">
        <?= ch_csrf_campo() ?>
        <input type="hidden" name="acao" value="retornar">
        <input type="hidden" name="cpf" value="<?= ch_h($cpf) ?>">
        <div class="col-md-8">
          <label class="fw-bold" for="turma-retorno">Nova escola e turma</label>
          <select class="form-select" id="turma-retorno" name="id_turma" required>
            <option value="">Selecione...</option>
            <?php foreach ($turmasDisponiveis as $turma): ?>
              <option value="<?= (int)$turma['ID'] ?>" <?= (int)$d['ID_TURMA'] === (int)$turma['ID'] ? 'selected' : '' ?>>
                <?= ch_h($turma['UNIDADE']) ?> — Turma <?= ch_h(ch_series_ensino()[$turma['SERIE'] ?? '']['rotulo'] ?? 'não classificada') ?>
                · Sala <?= ch_h($turma['NOME']) ?> · <?= ch_h($turma['PERIODO']) ?> (<?= (int)$turma['ANO_LETIVO'] ?>)
                (<?= (int)$turma['OCUPADAS'] ?>/<?= (int)$turma['CAPACIDADE'] ?>)
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-4">
          <label class="fw-bold" for="data-retorno">Início da matrícula</label>
          <input class="form-control" type="date" id="data-retorno" name="data_retorno"
                 min="<?= ch_h($dataMinima) ?>" value="<?= ch_h($d['DATA_RETORNO']) ?>" required>
        </div>
        <div class="col-12">
          <button class="btn btn-success" type="submit">
            <i class="fas fa-right-to-bracket"></i> Registrar retorno e matricular
          </button>
        </div>
      </form>
      <?php endif; ?>
    </div>
  </div>
<?php endif; ?>

<?php include(FOOTER_TEMPLATE); ?>
