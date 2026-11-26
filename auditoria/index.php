<?php require_once('processa/index_processa.php'); ?>
<?php include(HEADER_TEMPLATE); ?>

<?php ch_cabecalho('Auditoria', 'fas fa-clipboard-check'); ?>

<form method="get" action="index.php" class="row g-2 align-items-end mb-3">
  <div class="col-md-3">
    <label class="fw-bold" for="modulo">Módulo</label>
    <select name="modulo" id="modulo" class="form-select">
      <option value="">Todos</option>
      <?php foreach ($modulos as $m): ?>
        <option value="<?= ch_h($m) ?>" <?= $fModulo === $m ? 'selected' : '' ?>><?= ch_h($m) ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <div class="col-md-3">
    <label class="fw-bold" for="usuario_id">Usuário</label>
    <select name="usuario_id" id="usuario_id" class="form-select">
      <option value="">Todos</option>
      <?php foreach ($usuariosFiltro as $u): ?>
        <option value="<?= (int)$u['ID'] ?>" <?= $fUsuario === (int)$u['ID'] ? 'selected' : '' ?>>
          <?= ch_h($u['NOME']) ?><?= (int)($u['ATIVO'] ?? 1) === 0 ? ' (inativo)' : '' ?>
        </option>
      <?php endforeach; ?>
    </select>
  </div>
  <div class="col-md-2">
    <label class="fw-bold" for="de">De</label>
    <input type="date" name="de" id="de" value="<?= ch_h($fDe) ?>" class="form-control">
  </div>
  <div class="col-md-2">
    <label class="fw-bold" for="ate">Até</label>
    <input type="date" name="ate" id="ate" value="<?= ch_h($fAte) ?>" class="form-control">
  </div>
  <div class="col-md-2">
    <label class="fw-bold" for="busca">Buscar</label>
    <input type="text" name="busca" id="busca" value="<?= ch_h($fBusca) ?>" class="form-control" placeholder="Ação, registro ou detalhe">
  </div>
  <div class="col-12 d-flex gap-2">
    <button class="btn btn-primary"><i class="fas fa-filter"></i> Filtrar</button>
    <a class="btn btn-outline-secondary" href="<?= ch_h(ch_url('auditoria/index.php')) ?>">
      <i class="fas fa-eraser"></i> Limpar
    </a>
  </div>
</form>

<div class="alert alert-info py-2">
  <i class="fas fa-circle-info"></i>
  Registro automático de alterações sensíveis. O usuário é mantido no cadastro e apenas marcado como inativo.
</div>

<div class="table-responsive-sm">
  <table class="table table-light table-striped table-sm align-middle">
    <thead class="text-sm-center">
      <tr>
        <th width="12%">Data/Hora</th>
        <th width="14%">Usuário</th>
        <th width="10%">Módulo</th>
        <th width="13%">Ação</th>
        <th width="11%">Registro</th>
        <th width="7%">IP</th>
        <th>Detalhes</th>
      </tr>
    </thead>
    <tbody>
      <?php if (!$logs): ?>
        <tr><td colspan="7" class="text-center text-muted py-4">Nenhum registro encontrado.</td></tr>
      <?php endif; ?>
      <?php foreach ($logs as $row): ?>
        <?php
        $nomeUsuario = (string)($row['USUARIO_ATUAL'] ?? '');
        if ($nomeUsuario === '') $nomeUsuario = (string)($row['USUARIO_NOME'] ?? '—');
        $detalhes = (string)($row['DETALHES'] ?? '');
        $resumo = mb_strlen($detalhes) > 140 ? mb_substr($detalhes, 0, 140) . '…' : $detalhes;
        $cor = [
            'Permissões' => 'warning text-dark',
            'AEE'        => 'success',
            'Feriados'   => 'danger',
            'Unidades'   => 'info',
            'Usuários'   => 'primary',
        ][$row['MODULO']] ?? 'secondary';
        ?>
        <tr>
          <td class="text-nowrap"><?= ch_h($row['CRIADO_EM_TXT']) ?></td>
          <td>
            <?= ch_h($nomeUsuario) ?>
            <?php if ((int)($row['USUARIO_ATIVO'] ?? 1) === 0): ?>
              <br><small class="text-muted">inativo</small>
            <?php elseif (empty($row['ID_USUARIO'])): ?>
              <br><small class="text-muted">sem vínculo</small>
            <?php endif; ?>
          </td>
          <td><span class="badge bg-<?= $cor ?>"><?= ch_h($row['MODULO']) ?></span></td>
          <td><?= ch_h($row['ACAO']) ?></td>
          <td class="small">
            <?= ch_h($row['REGISTRO_TIPO']) ?>
            <?php if ((string)($row['REGISTRO_ID'] ?? '') !== ''): ?>
              #<?= ch_h($row['REGISTRO_ID']) ?>
            <?php endif; ?>
          </td>
          <td class="small text-muted"><?= ch_h($row['IP'] ?? '—') ?></td>
          <td>
            <code class="d-block small" style="white-space:pre-wrap;word-break:break-word;"
                  title="<?= ch_h($detalhes) ?>"><?= ch_h($resumo !== '' ? $resumo : '—') ?></code>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

<?php if ($paginas > 1): ?>
  <?php
  $linkPagina = function ($n) use ($filtros) {
      $query = http_build_query(array_merge($filtros, ['pagina' => $n]));
      return ch_h(ch_url('auditoria/index.php' . ($query !== '' ? '?' . $query : '')));
  };
  ?>
  <nav aria-label="Paginação da auditoria">
    <ul class="pagination pagination-sm justify-content-center">
      <li class="page-item <?= $pagina === 1 ? 'disabled' : '' ?>">
        <a class="page-link" href="<?= $linkPagina(1) ?>" aria-label="Primeira">«</a>
      </li>
      <li class="page-item <?= $pagina === 1 ? 'disabled' : '' ?>">
        <a class="page-link" href="<?= $linkPagina($pagina - 1) ?>" aria-label="Anterior">‹</a>
      </li>
      <li class="page-item disabled">
        <span class="page-link">Página <?= $pagina ?> de <?= $paginas ?></span>
      </li>
      <li class="page-item <?= $pagina === $paginas ? 'disabled' : '' ?>">
        <a class="page-link" href="<?= $linkPagina($pagina + 1) ?>" aria-label="Próxima">›</a>
      </li>
      <li class="page-item <?= $pagina === $paginas ? 'disabled' : '' ?>">
        <a class="page-link" href="<?= $linkPagina($paginas) ?>" aria-label="Última">»</a>
      </li>
    </ul>
  </nav>
<?php endif; ?>

<p class="text-muted small">
  Exibindo <?= $total ? $offset + 1 : 0 ?> a <?= min($offset + $porPagina, $total) ?> de <?= $total ?> registro(s).
</p>

<?php include(FOOTER_TEMPLATE); ?>
