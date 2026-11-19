<?php
/* Fragmento de tela: so faz sentido incluido por uma pagina que ja carregou
   o config.php. Aberto direto na URL nao tem ch_h() nem as variaveis que
   espera, e morreria com erro fatal. ABSPATH e o sinal de que vim de dentro. */
if (!defined('ABSPATH')) {
	http_response_code(404);
	exit;
}

/** Uma linha de matéria. Serve tanto para quem já está na turma quanto para
 *  o modelo em branco que o JavaScript clona ao adicionar uma matéria.
 *  A matéria vem da lista (para não digitar errado) e a turma define o
 *  TOTAL de aulas; os professores são atribuídos no painel. */
function turma_linha_disciplina($d, $op)
{
    $d = array_merge(['NOME' => '', 'AULAS' => ''], (array)$d);
    ob_start();
    ?>
    <div class="linha-disciplina" data-linha-disciplina>
      <div class="row g-2 align-items-end">
        <div class="col-12 col-lg-6">
          <label class="fw-bold small">Matéria</label>
          <select name="disc_nome[]" class="form-select">
            <option value="">Selecione...</option>
            <?php foreach ($op['materias'] as $mat): ?>
              <option value="<?= ch_h($mat) ?>"
                      <?= mb_strtolower((string)$d['NOME'], 'UTF-8') === mb_strtolower($mat, 'UTF-8') ? 'selected' : '' ?>>
                <?= ch_h($mat) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-6 col-lg-3">
          <label class="fw-bold small">Aulas/semana (total)</label>
          <input type="number" name="disc_aulas[]" class="form-control" min="1" max="40"
                 value="<?= ch_h($d['AULAS']) ?>" placeholder="Ex.: 10">
        </div>
        <div class="col-6 col-lg-3">
          <button type="button" class="btn btn-outline-danger btn-sm w-100" data-remover-disciplina title="Remover matéria">
            <i class="fas fa-trash"></i> Remover
          </button>
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
          <label class="fw-bold" for="serie">Série / ano escolar</label>
          <select class="form-select" id="serie" name="serie" <?= empty($id) ? 'required' : '' ?>>
            <?php if (!empty($id)): ?>
              <option value="" <?= empty($d['SERIE']) ? 'selected' : '' ?>>Não classificada (cadastro antigo)</option>
            <?php else: ?>
              <option value="">Selecione...</option>
            <?php endif; ?>
            <?php foreach (ch_series_ensino() as $codigo => $serie): ?>
              <option value="<?= ch_h($codigo) ?>" <?= ($d['SERIE'] ?? '') === $codigo ? 'selected' : '' ?>><?= ch_h($serie['rotulo']) ?></option>
            <?php endforeach; ?>
          </select>
          <small class="text-muted">A série define a ordem de progressão. O nome da turma pode identificar a sala, por exemplo A, B ou C.</small>
        </div>

        <div class="form-group col-md-5">
          <label class="fw-bold">Nome / identificação da sala</label>
          <input type="text" class="form-control" name="nome" maxlength="80" value="<?= ch_h($d['NOME']) ?>" placeholder="Ex.: A, B ou Integral" required>
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
          <small class="text-muted">Na Creche (Berçário I ao Maternal II), o período é sempre <b>Integral</b>.</small>
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
      <i class="fas fa-book"></i> Matérias da turma
      <span class="ms-auto badge bg-light text-dark" id="contador-disciplinas">0 matérias</span>
    </div>
    <div class="card-escola-corpo">
      <p class="text-muted mb-3">
        No <strong>Ensino Fundamental</strong>, defina as matérias e o total de aulas por semana.
        Na <strong>Creche</strong> (atribuída por período + professor de apoio o dia todo) e no
        <strong>Pré I / Pré II</strong> (regente PEB I sem matéria), adicione aqui apenas
        <strong>matérias específicas</strong> quando houver (ex.: <em>Educação Física</em>, <em>Artes</em>, <em>Inglês</em>).
        Os professores são vinculados no menu
        <a href="<?= ch_url('atribuicoes/index.php') ?>"><strong>Atribuição</strong></a>,
        e as escalas temporárias no menu
        <a href="<?= ch_url('substituicoes/index.php' . (!empty($id) ? '?turma=' . (int)$id : '')) ?>"><strong>Substituições</strong></a>.
      </p>

      <div id="lista-disciplinas">
        <?php
        $discAtual = (array)($d['DISCIPLINAS'] ?? []);
        foreach ($discAtual as $disc) {
            echo turma_linha_disciplina($disc, $op);
        }
        ?>
      </div>

      <div class="d-flex flex-wrap gap-2 mt-3">
        <button type="button" class="btn btn-outline-success" id="add-linha-disciplina">
          <i class="fas fa-plus"></i> Adicionar matéria
        </button>
      </div>

      <template id="modelo-linha-disciplina"><?= turma_linha_disciplina([], $op) ?></template>
    </div>
  </div>

  <div class="col-auto my-4">
    <a href="index.php" class="btn btn-outline-secondary">Cancelar</a>
    <button type="submit" class="btn btn-success btn-lg"><i class="fas fa-save"></i> <?= $rotuloBotao ?></button>
  </div>
</form>

<script>
/* Linhas de matéria: adicionar e remover, com o contador. */
(function () {
  var lista = document.getElementById('lista-disciplinas');
  if (!lista) return;
  var modelo = document.getElementById('modelo-linha-disciplina');
  var contador = document.getElementById('contador-disciplinas');

  function atualizar() {
    var n = lista.querySelectorAll('[data-linha-disciplina]').length;
    if (contador) contador.textContent = n + (n === 1 ? ' matéria' : ' matérias');
  }

  function novaLinha() {
    if (!modelo) return;
    var frag = document.createElement('template');
    frag.innerHTML = modelo.innerHTML.trim();
    lista.appendChild(frag.content.firstElementChild);
    atualizar();
    var nome = lista.lastElementChild.querySelector('select[name="disc_nome[]"]');
    if (nome) nome.focus();
  }

  document.getElementById('add-linha-disciplina').addEventListener('click', novaLinha);

  lista.addEventListener('click', function (ev) {
    var btn = ev.target.closest('[data-remover-disciplina]');
    if (!btn) return;
    var linha = btn.closest('[data-linha-disciplina]');
    if (linha) { linha.remove(); atualizar(); }
  });

  atualizar();
})();

/* Creche (Berçário I ao Maternal II): o período é sempre Integral.
   Ao escolher uma série de creche, seleciona automaticamente o período
   de turno INTEGRAL (o servidor também impõe isso na gravação). */
(function () {
  var serie = document.getElementById('serie');
  var periodo = document.getElementById('id_periodo');
  if (!serie || !periodo) return;
  var seriesCreche = ['BERCARIO_I', 'BERCARIO_II', 'MATERNAL_I', 'MATERNAL_II'];
  var eraCreche = seriesCreche.indexOf(serie.value) !== -1;
  var periodoAntesCreche = eraCreche ? periodo.value : null;

  function aplicarCreche() {
    var ehCreche = seriesCreche.indexOf(serie.value) !== -1;
    if (ehCreche) {
      if (!eraCreche) periodoAntesCreche = periodo.value;
      for (var i = 0; i < periodo.options.length; i++) {
        if (periodo.options[i].getAttribute('data-turno') === 'INTEGRAL') {
          periodo.selectedIndex = i;
          break;
        }
      }
    } else if (eraCreche && periodoAntesCreche !== null) {
      /* Ao trocar de Creche para Pré/Fundamental, restaura o período anterior
         (ou o placeholder em um cadastro novo). */
      periodo.value = periodoAntesCreche;
      periodoAntesCreche = null;
    }
    eraCreche = ehCreche;
  }

  serie.addEventListener('change', aplicarCreche);
  aplicarCreche();
})();
</script>
