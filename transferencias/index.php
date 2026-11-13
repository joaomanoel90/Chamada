<?php require_once('processa/index_processa.php'); ?>
<?php include(HEADER_TEMPLATE); ?>

<?php ch_cabecalho('Transferências de alunos', 'fa-right-left',
    '<a class="btn btn-outline-secondary" href="' . ch_url('alunos/index.php') . '"><i class="fas fa-child-reaching"></i> Ir para Alunos</a>'); ?>

<div class="alert alert-info">
  <i class="fas fa-circle-info"></i>
  <?php if (ch_secretario_escolar()): ?>
    Esta tela é somente para consulta: mostra transferências ligadas às suas unidades.
  <?php else: ?>
  A transferência tem <b>duas etapas</b>: a escola de origem indica a escola de destino
  (em <i>Alunos → o aluno → Transferir</i>) e a <b>escola de destino</b> escolhe a turma e a
  <b>data de início</b>. O aluno continua na turma atual, com chamada normal, até o destino aceitar.
  Para mudar de <b>turma na mesma escola</b>, use <i>Alunos → o aluno → Trocar de turma</i>:
  a troca é registrada na hora, com data de início e motivo.
  <?php endif; ?>
</div>

<?php if ($aguardando > 0): ?>
  <div class="alert alert-warning">
    <i class="fas fa-bell"></i>
    <b><?= $aguardando ?></b> transferência(s) aguardando a sua escola escolher turma e data de início.
  </div>
<?php endif; ?>

<div class="card border-0 shadow-sm rounded-4 mb-3">
  <div class="card-body">
    <?php if ($mostrarPapel): ?>
      <ul class="nav nav-pills mb-3">
        <?php
        $abas = ['recebidas' => 'Aguardando / recebidas', 'enviadas' => 'Enviadas pela minha escola', 'todas' => 'Todas'];
        foreach ($abas as $chave => $rotulo):
            $ativo = $papel === $chave;
            $url = 'index.php?papel=' . $chave . '&situacao=' . $situacao;
        ?>
          <li class="nav-item">
            <a class="nav-link <?= $ativo ? 'active' : '' ?>" href="<?= ch_h($url) ?>"><?= ch_h($rotulo) ?></a>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>

    <div class="d-flex flex-wrap gap-2 align-items-center mb-3">
      <?php
      $filtros = ['PENDENTE' => 'Aguardando', 'CONCLUIDA' => 'Concluídas', 'CANCELADA' => 'Canceladas', 'TODAS' => 'Todas as situações'];
      foreach ($filtros as $chave => $rotulo):
          $ativo = $situacao === $chave;
          $url = 'index.php?papel=' . $papel . '&situacao=' . $chave;
      ?>
        <a class="btn btn-sm <?= $ativo ? 'btn-primary' : 'btn-outline-primary' ?>" href="<?= ch_h($url) ?>"><?= ch_h($rotulo) ?></a>
      <?php endforeach; ?>

    </div>

    <?php if (!$transferencias): ?>
      <div class="text-center text-muted py-5">
        <i class="fas fa-right-left fa-2x mb-3 d-block"></i>
        Nenhuma transferência nesta seleção.
      </div>
    <?php else: ?>
      <div class="table-responsive">
        <table id="item" data-vazio="Nenhuma transferência nesta seleção." class="table table-light table-striped table-sm align-middle">
          <thead>
            <tr>
              <th>Aluno</th>
              <th>De</th>
              <th>Para</th>
              <th>Início na nova turma</th>
              <th>Situação</th>
              <th class="text-end">Ações</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($transferencias as $x): ?>
              <tr>
                <td>
                  <span class="fw-bold"><?= ch_h($x['ALUNO']) ?></span><br>
                  <small class="text-muted">Matrícula <?= ch_h($x['MATRICULA']) ?></small>
                </td>
                <td>
                  <?= ch_h($x['UNIDADE_ORIGEM']) ?><br>
                  <small class="text-muted"><?= ch_h($x['TURMA_ORIGEM']) ?></small>
                </td>
                <td>
                  <?php if (tr_e_troca_turma($x)): ?>
                    <span class="badge bg-success">Troca de turma</span><br>
                    <small class="text-muted"><?= $x['TURMA_DESTINO'] ? ch_h($x['TURMA_DESTINO']) : 'turma ainda não escolhida' ?></small>
                  <?php else: ?>
                    <?= ch_h($x['UNIDADE_DESTINO']) ?><br>
                    <small class="text-muted"><?= $x['TURMA_DESTINO'] ? ch_h($x['TURMA_DESTINO']) : 'turma ainda não escolhida' ?></small>
                  <?php endif; ?>
                </td>
                <td><?= $x['DATA_INICIO'] ? ch_data_br($x['DATA_INICIO']) : '<span class="text-muted">a definir</span>' ?></td>
                <td><?= tr_situacao_badge($x['SITUACAO']) ?></td>
                <td class="text-end text-nowrap">
                  <a class="btn btn-sm btn-outline-secondary" href="ver.php?id=<?= (int)$x['ID'] ?>" title="Detalhes">
                    <i class="far fa-eye"></i>
                  </a>
                  <?php if (ch_gestor() && $x['SITUACAO'] === 'PENDENTE' && ch_pode_unidade($x['ID_UNIDADE_DESTINO'])): ?>
                    <a class="btn btn-sm btn-success" href="receber.php?id=<?= (int)$x['ID'] ?>">
                      <i class="fas fa-door-open"></i> Receber
                    </a>
                  <?php endif; ?>
                  <?php if (ch_gestor() && $x['SITUACAO'] === 'PENDENTE'
                            && (ch_pode_unidade($x['ID_UNIDADE_ORIGEM']) || ch_pode_unidade($x['ID_UNIDADE_DESTINO']))): ?>
                    <a class="btn btn-sm btn-outline-danger" href="cancelar.php?id=<?= (int)$x['ID'] ?>" title="Cancelar">
                      <i class="fas fa-ban"></i>
                    </a>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>
</div>

<?php include(FOOTER_TEMPLATE); ?>