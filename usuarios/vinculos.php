<?php require_once('processa/vinculos_processa.php'); ?>
<?php include(HEADER_TEMPLATE); ?>

<?php ch_cabecalho('Vínculos de unidades escolares', 'fa-link'); ?>

<div class="alert alert-info">
  <i class="fas fa-circle-info"></i>
  Vincule Diretores e Secretários Escolares às unidades em que atuam.
  Esta tela não permite criar ou editar contas de usuário.
</div>

<?php if (!$usuarios): ?>
  <div class="alert alert-warning">Não há Diretores ou Secretários Escolares ativos para vincular.</div>
<?php else: ?>
  <div class="table-responsive-sm">
    <table class="table table-light table-striped table-sm align-middle">
      <thead>
        <tr><th>Nome</th><th>Perfil</th><th>Unidades vinculadas</th><th class="text-end">Ação</th></tr>
      </thead>
      <tbody>
        <?php foreach ($usuarios as $usuario): ?>
          <tr>
            <td><?= ch_h($usuario['NOME']) ?></td>
            <td><?= ch_h(ch_nivel_nome((int)$usuario['NIVEL'])) ?></td>
            <td><?= (int)$usuario['UNIDADES'] ?></td>
            <td class="text-end">
              <a class="btn btn-sm btn-primary"
                 href="<?= ch_url('usuarios/acesso.php?id=' . (int)$usuario['ID']) ?>">
                <i class="far fa-check-square"></i> Acesso Unidades
              </a>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
<?php endif; ?>

<?php include(FOOTER_TEMPLATE); ?>
