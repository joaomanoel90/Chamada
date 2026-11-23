<?php
/* Fragmento de tela: so faz sentido incluido por uma pagina que ja carregou
   o config.php. Aberto direto na URL nao tem ch_h() nem as variaveis que
   espera, e morreria com erro fatal. ABSPATH e o sinal de que vim de dentro. */
if (!defined('ABSPATH')) {
	http_response_code(404);
	exit;
}
?>
<form id="form-aluno" action="<?= $acao ?>" method="post" enctype="multipart/form-data">
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
      <input type="text" class="form-control" name="responsavel" maxlength="150" value="<?= ch_h($d['RESPONSAVEL']) ?>">
      <small class="text-muted">Pode ser preenchido pelo contato principal da seção Família abaixo.</small>
    </div>
    <div class="form-group col-md-3">
      <label class="fw-bold">Telefone</label>
      <input type="text" class="form-control" name="telefone" maxlength="30" value="<?= ch_h($d['TELEFONE']) ?>" placeholder="(15) 99999-9999">
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

  <?php $familia = $d['FAMILIA'] ?? fam_estrutura_vazia(); ?>
  <div class="card-escola mt-4">
    <div class="card-escola-titulo"><i class="fas fa-people-roof"></i> Família / Responsáveis (vínculo por CPF)</div>
    <div class="card-escola-corpo">
      <p class="text-muted mb-3">
        Vincule a <strong>mãe</strong>, o <strong>pai</strong> e/ou <strong>outro responsável</strong> pelo CPF.
        Se o CPF já estiver cadastrado no sistema, nome, telefone e e-mail são preenchidos automaticamente
        e o responsável passa a aparecer na tela
        <a href="<?= ch_url('familias/index.php') ?>"><strong>Famílias</strong></a> com todos os filhos vinculados.
        O responsável marcado como <strong>contato principal</strong> preenche os campos Responsável, Telefone e E-mail acima.
        Deixe um bloco em branco quando não houver esse responsável.
      </p>

      <?php foreach (fam_parentescos() as $cod => $rotulo): ?>
        <?php $pref = 'fam_' . strtolower($cod) . '_'; $f = $familia[$cod] ?? []; ?>
        <div class="border rounded-3 p-3 mb-3" data-fam-bloco="<?= $cod ?>">
          <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
            <strong><i class="fas fa-user me-1"></i> <?= ch_h($rotulo) ?></strong>
            <label class="form-check form-check-inline mb-0">
              <input class="form-check-input" type="radio" name="fam_contato_principal" value="<?= $cod ?>"
                     data-fam-principal="<?= $cod ?>" <?= !empty($f['CONTATO_PRINCIPAL']) ? 'checked' : '' ?>>
              <span class="form-check-label fw-bold">Contato principal</span>
            </label>
          </div>
          <div class="row g-3">
            <div class="form-group col-md-4">
              <label class="fw-bold">CPF</label>
              <input type="text" class="form-control" name="<?= $pref ?>cpf" maxlength="14" inputmode="numeric"
                     autocomplete="off" placeholder="000.000.000-00" data-fam-cpf="<?= $cod ?>"
                     value="<?= ch_h($f['CPF'] ?? '') ?>">
            </div>
            <div class="form-group col-md-8">
              <label class="fw-bold">Nome completo</label>
              <input type="text" class="form-control" name="<?= $pref ?>nome" maxlength="150"
                     data-fam-nome="<?= $cod ?>" value="<?= ch_h($f['NOME'] ?? '') ?>">
            </div>
            <div class="form-group col-md-4">
              <label class="fw-bold">Telefone</label>
              <input type="text" class="form-control" name="<?= $pref ?>telefone" maxlength="30"
                     placeholder="(15) 99999-9999" data-fam-telefone="<?= $cod ?>" value="<?= ch_h($f['TELEFONE'] ?? '') ?>">
            </div>
            <div class="form-group col-md-5">
              <label class="fw-bold">E-mail <small class="text-muted">(opcional)</small></label>
              <input type="email" class="form-control" name="<?= $pref ?>email" maxlength="120"
                     data-fam-email="<?= $cod ?>" value="<?= ch_h($f['EMAIL'] ?? '') ?>">
            </div>
            <?php if ($cod === 'OUTRO'): ?>
              <div class="form-group col-md-3">
                <label class="fw-bold">Parentesco / vínculo</label>
                <input type="text" class="form-control" name="<?= $pref ?>descricao" maxlength="60"
                       placeholder="Ex.: avó, tia, padrasto" value="<?= ch_h($f['DESCRICAO_OUTRO'] ?? '') ?>">
              </div>
            <?php endif; ?>
            <div class="col-12" data-fam-feedback="<?= $cod ?>"></div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>

  <script>
  /* Família por CPF: máscara, preenchimento automático quando o CPF já existe
     no sistema e sincronização do contato principal com os campos principais. */
  (function () {
    var form = document.querySelector('form');
    if (!form) return;
    var inpResponsavel = form.querySelector('input[name="responsavel"]');
    var inpTelefone = form.querySelector('input[name="telefone"]');
    var inpEmail = form.querySelector('input[name="email"]');
    var urlBusca = '<?= ch_url('familias/buscar_cpf.php') ?>';

    function digitos(s) { return (s || '').replace(/\D/g, ''); }

    function mascararCpf(inp) {
      var d = digitos(inp.value).slice(0, 11);
      if (d.length > 9) {
        inp.value = d.slice(0, 3) + '.' + d.slice(3, 6) + '.' + d.slice(6, 9) + '-' + d.slice(9);
      } else if (d.length > 6) {
        inp.value = d.slice(0, 3) + '.' + d.slice(3, 6) + '.' + d.slice(6);
      } else if (d.length > 3) {
        inp.value = d.slice(0, 3) + '.' + d.slice(3);
      } else {
        inp.value = d;
      }
    }

    function campo(cod, tipo) {
      return form.querySelector('[data-fam-' + tipo + '="' + cod + '"]');
    }

    function blocoPrincipal() {
      var r = form.querySelector('input[name="fam_contato_principal"]:checked');
      return r ? r.value : null;
    }

    /* Preenche Responsável/Telefone/E-mail a partir do contato principal,
       sem apagar o que a pessoa digitou manualmente nesses campos. */
    function sincronizarPrincipal() {
      var cod = blocoPrincipal();
      if (!cod) return;
      var nome = campo(cod, 'nome'), tel = campo(cod, 'telefone'), email = campo(cod, 'email');
      if (inpResponsavel && inpResponsavel.value.trim() === '' && nome && nome.value.trim() !== '') {
        inpResponsavel.value = nome.value.trim();
      }
      if (inpTelefone && inpTelefone.value.trim() === '' && tel && tel.value.trim() !== '') {
        inpTelefone.value = tel.value.trim();
      }
      if (inpEmail && inpEmail.value.trim() === '' && email && email.value.trim() !== '') {
        inpEmail.value = email.value.trim();
      }
    }

    form.querySelectorAll('input[name="fam_contato_principal"]').forEach(function (r) {
      r.addEventListener('change', sincronizarPrincipal);
    });
    form.addEventListener('submit', sincronizarPrincipal);

    ['mae', 'pai', 'outro'].forEach(function (cod) {
      var inpCpf = campo(cod, 'cpf');
      if (!inpCpf) return;
      var feedback = form.querySelector('[data-fam-feedback="' + cod + '"]');

      inpCpf.addEventListener('input', function () { mascararCpf(inpCpf); });

      inpCpf.addEventListener('blur', function () {
        var d = digitos(inpCpf.value);
        if (feedback) feedback.innerHTML = '';
        if (d.length !== 11) return;
        fetch(urlBusca, {
          method: 'POST',
          headers: {
            'Accept': 'application/json',
            'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8'
          },
          body: 'cpf=' + encodeURIComponent(d)
        })
          .then(function (resp) { return resp.json(); })
          .then(function (dados) {
            if (!dados || !dados.encontrado) return;
            var nome = campo(cod, 'nome'), tel = campo(cod, 'telefone'), email = campo(cod, 'email');
            if (nome && nome.value.trim() === '') nome.value = dados.nome || '';
            if (tel && tel.value.trim() === '' && dados.telefone) tel.value = dados.telefone;
            if (email && email.value.trim() === '' && dados.email) email.value = dados.email;
            if (feedback) {
              /* Os nomes vêm do cadastro: criar nós e usar textContent evita
                 interpretar dados digitados como HTML. */
              var aviso = document.createElement('div');
              aviso.className = 'alert alert-info py-2 small mb-0';
              var icone = document.createElement('i');
              icone.className = 'fas fa-circle-check me-1';
              aviso.appendChild(icone);
              aviso.appendChild(document.createTextNode(' Responsável já cadastrado no sistema — dados preenchidos automaticamente.'));
              if (dados.filhos && dados.filhos.length) {
                aviso.appendChild(document.createElement('br'));
                var tituloFilhos = document.createElement('strong');
                tituloFilhos.textContent = 'Filhos já vinculados: ';
                aviso.appendChild(tituloFilhos);
                dados.filhos.forEach(function (f, i) {
                  if (i > 0) aviso.appendChild(document.createTextNode(' · '));
                  var link = document.createElement('a');
                  link.href = '<?= ch_url('alunos/view.php') ?>?id=' + encodeURIComponent(f.id);
                  link.textContent = f.nome || 'Aluno';
                  aviso.appendChild(link);
                  aviso.appendChild(document.createTextNode(' (' + (f.turma || 'Turma') + ')'));
                });
              }
              feedback.replaceChildren(aviso);
            }
            sincronizarPrincipal();
          })
          .catch(function () { /* sem rede: segue o preenchimento manual */ });
      });
    });
  })();
  </script>

  <?php $inclusaoSelecionada = aluno_inclusao_codigos($d['DEFICIENCIAS_INCLUSAO'] ?? []); ?>
  <div class="card-escola mt-4">
    <div class="card-escola-titulo"><i class="fas fa-universal-access"></i> Necessidades de inclusão e crachá</div>
    <div class="card-escola-corpo">
      <p class="text-muted mb-3">As categorias ficam na ficha interna. O crachá exibe apenas os símbolos autorizados, nunca o nome da condição ou o diagnóstico.</p>
      <fieldset class="border rounded-3 p-3">
        <legend class="float-none w-auto px-2 fs-6 fw-bold mb-1">Necessidades de inclusão</legend>
        <div class="row g-2">
          <?php foreach (aluno_inclusao_opcoes() as $codigoInclusao => $rotuloInclusao): ?>
            <div class="col-sm-6 col-lg-4">
              <div class="form-check">
                <input class="form-check-input" type="checkbox" name="deficiencias_inclusao[]"
                       id="inclusao-<?= ch_h(strtolower($codigoInclusao)) ?>" value="<?= ch_h($codigoInclusao) ?>"
                       <?= in_array($codigoInclusao, $inclusaoSelecionada, true) ? 'checked' : '' ?>>
                <label class="form-check-label" for="inclusao-<?= ch_h(strtolower($codigoInclusao)) ?>"><?= ch_h($rotuloInclusao) ?></label>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      </fieldset>
      <div class="row g-3 mt-1">
        <div class="form-group col-md-6" id="bloco-simbolo-tea" <?= in_array('TEA', $inclusaoSelecionada, true) ? '' : 'hidden' ?>>
          <label class="fw-bold" for="sel-simbolo-tea"><i class="fas fa-infinity text-primary"></i> Símbolo para TEA / autismo</label>
          <select class="form-select" id="sel-simbolo-tea" name="simbolo_tea_inclusao"
                  <?= in_array('TEA', $inclusaoSelecionada, true) ? '' : 'disabled' ?>>
            <option value="INFINITO" <?= ($d['SIMBOLO_TEA_INCLUSAO'] ?? 'INFINITO') !== 'QUEBRA_CABECA' ? 'selected' : '' ?>>Infinito colorido — recomendado</option>
            <option value="QUEBRA_CABECA" <?= ($d['SIMBOLO_TEA_INCLUSAO'] ?? '') === 'QUEBRA_CABECA' ? 'selected' : '' ?>>Peça de quebra-cabeça — somente por preferência da família</option>
          </select>
        </div>
        <div class="col-md-6">
          <div class="form-check form-switch mt-2">
            <input class="form-check-input" type="checkbox" role="switch" id="chk-autoriza-marcador-inclusao"
                   name="autoriza_marcador_inclusao" value="1"
                   <?= !empty($d['AUTORIZA_MARCADOR_INCLUSAO']) ? 'checked' : '' ?>>
            <label class="form-check-label fw-bold" for="chk-autoriza-marcador-inclusao">Autorização do responsável para exibir o marcador na frente do crachá</label>
            <small class="text-muted d-block">Sem autorização, a impressão continua disponível, mas nenhum símbolo ou faixa é exibido.</small>
          </div>
        </div>
      </div>
      <div class="alert alert-info py-2 small mt-3 mb-0">
        <strong>Convenção visual deste sistema:</strong> física/motora → acesso; visual e auditiva → pictogramas correspondentes; intelectual/desenvolvimento, múltipla ou outra → ícone genérico; condição não aparente → girassol; neurodivergência, TEA e TDAH → infinito colorido, salvo preferência familiar pelo quebra-cabeça para TEA. É um mapeamento prático, não uma classificação oficial universal.
      </div>
    </div>
  </div>
  <script>
  (function () {
    var tea = document.querySelector('input[name="deficiencias_inclusao[]"][value="TEA"]');
    var bloco = document.getElementById('bloco-simbolo-tea');
    var simbolo = document.getElementById('sel-simbolo-tea');
    if (!tea || !bloco || !simbolo) return;
    function sincronizarSimboloTea() {
      var ativo = tea.checked;
      bloco.hidden = !ativo;
      simbolo.disabled = !ativo;
      if (!ativo) simbolo.value = 'INFINITO';
    }
    tea.addEventListener('change', sincronizarSimboloTea);
    sincronizarSimboloTea();
  })();
  </script>

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
