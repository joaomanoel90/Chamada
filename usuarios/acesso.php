<?php 
require_once('processa/acesso_processa.php'); 
include(HEADER_TEMPLATE); 

if ($modoLiberacaoRelatorios): ?>
<h2><i class="fas fa-file-shield"></i> Leitura de relatórios: <?= ch_h($usuario['NOME']) ?></h2>
<p class="text-muted">Escolha se este Secretário Escolar poderá consultar relatórios anuais em cada unidade.</p>
<?= ch_flash_html() ?>

<?php if (!$unidadesRelatorio): ?>
  <div class="alert alert-warning">
    Este Secretário Escolar não possui vínculo ativo com nenhuma unidade sob sua direção.
    Peça ao administrador para vincular as unidades em <strong>Acesso Unidades</strong>.
  </div>
<?php else: ?>
  <form action="<?= ch_url('usuarios/acesso.php?id=' . (int)$usuario['ID'] . '&relatorios=1') ?>" method="post">
    <?= ch_csrf_campo() ?>
    <div class="card-escola">
      <div class="card-escola-corpo">
        <?php foreach ($unidadesRelatorio as $unidade): ?>
          <div class="row align-items-center mb-3">
            <label class="col-md-5 fw-bold" for="relatorio<?= (int)$unidade['id'] ?>">
              <?= ch_h($unidade['unidade']) ?>
            </label>
            <div class="col-md-7">
              <select class="form-select" name="permissoes[<?= (int)$unidade['id'] ?>]"
                      id="relatorio<?= (int)$unidade['id'] ?>">
                <option value="0" <?= (int)$unidade['PERMITIDO'] === 0 ? 'selected' : '' ?>>Sem acesso</option>
                <option value="1" <?= (int)$unidade['PERMITIDO'] === 1 ? 'selected' : '' ?>>Somente ler</option>
              </select>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
    <div class="my-4">
      <button type="submit" class="btn btn-success"><i class="fas fa-save"></i> Salvar permissões</button>
      <a href="<?= ch_url('usuarios/secretarios.php') ?>" class="btn btn-outline-secondary">Voltar</a>
    </div>
  </form>
<?php endif; ?>
<?php include(FOOTER_TEMPLATE); exit; ?>
<?php endif; ?>

<?php if (!empty($modoLiberacaoObservacoes)): ?>
<h2><i class="fas fa-comment-dots"></i> Registro de observações: <?= ch_h($usuario['NOME']) ?></h2>
<p class="text-muted">Escolha se este Secretário Escolar pode consultar observações ou consultar e inserir novas observações em cada unidade.</p>
<?= ch_flash_html() ?>

<?php if (!$unidadesObservacoes): ?>
  <div class="alert alert-warning">
    Este Secretário Escolar não possui vínculo ativo com nenhuma unidade sob sua direção.
    Peça ao administrador para vincular as unidades em <strong>Acesso Unidades</strong>.
  </div>
<?php else: ?>
  <form action="<?= ch_url('usuarios/acesso.php?id=' . (int)$usuario['ID'] . '&observacoes=1') ?>" method="post">
    <?= ch_csrf_campo() ?>
    <div class="card-escola">
      <div class="card-escola-corpo">
        <?php foreach ($unidadesObservacoes as $unidade): ?>
          <div class="row align-items-center mb-3">
            <label class="col-md-5 fw-bold" for="permissao<?= (int)$unidade['id'] ?>">
              <?= ch_h($unidade['unidade']) ?>
            </label>
            <div class="col-md-7">
              <select class="form-select" name="permissoes[<?= (int)$unidade['id'] ?>]"
                      id="permissao<?= (int)$unidade['id'] ?>">
                <option value="0" <?= (int)$unidade['PERMITIDO'] === 0 ? 'selected' : '' ?>>Sem acesso</option>
                <option value="1" <?= (int)$unidade['PERMITIDO'] === 1 ? 'selected' : '' ?>>Somente ler</option>
                <option value="2" <?= (int)$unidade['PERMITIDO'] === 2 ? 'selected' : '' ?>>Ler e inserir</option>
              </select>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
    <div class="my-4">
      <button type="submit" class="btn btn-success"><i class="fas fa-save"></i> Salvar permissões</button>
      <a href="<?= ch_url('usuarios/secretarios.php?observacoes=1') ?>" class="btn btn-outline-secondary">Voltar</a>
    </div>
  </form>
<?php endif; ?>
<?php include(FOOTER_TEMPLATE); exit; ?>
<?php endif; ?>

<?php
if (!empty($_GET['id'])) {
    $id = $_GET['id'];

    if ($rows_usuario) {
        $usuario = sqlsrv_fetch_array($stmt_usuario, SQLSRV_FETCH_ASSOC);
?>

<h2>
    <i class="far fa-check-square"></i> 
    Acessos do usuário: <?= ch_h($usuario['NOME']) ?>
</h2>

<?php if (ch_secretaria_educacao()): ?>
  <div class="alert alert-info">
    Você pode vincular este <?= (int)$usuario['NIVEL'] === 2 ? 'Diretor' : 'Secretário Escolar' ?>
    às unidades escolares. A administração da conta continua restrita ao administrador.
  </div>
<?php endif; ?>

<?php if (!empty($_GET['erro']) && $_GET['erro'] == 'duplicado'): ?>
<div class="alert alert-warning">
    Este usuário já possui acesso à unidade selecionada.
</div>
<?php endif; ?>

<!-- 🔹 FORMULÁRIO NORMAL -->
<form action="acesso.php?id=<?php echo $id; ?>" method="post">
    <?= ch_csrf_campo() ?>
    <hr />

    <div class="row">
        <div class="form-group col-md-12">
            <label for="unidade">Unidades</label>
            <select class="form-control" name="unidade">
                <option selected disabled>Selecione...</option>
                <?php while ($unidade = sqlsrv_fetch_array($stmt_unidades, SQLSRV_FETCH_ASSOC)) : ?>
                    <option value="<?php echo $unidade['id']; ?>">
                        <?= ch_h($unidade['unidade']) ?>
                    </option>
                <?php endwhile; ?>
            </select>
        </div>
    </div>

    <div class="col-auto my-4">
        <button type="submit" name="submit" class="btn btn-success">
            Salvar
        </button>

        <a href="<?= ch_url(ch_secretaria_educacao() ? 'usuarios/vinculos.php' : 'usuarios/index.php') ?>"
           class="btn btn-outline-secondary">Voltar</a>
    </div>
</form>

<!-- 🔴 AÇÃO SEPARADA (SEGURO) -->
<hr>

<?php if (ch_admin()): ?>
<div class="alert alert-warning d-flex justify-content-between align-items-center">
    <div>
        <strong>Atenção:</strong> Esta ação irá adicionar <b>TODAS</b> as unidades ao usuário.
    </div>

    <form action="acesso.php?id=<?php echo $id; ?>" method="post" style="margin:0;">
        <?= ch_csrf_campo() ?>
        <button type="submit" name="add_all" class="btn btn-danger"
            onclick="return confirm('Tem certeza que deseja adicionar TODAS as unidades? Essa ação pode gerar muitos acessos.')">
            Adicionar Todas
        </button>
    </form>
</div>
<?php endif; ?>

<!-- 🔹 TABELA -->
<hr>

<div class="table-responsive-sm">
    <table class="table table-hover table-striped table-sm">
        <thead>
            <tr>
                <th>ID</th>
                <th>Unidade</th>
                <th class="text-end">Ações</th>
            </tr>
        </thead>
        <tbody>
            <?php if ($rows_acessos > 0): ?>
                <?php while ($acesso = sqlsrv_fetch_array($stmt_acessos, SQLSRV_FETCH_ASSOC)) : ?>
                    <tr>
                        <td><?php echo $acesso['ID_ACESSO']; ?></td>
                        <td><?php echo $acesso['NOME_UNIDADE']; ?></td>
                        <td class="text-end">
                            <a href="#" class="btn btn-sm btn-danger"
                               data-bs-toggle="modal"
                               data-bs-target="#acesso-modal"
                               data-id="<?php echo $acesso['ID_ACESSO']; ?>">
                                <i class="far fa-trash-alt"></i>
                            </a>
                        </td>
                    </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr>
                    <td colspan="3">Nenhum registro encontrado.</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php
        sqlsrv_free_stmt($stmt_usuario);
        sqlsrv_free_stmt($stmt_unidades);
        sqlsrv_free_stmt($stmt_acessos);
    }
} else {
    header("location:index.php");
}

include('modal_acesso.php'); 
include(FOOTER_TEMPLATE);
?>