<?php require_once('processa/index_processa.php'); require_once(ABSPATH . 'inc/chamada_categorias.php'); ?>
<?php include(ch_gestor() ? HEADER_TEMPLATE : HEADER_TEMPLATE2); ?>

<?php ch_cabecalho('Observações dos Alunos', 'fa-comment-dots',
    ch_pode_escrever_observacoes()
      ? '<a class="btn btn-success" href="add.php"><i class="fa fa-plus"></i> Nova Observação</a>'
      : ''); ?>

<?php if (ch_secretario_escolar() && !$podeLerObservacoes): ?>
  <div class="alert alert-info">
    <i class="fas fa-lock"></i>
    O Diretor ainda não liberou a consulta de observações para nenhuma unidade vinculada.
  </div>
<?php elseif (ch_secretario_escolar() && !ch_pode_escrever_observacoes()): ?>
  <div class="alert alert-info">
    <i class="fas fa-eye"></i>
    Consulta autorizada. Para inserir novas observações, o Diretor precisa liberar essa opção na unidade.
  </div>
<?php endif; ?>
<?php if (!empty($avisoObs)): ?>
  <div class="alert alert-warning">
    <i class="fas fa-eye-slash"></i> <?= ch_h($avisoObs) ?>
  </div>
<?php endif; ?>

<?php if ($alunoFiltro): ?>
  <div class="alert alert-light border">
    Filtrando por <strong><?= ch_h($alunoFiltro['NOME']) ?></strong> — <?= ch_h($alunoFiltro['TURMA']) ?>.
    <a href="<?= ch_url('alunos/view.php?id=' . (int)$alunoFiltro['ID']) ?>">Ver ficha</a>
  </div>
<?php endif; ?>

<form method="get" action="index.php" class="row g-2 align-items-end mb-3">
  <div class="col-md-3">
    <label class="fw-bold">Turma</label>
    <select name="turma_id" class="form-select">
      <option value="0">Todas</option>
      <?php foreach ($turmas as $t): ?>
        <option value="<?= (int)$t['ID'] ?>" <?= (int)$t['ID'] === $fTurma ? 'selected' : '' ?>>
          <?= ch_h($t['UNIDADE']) ?> — <?= ch_h($t['NOME']) ?>
        </option>
      <?php endforeach; ?>
    </select>
  </div>
  <div class="col-md-3">
    <label class="fw-bold">Categoria</label>
    <select name="categoria" class="form-select">
      <option value="">Todas</option>
      <?php foreach (ch_categorias() as $c): ?>
        <option value="<?= ch_h($c) ?>" <?= $c === $fCategoria ? 'selected' : '' ?>><?= ch_h($c) ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <div class="col-md-2">
    <label class="fw-bold">De</label>
    <input type="date" name="inicio" class="form-control" value="<?= ch_h($fInicio) ?>">
  </div>
  <div class="col-md-2">
    <label class="fw-bold">Até</label>
    <input type="date" name="fim" class="form-control" value="<?= ch_h($fFim) ?>">
  </div>
  <div class="col-md-2">
    <?php if ($fAluno): ?><input type="hidden" name="aluno_id" value="<?= (int)$fAluno ?>"><?php endif; ?>
    <button class="btn btn-primary w-100"><i class="fas fa-filter"></i> Filtrar</button>
  </div>
</form>

<?php if (!$observacoes): ?>
  <div class="alert alert-info">
    Nenhuma observação encontrada no período.
    <?php if (!empty($obsOcultas)): ?>
      As observações da equipe estão ocultas ou não há autorização de leitura para essas unidades.
    <?php endif; ?>
  </div>
<?php else: ?>
  <div class="row g-3">
    <?php foreach ($observacoes as $o): ?>
    <div class="col-md-6">
      <div class="card border-0 shadow-sm rounded-4 h-100">
        <div class="card-body">
          <div class="d-flex justify-content-between align-items-start mb-2">
            <div>
              <strong><?= ch_h($o['ALUNO']) ?></strong><br>
              <small class="text-muted"><?= ch_h($o['TURMA']) ?> &middot; <?= ch_h($o['UNIDADE']) ?></small>
            </div>
            <span class="badge bg-light text-dark"><?= ch_h($o['CATEGORIA']) ?></span>
          </div>
          <p class="mb-2"><?= nl2br(ch_h($o['TEXTO'])) ?></p>
          <div class="d-flex justify-content-between align-items-center">
            <small class="text-muted">
              <?= ch_data_br($o['DATA']) ?> &middot; <?= ch_h($o['AUTOR']) ?>
              <?php if ($o['MODIFIED']): ?> · última alteração <?= ch_h($o['MODIFIED']) ?><?php endif; ?>
            </small>
            <div>
              <a class="btn btn-sm btn-outline-secondary" href="<?= ch_url('alunos/view.php?id=' . (int)$o['ID_ALUNO']) ?>"><i class="far fa-eye"></i></a>
              <?php if ((int)$o['ALTERACOES'] > 0): ?>
                <a class="btn btn-sm btn-outline-info" title="Ver histórico de alterações"
                   href="<?= ch_url('observacoes/historico.php?id=' . (int)$o['ID']) ?>">
                  <i class="fas fa-clock-rotate-left"></i> <?= (int)$o['ALTERACOES'] ?>
                </a>
              <?php endif; ?>
              <?php if (!ch_secretario_escolar()
                    && ch_pode_escrever_observacao_turma($o['ID_TURMA'], $o['ID_UNIDADE'])
                    && (ch_gestor() || (int)$o['ID_USUARIO'] === ch_uid())): ?>
                <a class="btn btn-sm btn-outline-success" href="edit.php?id=<?= (int)$o['ID'] ?>"><i class="far fa-edit"></i></a>
              <?php endif; ?>
            </div>
          </div>
        </div>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

<?php include(FOOTER_TEMPLATE); ?>
