<?php require_once('processa/secretarios_processa.php'); ?>
<?php include(HEADER_TEMPLATE); ?>

<?php ch_cabecalho(
  $modoObservacoes ? 'Permissões de observações' : 'Permissões de relatórios',
  $modoObservacoes ? 'fa-comment-dots' : 'fa-file-shield'
); ?>

<div class="alert alert-info">
  <?php if ($modoObservacoes): ?>
    Defina por unidade se o Secretário Escolar pode somente ler ou ler e inserir observações.
    A permissão vale somente para unidades que você dirige.
  <?php else: ?>
    Libere ou revogue a leitura dos relatórios anuais dos Secretários Escolares
    vinculados às suas unidades. A permissão vale somente para as unidades que você dirige.
  <?php endif; ?>
</div>

<?php if (!$unidadesDiretor): ?>
  <div class="alert alert-warning">
    Sua conta ainda não tem unidades vinculadas. Peça ao administrador para configurar seu acesso.
  </div>
<?php elseif (!$secretarios): ?>
  <div class="alert alert-secondary">
    Não há Secretários Escolares ativos vinculados às suas unidades.
  </div>
<?php else: ?>
  <div class="table-responsive-sm">
    <table class="table table-light table-striped table-sm align-middle">
      <thead>
        <tr><th>Secretário Escolar</th><th>Unidades liberadas</th><th class="text-end">Ação</th></tr>
      </thead>
      <tbody>
        <?php foreach ($secretarios as $secretario): ?>
          <tr>
            <td><?= ch_h($secretario['NOME']) ?></td>
            <td><?= (int)$secretario['UNIDADES_LIBERADAS'] ?></td>
            <td class="text-end">
              <a class="btn btn-sm btn-primary"
                 href="<?= ch_url('usuarios/acesso.php?id=' . (int)$secretario['ID'] . ($modoObservacoes ? '&observacoes=1' : '&relatorios=1')) ?>">
                <i class="fas fa-sliders"></i> Configurar por unidade
              </a>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
<?php endif; ?>

<?php include(FOOTER_TEMPLATE); ?>
