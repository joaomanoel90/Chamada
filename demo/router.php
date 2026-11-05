<?php
/* Roteador da demonstração: serve o sistema sob /Chamada/, como no IIS/Apache. */
$uri  = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$raiz = dirname(__DIR__);

if ($uri === '/' || $uri === '') { header('Location: /Chamada/login.php'); exit; }

$caminho = preg_replace('#^/Chamada#', '', $uri);
if ($caminho === '' || $caminho === '/') $caminho = '/index.php';

// bloqueia pastas internas
if (preg_match('#^/(demo|sql_inicial)(/|$)#', $caminho)) { http_response_code(403); echo 'Acesso negado'; return true; }

$arquivo = realpath($raiz . $caminho);
if ($arquivo === false || strpos($arquivo, $raiz) !== 0 || !is_file($arquivo)) {
    http_response_code(404); echo 'Não encontrado'; return true;
}

if (strtolower(pathinfo($arquivo, PATHINFO_EXTENSION)) === 'php') {
    // A trava de autenticação do config.php usa basename(SCRIPT_NAME).
    // Com router, o PHP aponta para o router.php; restauramos o script real.
    $_SERVER['SCRIPT_NAME']     = '/Chamada' . $caminho;
    $_SERVER['PHP_SELF']        = '/Chamada' . $caminho;
    $_SERVER['SCRIPT_FILENAME'] = $arquivo;
    chdir(dirname($arquivo)); // o sistema usa includes relativos
    require $arquivo;
    return true;
}
return false; // arquivos estáticos
