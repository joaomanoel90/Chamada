<?php
/* ============================================================================
   ESCOLHA DE ESCOLA E TURMA — contexto de trabalho do professor
   ----------------------------------------------------------------------------
   O professor pode lecionar em mais de uma escola e em mais de uma turma.
   Ao entrar, ele declara onde está trabalhando; a partir daí o sistema só
   mostra essa escola e essa turma. As turmas de anos anteriores ficam
   ocultas de todas as telas — é o que acontece quando a secretaria encerra
   o ano letivo e cria as turmas do ano seguinte.

   A troca pode ser feita a qualquer momento pelo menu lateral.
   ============================================================================ */
require_once(__DIR__ . '/config.php');
require_once(DBAPI);
require_once(ABSPATH . 'inc/chamada.php');
ch_db();

if (!ch_professor()) {
    header('Location: ' . BASEURL . 'index.php');
    exit;
}

$anoAtual = (int)date('Y');
$anos = ch_professor_anos(ch_uid());

/* --- gravar a escolha ---------------------------------------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!ch_csrf_ok()) {
        header('Location: ' . BASEURL . 'contexto.php');
        exit;
    }
    $turmaId = (int)($_POST['turma_id'] ?? 0);
    /* A turma precisa estar na equipe dele HOJE (respeita o prazo do
       substituto) e estar ativa. Não basta confiar no id enviado. */
    $validas = eq_turmas_ids_em(ch_uid(), ch_hoje());
    $t = ($turmaId > 0 && in_array($turmaId, $validas, true)) ? ch_turma($turmaId) : null;
    if ($t && (int)$t['ATIVO'] === 1) {
        $_SESSION['ch_contexto'] = [
            'unidade' => (int)$t['ID_UNIDADE'],
            'ano'     => (int)$t['ANO_LETIVO'],
            'turma'   => $turmaId,
        ];
        ch_flash('Você está trabalhando com a turma ' . $t['NOME'] . ' — ' . $t['UNIDADE'] . ' (' . (int)$t['ANO_LETIVO'] . ').');
        header('Location: ' . BASEURL . 'index.php');
        exit;
    }
    ch_flash('Turma inválida ou fora da sua equipe.', 'danger');
    header('Location: ' . BASEURL . 'contexto.php');
    exit;
}

/* --- montar a tela -------------------------------------------------------- */
$ctx = ch_contexto();
$anoSel = (int)($_GET['ano'] ?? ($ctx['ano'] > 0 ? $ctx['ano'] : $anoAtual));
if ($anos && !in_array($anoSel, $anos, true)) {
    $anoSel = $anos[0];
}
$turmasAno = ch_professor_turmas_por_escola(ch_uid(), $anoSel);

/* Agrupa por escola, na ordem em que vieram (alfabética). */
$porEscola = [];
foreach ($turmasAno as $t) {
    $idUnidade = (int)$t['ID_UNIDADE'];
    if (!isset($porEscola[$idUnidade])) {
        $porEscola[$idUnidade] = ['nome' => $t['UNIDADE'], 'turmas' => []];
    }
    $porEscola[$idUnidade]['turmas'][] = $t;
}
?>
<?php include(HEADER_TEMPLATE2); ?>

<?php ch_cabecalho('Escolha sua escola e turma', 'fa-school'); ?>

<div class="alert alert-info">
  <i class="fas fa-circle-info"></i>
  Você leciona em mais de uma escola ou turma. Escolha onde está trabalhando agora:
  o sistema passa a mostrar <strong>somente essa escola e essa turma</strong>.
  Turmas de anos anteriores ficam ocultas. Você pode trocar a qualquer momento
  pelo menu lateral, em <strong>Trocar escola/turma</strong>.
</div>

<?php if ($anos): ?>
<form method="get" action="contexto.php" class="row g-2 align-items-end mb-4">
  <div class="col-md-4">
    <label class="fw-bold" for="ano">Ano letivo</label>
    <select name="ano" id="ano" class="form-select" onchange="this.form.submit()">
      <?php foreach ($anos as $a): ?>
        <option value="<?= $a ?>" <?= $a === $anoSel ? 'selected' : '' ?>><?= $a ?><?= $a === $anoAtual ? ' (atual)' : '' ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <div class="col-md-2">
    <button class="btn btn-outline-primary w-100" type="submit"><i class="fas fa-filter"></i> Filtrar</button>
  </div>
</form>
<?php endif; ?>

<?php if (!$turmasAno): ?>
  <div class="alert alert-warning">
    Você ainda não está na equipe de nenhuma turma ativa em <?= $anoSel ?>.
    Peça à gestão para incluí-lo na equipe em <strong>Turmas → Editar</strong>.
  </div>
<?php else: ?>
  <?php foreach ($porEscola as $idUnidade => $grupo): ?>
    <h3 class="h5 mt-4 mb-2"><i class="fas fa-building"></i> <?= ch_h($grupo['nome']) ?></h3>
    <div class="row g-3">
      <?php foreach ($grupo['turmas'] as $t): ?>
        <div class="col-md-6 col-lg-4">
          <div class="card h-100">
            <div class="card-body">
              <div class="fw-bold">
                <?= ch_h(ch_series_ensino()[$t['SERIE'] ?? '']['rotulo'] ?? 'Série não classificada') ?>
                · Sala <?= ch_h($t['NOME']) ?>
              </div>
              <div class="small text-muted">
                <?= ch_h($t['PERIODO']) ?> · <?= ch_h(eq_turno_rotulo($t['TURNO'])) ?>
                · <?= ch_h(eq_papel_rotulo($t['PAPEL'])) ?>
              </div>
              <form method="post" action="contexto.php" class="mt-2">
                <?= ch_csrf_campo() ?>
                <input type="hidden" name="turma_id" value="<?= (int)$t['ID'] ?>">
                <button class="btn btn-primary w-100" type="submit">
                  <i class="fas fa-check"></i> Trabalhar com esta turma
                </button>
              </form>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endforeach; ?>
<?php endif; ?>

<?php include(FOOTER_TEMPLATE); ?>
