<?php

/* Fragmento de tela: so faz sentido incluido por uma pagina que ja carregou
   o config.php. Aberto direto na URL nao tem ch_h() nem as variaveis que
   espera, e morreria com erro fatal. ABSPATH e o sinal de que vim de dentro. */
if (!defined('ABSPATH')) {
	http_response_code(404);
	exit;
}

/* ============================================================================
   FORMULÁRIO DE TURMA
   ----------------------------------------------------------------------------
   A equipe deixou de ser "marque os professores e escolha um regente". Agora
   cada linha diz QUEM, com que PAPEL e em que TURNO — porque uma turma pode ter
   uma responsável de manhã e outra de tarde, com o professor de apoio o dia
   todo. O substituto entra na mesma lista, com prazo e dizendo a quem cobre.

   Usado por add.php e edit.php. Espera $d, $op, $erros, $acao, $rotuloBotao.
   ============================================================================ */

/** Uma linha da equipe. Serve tanto para quem já está na turma quanto para o
 *  modelo em branco que o JavaScript clona ao adicionar alguém. */
function turma_linha_equipe($m, $op)
{
    $m = array_merge([
        'ID_USUARIO' => 0, 'PAPEL' => 'AUXILIAR', 'TURNO' => '',
        'ID_SUBSTITUIDO' => 0, 'DATA_INICIO' => '', 'DATA_FIM' => '', 'MOTIVO' => '',
        'VER_OBSERVACOES' => 0,
    ], (array)$m);
    $ehSub = $m['PAPEL'] === 'SUBSTITUTO';
    ob_start();
    ?>
    <div class="linha-equipe" data-linha-equipe>
      <div class="row g-2 align-items-end">
        <div class="col-12 col-lg-4">
          <label class="fw-bold small">Professor(a)</label>
          <select name="eq_prof[]" class="form-select eq-prof">
            <option value="">Selecione...</option>
            <?php foreach ($op['professores'] as $pr): ?>
              <option value="<?= (int)$pr['ID'] ?>"
                      data-turno="<?= ch_h($pr['TURNO'] ?? '') ?>"
                      data-nome="<?= ch_h($pr['NOME']) ?>"
                      <?= (int)$m['ID_USUARIO'] === (int)$pr['ID'] ? 'selected' : '' ?>>
                <?= ch_h($pr['NOME']) ?><?= $pr['TURNO'] ? ' — ' . ch_h(eq_turno_rotulo($pr['TURNO'])) : '' ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-6 col-lg-3">
          <label class="fw-bold small">Papel</label>
          <select name="eq_papel[]" class="form-select eq-papel">
            <?php foreach (eq_papeis() as $sigla => $rotulo): ?>
              <option value="<?= $sigla ?>" <?= $m['PAPEL'] === $sigla ? 'selected' : '' ?>><?= ch_h($rotulo) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-6 col-lg-3">
          <label class="fw-bold small">Turno nesta turma</label>
          <select name="eq_turno[]" class="form-select eq-turno">
            <?php foreach (eq_turnos() as $sigla => $rotulo): ?>
              <option value="<?= $sigla ?>" <?= $m['TURNO'] === $sigla ? 'selected' : '' ?>><?= ch_h($rotulo) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-12 col-lg-2 text-lg-end">
          <button type="button" class="btn btn-outline-danger btn-sm eq-remover w-100">
            <i class="fas fa-trash"></i> Remover
          </button>
        </div>
      </div>

      <div class="eq-subst mt-2" <?= $ehSub ? '' : 'hidden' ?>>
        <div class="eq-subst-caixa">
          <div class="row g-2">
            <div class="col-12 col-lg-4">
              <label class="fw-bold small">Substitui quem?</label>
              <select name="eq_subst[]" class="form-select eq-coberto">
                <option value="">Selecione...</option>
                <?php foreach ($op['professores'] as $pr): ?>
                  <option value="<?= (int)$pr['ID'] ?>" <?= (int)$m['ID_SUBSTITUIDO'] === (int)$pr['ID'] ? 'selected' : '' ?>>
                    <?= ch_h($pr['NOME']) ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-6 col-lg-2">
              <label class="fw-bold small">De</label>
              <input type="date" name="eq_ini[]" class="form-control eq-ini" value="<?= ch_h($m['DATA_INICIO']) ?>">
            </div>
            <div class="col-6 col-lg-2">
              <label class="fw-bold small">Até</label>
              <input type="date" name="eq_fim[]" class="form-control eq-fim" value="<?= ch_h($m['DATA_FIM']) ?>">
            </div>
            <div class="col-12 col-lg-4">
              <label class="fw-bold small">Motivo</label>
              <input type="text" name="eq_motivo[]" class="form-control eq-motivo" maxlength="300"
                     value="<?= ch_h($m['MOTIVO']) ?>" placeholder="Licença, férias, afastamento...">
            </div>
            <div class="col-12">
              <?php /* Checkbox desmarcado nao e enviado pelo navegador, e isso
                       desalinharia os arrays eq_*[] desta linha com as outras.
                       Quem vai no POST e o hidden, sempre presente; o checkbox
                       nao tem name e so alimenta o hidden via JavaScript. */ ?>
              <input type="hidden" name="eq_obs[]" class="eq-obs-valor" value="<?= (int)$m['VER_OBSERVACOES'] ?>">
              <div class="form-check form-switch mt-1">
                <input class="form-check-input eq-obs" type="checkbox" role="switch"
                       id="eqObs<?= (int)$m['ID_USUARIO'] ?>" <?= (int)$m['VER_OBSERVACOES'] === 1 ? 'checked' : '' ?>>
                <label class="form-check-label small fw-bold" for="eqObs<?= (int)$m['ID_USUARIO'] ?>">
                  <i class="fas fa-eye"></i> Pode ler as observações do professor
                </label>
              </div>
              <div class="form-text mt-0">
                Desligado, ele vê apenas as observações que ele mesmo registrar.
                Ligado, lê também as da equipe. Vale só para esta substituição.
              </div>
            </div>
          </div>
          <div class="form-text mt-1">
            <i class="fas fa-circle-info"></i>
            Só entre <strong>De</strong> e <strong>Até</strong> o substituto vê esta turma e lança chamada.
            Fora desse prazo ele volta a não ver. Quem está sendo substituído continua na equipe.
            Ele registra observação normalmente nos dois casos — o que muda é só a leitura do que os outros escreveram.
          </div>
        </div>
      </div>
    </div>
    <?php
    return ob_get_clean();
}
?>
<form action="<?= $acao ?>" method="post" id="form-turma">
  <?= ch_csrf_campo() ?>
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
        <div class="form-group col-md-8">
          <label class="fw-bold" for="etapa-ensino">Etapa de ensino</label>
          <select class="form-select" id="etapa-ensino" name="etapa_ensino" required>
            <option value="">Selecione...</option>
            <?php foreach (ch_etapas_ensino() as $codigo => $rotulo): ?>
              <option value="<?= ch_h($codigo) ?>" <?= ($d['ETAPA_ENSINO'] ?? '') === $codigo ? 'selected' : '' ?>><?= ch_h($rotulo) ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="form-group col-md-5">
          <label class="fw-bold">Nome da turma</label>
          <input type="text" class="form-control" name="nome" maxlength="80" value="<?= ch_h($d['NOME']) ?>" placeholder="Ex.: Berçário I" required>
        </div>
        <div class="form-group col-md-4">
          <label class="fw-bold">Período</label>
          <select name="id_periodo" id="id_periodo" class="form-select" required>
            <option value="">Selecione...</option>
            <?php foreach ($op['periodos'] as $p): ?>
              <option value="<?= (int)$p['ID'] ?>" data-turno="<?= ch_h($p['TURNO']) ?>" <?= (int)$d['ID_PERIODO'] === (int)$p['ID'] ? 'selected' : '' ?>>
                <?= ch_h($p['NOME']) ?> (<?= ch_h($p['HORA_INICIO']) ?>–<?= ch_h($p['HORA_FIM']) ?>)
              </option>
            <?php endforeach; ?>
          </select>
          <small class="text-muted">O turno do período é a referência da equipe.</small>
        </div>
        <div class="form-group col-md-3">
          <label class="fw-bold">Capacidade</label>
          <input type="number" class="form-control" name="capacidade" min="1" max="500" value="<?= (int)$d['CAPACIDADE'] ?>" required>
        </div>
        <div class="form-group col-12">
          <div class="form-check form-switch">
            <input class="form-check-input" type="checkbox" role="switch" id="notas-ativas"
                   name="notas_ativas" value="1" <?= !empty($d['NOTAS_ATIVAS']) ? 'checked' : '' ?>>
            <label class="form-check-label fw-bold" for="notas-ativas">Esta turma lança notas escolares</label>
          </div>
          <small class="text-muted">Ative somente para Pré-Escola e Ensino Fundamental. Turmas de Creche não terão lançamento de notas, mesmo quando funcionam na mesma unidade. Desativar a opção não apaga notas já lançadas.</small>
        </div>
      </div>
    </div>
  </div>

  <div class="card-escola mb-4">
    <div class="card-escola-titulo">
      <i class="fas fa-people-group"></i> Equipe da turma
      <span class="ms-auto badge bg-light text-dark" id="contador-equipe">0 na equipe</span>
    </div>
    <div class="card-escola-corpo">
      <p class="text-muted mb-3">
        Cada professor entra com o <strong>papel</strong> e o <strong>turno</strong> que cobre nesta turma.
        Pode haver <strong>um responsável pela manhã e outro pela tarde</strong>; quem fica em
        <strong>integral</strong> responde pelos dois turnos. O <strong>apoio</strong> acompanha a turma
        sem ser o responsável — e pode ficar o dia todo.
      </p>

      <div class="alert alert-light border mb-3 py-2 small">
        <i class="fas fa-circle-info text-primary"></i>
        O turno vem preenchido com o turno de trabalho do professor, mas <strong>pode ser mudado aqui</strong>:
        é a turma que manda. Todos os que estiverem na lista lançam chamada e retificam presença.
      </div>

      <?php if (!$op['professores']): ?>
        <div class="alert alert-warning mb-0">
          Nenhum professor ativo. Cadastre professores em <strong>Usuários</strong> primeiro.
        </div>
      <?php else: ?>

      <div id="lista-equipe">
        <?php
        $equipeAtual = (array)($d['EQUIPE'] ?? []);
        foreach ($equipeAtual as $m) {
            echo turma_linha_equipe($m, $op);
        }
        ?>
      </div>

      <div class="d-flex flex-wrap gap-2 mt-3">
        <button type="button" class="btn btn-outline-success" id="add-linha-equipe">
          <i class="fas fa-plus"></i> Adicionar professor
        </button>
        <button type="button" class="btn btn-outline-danger" id="add-linha-substituto">
          <i class="fas fa-user-clock"></i> Adicionar substituto
        </button>
      </div>

      <div class="alert alert-warning mt-3 mb-0" id="aviso-equipe" hidden></div>

      <template id="modelo-linha-equipe"><?= turma_linha_equipe([], $op) ?></template>
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
  var modelo = document.getElementById('modelo-linha-equipe');
  var contador = document.getElementById('contador-equipe');
  var aviso = document.getElementById('aviso-equipe');
  var periodo = document.getElementById('id_periodo');

  function turnoDaTurma() {
    if (!periodo) return 'INTEGRAL';
    var o = periodo.options[periodo.selectedIndex];
    return (o && o.getAttribute('data-turno')) || 'INTEGRAL';
  }

  function cobre(t) { return t === 'INTEGRAL' ? ['MANHA', 'TARDE'] : [t]; }

  function sobrepoe(a, b) {
    return cobre(a).some(function (x) { return cobre(b).indexOf(x) !== -1; });
  }

  /* Quem pode ser substituído: os membros fixos da própria equipe. Assim a
     lista não oferece gente de fora, que o servidor recusaria. */
  function membrosFixos() {
    var r = [];
    lista.querySelectorAll('[data-linha-equipe]').forEach(function (linha) {
      var prof = linha.querySelector('.eq-prof');
      var papel = linha.querySelector('.eq-papel');
      if (prof.value && papel.value !== 'SUBSTITUTO') {
        var o = prof.options[prof.selectedIndex];
        r.push({ id: prof.value, nome: o.getAttribute('data-nome') || o.textContent.trim(),
                 turno: linha.querySelector('.eq-turno').value });
      }
    });
    return r;
  }

  /* Delegado na lista: pega tambem as linhas que o botao adiciona depois. */
  lista.addEventListener('change', function (ev) {
    if (!ev.target.classList || !ev.target.classList.contains('eq-obs')) return;
    var linha = ev.target.closest('[data-linha-equipe]');
    if (!linha) return;
    var valor = linha.querySelector('.eq-obs-valor');
    if (valor) valor.value = ev.target.checked ? '1' : '0';
  });

  function atualizar() {
    var fixos = membrosFixos();
    var total = 0;

    lista.querySelectorAll('[data-linha-equipe]').forEach(function (linha) {
      var prof = linha.querySelector('.eq-prof');
      var papel = linha.querySelector('.eq-papel');
      var turno = linha.querySelector('.eq-turno');
      var bloco = linha.querySelector('.eq-subst');
      var ehSub = papel.value === 'SUBSTITUTO';
      if (prof.value) total++;

      bloco.hidden = !ehSub;
      linha.classList.toggle('eh-substituto', ehSub);

      /* A permissao de leitura so existe para substituto. Ao trocar o papel,
         desmarca e zera o hidden para nao mandar sobra de escala antiga. */
      var obsChk = linha.querySelector('.eq-obs');
      var obsVal = linha.querySelector('.eq-obs-valor');
      if (obsChk && obsVal) {
        if (!ehSub) obsChk.checked = false;
        obsVal.value = obsChk.checked ? '1' : '0';
        obsChk.disabled = !ehSub;
      }

      if (ehSub) {
        /* Só oferece quem está na equipe como responsável ou apoio. */
        var sel = linha.querySelector('.eq-coberto');
        var atual = sel.value;
        sel.innerHTML = '<option value="">Selecione...</option>';
        fixos.forEach(function (f) {
          var op = document.createElement('option');
          op.value = f.id; op.textContent = f.nome;
          if (f.id === atual) op.selected = true;
          sel.appendChild(op);
        });
        /* O substituto cobre o turno de quem substitui — não escolhe o dele. */
        var escolhido = fixos.filter(function (f) { return f.id === sel.value; })[0];
        if (escolhido) turno.value = escolhido.turno;
        turno.disabled = true;
      } else {
        turno.disabled = false;
      }
    });

    contador.textContent = total + ' na equipe';
    conferirResponsaveis();
  }

  /* Aviso imediato quando dois responsáveis ficariam no mesmo turno.
     O servidor confere de novo — isto é só para não deixar salvar à toa. */
  function conferirResponsaveis() {
    var resp = [];
    lista.querySelectorAll('[data-linha-equipe]').forEach(function (linha) {
      var prof = linha.querySelector('.eq-prof');
      var papel = linha.querySelector('.eq-papel');
      if (!prof.value || papel.value !== 'REGENTE') return;
      var o = prof.options[prof.selectedIndex];
      resp.push({ nome: o.getAttribute('data-nome') || o.textContent.trim(),
                  turno: linha.querySelector('.eq-turno').value });
    });

    var msgs = [];
    for (var i = 0; i < resp.length; i++) {
      for (var j = i + 1; j < resp.length; j++) {
        if (sobrepoe(resp[i].turno, resp[j].turno)) {
          msgs.push(resp[i].nome + ' e ' + resp[j].nome +
                    ' ficariam responsáveis pelo mesmo turno. Só pode haver um por turno.');
        }
      }
    }
    if (resp.length === 0) {
      msgs.push('Nenhum responsável definido. Escolha quem responde pela turma em cada turno.');
    }
    if (msgs.length) {
      aviso.hidden = false;
      aviso.innerHTML = '<i class="fas fa-triangle-exclamation"></i> ' + msgs.join('<br>');
    } else {
      aviso.hidden = true;
      aviso.innerHTML = '';
    }
  }

  /* Ao escolher o professor, o turno de trabalho dele entra como padrão.
     Se a gestão já mexeu no turno daquela linha, a escolha dela é respeitada. */
  lista.addEventListener('change', function (ev) {
    var alvo = ev.target;
    if (alvo.classList.contains('eq-turno')) {
      var linha = alvo.closest('[data-linha-equipe]');
      if (linha) linha.setAttribute('data-turno-mexido', '1');
    }
    if (alvo.classList.contains('eq-prof')) {
      var l = alvo.closest('[data-linha-equipe]');
      if (l && !l.getAttribute('data-turno-mexido')) {
        var o = alvo.options[alvo.selectedIndex];
        var t = o ? o.getAttribute('data-turno') : '';
        l.querySelector('.eq-turno').value = t || turnoDaTurma();
      }
    }
    atualizar();
  });

  lista.addEventListener('click', function (ev) {
    var btn = ev.target.closest('.eq-remover');
    if (!btn) return;
    var linha = btn.closest('[data-linha-equipe]');
    if (linha) linha.remove();
    atualizar();
  });

  function novaLinha(papel) {
    var frag = modelo.content.cloneNode(true);
    var linha = frag.querySelector('[data-linha-equipe]');
    if (papel) linha.querySelector('.eq-papel').value = papel;
    linha.querySelector('.eq-turno').value = turnoDaTurma();
    lista.appendChild(frag);
    atualizar();
    var sel = lista.lastElementChild.querySelector('.eq-prof');
    if (sel) sel.focus();
  }

  document.getElementById('add-linha-equipe').addEventListener('click', function () { novaLinha('AUXILIAR'); });
  document.getElementById('add-linha-substituto').addEventListener('click', function () { novaLinha('SUBSTITUTO'); });

  /* Um turno disabled não é enviado no POST. Antes de submeter, liberamos
     todos: o servidor já força o turno do substituto para o de quem ele cobre. */
  document.getElementById('form-turma').addEventListener('submit', function () {
    lista.querySelectorAll('.eq-turno').forEach(function (s) { s.disabled = false; });
  });

  if (periodo) periodo.addEventListener('change', atualizar);

  /* Turma nova já abre com uma linha em branco, para não parecer vazio. */
  if (!lista.querySelector('[data-linha-equipe]')) novaLinha('REGENTE');
  atualizar();
})();
</script>