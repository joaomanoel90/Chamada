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
/* Arquivos estáticos são servidos aqui mesmo, e NÃO com "return false".
   O docroot é a raiz do repositório, mas a URL usa o prefixo /Chamada/, que não
   existe como pasta. Se devolvêssemos false, o servidor embutido não acharia o
   arquivo, subiria na árvore de diretórios e acabaria executando o index.php —
   que redireciona para o login. Resultado: CSS/JS viravam um redirect 302. */
$ext = strtolower(pathinfo($arquivo, PATHINFO_EXTENSION));
$mime = [
    'css'   => 'text/css; charset=UTF-8',
    'js'    => 'application/javascript; charset=UTF-8',
    'json'  => 'application/json; charset=UTF-8',
    'map'   => 'application/json; charset=UTF-8',
    'svg'   => 'image/svg+xml',
    'png'   => 'image/png',
    'jpg'   => 'image/jpeg',
    'jpeg'  => 'image/jpeg',
    'gif'   => 'image/gif',
    'ico'   => 'image/x-icon',
    'woff'  => 'font/woff',
    'woff2' => 'font/woff2',
    'ttf'   => 'font/ttf',
    'eot'   => 'application/vnd.ms-fontobject',
    'mp4'   => 'video/mp4',
    'txt'   => 'text/plain; charset=UTF-8',
];

header('Content-Type: ' . ($mime[$ext] ?? 'application/octet-stream'));
header('Content-Length: ' . filesize($arquivo));
header('Cache-Control: public, max-age=3600');
readfile($arquivo);
return true;
