<?php require_once('processa/index_processa.php'); ?>
<?php include(HEADER_TEMPLATE); ?>

<?php ch_cabecalho('Usuários', 'fa-users',
    '<a class="btn btn-success" href="add.php"><i class="fa fa-plus"></i> Novo Usuário</a>'); ?>

<div class="table-responsive-sm">
  <table id="item" data-vazio="Nenhum usuário cadastrado." class="table table-light table-striped table-sm">
    <thead>
      <tr>
        <th>Nome</th>
        <th>CPF</th>
        <th>E-mail</th>
        <th>Nível</th>
        <th>Alcance</th>
        <th>Situação</th>
        <th>Opções</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($usuarios as $u):
        $nivel = (int)$u['NIVEL'];
        $cor   = [1 => 'primary', 2 => 'info', 3 => 'success'][$nivel] ?? 'secondary';
      ?>
        <tr>
          <td>
            <div class="d-flex align-items-center gap-2">
              <span class="avatar-mini"><?= ch_h(ch_iniciais($u['NOME'])) ?></span>
              <span><?= ch_h($u['NOME']) ?><?= (int)$u['ID'] === ch_uid() ? ' <small class="text-muted">(você)</small>' : '' ?></span>
            </div>
          </td>
          <td><?= ch_h($u['CPF']) ?></td>
          <td><?= ch_h($u['EMAIL'] ?? '') ?></td>
          <td><span class="badge bg-<?= $cor ?>"><?= ch_h(ch_nivel_nome($nivel)) ?></span></td>
          <td>
            <?php if ($nivel === 1): ?>
              <small class="text-muted">Toda a rede</small>
            <?php elseif ($nivel === 2): ?>
              <?php $n = $alcance[(int)$u['ID']] ?? 0; ?>
              <?= $n ?> unidade<?= $n === 1 ? '' : 's' ?>
              <?php if ($n === 0): ?><br><small class="text-danger">libere as unidades</small><?php endif; ?>
            <?php else: ?>
              <?php $n = $alcance[(int)$u['ID']] ?? 0; ?>
              <?= $n ?> turma<?= $n === 1 ? '' : 's' ?>
              <?php if ($n === 0): ?><br><small class="text-danger">inclua na equipe</small><?php endif; ?>
            <?php endif; ?>
          </td>
          <td>
            <?php if ((int)$u['ATIVO'] === 1): ?>
              <span class="badge bg-success">Ativo</span>
            <?php else: ?>
              <span class="badge bg-secondary">Inativo</span>
            <?php endif; ?>
          </td>
          <td class="text-sm-end">
            <div class="btn-group">
              <button type="button" class="btn btn-success btn-sm dropdown-toggle"
                      data-bs-toggle="dropdown" aria-expanded="false">
                <i class="fa-solid fa-gear"></i>
              </button>
              <ul class="dropdown-menu dropdown-menu-end">
                <li><a href="edit.php?id=<?= (int)$u['ID'] ?>" class="dropdown-item">
                  <i class="far fa-edit"></i> Editar</a></li>
                <?php if ($nivel === 2): ?>
                  <li><a href="acesso.php?id=<?= (int)$u['ID'] ?>" class="dropdown-item">
                    <i class="far fa-check-square"></i> Acesso Unidades</a></li>
                <?php endif; ?>
              </ul>
            </div>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

<script src="<?php echo BASEURL; ?>js/tabela.js"></script>

<?php include(FOOTER_TEMPLATE); ?>
