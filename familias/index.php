<?php require_once('processa/index_processa.php'); ?>
<?php include(ch_gestao_alunos() ? HEADER_TEMPLATE : HEADER_TEMPLATE2); ?>

<?php
$botaoAlunos = '<a class="btn btn-outline-secondary" href="' . ch_url('alunos/index.php') . '"><i class="fas fa-child-reaching"></i> Ver lista de alunos</a>';
ch_cabecalho('Famílias e Responsáveis', 'fa-people-roof', $botaoAlunos);
?>

<div class="alert alert-light border py-2 mb-3 small">
  <i class="fas fa-circle-info text-primary"></i>
  Pesquise pelo <strong>nome ou CPF do pai, da mãe ou do responsável</strong> para visualizar todas as crianças vinculadas a ele(a) na rede municipal.
  Os vínculos por CPF são feitos na ficha de cadastro/edição do aluno.
</div>

<form method="get" action="index.php" class="row g-2 align-items-end mb-3">
  <div class="col-md-4">
    <label class="fw-bold">Pai, mãe, responsável (nome ou CPF) ou aluno</label>
    <input type="text" name="q" class="form-control" value="<?= ch_h($q) ?>"
           placeholder="Ex.: Maria Aparecida ou 123.456.789-00">
  </div>
  <div class="col-md-3">
    <label class="fw-bold">Unidade</label>
    <select name="unidade" class="form-select">
      <option value="0">Todas</option>
      <?php foreach ($unidades as $u): ?>
        <option value="<?= (int)$u['id'] ?>" <?= $fUnidade === (int)$u['id'] ? 'selected' : '' ?>>
          <?= ch_h($u['unidade']) ?>
        </option>
      <?php endforeach; ?>
    </select>
  </div>
  <div class="col-md-2">
    <label class="fw-bold">Vínculo</label>
    <select name="parentesco" class="form-select">
      <option value="">Todos</option>
      <?php foreach (fam_parentescos() as $cod => $rot): ?>
        <option value="<?= ch_h($cod) ?>" <?= $fParentesco === $cod ? 'selected' : '' ?>><?= ch_h($rot) ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <div class="col-md-3 d-flex align-items-center gap-2">
    <div class="form-check me-2">
      <input class="form-check-input" type="checkbox" name="irmaos" id="f-irmaos" value="1" <?= $soIrmaos ? 'checked' : '' ?>>
      <label class="form-check-label small" for="f-irmaos">2+ filhos</label>
    </div>
    <button class="btn btn-primary flex-grow-1"><i class="fas fa-magnifying-glass"></i> Buscar</button>
  </div>
</form>

<?php if (!$responsaveis): ?>
  <div class="text-center text-muted py-5">
    <i class="fas fa-people-roof fa-2x mb-3 d-block"></i>
    Nenhum responsável com vínculo por CPF encontrado para os filtros informados.
  </div>
<?php else: ?>
  <div class="mb-2 text-muted small">
    <b><?= count($responsaveis) ?></b> responsável(is) listado(s).
  </div>

  <?php foreach ($responsaveis as $r): ?>
    <div class="card border-0 shadow-sm rounded-4 mb-3">
      <div class="card-body">
        <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-2">
          <div>
            <h5 class="mb-0">
              <i class="fas fa-user text-primary me-1"></i> <?= ch_h($r['NOME']) ?>
              <span class="badge bg-success ms-1"><?= count($r['FILHOS']) ?> <?= count($r['FILHOS']) === 1 ? 'filho(a)/aluno(a)' : 'filhos/alunos' ?></span>
            </h5>
            <div class="small text-muted mt-1">
              <?php if ($verCpf): ?>
                <span class="me-3"><i class="fas fa-id-card"></i> CPF: <b><?= ch_h(ch_cpf_mask($r['CPF'])) ?></b></span>
              <?php endif; ?>
              <?php if (!empty($r['TELEFONE'])): ?>
                <span class="me-3"><i class="fas fa-phone"></i> <?= ch_h($r['TELEFONE']) ?></span>
              <?php endif; ?>
              <?php if (!empty($r['EMAIL'])): ?>
                <span><i class="fas fa-envelope"></i> <?= ch_h($r['EMAIL']) ?></span>
              <?php endif; ?>
            </div>
          </div>
        </div>

        <div class="table-responsive">
          <table class="table table-sm align-middle mb-0">
            <thead>
              <tr>
                <th>Vínculo</th>
                <th>Aluno(a)</th>
                <th>Matrícula</th>
                <th>Unidade / Turma</th>
                <th class="text-end">Opções</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($r['FILHOS'] as $f): ?>
                <tr class="<?= (int)$f['ATIVO'] === 1 ? '' : 'text-muted' ?>">
                  <td style="width:170px">
                    <span class="badge bg-light text-dark border">
                      <?= ch_h(fam_parentesco_rotulo($f['PARENTESCO'], $f['DESCRICAO_OUTRO'] ?? '')) ?>
                    </span>
                    <?php if (!empty($f['CONTATO_PRINCIPAL'])): ?>
                      <span class="badge bg-info text-dark" title="Contato principal">Principal</span>
                    <?php endif; ?>
                  </td>
                  <td>
                    <a class="fw-bold text-decoration-none" href="<?= ch_url('alunos/view.php?id=' . (int)$f['ID_ALUNO']) ?>">
                      <?= ch_h($f['ALUNO_NOME']) ?>
                    </a>
                    <?php if ((int)$f['ATIVO'] !== 1): ?>
                      <span class="badge bg-secondary ms-1">Inativo</span>
                    <?php endif; ?>
                  </td>
                  <td><?= ch_h($f['MATRICULA']) ?></td>
                  <td>
                    <?= ch_h($f['UNIDADE']) ?> —
                    <small class="text-muted">
                      <?= ch_h($series[$f['SERIE'] ?? '']['rotulo'] ?? 'Série') ?>
                      · Sala <?= ch_h($f['TURMA']) ?> · <?= ch_h($f['PERIODO']) ?> (<?= (int)$f['ANO_LETIVO'] ?>)
                    </small>
                  </td>
                  <td class="text-end text-nowrap">
                    <a class="btn btn-sm btn-outline-warning" href="<?= ch_url('alunos/view.php?id=' . (int)$f['ID_ALUNO']) ?>" title="Ficha do aluno">
                      <i class="far fa-eye"></i>
                    </a>
                    <a class="btn btn-sm btn-outline-primary" target="_blank" rel="noopener"
                       href="<?= ch_url('alunos/cracha.php?id=' . (int)$f['ID_ALUNO']) ?>" title="Gerar crachá frente e verso">
                      <i class="fas fa-id-card"></i>
                    </a>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  <?php endforeach; ?>
<?php endif; ?>

<?php include(FOOTER_TEMPLATE); ?>
