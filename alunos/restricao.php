<?php require_once('processa/restricao_processa.php'); ?>
<?php include(HEADER_TEMPLATE); ?>

<?php
$voltar = '<a class="btn btn-outline-secondary" href="' . ch_url('alunos/view.php?id=' . (int)$idAluno) . '"><i class="fas fa-arrow-left"></i> Voltar à ficha</a>';
ch_cabecalho('Restrição familiar', 'fa-lock', $voltar);
?>

<div class="alert alert-info">
  <i class="fas fa-circle-info me-1"></i>
  A restrição <b>não</b> é alterada na edição do cadastro. Use esta tela para restringir ou liberar cada
  contato. Toda ação fica registrada no histórico com <b>data, usuário e motivo</b>. O contato restrito
  continua na ficha, mas não aparece no crachá individual nem no crachá em lote.
</div>

<?php if (!fam_tem_restricao() || !fam_tem_historico_restricao()): ?>
  <div class="alert alert-danger">
    <i class="fas fa-triangle-exclamation me-1"></i>
    Este banco ainda não tem a restrição familiar instalada. Execute
    <code>sql_inicial/ATUALIZAR_RESTRICAO_CONTATO_FAMILIA.sql</code> no SQL Server e recarregue a página.
  </div>
<?php endif; ?>

<?php if ($erros): ?>
  <div class="alert alert-danger">
    <ul class="mb-0"><?php foreach ($erros as $e): ?><li><?= ch_h($e) ?></li><?php endforeach; ?></ul>
  </div>
<?php endif; ?>

<div class="row g-3">
  <div class="col-lg-7">
    <div class="card border-0 shadow-sm rounded-4">
      <div class="card-body">
        <h5 class="mb-1"><i class="fas fa-people-roof text-success me-1"></i> Contatos de <?= ch_h($aluno['NOME']) ?></h5>
        <p class="small text-muted mb-3">Matrícula <?= ch_h($aluno['MATRICULA']) ?> · <?= ch_h($aluno['UNIDADE']) ?></p>

        <?php if (!$vinculos): ?>
          <div class="alert alert-warning mb-0">
            Este aluno ainda não tem responsáveis vinculados pelo CPF. Cadastre os contatos na
            <a href="<?= ch_url('alunos/edit.php?id=' . (int)$idAluno) ?>">edição do aluno</a> antes de aplicar uma restrição.
          </div>
        <?php else: ?>
          <?php foreach ($vinculos as $v): ?>
            <?php $restrito = !empty($v['RESTRITO_CONTATO']); ?>
            <div class="border rounded-3 p-3 mb-3 bg-light bg-opacity-50">
              <div class="d-flex flex-wrap justify-content-between align-items-start gap-2">
                <div>
                  <span class="badge bg-secondary"><?= ch_h(fam_parentesco_rotulo($v['PARENTESCO'], $v['DESCRICAO_OUTRO'])) ?></span>
                  <?php if (!empty($v['CONTATO_PRINCIPAL'])): ?>
                    <span class="badge bg-warning text-dark"><i class="fas fa-star me-1"></i> Principal</span>
                  <?php endif; ?>
                  <span class="badge bg-<?= $restrito ? 'danger' : 'success' ?>">
                    <i class="fas fa-<?= $restrito ? 'lock' : 'lock-open' ?> me-1"></i>
                    <?= $restrito ? 'Restrito' : 'Liberado' ?>
                  </span>
                  <div class="fw-bold mt-2"><?= ch_h($v['NOME']) ?></div>
                  <?php if (!empty($v['TELEFONE'])): ?>
                    <div class="small text-muted"><i class="fas fa-phone me-1"></i><?= ch_h($v['TELEFONE']) ?></div>
                  <?php endif; ?>
                </div>
              </div>

              <form method="post" action="restricao.php" class="mt-3">
                <?= ch_csrf_campo() ?>
                <input type="hidden" name="aluno_id" value="<?= (int)$idAluno ?>">
                <input type="hidden" name="vinculo_id" value="<?= (int)$v['ID'] ?>">
                <input type="hidden" name="acao" value="<?= $restrito ? 'LIBERAR' : 'RESTRINGIR' ?>">
                <label class="form-label fw-bold small" for="motivo_<?= (int)$v['ID'] ?>">
                  Motivo da <?= $restrito ? 'liberação' : 'restrição' ?> <span class="text-danger">*</span>
                </label>
                <textarea name="motivo" id="motivo_<?= (int)$v['ID'] ?>" class="form-control form-control-sm"
                          rows="2" maxlength="500" required
                          placeholder="Ex.: solicitação da família; decisão judicial; troca de responsável."><?= ch_h($d['MOTIVO'] ?? '') ?></textarea>
                <div class="form-text">Obrigatório e visível no histórico desta ação.</div>
                <div class="mt-2">
                  <?php if ($restrito): ?>
                    <button type="submit" class="btn btn-sm btn-outline-success">
                      <i class="fas fa-lock-open me-1"></i> Tirar a restrição
                    </button>
                  <?php else: ?>
                    <button type="submit" class="btn btn-sm btn-outline-danger">
                      <i class="fas fa-lock me-1"></i> Restringir contato no crachá
                    </button>
                  <?php endif; ?>
                </div>
              </form>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <div class="col-lg-5">
    <div class="card border-0 shadow-sm rounded-4">
      <div class="card-body">
        <h5 class="mb-3"><i class="fas fa-clock-rotate-left me-1"></i> Histórico de restrições</h5>
        <?php if (!$historico): ?>
          <p class="text-muted small mb-0">Nenhuma restrição ou liberação registrada para este aluno.</p>
        <?php else: ?>
          <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
              <thead>
                <tr>
                  <th>Data</th>
                  <th>Ação</th>
                  <th>Contato / Motivo</th>
                  <th>Registrado por</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($historico as $h): ?>
                  <tr>
                    <td class="text-nowrap small"><?= ch_h($h['CRIADO_EM_TXT']) ?></td>
                    <td>
                      <span class="badge bg-<?= $h['ACAO'] === 'RESTRINGIR' ? 'danger' : 'success' ?>">
                        <?= $h['ACAO'] === 'RESTRINGIR' ? 'Restringiu' : 'Liberou' ?>
                      </span>
                    </td>
                    <td>
                      <div class="small fw-bold">
                        <?= ch_h(fam_parentesco_rotulo($h['PARENTESCO'], $h['DESCRICAO_OUTRO'] ?? '')) ?>
                        · <?= ch_h($h['NOME_RESPONSAVEL']) ?>
                      </div>
                      <div class="small text-muted"><?= nl2br(ch_h($h['MOTIVO'])) ?></div>
                    </td>
                    <td class="small"><?= ch_h($h['USUARIO_NOME']) ?></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>
      </div>
    </div>

    <div class="card border-0 shadow-sm rounded-4 mt-3">
      <div class="card-body">
        <h5 class="mb-2"><i class="fas fa-circle-question me-1"></i> Crachás já impressos</h5>
        <p class="small text-muted mb-0">
          Ao restringir um contato, o sistema grava uma observação orientando a gerar outro crachá sem
          aquela informação. Os crachás anteriores devem ser substituídos na unidade.
        </p>
      </div>
    </div>
  </div>
</div>

<?php include(FOOTER_TEMPLATE); ?>
