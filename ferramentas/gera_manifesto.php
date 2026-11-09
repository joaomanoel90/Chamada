<?php
/**
 * Gera inc/manifesto.php com o SHA-256 de cada arquivo do sistema.
 *
 * Serve para o verifica_instalacao.php detectar arquivo ATRASADO, e nao
 * so arquivo faltando. Copiar tela nova por cima de include antigo produz
 * "Call to undefined function", que nao diz qual arquivo ficou velho;
 * o manifesto diz.
 *
 * Uso, na raiz do sistema, pelo terminal:
 *     php ferramentas/gera_manifesto.php
 *
 * Rode sempre antes de publicar uma versao nova. Nao precisa rodar no
 * servidor: o manifesto ja vem pronto dentro do pacote.
 *
 * Este arquivo nao faz parte da execucao do sistema.
 */

if (PHP_SAPI !== 'cli') {
	http_response_code(403);
	exit('Rode pelo terminal: php ferramentas/gera_manifesto.php');
}

$raiz = dirname(__DIR__);

/* O proprio manifesto nao entra na conta, e o config.php fica de fora
 * porque e o arquivo que voce edita no servidor (senha do banco, pasta). */
$pular = array('inc/manifesto.php', 'config.php');

/* Arquivos que, se estiverem atrasados, quebram o sistema. */
$criticos = array('php');

$it = new RecursiveIteratorIterator(
	new RecursiveDirectoryIterator($raiz, FilesystemIterator::SKIP_DOTS)
);

$linhas = array();
$total  = 0;

foreach ($it as $arquivo) {
	if (!$arquivo->isFile()) continue;

	$rel = str_replace('\\', '/', substr($arquivo->getPathname(), strlen($raiz) + 1));

	if (strpos($rel, '.git/') === 0 || $rel === '.gitattributes') continue;
	if (in_array($rel, $pular, true)) continue;

	$ext      = strtolower(pathinfo($rel, PATHINFO_EXTENSION));
	$critico  = in_array($ext, $criticos, true) ? 1 : 0;
	/* Normaliza CRLF e BOM: o manifesto tem de valer tanto para quem
	 * extraiu o ZIP (LF) quanto para quem deu git clone no Windows (CRLF). */
	$conteudo = (string) file_get_contents($arquivo->getPathname());
	if (substr($conteudo, 0, 3) === "\xEF\xBB\xBF") $conteudo = substr($conteudo, 3);
	$conteudo = str_replace(array("\r\n", "\r"), "\n", $conteudo);
	$hash     = hash('sha256', $conteudo);
	$bytes    = filesize($arquivo->getPathname());

	$linhas[$rel] = sprintf(
		"\t'%s' => array('%s', %d, %d),",
		addslashes($rel), $hash, $bytes, $critico
	);
	$total++;
}

ksort($linhas);

$saida  = "<?php\n";
$saida .= "/**\n";
$saida .= " * Conferencia de arquivos do sistema. GERADO AUTOMATICAMENTE.\n";
$saida .= " * Nao edite a mao: rode php ferramentas/gera_manifesto.php\n";
$saida .= " *\n";
$saida .= " * caminho => array(sha256, bytes, critico)\n";
$saida .= " *   critico = 1 -> arquivo de codigo: atrasado, quebra o sistema\n";
$saida .= " *   critico = 0 -> apoio (SQL, texto, estilo): atrasado, avisa\n";
$saida .= " *\n";
$saida .= " * config.php nao entra: e o arquivo que voce edita no servidor.\n";
$saida .= " */\n";
$saida .= "return array(\n" . implode("\n", $linhas) . "\n);\n";

$destino = $raiz . '/inc/manifesto.php';
if (file_put_contents($destino, $linhas === array() ? '' : $saida) === false) {
	exit("Nao consegui gravar {$destino}\n");
}

echo 'inc/manifesto.php gerado com ' . $total . " arquivos.\n";
