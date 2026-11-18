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
          Para mudar o aluno de escola, use
          <a href="<?= ch_url('transferencias/solicitar.php?aluno_id=' . (int)$id) ?>">Transferir aluno</a>;
          para mudar de turma na mesma escola, use
          <a href="<?= ch_url('transferencias/trocar_turma.php?aluno_id=' . (int)$id) ?>">Trocar de turma</a>.
          As duas opções registram o motivo, a data de início e mantêm o histórico.
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

  <?php $professoresAee = $professoresAee ?? ch_professores_aee_opcoes(); ?>
  <div class="card-escola mt-4">
    <div class="card-escola-titulo"><i class="fas fa-puzzle-piece"></i> Educação Especial, AEE e outros acompanhamentos</div>
    <div class="card-escola-corpo">
      <p class="text-muted mb-3">
        <i class="fas fa-lock me-1"></i>
        <strong>Informação restrita à ficha interna do aluno</strong> — não aparece na tela de chamada nem sai impressa no relatório de desenvolvimento.
        Se você vincular um professor de AEE do sistema, ele passará a enxergar a ficha e poderá registrar observações deste aluno.
      </p>
      <div class="row g-3">
        <div class="form-group col-md-6">
          <label class="fw-bold"><i class="fas fa-notes-medical text-primary"></i> Condição, diagnóstico ou necessidade específica</label>
          <textarea class="form-control" name="condicao_especifica" rows="3" maxlength="1000"
                    placeholder="Ex.: TEA (Transtorno do Espectro Autista), TDAH, Deficiência Intelectual, baixa visão, em investigação diagnóstica..."><?= ch_h($d['CONDICAO_ESPECIFICA'] ?? '') ?></textarea>
          <small class="text-muted">Deixe em branco quando não houver condição específica informada.</small>
        </div>
        <div class="form-group col-md-6">
          <label class="fw-bold"><i class="fas fa-user-doctor text-info"></i> Outros acompanhamentos</label>
          <textarea class="form-control" name="outros_acompanhamentos" rows="3" maxlength="1000"
                    placeholder="Ex.: fonoaudiologia semanal, psicologia, terapia ocupacional, neuropediatra, APAE/CAPS..."><?= ch_h($d['OUTROS_ACOMPANHAMENTOS'] ?? '') ?></textarea>
          <small class="text-muted">Atendimentos clínicos, terapêuticos ou da rede de apoio externa.</small>
        </div>

        <div class="col-12">
          <hr class="my-1">
          <div class="form-check form-switch mt-2">
            <input class="form-check-input" type="checkbox" role="switch" id="chk-passa-aee" name="passa_aee" value="1"
                   <?= !empty($d['PASSA_AEE']) ? 'checked' : '' ?>>
            <label class="form-check-label fw-bold" for="chk-passa-aee">
              Aluno passa pelo Atendimento Educacional Especializado (AEE)
            </label>
          </div>
        </div>

        <div class="form-group col-md-6" id="bloco-prof-aee">
          <label class="fw-bold" for="sel-prof-aee"><i class="fas fa-chalkboard-user text-success"></i> Professor(a) de AEE <small class="text-muted">(opcional — dá acesso às observações)</small></label>
          <select class="form-select" id="sel-prof-aee" name="id_professor_aee">
            <option value="0">Nenhum professor vinculado no sistema</option>
            <?php foreach ($professoresAee as $pa): ?>
              <option value="<?= (int)$pa['ID'] ?>" <?= (int)($d['ID_PROFESSOR_AEE'] ?? 0) === (int)$pa['ID'] ? 'selected' : '' ?>>
                <?= ch_h($pa['NOME']) ?>
              </option>
            <?php endforeach; ?>
          </select>
          <small class="text-muted">Aparecem aqui os professores cadastrados na categoria <b>Professor de AEE</b> em <i>Usuários</i>. O professor selecionado poderá consultar esta ficha e registrar observações do aluno.</small>
        </div>

        <div class="form-group col-md-6" id="bloco-obs-aee">
          <label class="fw-bold" for="inp-obs-aee"><i class="fas fa-clock text-secondary"></i> Dias / horário / observações do AEE <small class="text-muted">(opcional)</small></label>
          <input type="text" class="form-control" id="inp-obs-aee" name="aee_observacoes" maxlength="500"
                 value="<?= ch_h($d['AEE_OBSERVACOES'] ?? '') ?>"
                 placeholder="Ex.: Sala de recursos no contraturno — 3ª e 5ª das 14h às 15h">
        </div>
      </div>
    </div>
  </div>

  <script>
  (function () {
    var chk = document.getElementById('chk-passa-aee');
    var sel = document.getElementById('sel-prof-aee');
    var obs = document.getElementById('inp-obs-aee');
    if (!chk || !sel || !obs) return;
    function syncAee() {
      var ativo = chk.checked;
      sel.disabled = !ativo;
      obs.disabled = !ativo;
      if (!ativo) {
        sel.value = '0';
      }
    }
    sel.addEventListener('change', function () {
      if (parseInt(sel.value, 10) > 0) {
        chk.checked = true;
      }
    });
    chk.addEventListener('change', syncAee);
    syncAee();
  })();
  </script>

  <div class="col-auto my-4">
    <a href="index.php" class="btn btn-outline-success">Cancelar</a>
    <button type="submit" class="btn btn-success"><i class="fas fa-save"></i> <?= $rotuloBotao ?></button>
  </div>
</form>
