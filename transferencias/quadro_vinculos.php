<?php
/* Quadro "escolas e turmas por período". Espera $vinculos (tr_vinculos() ou
   tr_vinculos_no_ano()). Usado no detalhe da transferência, na ficha do aluno
   e no relatório anual — inclusive na impressão. */
if (!isset($vinculos)) { $vinculos = []; }
$tituloQuadro = $tituloQuadro ?? 'Escolas e turmas por período';
?>
<h5 class="mb-3"><i class="fas fa-route"></i> <?= ch_h($tituloQuadro) ?></h5>
<?php if (!$vinculos): ?>
  <p class="text-muted mb-0">Nenhum período registrado.</p>
<?php else: ?>
  <div class="table-responsive">
    <table class="table table-sm align-middle mb-0">
      <thead>
        <tr><th>Escola</th><th>Turma</th><th>Período</th><th>De</th><th>Até</th></tr>
      </thead>
      <tbody>
        <?php foreach ($vinculos as $v):
            $de  = $v['DE_NO_ANO']  ?? $v['DATA_INICIO'];
            $ate = $v['ATE_NO_ANO'] ?? $v['DATA_FIM'];
            $aberto = array_key_exists('EM_ABERTO', $v) ? $v['EM_ABERTO'] : ($v['DATA_FIM'] === null);
        ?>
          <tr>
            <td><?= ch_h($v['UNIDADE']) ?></td>
            <td><?= ch_h($v['TURMA']) ?> <small class="text-muted">(<?= (int)$v['ANO_LETIVO'] ?>)</small></td>
            <td><?= ch_h($v['PERIODO']) ?></td>
            <td><?= $de ? ch_data_br($de) : '<span class="text-muted">desde a matrícula</span>' ?></td>
            <td><?= $aberto ? '<b>até hoje</b>' : ($ate ? ch_data_br($ate) : '—') ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php if (count($vinculos) > 1): ?>
    <p class="text-muted mt-2 mb-0"><small>
      <i class="fas fa-circle-info"></i>
      A chamada, as observações e os pareceres acompanham o aluno em todos os períodos acima.
    </small></p>
  <?php endif; ?>
<?php endif; ?>