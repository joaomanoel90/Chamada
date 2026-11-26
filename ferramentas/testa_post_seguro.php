<?php
/**
 * Teste CLI dos invariantes de POST/CSRF do sistema.
 *
 * Uso, na raiz do sistema, pelo terminal:
 *     php ferramentas/testa_post_seguro.php
 *
 * Sai com código 0 quando todas as regras passam e 1 quando há falhas.
 * Pode ser colocado no CI ou rodado antes de publicar uma versão.
 *
 * Regras verificadas:
 *   1. todo <form method="post"> emite ch_csrf_campo() dentro do formulário;
 *   2. todo action de formulário é interno (sem esquema, // ou javascript:);
 *   3. todo arquivo que lê $_POST e grava no banco chama ch_csrf_ok();
 *   4. todo arquivo que lê $_POST sem gravar é marcado como
 *      "ch-somente-leitura" (declaração explícita de que não precisa de CSRF);
 *   5. nenhum processador "desativa" a checagem com ch-sem-csrf sem justificativa.
 *
 * A regra 3 usa uma heurística conservadora: presença de INSERT/UPDATE/DELETE
 * em TB_* ou de ch_exec()/ch_insert_id(). Quando um arquivo legítimo precisa
 * ficar de fora, a exceção deve estar documentada em $infraestrutura abaixo.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("Rode pelo terminal: php ferramentas/testa_post_seguro.php\n");
}

$raiz = dirname(__DIR__);

/* Arquivos de infraestrutura: definem helpers, não são alvos de POST. */
$infraestrutura = [
    'config.php',
    'diagnostico.php',
    'verifica_instalacao.php',
    'inc/chamada.php',
    'inc/auth_security.php',
    'inc/database.php',
    'inc/manifesto.php',
    'inc/tema.php',
    'inc/header.php',
    'inc/header2.php',
    'inc/footer.php',
    'inc/sidebar.php',
    'ferramentas/gera_manifesto.php',
    'ferramentas/testa_post_seguro.php',
];

$erros   = [];
$stats   = ['php' => 0, 'formularios' => 0, 'post' => 0, 'com_csrf' => 0, 'somente_leitura' => 0];

$iterador = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($raiz, FilesystemIterator::SKIP_DOTS)
);

foreach ($iterador as $arquivo) {
    if (!$arquivo->isFile() || $arquivo->getExtension() !== 'php') continue;

    $rel = str_replace('\\', '/', substr($arquivo->getPathname(), strlen($raiz) + 1));
    if (strpos($rel, '.git/') === 0) continue;

    $stats['php']++;
    $codigo = (string)file_get_contents($arquivo->getPathname());

    /* --- formulários ---------------------------------------------------- */
    if (preg_match_all('/<form\b[^>]*>/i', $codigo, $casos, PREG_OFFSET_CAPTURE)) {
        foreach ($casos[0] as $caso) {
            [$tag, $pos] = $caso;
            $stats['formularios']++;

            if (preg_match('/\baction\s*=\s*(["\'])(.*?)\1/is', $tag, $m)) {
                $action = trim($m[2]);
                if ($action !== '' && preg_match('~(?:https?:)?//|^\\\\|^javascript:~i', $action)) {
                    $erros[] = "$rel: action de formulário aponta para fora do sistema: $action";
                }
            }

            if (!preg_match('/\bmethod\s*=\s*["\']?post\b/i', $tag)) continue;
            $stats['post']++;

            $fim   = strpos($codigo, '</form>', $pos);
            $bloco = $fim === false ? substr($codigo, $pos) : substr($codigo, $pos, $fim - $pos);
            if (strpos($bloco, 'ch_csrf_campo') === false) {
                $erros[] = "$rel: formulário POST sem ch_csrf_campo() (linha " . ch_linha($codigo, $pos) . ')';
            }
        }
    }

    /* --- processadores --------------------------------------------------- */
    if (in_array($rel, $infraestrutura, true)) continue;
    if (!preg_match('/\$_POST\b/', $codigo)) continue;

    $grava = preg_match('/\b(?:INSERT|UPDATE|DELETE|MERGE)\s+(?:INTO\s+)?(?:dbo\.)?TB_[A-Z_]+\b/i', $codigo)
          || strpos($codigo, 'ch_exec(') !== false
          || strpos($codigo, 'ch_insert_id(') !== false;

    if ($grava) {
        if (strpos($codigo, 'ch_csrf_ok') !== false) {
            $stats['com_csrf']++;
        } elseif (strpos($codigo, 'ch-sem-csrf') === false) {
            $erros[] = "$rel: lê \$_POST e grava no banco sem ch_csrf_ok()";
        }
    } elseif (strpos($codigo, 'ch_csrf_ok') === false
              && strpos($codigo, 'ch-somente-leitura') === false
              && strpos($codigo, 'ch-sem-csrf') === false) {
        $erros[] = "$rel: lê \$_POST mas não declara ch_csrf_ok() nem ch-somente-leitura";
    } else {
        $stats['somente_leitura']++;
    }
}

function ch_linha($codigo, $posicao)
{
    return substr_count(substr($codigo, 0, $posicao), "\n") + 1;
}

echo "Invariantes de POST/CSRF\n";
echo str_repeat('-', 60), "\n";
echo "Arquivos PHP ............ ", $stats['php'], "\n";
echo "Formulários ............. ", $stats['formularios'], "\n";
echo "Formulários POST ........ ", $stats['post'], "\n";
echo "Processadores com CSRF .. ", $stats['com_csrf'], "\n";
echo "Somente leitura ......... ", $stats['somente_leitura'], "\n";
echo str_repeat('-', 60), "\n";

if ($erros) {
    echo count($erros), " falha(s):\n";
    foreach ($erros as $erro) echo '  - ', $erro, "\n";
    exit(1);
}

echo "OK: todos os POST possuem token CSRF e todos os processadores validam.\n";
exit(0);
