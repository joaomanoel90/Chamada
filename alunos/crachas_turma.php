<?php
require_once(dirname(__DIR__) . '/config.php');
require_once(DBAPI);
require_once(ABSPATH . 'inc/chamada.php');
require_once(__DIR__ . '/fotos.php');
require_once(__DIR__ . '/inclusao.php');
ch_db();
if (empty($_SESSION['usuarioId'])) {
    header('Location: ' . BASEURL . 'login.php');
    exit;
}

$turmaId = (int)($_GET['turma_id'] ?? 0);
$turma = ch_turma($turmaId);
if (!$turma || !ch_pode_turma($turmaId)) {
    ch_flash('Turma não encontrada ou fora do seu alcance.', 'danger');
    header('Location: ' . ch_url('turmas/index.php'));
    exit;
}

$alunos = ch_rows(
    'SELECT ID, NOME, MATRICULA, RESPONSAVEL, TELEFONE,
            ALERGIAS, RESTRICOES_ALIMENTARES, MEDICACAO,
            DEFICIENCIAS_INCLUSAO, SIMBOLO_TEA_INCLUSAO, AUTORIZA_MARCADOR_INCLUSAO,
            CASE WHEN FOTO_DADOS IS NULL THEN 0 ELSE 1 END AS TEM_FOTO
     FROM TB_ALUNOS
     WHERE ID_TURMA = ? AND ATIVO = 1
     ORDER BY NOME, ID',
    [$turmaId]
);
$folhas = array_chunk($alunos, 9);
/* Fonte do nome do responsável no verso, pelo tamanho (igual ao crachá
   individual em alunos/cracha.php). */
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
  <title>Crachás — <?= ch_h($turma['NOME']) ?></title>
  <style>
    * { box-sizing: border-box; }
    body {
      margin: 0;
      padding: 24px;
      background: #f2f5f8;
      color: #203047;
      font: 14px Arial, sans-serif;
    }
    .acoes {
      display: flex;
      justify-content: center;
      gap: 10px;
      margin-bottom: 20px;
    }
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
    .resumo { max-width: 700px; margin: 0 auto 18px; text-align: center; }
    .folha {
      width: 194mm;
      min-height: 281mm;
      margin: 0 auto 20px;
      padding: 7mm 0;
      display: grid;
      grid-template-columns: repeat(3, 54mm);
      grid-auto-rows: 86mm;
      gap: 4mm;
      align-content: start;
      justify-content: center;
      background: #fff;
      box-shadow: 0 4px 18px rgba(32, 48, 71, .12);
    }
    .cracha {
      position: relative;
      width: 54mm;
      height: 86mm;
      overflow: hidden;
      padding: 5mm 4mm;
      border: 1px solid #d6e1e8;
      border-radius: 4mm;
      background: linear-gradient(160deg, #fff 0%, #f5fbff 60%, #e8f6ef 100%);
      text-align: center;
      /* Coluna flex: o nome ocupa o espaço que sobra e as informações de
         baixo (turma, período, ano) nunca saem do crachá. */
      display: flex;
      flex-direction: column;
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
    /* Nome: nunca corta. A fonte é ajustada no PHP pelo tamanho do nome
       (ver $nomeFonte abaixo) e a quebra de palavra cobre sobrenomes
       compridos sem espaço. */
    .nome {
      margin: 0 auto 1.5mm;
      line-height: 1.15;
      font-weight: 800;
      overflow-wrap: break-word;
      word-break: break-word;
      flex: 1 1 auto;
    }
    /* margin-top:auto cola o bloco de baixo no rodapé do crachá. */
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
    /* Verso: só o que NÃO está na frente — saúde/alimentação e responsáveis,
       sem repetir nome, matrícula, turma, unidade ou prefeitura. */
    .cracha.verso {
      padding: 4mm;
      text-align: left;
      background: linear-gradient(155deg, #f5fbff 0%, #fff 50%, #e9f6ee 100%);
    }
    .verso-secao-titulo { margin: 1.5mm 0 1mm; color: #26734d; font-size: 6pt; font-weight: 800; text-transform: uppercase; letter-spacing: .3px; }
    .verso-secao-titulo:first-child { margin-top: 0; }
    .lista-saude, .lista-contatos { display: flex; flex-direction: column; gap: 1mm; }
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
    .sem-saude, .sem-contato { padding: 2mm; border: 1px dashed #cbd9d1; border-radius: 1.5mm; color: #657487; font-size: 6pt; text-align: center; }
    .verso-rodape { margin-top: auto; padding-top: 1mm; color: #657487; font-size: 5.3pt; line-height: 1.15; text-align: center; }
    @page { size: A4 portrait; margin: 8mm; }
    @media print {
      body { padding: 0; background: #fff; }
      .acoes, .resumo { display: none; }
      .folha {
        width: auto;
        height: 281mm;
        min-height: 0;
        margin: 0;
        padding: 7mm 0;
        box-shadow: none;
        break-after: page;
        page-break-after: always;
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
      }
      .folha:last-child { break-after: auto; page-break-after: auto; }
      .cracha { break-inside: avoid; }
    }
  </style>
</head>
<body>
  <nav class="acoes" aria-label="Ações dos crachás">
    <a href="<?= ch_url('turmas/index.php') ?>">Voltar para turmas</a>
    <?php if ($alunos): ?>
      <button type="button" onclick="window.print()">Imprimir / salvar PDF</button>
    <?php endif; ?>
  </nav>
  <header class="resumo">
    <h1>Crachás — Turma <?= ch_h(ch_series_ensino()[$turma['SERIE'] ?? '']['rotulo'] ?? 'não classificada') ?>
      · Sala <?= ch_h($turma['NOME']) ?></h1>
    <p><?= ch_h($turma['UNIDADE']) ?> · <?= ch_h($turma['PERIODO']) ?> · Ano letivo <?= (int)$turma['ANO_LETIVO'] ?></p>
    <p><?= count($alunos) ?> aluno(s) ativo(s); até 9 crachás por folha A4, frente e verso.</p>
    <p>Para cartão frente e verso, imprima em duplex virando pela borda longa: cada folha de frentes é seguida pela folha de versos já espelhada. Os marcadores aparecem somente com autorização registrada.</p>
  </header>

  <?php if (!$alunos): ?>
    <p class="resumo">Não há alunos ativos nesta turma.</p>
  <?php else: ?>
    <?php foreach ($folhas as $folha): ?>
      <main class="folha">
        <?php foreach ($folha as $aluno): ?>
          <?php
            $fotoSrc = aluno_foto_src($aluno['TEM_FOTO'], $aluno['ID']);
            $marcadoresInclusaoHtml = aluno_inclusao_html_marcadores($aluno);
            /* Fonte do nome pelo tamanho: garante que nomes grandes caibam
               no crachá sem cortar nenhuma letra. */
            $tamNome = mb_strlen($aluno['NOME']);
            $nomeFonte = $tamNome <= 28 ? '11pt'
                       : ($tamNome <= 38 ? '10pt'
                       : ($tamNome <= 48 ? '9pt' : '8pt'));
          ?>
          <article class="cracha">
            <div class="marca">PREFEITURA DE ANGATUBA</div>
            <div class="subtitulo"><?= ch_h($turma['UNIDADE']) ?></div>
            <div class="foto">
              <?php if ($fotoSrc): ?>
                <img src="<?= ch_h($fotoSrc) ?>" alt="Foto de <?= ch_h($aluno['NOME']) ?>">
              <?php else: ?>
                <?= ch_h(ch_iniciais($aluno['NOME'])) ?>
              <?php endif; ?>
            </div>
            <h2 class="nome" style="font-size: <?= $nomeFonte ?>"><?= ch_h($aluno['NOME']) ?></h2>
            <div class="matricula">Matrícula: <?= ch_h($aluno['MATRICULA']) ?></div>
            <div class="linha">Turma <?= ch_h(ch_series_ensino()[$turma['SERIE'] ?? '']['rotulo'] ?? 'não classificada') ?>
              · Sala <?= ch_h($turma['NOME']) ?></div>
            <div class="linha"><?= ch_h($turma['PERIODO']) ?></div>
            <div class="ano">Ano letivo <?= (int)$turma['ANO_LETIVO'] ?></div>
            <?= $marcadoresInclusaoHtml ?>
          </article>
        <?php endforeach; ?>
      </main>
      <?php
        /* Verso espelhado por linha (3 colunas): ao virar a folha pela borda
           longa no duplex, cada verso cai atrás da sua frente. */
        $folhaVerso = [];
        foreach (array_chunk($folha, 3) as $linha) {
            $folhaVerso = array_merge($folhaVerso, array_reverse($linha));
        }
      ?>
      <main class="folha">
        <?php foreach ($folhaVerso as $aluno): ?>
          <?php
            $responsaveisFamilia = fam_responsaveis_aluno($aluno['ID']);
            $alertasSaude = eq_saude_alertas($aluno);
          ?>
          <article class="cracha verso">
            <div class="verso-secao-titulo">Saúde e alimentação</div>
            <div class="lista-saude">
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
              <?php elseif (trim((string)($aluno['RESPONSAVEL'] ?? '')) !== '' || trim((string)($aluno['TELEFONE'] ?? '')) !== ''): ?>
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
          </article>
        <?php endforeach; ?>
      </main>
    <?php endforeach; ?>
  <?php endif; ?>
</body>
</html>
