<?php require_once('processa/index_processa.php'); ?>
<?php include(ch_gestor() ? HEADER_TEMPLATE : HEADER_TEMPLATE2); ?>

<?php
$novoFeriadoUrl = 'add.php';
if ($fCalendario !== '' && $fCalendario !== 'AMBOS') {
    $novoFeriadoUrl .= '?calendario=' . urlencode($fCalendario);
}
ch_cabecalho('Calendário Escolar', 'fa-calendar-days',
    ch_gestor() ? '<a class="btn btn-success" href="' . ch_h($novoFeriadoUrl) . '"><i class="fa fa-plus"></i> Novo Feriado</a>' : '');
?>

<div class="card card-body bg-light mb-4">
  <form method="get" action="index.php" class="row g-3 align-items-end">
    <div class="col-md-4">
      <label class="form-label small fw-bold mb-1">Ano letivo</label>
      <select name="ano" class="form-select">
        <?php foreach ($anos as $a): ?>
          <option value="<?= (int)$a ?>" <?= $a === $fAno ? 'selected' : '' ?>><?= (int)$a ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-md-5">
      <label class="form-label small fw-bold mb-1">Calendário</label>
      <select name="calendario" class="form-select">
        <option value="">Todos os calendários</option>
        <option value="AMBOS" <?= $fCalendario === 'AMBOS' ? 'selected' : '' ?>>Ambos os calendários</option>
        <?php foreach ($opcoesCalendario as $codigo => $rotulo): ?>
          <option value="<?= ch_h($codigo) ?>" <?= $fCalendario === $codigo ? 'selected' : '' ?>><?= ch_h($rotulo) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-md-3">
      <button class="btn btn-primary w-100"><i class="fas fa-filter"></i> Filtrar</button>
    </div>
  </form>
</div>

<?php if (ch_rede()): ?>
<details class="mb-4">
  <summary class="btn btn-sm btn-outline-primary"><i class="fas fa-door-open"></i> Abrir novo ano letivo</summary>
  <div class="card card-body mt-3">
    <form method="post" action="processa/abrir_ano_processa.php" class="row g-3">
      <?= ch_csrf_campo() ?>
      <div class="col-md-2">
        <label class="form-label small fw-bold mb-1">Ano letivo</label>
        <input type="number" name="ano" class="form-control" min="2000" max="2100" value="<?= (int)$fAno + 1 ?>" required>
      </div>
      <?php foreach ($opcoesCalendario as $codigo => $rotulo): ?>
        <?php $chave = strtolower($codigo); ?>
        <div class="col-md-5">
          <div class="fw-bold small mb-2"><?= ch_h($rotulo) ?></div>
          <div class="row g-2">
            <div class="col">
              <label class="form-label small text-muted mb-1">Início</label>
              <input type="date" name="inicio_<?= $chave ?>" class="form-control form-control-sm" required>
            </div>
            <div class="col">
              <label class="form-label small text-muted mb-1">Fim</label>
              <input type="date" name="fim_<?= $chave ?>" class="form-control form-control-sm" required>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
      <div class="col-12 d-flex justify-content-end">
        <button type="submit" class="btn btn-success"><i class="fas fa-save"></i> Abrir ano letivo</button>
      </div>
    </form>
  </div>
</details>
<?php endif; ?>

<h5 class="mb-3"><i class="fas fa-calendar-check"></i> Período letivo e dias letivos — <?= (int)$fAno ?></h5>
<div class="row g-3 mb-4">
  <?php foreach ($opcoesCalendario as $codigo => $rotulo): ?>
    <?php
      $dadosCal = $calendariosDoAno[$codigo] ?? [];
      $cont = $contadores[$codigo];
      $inicio = (string)($dadosCal['INICIO'] ?? '');
      $fim = (string)($dadosCal['FIM'] ?? '');
    ?>
    <div class="col-md-6">
      <div class="card h-100">
        <div class="card-body">
          <div class="d-flex justify-content-between align-items-start gap-2">
            <div>
              <h6 class="fw-bold mb-1"><?= ch_h($rotulo) ?></h6>
              <div class="small text-muted">Período:
                <?php if ($inicio !== '' || $fim !== ''): ?>
                  <?= $inicio !== '' ? ch_h(ch_data_br($inicio)) : '—' ?> a <?= $fim !== '' ? ch_h(ch_data_br($fim)) : '—' ?>
                <?php else: ?>
                  <span class="badge bg-secondary">Não definido</span>
                <?php endif; ?>
              </div>
            </div>
            <div class="text-end">
              <div class="fs-4 fw-bold text-success"><?= (int)$cont['TOTAL'] ?></div>
              <div class="small text-muted">dias letivos</div>
            </div>
          </div>
          <div class="d-flex flex-wrap gap-2 mt-3">
            <span class="badge bg-light text-dark border">Dias úteis: <?= (int)$cont['DIAS_UTEIS'] ?></span>
            <span class="badge bg-light text-dark border">Feriados: <?= (int)$cont['FERIADOS'] ?></span>
            <span class="badge bg-light text-dark border">Acrescidos: <?= (int)$cont['ACRESCIDOS'] ?></span>
            <span class="badge bg-light text-dark border">Dispensados: <?= (int)$cont['RETIRADOS'] ?></span>
          </div>
          <?php if (ch_rede()): ?>
            <details class="mt-3">
              <summary class="btn btn-sm btn-outline-secondary"><i class="fas fa-edit"></i> Alterar período</summary>
              <form method="post" action="processa/calendario_processa.php" class="row g-2 mt-3">
                <?= ch_csrf_campo() ?>
                <input type="hidden" name="ano" value="<?= (int)$fAno ?>">
                <input type="hidden" name="calendario" value="<?= ch_h($codigo) ?>">
                <div class="col-md-6">
                  <label class="form-label small fw-bold mb-1">Início</label>
                  <input type="date" name="inicio" class="form-control form-control-sm" value="<?= ch_h($inicio) ?>" required>
                </div>
                <div class="col-md-6">
                  <label class="form-label small fw-bold mb-1">Fim</label>
                  <input type="date" name="fim" class="form-control form-control-sm" value="<?= ch_h($fim) ?>" required>
                </div>
                <div class="col-12 d-flex justify-content-end">
                  <button type="submit" class="btn btn-sm btn-success"><i class="fas fa-save"></i> Salvar período</button>
                </div>
              </form>
            </details>
          <?php endif; ?>
        </div>
      </div>
    </div>
  <?php endforeach; ?>
</div>

<h5 class="mb-3"><i class="fas fa-calendar-day"></i> Dias do calendário — feriados e exceções — <?= (int)$fAno ?></h5>
<?php if (ch_gestor() && (ch_rede() || $unidades)): ?>
  <details class="mb-4">
    <summary class="btn btn-sm btn-outline-success"><i class="fas fa-plus"></i> Adicionar exceção</summary>
    <div class="card card-body mt-3">
      <form method="post" action="processa/excecao_processa.php" class="row g-3">
        <?= ch_csrf_campo() ?>
        <input type="hidden" name="acao" value="salvar">
        <input type="hidden" name="ano" value="<?= (int)$fAno ?>">
        <div class="col-md-3">
          <label class="form-label small fw-bold mb-1">Calendário</label>
          <select name="calendario" class="form-select form-select-sm" required>
            <?php foreach ($opcoesCalendario as $codigo => $rotulo): ?>
              <option value="<?= ch_h($codigo) ?>" <?= $fCalendario === $codigo ? 'selected' : '' ?>><?= ch_h($rotulo) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-3">
          <label class="form-label small fw-bold mb-1">Abrangência</label>
          <select name="escopo" class="form-select form-select-sm" data-escopo-select required>
            <?php if (ch_rede()): ?><option value="global">Todas as unidades</option><?php endif; ?>
            <?php if ($unidades): ?><option value="unidade">Uma unidade</option><?php endif; ?>
            <?php if ($turmasPorUnidade): ?><option value="turma">Uma turma</option><?php endif; ?>
          </select>
        </div>
        <div class="col-md-3" data-escopo="unidade">
          <label class="form-label small fw-bold mb-1">Unidade</label>
          <select name="id_unidade" class="form-select form-select-sm">
            <?php foreach ($unidades as $u): ?>
              <option value="<?= (int)$u['id'] ?>"><?= ch_h($u['unidade']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-3" data-escopo="turma">
          <label class="form-label small fw-bold mb-1">Turma</label>
          <select name="id_turma" class="form-select form-select-sm">
            <?php foreach ($turmasPorUnidade as $turmas): ?>
              <optgroup label="<?= ch_h($turmas[0]['UNIDADE']) ?>">
                <?php foreach ($turmas as $turma): ?>
                  <option value="<?= (int)$turma['ID'] ?>"><?= ch_h(trim($turma['NOME'] . ($turma['SERIE'] !== '' ? ' · ' . $turma['SERIE'] : ''))) ?></option>
                <?php endforeach; ?>
              </optgroup>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-3">
          <label class="form-label small fw-bold mb-1">Data</label>
          <input type="date" name="data" class="form-control form-control-sm" min="<?= (int)$fAno ?>-01-01" max="<?= (int)$fAno ?>-12-31" required>
        </div>
        <div class="col-md-3">
          <label class="form-label small fw-bold mb-1">Tipo</label>
          <select name="tipo" class="form-select form-select-sm" required>
            <option value="LETIVO">Tornar letivo</option>
            <option value="NAO_LETIVO">Tornar não letivo</option>
          </select>
        </div>
        <div class="col-md-6">
          <label class="form-label small fw-bold mb-1">Motivo</label>
          <input type="text" name="motivo" class="form-control form-control-sm" maxlength="150" placeholder="Ex.: aula de reposição">
        </div>
        <div class="col-12 d-flex justify-content-end">
          <button type="submit" class="btn btn-sm btn-success"><i class="fas fa-check"></i> Adicionar exceção</button>
        </div>
      </form>
    </div>
  </details>
<?php endif; ?>

<div class="alert alert-info py-2">
  <i class="fas fa-circle-info"></i>
  Feriado integral = <strong>não letivo</strong>; feriado parcial = <strong>letivo parcial</strong>.
  A exceção muda o dia para <strong>letivo</strong> ou <strong>não letivo</strong> e pode valer para
  <strong>todas as unidades</strong>, <strong>uma unidade</strong> ou <strong>uma turma</strong>.
  Na chamada, vale a regra mais específica: turma &gt; unidade &gt; global.
</div>

<div class="table-responsive">
  <table id="item" data-vazio="Nenhum feriado ou exceção cadastrado neste ano." class="table table-hover table-striped table-sm align-middle">
    <thead class="table-light text-center">
      <tr>
        <th>Data</th>
        <th>Calendário</th>
        <th>Origem</th>
        <th>Situação</th>
        <th>Detalhe</th>
        <th style="width:30%">Ações</th>
      </tr>
    </thead>
    <tbody>
      <?php if (!$dias): ?>
        <tr><td colspan="6" class="text-center text-muted py-4">Nenhum feriado ou exceção cadastrado neste ano.</td></tr>
      <?php endif; ?>
      <?php foreach ($dias as $d): ?>
        <?php
          $classeEfetivo = $d['EFETIVO'] === 'NAO_LETIVO' ? 'danger'
              : ($d['EFETIVO'] === 'LETIVO' ? 'success' : 'warning text-dark');
          $rotuloEfetivo = $d['EFETIVO'] === 'NAO_LETIVO' ? 'Não letivo'
              : ($d['EFETIVO'] === 'LETIVO' ? 'Letivo' : 'Letivo parcial');
          $rotuloCalDia = $d['CALENDARIO'] === 'CRECHE' ? 'Creche' : 'Fundamental';
          $exc = $d['EXCECAO'];
          $podeCriarExc = $d['ORIGEM'] === 'FERIADO' && ch_gestor()
              && ch_pode_gerenciar_excecao($d['ID_UNIDADE_FERIADO'] ?? 0, 0);
          $podeEditarExc = $exc && $exc['PODE_GERENCIAR'];
          $temAcao = (($d['ORIGEM'] === 'FERIADO' && ch_gestor()) || $podeEditarExc);
        ?>
        <tr>
          <td class="text-nowrap fw-semibold"><?= ch_h(ch_data_br($d['DATA'])) ?></td>
          <td>
            <span class="badge bg-<?= $d['CALENDARIO'] === 'CRECHE' ? 'info text-dark' : 'primary' ?>"><?= ch_h($rotuloCalDia) ?></span>
          </td>
          <td>
            <span class="badge bg-<?= $d['ORIGEM'] === 'FERIADO' ? 'secondary' : 'dark' ?>">
              <?= $d['ORIGEM'] === 'FERIADO' ? 'Feriado' : 'Exceção' ?>
            </span>
          </td>
          <td><span class="badge bg-<?= $classeEfetivo ?>"><?= $rotuloEfetivo ?></span></td>
          <td>
            <?php if ($d['ORIGEM'] === 'FERIADO'): ?>
              <div class="fw-semibold"><?= ch_h($d['DESCRICAO']) ?></div>
              <div class="small text-muted">
                <?= $d['FERIADO_TIPO'] === 'INTEGRAL' ? 'Dia inteiro' : 'Parcial: ' . ch_h($d['HORA_INICIO']) . ' às ' . ch_h($d['HORA_FIM']) ?>
                · <?= $d['UNIDADE'] !== '' ? ch_h($d['UNIDADE']) : 'Todas as unidades' ?>
              </div>
              <?php if ($d['EXCECAO'] && $d['MOTIVO'] !== ''): ?>
                <div class="small text-muted mt-1"><i class="fas fa-arrow-right"></i> <?= ch_h($d['MOTIVO']) ?></div>
              <?php endif; ?>
              <?php if ($d['EXCECAO'] && $d['ESCOPO'] !== 'GLOBAL'): ?>
                <div class="small text-muted mt-1"><i class="fas fa-users"></i> <?= ch_h($d['ROTULO_ESCOPO']) ?></div>
              <?php endif; ?>
            <?php else: ?>
              <span class="small"><?= $d['MOTIVO'] !== '' ? ch_h($d['MOTIVO']) : 'Sem motivo informado' ?></span>
              <div class="small text-muted mt-1"><i class="fas fa-users"></i> <?= ch_h($d['ROTULO_ESCOPO']) ?></div>
            <?php endif; ?>
          </td>
          <td>
            <?php if (!$temAcao): ?>
              <span class="text-muted">—</span>
            <?php else: ?>
              <div class="d-grid gap-2">
                <?php if ($d['ORIGEM'] === 'FERIADO' && ch_gestor()): ?>
                  <div class="btn-group">
                    <button type="button" class="btn btn-sm btn-outline-success dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
                      Feriado
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end">
                      <li><a class="dropdown-item" href="edit.php?id=<?= (int)$d['FERIADO_ID'] ?>"><i class="far fa-edit"></i> Editar feriado</a></li>
                      <li>
                        <form method="post" action="processa/delete_processa.php" class="px-3 py-1">
                          <?= ch_csrf_campo() ?>
                          <input type="hidden" name="id" value="<?= (int)$d['FERIADO_ID'] ?>">
                          <button type="submit" class="btn btn-sm btn-outline-danger w-100"
                                  data-confirm="Excluir este feriado? As chamadas já lançadas serão mantidas.">
                            Excluir feriado
                          </button>
                        </form>
                      </li>
                    </ul>
                  </div>
                <?php endif; ?>

                <?php if ($podeCriarExc && !$d['EXCECAO']): ?>
                  <div class="btn-group">
                    <button type="button" class="btn btn-sm btn-outline-primary dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
                      Exceção
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end">
                      <li>
                        <form method="post" action="processa/excecao_processa.php" class="px-3 py-1">
                          <?= ch_csrf_campo() ?>
                          <input type="hidden" name="acao" value="salvar">
                          <input type="hidden" name="ano" value="<?= (int)$fAno ?>">
                          <input type="hidden" name="id" value="0">
                          <input type="hidden" name="calendario" value="<?= ch_h($d['CALENDARIO']) ?>">
                          <input type="hidden" name="escopo" value="<?= $d['ID_UNIDADE_FERIADO'] > 0 ? 'unidade' : 'global' ?>">
                          <input type="hidden" name="id_unidade" value="<?= (int)$d['ID_UNIDADE_FERIADO'] ?>">
                          <input type="hidden" name="id_turma" value="0">
                          <input type="hidden" name="data" value="<?= ch_h($d['DATA']) ?>">
                          <input type="hidden" name="tipo" value="LETIVO">
                          <input type="hidden" name="motivo" value="Ajuste no calendário escolar">
                          <button type="submit" class="btn btn-sm btn-outline-success w-100"><i class="fas fa-check"></i> Tornar letivo</button>
                        </form>
                      </li>
                      <li>
                        <form method="post" action="processa/excecao_processa.php" class="px-3 py-1">
                          <?= ch_csrf_campo() ?>
                          <input type="hidden" name="acao" value="salvar">
                          <input type="hidden" name="ano" value="<?= (int)$fAno ?>">
                          <input type="hidden" name="id" value="0">
                          <input type="hidden" name="calendario" value="<?= ch_h($d['CALENDARIO']) ?>">
                          <input type="hidden" name="escopo" value="<?= $d['ID_UNIDADE_FERIADO'] > 0 ? 'unidade' : 'global' ?>">
                          <input type="hidden" name="id_unidade" value="<?= (int)$d['ID_UNIDADE_FERIADO'] ?>">
                          <input type="hidden" name="id_turma" value="0">
                          <input type="hidden" name="data" value="<?= ch_h($d['DATA']) ?>">
                          <input type="hidden" name="tipo" value="NAO_LETIVO">
                          <input type="hidden" name="motivo" value="Ajuste no calendário escolar">
                          <button type="submit" class="btn btn-sm btn-outline-secondary w-100"><i class="fas fa-ban"></i> Marcar não letivo</button>
                        </form>
                      </li>
                    </ul>
                  </div>
                <?php elseif ($podeEditarExc): ?>
                  <details>
                    <summary class="btn btn-sm btn-outline-primary">Exceção</summary>
                    <div class="border rounded p-3 mt-2 bg-light">
                      <form method="post" action="processa/excecao_processa.php">
                        <?= ch_csrf_campo() ?>
                        <input type="hidden" name="acao" value="salvar">
                        <input type="hidden" name="ano" value="<?= (int)$fAno ?>">
                        <input type="hidden" name="id" value="<?= (int)$exc['ID'] ?>">
                        <input type="hidden" name="calendario" value="<?= ch_h($exc['CALENDARIO']) ?>">
                        <input type="hidden" name="escopo" value="<?= ch_h(strtolower($exc['ESCOPO'])) ?>">
                        <input type="hidden" name="id_unidade" value="<?= (int)$exc['ID_UNIDADE'] ?>">
                        <input type="hidden" name="id_turma" value="<?= (int)$exc['ID_TURMA'] ?>">
                        <div class="small text-muted mb-2">Abrangência: <?= ch_h($exc['ROTULO_ESCOPO']) ?>. Para mudar, exclua e crie outra exceção.</div>
                        <?php if ($d['ORIGEM'] === 'EXCECAO'): ?>
                          <div class="mb-2">
                            <label class="form-label small text-muted mb-1">Data</label>
                            <input type="date" name="data" class="form-control form-control-sm" value="<?= ch_h($exc['DATA']) ?>" required>
                          </div>
                        <?php else: ?>
                          <input type="hidden" name="data" value="<?= ch_h($exc['DATA']) ?>">
                        <?php endif; ?>
                        <div class="mb-2">
                          <label class="form-label small text-muted mb-1">Tipo</label>
                          <select name="tipo" class="form-select form-select-sm">
                            <option value="LETIVO" <?= $exc['TIPO'] === 'LETIVO' ? 'selected' : '' ?>>Letivo</option>
                            <option value="NAO_LETIVO" <?= $exc['TIPO'] === 'NAO_LETIVO' ? 'selected' : '' ?>>Não letivo</option>
                          </select>
                        </div>
                        <div class="mb-3">
                          <label class="form-label small text-muted mb-1">Motivo</label>
                          <input type="text" name="motivo" class="form-control form-control-sm" maxlength="150" value="<?= ch_h($exc['MOTIVO']) ?>">
                        </div>
                        <button type="submit" class="btn btn-sm btn-success w-100 mb-2"><i class="fas fa-save"></i> Salvar exceção</button>
                      </form>
                      <form method="post" action="processa/excecao_processa.php" id="del-exc-<?= (int)$exc['ID'] ?>">
                        <?= ch_csrf_campo() ?>
                        <input type="hidden" name="acao" value="excluir">
                        <input type="hidden" name="ano" value="<?= (int)$fAno ?>">
                        <input type="hidden" name="id" value="<?= (int)$exc['ID'] ?>">
                        <input type="hidden" name="escopo" value="<?= ch_h(strtolower($exc['ESCOPO'])) ?>">
                        <input type="hidden" name="id_unidade" value="<?= (int)$exc['ID_UNIDADE'] ?>">
                        <input type="hidden" name="id_turma" value="<?= (int)$exc['ID_TURMA'] ?>">
                        <button type="submit" class="btn btn-sm btn-outline-danger w-100"
                                data-confirm="Excluir esta exceção?"><i class="fas fa-trash"></i> Excluir exceção</button>
                      </form>
                    </div>
                  </details>
                <?php endif; ?>
              </div>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

<script src="<?= BASEURL ?>js/tabela.js"></script>
<script src="<?= BASEURL ?>js/excecao_escopo.js"></script>
<?php include(FOOTER_TEMPLATE); ?>
