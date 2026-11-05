<form action="<?= $acao ?>" method="post" id="form-turma">
  <?php if ($id ?? 0): ?><input type="hidden" name="id" value="<?= (int)$id ?>"><?php endif; ?>

  <?php if ($erros): ?>
    <div class="alert alert-danger"><ul class="mb-0"><?php foreach ($erros as $e): ?><li><?= ch_h($e) ?></li><?php endforeach; ?></ul></div>
  <?php endif; ?>

  <div class="card-escola mb-4">
    <div class="card-escola-titulo"><i class="fas fa-school"></i> Dados da turma</div>
    <div class="card-escola-corpo">
      <div class="row g-3">
        <div class="form-group col-md-8">
          <label class="fw-bold">Unidade (creche / escola)</label>
          <select name="id_unidade" class="form-select" required>
            <option value="">Selecione...</option>
            <?php foreach ($op['unidades'] as $u): ?>
              <option value="<?= (int)$u['id'] ?>" <?= (int)$d['ID_UNIDADE'] === (int)$u['id'] ? 'selected' : '' ?>><?= ch_h($u['unidade']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group col-md-4">
          <label class="fw-bold">Ano letivo</label>
          <input type="number" class="form-control" name="ano_letivo" min="2000" max="2100" value="<?= (int)$d['ANO_LETIVO'] ?>" required>
        </div>

        <div class="form-group col-md-5">
          <label class="fw-bold">Nome da turma</label>
          <input type="text" class="form-control" name="nome" maxlength="80" value="<?= ch_h($d['NOME']) ?>" placeholder="Ex.: Berçário I" required>
        </div>
        <div class="form-group col-md-4">
          <label class="fw-bold">Período</label>
          <select name="id_periodo" class="form-select" required>
            <option value="">Selecione...</option>
            <?php foreach ($op['periodos'] as $p): ?>
              <option value="<?= (int)$p['ID'] ?>" <?= (int)$d['ID_PERIODO'] === (int)$p['ID'] ? 'selected' : '' ?>>
                <?= ch_h($p['NOME']) ?> (<?= ch_h($p['HORA_INICIO']) ?>–<?= ch_h($p['HORA_FIM']) ?>)
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group col-md-3">
          <label class="fw-bold">Capacidade</label>
          <input type="number" class="form-control" name="capacidade" min="1" max="500" value="<?= (int)$d['CAPACIDADE'] ?>" required>
        </div>
      </div>
    </div>
  </div>

  <div class="card-escola mb-4">
    <div class="card-escola-titulo">
      <i class="fas fa-people-group"></i> Equipe da turma
      <span class="ms-auto badge bg-light text-dark" id="contador-equipe">0 selecionado(s)</span>
    </div>
    <div class="card-escola-corpo">
      <p class="text-muted mb-3">
        Marque <strong>todos os professores</strong> que atendem esta turma e escolha
        <strong>um regente</strong>. Todos os marcados poderão lançar a chamada e
        retificar presenças; quem não estiver marcado não verá a turma.
      </p>

      <input type="text" class="form-control mb-3" id="busca-professor"
             placeholder="Buscar professor pelo nome..." autocomplete="off">

      <?php if (!$op['professores']): ?>
        <div class="alert alert-warning mb-0">
          Nenhum usuário ativo. Cadastre professores em <strong>Usuários</strong> primeiro.
        </div>
      <?php else: ?>
      <div class="lista-equipe" id="lista-equipe">
        <?php foreach ($op['professores'] as $pr):
            $pid = (int)$pr['ID'];
            $naEquipe = in_array($pid, array_map('intval', (array)($d['PROFESSORES'] ?? [])), true);
            $ehRegente = (int)($d['REGENTE'] ?? 0) === $pid;
            $iniciais = ch_iniciais($pr['NOME']);
        ?>
        <div class="item-equipe <?= $naEquipe ? 'ativo' : '' ?>" data-nome="<?= ch_h(mb_strtolower($pr['NOME'])) ?>">
          <label class="item-equipe-check">
            <input type="checkbox" name="professores[]" value="<?= $pid ?>"
                   class="form-check-input chk-prof" <?= $naEquipe ? 'checked' : '' ?>>
            <span class="avatar-prof"><?= ch_h($iniciais) ?></span>
            <span class="item-equipe-nome">
              <?= ch_h($pr['NOME']) ?>
              <?php if ((int)$pr['NIVEL'] !== 3): ?>
                <small class="text-muted">· gestão</small>
              <?php endif; ?>
            </span>
          </label>
          <label class="item-equipe-regente" title="Definir como regente">
            <input type="radio" name="regente" value="<?= $pid ?>" class="form-check-input rad-regente"
                   <?= $ehRegente ? 'checked' : '' ?> <?= $naEquipe ? '' : 'disabled' ?>>
            <span>Regente</span>
          </label>
        </div>
        <?php endforeach; ?>
      </div>
      <div class="form-text mt-2">
        <i class="fas fa-circle-info"></i>
        Se nenhum regente for escolhido, o primeiro professor marcado assume o papel.
      </div>
      <?php endif; ?>
    </div>
  </div>

  <div class="col-auto my-4">
    <a href="index.php" class="btn btn-outline-secondary">Cancelar</a>
    <button type="submit" class="btn btn-success btn-lg"><i class="fas fa-save"></i> <?= $rotuloBotao ?></button>
  </div>
</form>

<script>
(function () {
  var lista = document.getElementById('lista-equipe');
  if (!lista) return;
  var busca = document.getElementById('busca-professor');
  var contador = document.getElementById('contador-equipe');

  function atualizar() {
    var marcados = 0;
    lista.querySelectorAll('.item-equipe').forEach(function (item) {
      var chk = item.querySelector('.chk-prof');
      var rad = item.querySelector('.rad-regente');
      item.classList.toggle('ativo', chk.checked);
      rad.disabled = !chk.checked;
      if (!chk.checked && rad.checked) rad.checked = false;
      if (chk.checked) marcados++;
    });
    /* Garante que sempre haja um regente quando a equipe não está vazia. */
    if (marcados > 0 && !lista.querySelector('.rad-regente:checked')) {
      var primeiro = lista.querySelector('.chk-prof:checked');
      if (primeiro) primeiro.closest('.item-equipe').querySelector('.rad-regente').checked = true;
    }
    contador.textContent = marcados + ' selecionado(s)';
  }

  lista.addEventListener('change', atualizar);

  if (busca) {
    busca.addEventListener('input', function () {
      var termo = busca.value.trim().toLowerCase();
      lista.querySelectorAll('.item-equipe').forEach(function (item) {
        item.style.display = item.getAttribute('data-nome').indexOf(termo) !== -1 ? '' : 'none';
      });
    });
  }

  atualizar();
})();
</script>
