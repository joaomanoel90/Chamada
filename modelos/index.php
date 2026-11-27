<?php
require_once(dirname(__DIR__) . '/config.php');
require_once(DBAPI);
require_once(ABSPATH . 'inc/chamada.php');
ch_db();
ch_require_gestor();

$tabelaOk = mod_tem_tabela();
$modelos = $tabelaOk ? (mod_opcoes(false) ?: []) : [];
$modeloId = (int)($_GET['id'] ?? 0);
$modelo = [];
$d = ['ID' => 0, 'NOME' => '', 'AULAS_DIA' => 2, 'DESCRICAO' => '', 'ATIVO' => 1, 'DISCIPLINAS' => []];
$erros = [];

if ($modeloId > 0) {
    $modelo = mod_dados($modeloId);
    if (!$modelo) {
        ch_flash('Modelo não encontrado.', 'danger');
        header('Location: index.php');
        exit;
    }
    $d = $modelo;
}

include(HEADER_TEMPLATE);
ch_cabecalho('Modelos de Turma', 'fa-diagram-project');
?>
<?php if (!$tabelaOk): ?>
  <div class="alert alert-warning">
    Execute <code>sql_inicial/ATUALIZAR_AULAS_DIA_MODELOS.sql</code> para usar os modelos.
  </div>
<?php endif; ?>

<div class="row g-4">
  <div class="col-12 col-xl-5">
    <div class="card shadow-sm">
      <div class="card-header fw-bold"><i class="fas fa-plus-circle me-2"></i><?= $modeloId ? 'Editar modelo' : 'Novo modelo' ?></div>
      <div class="card-body">
        <?php foreach ($erros as $msg): ?>
          <div class="alert alert-danger"><?= ch_h($msg) ?></div>
        <?php endforeach; ?>
        <form method="post" action="processa/salvar_processa.php">
          <?= ch_csrf_campo() ?>
          <input type="hidden" name="id" value="<?= (int)$modeloId ?>">
          <div class="mb-3">
            <label class="form-label small fw-bold">Nome do modelo</label>
            <input type="text" class="form-control" name="nome" maxlength="80" value="<?= ch_h($d['NOME']) ?>" required placeholder="Ex.: PEB II — 4 aulas/dia">
          </div>
          <div class="row g-2 mb-3">
            <div class="col-6">
              <label class="form-label small fw-bold">Aulas por dia</label>
              <select class="form-select" name="aulas_dia" required>
                <?php for ($n = 1; $n <= ch_aula_max(); $n++): ?>
                  <option value="<?= $n ?>"<?= (int)$d['AULAS_DIA'] === $n ? ' selected' : '' ?>><?= $n ?><?= $n === 1 ? ' aula' : ' aulas' ?></option>
                <?php endfor; ?>
              </select>
            </div>
            <div class="col-6 d-flex align-items-end">
              <div class="form-check">
                <input class="form-check-input" type="checkbox" name="ativo" id="modelo-ativo" value="1"<?= !empty($d['ATIVO']) ? ' checked' : '' ?>>
                <label class="form-check-label small" for="modelo-ativo">Ativo</label>
              </div>
            </div>
          </div>
          <div class="mb-3">
            <label class="form-label small fw-bold">Descrição</label>
            <input type="text" class="form-control" name="descricao" maxlength="300" value="<?= ch_h($d['DESCRICAO'] ?? '') ?>">
          </div>

          <div class="d-flex justify-content-between align-items-center mb-2">
            <span class="small fw-bold">Matérias e aulas/semana</span>
            <button type="button" class="btn btn-sm btn-outline-primary" id="add-linha-modelo"><i class="fas fa-plus"></i> Matéria</button>
          </div>
          <div id="lista-disciplinas-modelo">
            <?php foreach ((array)$d['DISCIPLINAS'] as $disc): ?>
              <div class="row g-2 mb-2" data-linha-modelo>
                <div class="col-8">
                  <select name="disc_nome[]" class="form-select" required>
                    <option value="">Selecione...</option>
                    <?php foreach (eq_materias_opcoes() as $op): ?>
                      <option value="<?= ch_h($op) ?>"<?= ($disc['NOME'] ?? '') === $op ? ' selected' : '' ?>><?= ch_h($op) ?></option>
                    <?php endforeach; ?>
                    <?php if (!in_array($disc['NOME'] ?? '', eq_materias_opcoes(), true) && ($disc['NOME'] ?? '') !== ''): ?>
                      <option value="<?= ch_h($disc['NOME']) ?>" selected><?= ch_h($disc['NOME']) ?></option>
                    <?php endif; ?>
                  </select>
                </div>
                <div class="col-3">
                  <input type="number" name="disc_aulas[]" class="form-control" min="1" max="40" value="<?= (int)($disc['AULAS'] ?? 1) ?>" required>
                </div>
                <div class="col-1 d-flex align-items-center">
                  <button type="button" class="btn btn-sm btn-outline-danger" data-remover-modelo title="Remover"><i class="fas fa-trash"></i></button>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
          <template id="modelo-linha-modelo">
            <div class="row g-2 mb-2" data-linha-modelo>
              <div class="col-8">
                <select name="disc_nome[]" class="form-select" required>
                  <option value="">Selecione...</option>
                  <?php foreach (eq_materias_opcoes() as $op): ?>
                    <option value="<?= ch_h($op) ?>"><?= ch_h($op) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="col-3">
                <input type="number" name="disc_aulas[]" class="form-control" min="1" max="40" value="1" required>
              </div>
              <div class="col-1 d-flex align-items-center">
                <button type="button" class="btn btn-sm btn-outline-danger" data-remover-modelo title="Remover"><i class="fas fa-trash"></i></button>
              </div>
            </div>
          </template>

          <button type="submit" class="btn btn-primary mt-3"><i class="fas fa-save me-2"></i>Salvar modelo</button>
          <?php if ($modeloId): ?>
            <a class="btn btn-outline-secondary mt-3" href="index.php">Cancelar</a>
          <?php endif; ?>
        </form>
      </div>
    </div>
  </div>

  <div class="col-12 col-xl-7">
    <div class="card shadow-sm">
      <div class="card-header fw-bold"><i class="fas fa-copy me-2"></i>Aplicar modelo a turmas</div>
      <div class="card-body">
        <?php
        $turmasOp = ch_rows(
            'SELECT t.ID, t.NOME, t.SERIE, t.ANO_LETIVO, u.unidade AS UNIDADE, p.NOME AS PERIODO
               FROM TB_TURMAS t
              INNER JOIN TB_UNIDADES u ON u.id = t.ID_UNIDADE
              INNER JOIN TB_PERIODOS p ON p.ID = t.ID_PERIODO
              ORDER BY t.ANO_LETIVO DESC, u.unidade, t.SERIE, t.NOME, p.NOME'
        ) ?: [];
        ?>
        <form method="post" action="processa/aplicar_processa.php">
          <?= ch_csrf_campo() ?>
          <div class="mb-3">
            <label class="form-label small fw-bold">Modelo</label>
            <select name="id_modelo" class="form-select" required>
              <option value="">Selecione...</option>
              <?php foreach (mod_opcoes() as $op): ?>
                <option value="<?= (int)$op['ID'] ?>"<?= $modeloId === (int)$op['ID'] ? ' selected' : '' ?>>
                  <?= ch_h($op['NOME']) ?> · <?= (int)$op['AULAS_DIA'] ?> aula(s)/dia
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="mb-3">
            <label class="form-label small fw-bold">Turmas de destino (mesma unidade ou outras)</label>
            <select name="turmas[]" class="form-select" size="12" multiple required>
              <?php foreach ($turmasOp as $t): ?>
                <option value="<?= (int)$t['ID'] ?>">
                  <?= (int)$t['ANO_LETIVO'] ?> · <?= ch_h($t['UNIDADE']) ?> — Sala <?= ch_h($t['NOME']) ?>
                  · <?= ch_h(ch_series_ensino()[$t['SERIE'] ?? '']['rotulo'] ?? 'não classificada') ?> · <?= ch_h($t['PERIODO']) ?>
                </option>
              <?php endforeach; ?>
            </select>
            <small class="text-muted">Segure Ctrl (ou Cmd) para escolher várias turmas. Apenas turmas que você pode gerenciar serão alteradas.</small>
          </div>
          <button type="submit" class="btn btn-success"><i class="fas fa-wand-magic-sparkles me-2"></i>Aplicar às turmas selecionadas</button>
        </form>
      </div>
    </div>
  </div>
</div>

<div class="card shadow-sm mt-4">
  <div class="card-header fw-bold"><i class="fas fa-list me-2"></i>Modelos cadastrados</div>
  <div class="table-responsive">
    <table class="table table-striped table-hover align-middle mb-0">
      <thead><tr><th>Modelo</th><th>Aulas/dia</th><th>Matérias</th><th>Status</th><th class="text-end">Ações</th></tr></thead>
      <tbody>
        <?php if (!$modelos): ?>
          <tr><td colspan="5" class="text-center text-muted py-4">Nenhum modelo cadastrado.</td></tr>
        <?php endif; ?>
        <?php foreach ($modelos as $m): ?>
          <?php $completo = mod_dados($m['ID']); ?>
          <tr>
            <td><strong><?= ch_h($m['NOME']) ?></strong><?php if (!empty($m['DESCRICAO'])): ?><br><small class="text-muted"><?= ch_h($m['DESCRICAO']) ?></small><?php endif; ?></td>
            <td><?= (int)$m['AULAS_DIA'] ?></td>
            <td>
              <?php foreach ((array)($completo['DISCIPLINAS'] ?? []) as $disc): ?>
                <span class="badge bg-light text-dark border"><?= ch_h($disc['NOME']) ?> (<?= (int)$disc['AULAS'] ?>)</span>
              <?php endforeach; ?>
            </td>
            <td><span class="badge <?= !empty($m['ATIVO']) ? 'bg-success' : 'bg-secondary' ?>"><?= !empty($m['ATIVO']) ? 'Ativo' : 'Inativo' ?></span></td>
            <td class="text-end"><a class="btn btn-sm btn-outline-primary" href="index.php?id=<?= (int)$m['ID'] ?>"><i class="fas fa-edit"></i> Editar</a></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<script nonce="<?= ch_csp_nonce() ?>">
(function () {
  var lista = document.getElementById('lista-disciplinas-modelo');
  var modelo = document.getElementById('modelo-linha-modelo');
  var add = document.getElementById('add-linha-modelo');
  if (!lista || !modelo || !add) return;
  add.addEventListener('click', function () {
    var frag = document.createElement('template');
    frag.innerHTML = modelo.innerHTML.trim();
    lista.appendChild(frag.content.firstElementChild);
    lista.lastElementChild.querySelector('select').focus();
  });
  lista.addEventListener('click', function (ev) {
    var btn = ev.target.closest('[data-remover-modelo]');
    if (!btn) return;
    var linha = btn.closest('[data-linha-modelo]');
    if (linha && lista.querySelectorAll('[data-linha-modelo]').length > 1) linha.remove();
  });
})();
</script>

<?php include(FOOTER_TEMPLATE); ?>
