<?php require_once('processa/index_processa.php'); ?>
<?php include(ch_gestao_alunos() ? HEADER_TEMPLATE : HEADER_TEMPLATE2); ?>

<?php
$botoesTopo = '';
if (ch_gestao_alunos()) {
    $botoesTopo = '<a class="btn btn-success" href="' . ch_url('aee/atribuir.php?ano=' . $fAno) . '"><i class="fas fa-plus"></i> Novo Atendimento AEE</a>';
}
ch_cabecalho('Atendimento Educacional Especializado (AEE)', 'fa-hands-holding-child', $botoesTopo);
?>

<div class="row g-3 mb-4">
  <div class="col-sm-6 col-md-4">
    <div class="card border-0 shadow-sm rounded-4 bg-primary text-white p-3">
      <div class="d-flex justify-content-between align-items-center">
        <div>
          <div class="small text-uppercase opacity-75 fw-bold">Alunos no AEE (<?= $fAno ?>)</div>
          <h2 class="mb-0 fw-bold"><?= $totalAlunosAee ?></h2>
        </div>
        <i class="fas fa-hands-holding-child fa-2x opacity-50"></i>
      </div>
    </div>
  </div>
  <div class="col-sm-6 col-md-4">
    <div class="card border-0 shadow-sm rounded-4 bg-success text-white p-3">
      <div class="d-flex justify-content-between align-items-center">
        <div>
          <div class="small text-uppercase opacity-75 fw-bold">Em Atendimento Ativo</div>
          <h2 class="mb-0 fw-bold"><?= $qtdAtivos ?></h2>
        </div>
        <i class="fas fa-circle-check fa-2x opacity-50"></i>
      </div>
    </div>
  </div>
  <div class="col-sm-6 col-md-4">
    <div class="card border-0 shadow-sm rounded-4 bg-warning text-dark p-3">
      <div class="d-flex justify-content-between align-items-center">
        <div>
          <div class="small text-uppercase opacity-75 fw-bold">Sem Professor Vinculado</div>
          <h2 class="mb-0 fw-bold"><?= $qtdSemProfessor ?></h2>
        </div>
        <i class="fas fa-user-clock fa-2x opacity-50"></i>
      </div>
    </div>
  </div>
</div>

<form method="get" action="index.php" class="card border-0 shadow-sm rounded-4 p-3 mb-4">
  <div class="row g-2 align-items-end">
    <div class="col-md-2">
      <label class="fw-bold" for="ano">Ano Letivo</label>
      <select name="ano" id="ano" class="form-select">
        <?php foreach ($anos as $a): ?>
          <option value="<?= $a ?>" <?= $a === $fAno ? 'selected' : '' ?>><?= $a ?><?= $a === $anoAtual ? ' (atual)' : '' ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <?php if (ch_rede() || count($unidadesOpcoes) > 1): ?>
    <div class="col-md-3">
      <label class="fw-bold" for="unidade_id">Unidade Escolar</label>
      <select name="unidade_id" id="unidade_id" class="form-select">
        <option value="0">Todas as escolas</option>
        <?php foreach ($unidadesOpcoes as $u): ?>
          <option value="<?= (int)$u['id'] ?>" <?= (int)$u['id'] === $fUnidade ? 'selected' : '' ?>><?= ch_h($u['unidade']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <?php endif; ?>
    <div class="col-md-3">
      <label class="fw-bold" for="professor_id">Professor(a) de AEE</label>
      <select name="professor_id" id="professor_id" class="form-select">
        <option value="0">Todos os professores</option>
        <option value="-1" <?= $fProf === -1 ? 'selected' : '' ?>>[ Sem professor atribuído ]</option>
        <?php foreach ($professoresAeeOpcoes as $pa): ?>
          <option value="<?= (int)$pa['ID'] ?>" <?= (int)$pa['ID'] === $fProf ? 'selected' : '' ?>><?= ch_h($pa['NOME']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-md-2">
      <label class="fw-bold" for="status">Situação</label>
      <select name="status" id="status" class="form-select">
        <option value="">Todas</option>
        <option value="atendimento" <?= $fStatus === 'atendimento' ? 'selected' : '' ?>>Em atendimento</option>
        <option value="aguardando"  <?= $fStatus === 'aguardando' ? 'selected' : '' ?>>Aguardando professor</option>
        <option value="inativo"     <?= $fStatus === 'inativo' ? 'selected' : '' ?>>Suspenso / Inativo</option>
      </select>
    </div>
    <div class="col-md-2">
      <label class="fw-bold" for="busca">Buscar</label>
      <input type="text" name="busca" id="busca" class="form-control" value="<?= ch_h($fBusca) ?>" placeholder="Aluno, matrícula, SRM...">
    </div>
    <div class="col-12 d-flex justify-content-end gap-2 mt-3">
      <a href="index.php?ano=<?= $fAno ?>" class="btn btn-outline-secondary"><i class="fas fa-rotate-left"></i> Limpar</a>
      <button class="btn btn-primary" type="submit"><i class="fas fa-filter"></i> Filtrar</button>
    </div>
  </div>
</form>

<div class="table-responsive-sm">
  <table id="item" data-vazio="Nenhum aluno em AEE encontrado para os filtros selecionados." class="table table-light table-striped table-sm align-middle">
    <thead class="text-sm-center">
      <tr>
        <th width="8%">Matrícula</th>
        <th width="24%">Aluno</th>
        <th width="18%">Turma / Unidade</th>
        <th width="18%">Professor(a) de AEE</th>
        <th width="18%">Horários / SRM</th>
        <th width="6%">Situação</th>
        <th width="8%">Ações</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($alunosAee as $row): ?>
        <?php
          $inclusoes = aluno_inclusao_codigos($row['DEFICIENCIAS_INCLUSAO'] ?? []);
          $opcoesInclusao = aluno_inclusao_opcoes();
          $temProf = !empty($row['ID_PROFESSOR_AEE']);
          $ativoAee = !empty($row['AEE_ATIVO']);
        ?>
        <tr>
          <td><?= ch_h($row['MATRICULA']) ?></td>
          <td>
            <a href="<?= ch_url('alunos/view.php?id=' . (int)$row['ID']) ?>" class="fw-bold text-dark text-decoration-none">
              <?= ch_h($row['NOME']) ?>
            </a>
            <?php if ($inclusoes): ?>
              <div class="mt-1">
                <?php foreach ($inclusoes as $codInc): ?>
                  <span class="badge bg-light text-dark border me-1" style="font-size: .7rem;" title="<?= ch_h($opcoesInclusao[$codInc] ?? $codInc) ?>">
                    <?= ch_h($codInc) ?>
                  </span>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>
          </td>
          <td>
            <?= ch_h($row['TURMA']) ?><br>
            <small class="text-muted"><?= ch_h($row['UNIDADE']) ?></small>
          </td>
          <td>
            <?php if ($temProf): ?>
              <strong><i class="fas fa-chalkboard-user text-success me-1"></i> <?= ch_h($row['PROFESSOR_AEE_NOME']) ?></strong>
            <?php else: ?>
              <span class="badge bg-warning text-dark"><i class="fas fa-triangle-exclamation me-1"></i> Aguardando professor</span>
            <?php endif; ?>
          </td>
          <td>
            <?php if (!empty($row['DIAS_HORARIOS'])): ?>
              <div><i class="fas fa-clock text-primary me-1"></i> <?= ch_h($row['DIAS_HORARIOS']) ?></div>
            <?php endif; ?>
            <?php if (!empty($row['SALA_RECURSOS'])): ?>
              <small class="text-muted"><i class="fas fa-door-open me-1"></i> SRM: <?= ch_h($row['SALA_RECURSOS']) ?></small>
            <?php endif; ?>
            <?php if (empty($row['DIAS_HORARIOS']) && empty($row['SALA_RECURSOS'])): ?>
              <span class="text-muted small">Não definidos</span>
            <?php endif; ?>
          </td>
          <td class="text-center">
            <?php if ($ativoAee && $temProf): ?>
              <span class="badge bg-success">Ativo</span>
            <?php elseif ($ativoAee && !$temProf): ?>
              <span class="badge bg-warning text-dark">Pendente</span>
            <?php else: ?>
              <span class="badge bg-secondary">Inativo</span>
            <?php endif; ?>
          </td>
          <td class="text-center">
            <?php if (ch_gestao_alunos()): ?>
              <a class="btn btn-sm btn-outline-primary" href="<?= ch_url('aee/atribuir.php?aluno_id=' . (int)$row['ID'] . '&ano=' . $fAno) ?>" title="Atribuir ou editar horários do AEE">
                <i class="fas fa-pen-to-square"></i> Atribuir
              </a>
            <?php else: ?>
              <a class="btn btn-sm btn-outline-secondary" href="<?= ch_url('alunos/view.php?id=' . (int)$row['ID']) ?>" title="Ver ficha">
                <i class="far fa-eye"></i>
              </a>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

<script src="<?= BASEURL ?>js/tabela.js"></script>
<?php include(FOOTER_TEMPLATE); ?>
