<?php
/* Fragmento de tela: so faz sentido incluido por uma pagina que ja carregou
   o config.php. Aberto direto na URL nao tem ch_h() nem as variaveis que
   espera, e morreria com erro fatal. ABSPATH e o sinal de que vim de dentro. */
if (!defined('ABSPATH')) {
	http_response_code(404);
	exit;
}
?>
<form action="<?= $acao ?>" method="post" enctype="multipart/form-data">
  <?= ch_csrf_campo() ?>
  <?php $editando = !empty($id); ?>
  <?php if ($editando): ?><input type="hidden" name="id" value="<?= (int)$id ?>"><?php endif; ?>

  <?php if ($erros): ?>
    <div class="alert alert-danger"><ul class="mb-0"><?php foreach ($erros as $e): ?><li><?= ch_h($e) ?></li><?php endforeach; ?></ul></div>
  <?php endif; ?>

  <div class="row g-3">
    <div class="form-group col-md-8">
      <label class="fw-bold">Turma</label>
      <?php if ($editando): ?>
        <?php $atual = null; foreach ($turmasDisponiveis as $t) { if ((int)$t['ID'] === (int)$d['ID_TURMA']) { $atual = $t; break; } } ?>
        <input type="text" class="form-control" disabled
               value="<?= $atual
                    ? ch_h($atual['UNIDADE']) . ' — Turma ' . ch_h(ch_series_ensino()[$atual['SERIE'] ?? '']['rotulo'] ?? 'não classificada')
                        . ' · Sala ' . ch_h($atual['NOME']) . ' · ' . ch_h($atual['PERIODO']) . ' (' . (int)$atual['ANO_LETIVO'] . ')'
                    : 'Turma #' . (int)$d['ID_TURMA'] ?>">
        <div class="form-text">
          Para mudar o aluno de escola ou turma, use
          <a href="<?= ch_url('transferencias/solicitar.php?aluno_id=' . (int)$id) ?>">Transferir aluno</a>.
          A transferência registra o motivo, a data de início e mantém o histórico.
        </div>
      <?php else: ?>
        <select name="id_turma" class="form-select" required>
          <option value="">Selecione...</option>
          <?php foreach ($turmasDisponiveis as $t): ?>
            <option value="<?= (int)$t['ID'] ?>" <?= (int)$d['ID_TURMA'] === (int)$t['ID'] ? 'selected' : '' ?>>
              <?= ch_h($t['UNIDADE']) ?> — Turma <?= ch_h(ch_series_ensino()[$t['SERIE'] ?? '']['rotulo'] ?? 'não classificada') ?>
              · Sala <?= ch_h($t['NOME']) ?> · <?= ch_h($t['PERIODO']) ?> (<?= (int)$t['ANO_LETIVO'] ?>)
              [<?= (int)$t['OCUPADAS'] ?>/<?= (int)$t['CAPACIDADE'] ?>]
            </option>
          <?php endforeach; ?>
        </select>
      <?php endif; ?>
    </div>
    <div class="form-group col-md-4">
      <label class="fw-bold">Matrícula</label>
      <input type="text" class="form-control" name="matricula" maxlength="30" value="<?= ch_h($d['MATRICULA']) ?>" required>
    </div>

    <div class="form-group col-md-4">
      <label class="fw-bold" for="cpfcnpj">CPF do aluno</label>
      <input type="text" class="form-control" id="cpfcnpj" name="cpf" maxlength="14"
             inputmode="numeric" autocomplete="off"
             value="<?= ch_h(!empty($d['CPF']) ? ch_cpf_mask($d['CPF']) : '') ?>"
             placeholder="000.000.000-00" required>
    </div>

    <div class="form-group col-md-8">
      <label class="fw-bold">Nome completo do aluno</label>
      <input type="text" class="form-control" name="nome" maxlength="150" value="<?= ch_h($d['NOME']) ?>" required>
    </div>
    <div class="form-group col-md-4">
      <label class="fw-bold">Data de nascimento</label>
      <input type="date" class="form-control" name="nascimento" min="1900-01-01" max="<?= ch_hoje() ?>" value="<?= ch_h($d['NASCIMENTO']) ?>" required>
    </div>

    <div class="form-group col-md-12">
      <label class="fw-bold" for="foto-aluno">Foto do aluno <small class="text-muted">(opcional)</small></label>
      <?php if (!empty($d['TEM_FOTO']) && ($fotoSrc = aluno_foto_src($d['TEM_FOTO'], $id ?? 0))): ?>
        <div class="mb-2"><img src="<?= ch_h($fotoSrc) ?>" alt="Foto atual de <?= ch_h($d['NOME']) ?>"
                               style="width:96px;height:120px;object-fit:cover;border-radius:12px"></div>
      <?php endif; ?>
      <input type="file" class="form-control" id="foto-aluno" name="foto" accept="image/jpeg,image/png,image/webp">
      <small class="text-muted">JPEG, PNG ou WebP; até 5 MB. A imagem será reduzida e comprimida antes de ser salva no banco.</small>
      <?php if (!empty($d['TEM_FOTO'])): ?>
        <div class="form-check mt-2">
          <input class="form-check-input" type="checkbox" name="remover_foto" value="1" id="remover-foto">
          <label class="form-check-label" for="remover-foto">Remover foto atual</label>
        </div>
      <?php endif; ?>
    </div>

    <div class="form-group col-md-5">
      <label class="fw-bold">Responsável</label>
      <input type="text" class="form-control" name="responsavel" maxlength="150" value="<?= ch_h($d['RESPONSAVEL']) ?>" required>
    </div>
    <div class="form-group col-md-3">
      <label class="fw-bold">Telefone</label>
      <input type="text" class="form-control" name="telefone" maxlength="30" value="<?= ch_h($d['TELEFONE']) ?>" placeholder="(15) 99999-9999" required>
    </div>
    <div class="form-group col-md-4">
      <label class="fw-bold">Data de matrícula</label>
      <input type="date" class="form-control" name="data_matricula" value="<?= ch_h($d['DATA_MATRICULA']) ?>" required>
    </div>

    <div class="form-group col-md-12">
      <label class="fw-bold">E-mail do responsável <small class="text-muted">(opcional)</small></label>
      <input type="email" class="form-control" name="email" maxlength="150" value="<?= ch_h($d['EMAIL']) ?>">
    </div>
  </div>


  <div class="card-escola mt-4">
    <div class="card-escola-titulo"><i class="fas fa-heart-pulse"></i> Saúde e alimentação</div>
    <div class="card-escola-corpo">
      <p class="text-muted mb-3">
        Preencha só o que a família informou ou o que consta no laudo. O que estiver aqui
        aparece <strong>em destaque na tela de chamada</strong> e no relatório do aluno, para
        quem está na sala ver antes de servir a merenda ou dar qualquer medicamento.
        Deixe em branco quando não houver nada a registrar.
      </p>
      <div class="row g-3">
<?php foreach (eq_saude_campos() as $campo => $def): ?>
        <div class="form-group col-md-4">
          <label class="fw-bold"><i class="fas <?= $def['icone'] ?> text-<?= $def['cor'] ?>"></i> <?= ch_h($def['rotulo']) ?></label>
          <textarea class="form-control" name="<?= strtolower($campo) ?>" rows="4" maxlength="1000"
                    placeholder="<?= $campo === 'ALERGIAS' ? 'Ex.: alergia a leite de vaca e a amendoim.' : ($campo === 'RESTRICOES_ALIMENTARES' ? 'Ex.: sem lactose.' : 'Ex.: usa bombinha em caso de crise.') ?>"><?= ch_h($d[$campo] ?? '') ?></textarea>
          <small class="text-muted"><?= ch_h($def['ajuda']) ?></small>
        </div>
<?php endforeach; ?>
      </div>
    </div>
  </div>

  <div class="col-auto my-4">
    <a href="index.php" class="btn btn-outline-success">Cancelar</a>
    <button type="submit" class="btn btn-success"><i class="fas fa-save"></i> <?= $rotuloBotao ?></button>
  </div>
</form>
