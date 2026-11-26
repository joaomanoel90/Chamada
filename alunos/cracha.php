<?php
require_once(dirname(__DIR__) . '/config.php');
require_once(DBAPI);
require_once(ABSPATH . 'inc/chamada.php');
require_once(__DIR__ . '/fotos.php');
require_once(__DIR__ . '/inclusao.php');
ch_db();

$id = (int)($_GET['id'] ?? 0);
$aluno = ch_exige_aluno($id);
$fotoSrc = aluno_foto_src($aluno['TEM_FOTO'] ?? false, $id);
$vinculosFamilia = fam_responsaveis_aluno($id);
$responsaveisFamilia = fam_contatos_cracha($id);
$marcadoresInclusaoHtml = aluno_inclusao_html_marcadores($aluno);

/* Fonte do nome pelo tamanho: garante que nomes grandes caibam no crachá sem
   cortar nenhuma letra. */
$tamNome = mb_strlen($aluno['NOME']);
$nomeFonte = $tamNome <= 28 ? '11pt'
           : ($tamNome <= 38 ? '10pt'
           : ($tamNome <= 48 ? '9pt' : '8pt'));
$fonteContato = function ($nome) {
    $n = mb_strlen((string)$nome);
    return $n <= 34 ? '7pt' : ($n <= 60 ? '6.3pt' : '5.5pt');
};
?>
<!doctype html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Crachá — <?= ch_h($aluno['NOME']) ?></title>
  <style>
    * { box-sizing: border-box; }
    body {
      margin: 0;
      min-height: 100vh;
      padding: 24px;
      display: flex;
      flex-direction: column;
      align-items: center;
      gap: 18px;
      background: #f2f5f8;
      color: #203047;
      font: 14px Arial, sans-serif;
    }
    .acoes { display: flex; gap: 10px; }
    .acoes a, .acoes button {
      border: 0;
      border-radius: 8px;
      padding: 10px 16px;
      background: #26734d;
      color: #fff;
      font: inherit;
      font-weight: 700;
      text-decoration: none;
      cursor: pointer;
    }
    .acoes a { background: #526174; }
    .orientacao { max-width: 620px; margin: 0; color: #526174; text-align: center; font-size: 13px; }
    .cracha-par { display: flex; flex-wrap: wrap; justify-content: center; gap: 20px; }
    .cracha {
      position: relative;
      width: 54mm;
      height: 86mm;
      overflow: hidden;
      padding: 5mm 4mm;
      border: 1px solid #d6e1e8;
      border-radius: 4mm;
      background: linear-gradient(160deg, #fff 0%, #f5fbff 60%, #e8f6ef 100%);
      box-shadow: 0 8px 26px rgba(32, 48, 71, .18);
      text-align: center;
      display: flex;
      flex-direction: column;
    }
    .lado-label {
      position: absolute;
      top: 1.5mm;
      right: 3mm;
      color: #657487;
      font-size: 5pt;
      font-weight: 800;
      letter-spacing: .8px;
    }
    .marca { color: #26734d; font-size: 9pt; font-weight: 800; letter-spacing: .5px; }
    .subtitulo {
      margin-top: 1mm;
      color: #405168;
      font-size: 7pt;
      font-weight: 700;
      line-height: 1.2;
      overflow-wrap: break-word;
      word-break: break-word;
    }
    .foto {
      width: 25mm;
      height: 26mm;
      margin: 3mm auto 2mm;
      flex: 0 0 auto;
      overflow: hidden;
      border: 2px solid #fff;
      border-radius: 3mm;
      background: #dcefe5;
      box-shadow: 0 2px 8px rgba(32, 48, 71, .2);
      display: grid;
      place-items: center;
      color: #26734d;
      font-size: 26pt;
      font-weight: 800;
    }
    .foto img { width: 100%; height: 100%; object-fit: cover; }
    .nome {
      margin: 0 auto 1.5mm;
      line-height: 1.15;
      font-weight: 800;
      overflow-wrap: break-word;
      word-break: break-word;
      flex: 1 1 auto;
    }
    .matricula { color: #26734d; font-size: 8pt; font-weight: 700; margin-top: auto; }
    .linha {
      margin-top: 2mm;
      color: #405168;
      font-size: 7pt;
      line-height: 1.2;
      overflow-wrap: break-word;
      word-break: break-word;
    }
    .ano { margin-top: 2mm; color: #657487; font-size: 7pt; }
    .cracha-inclusao {
      position: absolute;
      z-index: 2;
      right: 0;
      bottom: 0;
      left: 0;
      height: 5mm;
      display: flex;
      overflow: hidden;
      -webkit-print-color-adjust: exact;
      print-color-adjust: exact;
    }
    .cracha-inclusao-item { flex: 1 1 0; min-width: 0; display: grid; place-items: center; color: #fff; }
    .cracha-inclusao-item svg { display: block; width: 3.5mm; height: 3.5mm; }
    .cracha-inclusao-acesso { background: #1769aa; }
    .cracha-inclusao-visual { background: #4357a7; }
    .cracha-inclusao-auditiva { background: #21778f; }
    .cracha-inclusao-geral { background: #70459b; }
    .cracha-inclusao-girassol { background: #347344; color: #ffd74a; }
    .cracha-inclusao-infinito, .cracha-inclusao-quebra-cabeca {
      background: linear-gradient(90deg, #e44d5c, #ee9a3a, #e9d24a, #48a76a, #3989c9, #8053ae);
    }

    /* Verso: só o que NÃO está na frente — contatos de emergência e dados de
       saúde/alimentação (alergias, restrições alimentares, medicação), sem
       repetir nome, matrícula, turma, unidade ou prefeitura. A lista de
       contatos prioriza o contato principal e omite os marcados com restrição familiar. */
    .cracha-verso {
      padding: 4mm;
      text-align: left;
      background: linear-gradient(155deg, #f5fbff 0%, #fff 50%, #e9f6ee 100%);
    }
    .verso-secao-titulo { margin: 1.5mm 0 1mm; color: #26734d; font-size: 6pt; font-weight: 800; text-transform: uppercase; letter-spacing: .3px; }
    .verso-secao-titulo:first-child { margin-top: 0; }
    .lista-saude { display: flex; flex-direction: column; gap: 1mm; }
    .item-saude {
      padding: 1.2mm 1.5mm;
      border: 1px solid #dce5e0;
      border-left: 1.2mm solid #26734d;
      border-radius: 1.5mm;
      background: rgba(255,255,255,.88);
      overflow: hidden;
    }
    .item-saude.alerta { border-left-color: #c0392b; }
    .saude-rotulo { color: #526174; font-size: 5.3pt; line-height: 1.05; text-transform: uppercase; letter-spacing: .2px; }
    .saude-texto { margin-top: .4mm; color: #203047; font-size: 5.8pt; line-height: 1.15; overflow-wrap: anywhere; white-space: pre-wrap; }
    .sem-saude { padding: 2mm; border: 1px dashed #cbd9d1; border-radius: 1.5mm; color: #657487; font-size: 6pt; text-align: center; }
    .lista-contatos { display: flex; flex-direction: column; gap: 1mm; }
    .contato {
      padding: 1.2mm 1.5mm;
      border: 1px solid #dce5e0;
      border-radius: 1.5mm;
      background: rgba(255,255,255,.88);
      overflow: hidden;
    }
    .contato-vinculo { color: #526174; font-size: 5.3pt; line-height: 1.05; text-transform: uppercase; letter-spacing: .2px; }
    .contato-nome { margin-top: .4mm; color: #203047; font-weight: 700; line-height: 1.08; overflow-wrap: anywhere; }
    .contato-telefone { margin-top: .4mm; color: #26734d; font-size: 6.2pt; font-weight: 700; line-height: 1.05; }
    .sem-contato { padding: 2mm; border: 1px dashed #cbd9d1; border-radius: 1.5mm; color: #657487; font-size: 6pt; text-align: center; }
    .verso-rodape { margin-top: auto; padding-top: 1mm; color: #657487; font-size: 5.3pt; line-height: 1.15; text-align: center; }

    @page { size: 54mm 86mm; margin: 0; }
    @media print {
      body { display: block; width: 54mm; min-height: 0; padding: 0; background: #fff; }
      .acoes, .orientacao, .lado-label { display: none !important; }
      .cracha-par { display: block; }
      .cracha {
        margin: 0;
        border: 0;
        border-radius: 0;
        box-shadow: none;
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
        page-break-after: always;
        break-after: page;
      }
      .cracha:last-child { page-break-after: auto; break-after: auto; }
    }
  </style>
<script src="<?= BASEURL ?>js/csp-handlers.js"></script>
</head>
<body>
  <nav class="acoes" aria-label="Ações do crachá">
    <a href="<?= ch_url('alunos/view.php?id=' . (int)$id) ?>">Voltar à ficha</a>
    <button type="button" data-print>Imprimir frente e verso / salvar PDF</button>
  </nav>
  <p class="orientacao">O crachá contém duas páginas: frente (identificação) e verso (saúde, alimentação e responsáveis). Para imprimir em cartão frente e verso, selecione impressão duplex e vire pela borda longa.</p>

  <div class="cracha-par">
    <main class="cracha">
      <span class="lado-label">FRENTE</span>
      <div class="marca">PREFEITURA DE ANGATUBA</div>
      <div class="subtitulo"><?= ch_h($aluno['UNIDADE']) ?></div>
      <div class="foto">
        <?php if ($fotoSrc): ?>
          <img src="<?= ch_h($fotoSrc) ?>" alt="Foto de <?= ch_h($aluno['NOME']) ?>">
        <?php else: ?>
          <?= ch_h(ch_iniciais($aluno['NOME'])) ?>
        <?php endif; ?>
      </div>
      <h1 class="nome" style="font-size: <?= $nomeFonte ?>"><?= ch_h($aluno['NOME']) ?></h1>
      <div class="matricula">Matrícula: <?= ch_h($aluno['MATRICULA']) ?></div>
      <div class="linha">Turma <?= ch_h(ch_series_ensino()[$aluno['SERIE'] ?? '']['rotulo'] ?? 'não classificada') ?>
        · Sala <?= ch_h($aluno['TURMA']) ?></div>
      <div class="linha"><?= ch_h($aluno['PERIODO']) ?></div>
      <div class="ano">Ano letivo <?= (int)$aluno['ANO_LETIVO'] ?></div>
      <?= $marcadoresInclusaoHtml ?>
    </main>

    <main class="cracha cracha-verso">
      <span class="lado-label">VERSO</span>
      <div class="verso-secao-titulo">Saúde e alimentação</div>
      <div class="lista-saude">
        <?php $alertasSaude = eq_saude_alertas($aluno); ?>
        <?php if ($alertasSaude): ?>
          <?php foreach ($alertasSaude as $a): ?>
            <div class="item-saude<?= $a['campo'] === 'ALERGIAS' ? ' alerta' : '' ?>">
              <div class="saude-rotulo"><?= ch_h($a['rotulo']) ?></div>
              <div class="saude-texto"><?= ch_h($a['texto']) ?></div>
            </div>
          <?php endforeach; ?>
        <?php else: ?>
          <div class="sem-saude">Sem registros de saúde ou alimentação.</div>
        <?php endif; ?>
      </div>
      <div class="verso-secao-titulo">Responsáveis</div>
      <div class="lista-contatos">
        <?php if ($responsaveisFamilia): ?>
          <?php foreach ($responsaveisFamilia as $resp): ?>
            <div class="contato">
              <div class="contato-vinculo">
                <?= ch_h(fam_parentesco_rotulo($resp['PARENTESCO'], $resp['DESCRICAO_OUTRO'] ?? '')) ?>
                <?= !empty($resp['CONTATO_PRINCIPAL']) ? ' · PRINCIPAL' : '' ?>
              </div>
              <div class="contato-nome" style="font-size: <?= $fonteContato($resp['NOME'] ?? '') ?>"><?= ch_h($resp['NOME']) ?></div>
              <?php if (trim((string)($resp['TELEFONE'] ?? '')) !== ''): ?>
                <div class="contato-telefone"><?= ch_h($resp['TELEFONE']) ?></div>
              <?php endif; ?>
            </div>
          <?php endforeach; ?>
        <?php elseif (!$vinculosFamilia && (trim((string)($aluno['RESPONSAVEL'] ?? '')) !== '' || trim((string)($aluno['TELEFONE'] ?? '')) !== '')): ?>
          <div class="contato">
            <div class="contato-vinculo">Responsável</div>
            <?php if (trim((string)($aluno['RESPONSAVEL'] ?? '')) !== ''): ?>
              <div class="contato-nome" style="font-size: <?= $fonteContato($aluno['RESPONSAVEL']) ?>"><?= ch_h($aluno['RESPONSAVEL']) ?></div>
            <?php endif; ?>
            <?php if (trim((string)($aluno['TELEFONE'] ?? '')) !== ''): ?>
              <div class="contato-telefone"><?= ch_h($aluno['TELEFONE']) ?></div>
            <?php endif; ?>
          </div>
        <?php else: ?>
          <div class="sem-contato">Contate a unidade escolar.</div>
        <?php endif; ?>
      </div>
      <div class="verso-rodape">Se encontrar este crachá, devolva à unidade escolar.</div>
    </main>
  </div>
</body>
</html>
