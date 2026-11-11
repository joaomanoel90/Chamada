<?php
require_once(dirname(__DIR__) . '/config.php');
require_once(DBAPI);
require_once(ABSPATH . 'inc/chamada.php');
require_once(__DIR__ . '/fotos.php');
ch_db();

$id = (int)($_GET['id'] ?? 0);
$aluno = ch_exige_aluno($id);
$fotoSrc = aluno_foto_src($aluno['TEM_FOTO'] ?? false, $id);
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
      display: grid;
      place-items: center;
      gap: 20px;
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
    .cracha {
      width: 54mm;
      height: 86mm;
      overflow: hidden;
      padding: 5mm 4mm;
      border: 1px solid #d6e1e8;
      border-radius: 4mm;
      background: linear-gradient(160deg, #fff 0%, #f5fbff 60%, #e8f6ef 100%);
      box-shadow: 0 8px 26px rgba(32, 48, 71, .18);
      text-align: center;
    }
    .marca { color: #26734d; font-size: 9pt; font-weight: 800; letter-spacing: .5px; }
    .subtitulo { margin-top: 1mm; color: #657487; font-size: 7pt; }
    .foto {
      width: 31mm;
      height: 34mm;
      margin: 4mm auto 2.5mm;
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
    .nome { margin: 0 auto 1.5mm; max-height: 14mm; overflow: hidden; font-size: 11pt; line-height: 1.15; font-weight: 800; }
    .matricula { color: #26734d; font-size: 8pt; font-weight: 700; }
    .linha { margin-top: 2mm; color: #405168; font-size: 7.5pt; line-height: 1.2; }
    .ano { margin-top: 2mm; color: #657487; font-size: 7pt; }
    @page { size: 54mm 86mm; margin: 0; }
    @media print {
      body { display: block; min-height: 0; padding: 0; background: #fff; }
      .acoes { display: none; }
      .cracha { border: 0; border-radius: 0; box-shadow: none; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    }
  </style>
</head>
<body>
  <nav class="acoes" aria-label="Ações do crachá">
    <a href="<?= ch_url('alunos/view.php?id=' . (int)$id) ?>">Voltar à ficha</a>
    <button type="button" onclick="window.print()">Imprimir / salvar PDF</button>
  </nav>
  <main class="cracha">
    <div class="marca">PREFEITURA DE ANGATUBA</div>
    <div class="subtitulo"><?= ch_h($aluno['UNIDADE']) ?></div>
    <div class="foto">
      <?php if ($fotoSrc): ?>
        <img src="<?= ch_h($fotoSrc) ?>" alt="Foto de <?= ch_h($aluno['NOME']) ?>">
      <?php else: ?>
        <?= ch_h(ch_iniciais($aluno['NOME'])) ?>
      <?php endif; ?>
    </div>
    <h1 class="nome"><?= ch_h($aluno['NOME']) ?></h1>
    <div class="matricula">Matrícula: <?= ch_h($aluno['MATRICULA']) ?></div>
    <div class="linha"><?= ch_h($aluno['TURMA']) ?></div>
    <div class="linha"><?= ch_h($aluno['PERIODO']) ?></div>
    <div class="ano">Ano letivo <?= (int)$aluno['ANO_LETIVO'] ?></div>
  </main>
</body>
</html>
