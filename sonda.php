<?php
/**
 * SONDA DE RUNTIME — responde "o arquivo esta certo, por que o erro continua?"
 *
 *     http://localhost/Chamada/sonda.php
 *     http://localhost/Chamada/sonda.php?texto=1     (para copiar e colar)
 *     http://localhost/Chamada/sonda.php?limpar=1    (limpa o cache do PHP)
 *
 * Diferenca para o diagnostico.php: aquele LE o texto dos arquivos. Este
 * CARREGA o inc/chamada.php de verdade, dentro do PHP do Apache, e mostra
 * quais funcoes existem depois disso, quais arquivos foram realmente
 * incluidos e o que o opcache tem guardado.
 *
 * Serve para o caso em que os arquivos estao corretos no disco mas o erro
 * persiste — quase sempre opcache, inclusive o opcache.file_cache, que
 * sobrevive ao reinicio do Apache.
 *
 * Nao precisa de login nem de banco: define ABSPATH e DBAPI antes de carregar
 * o chamada.php, o que faz ele pular o config.php (e portanto pular a sessao,
 * a trava de autenticacao e a conexao).
 *
 * Responde so para a maquina local / rede interna. APAGUE depois de usar.
 */

$ip = $_SERVER['REMOTE_ADDR'] ?? '';
$local = in_array($ip, array('127.0.0.1', '::1', 'localhost'), true)
	|| preg_match('/^(10\.|192\.168\.|172\.(1[6-9]|2\d|3[01])\.|fe80:|fc00:|fd)/', $ip);
if (!$local) {
	http_response_code(403);
	exit('Sonda disponível apenas na máquina local ou na rede interna.');
}

$raiz = __DIR__;
function so_h($s) { return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8'); }

/* ------------------------------------------------------------------ *
 * Limpeza de cache, se pedida.
 * ------------------------------------------------------------------ */
$acoes = array();
if (isset($_GET['limpar'])) {
	if (function_exists('opcache_reset')) {
		$acoes[] = opcache_reset()
			? 'opcache_reset() executado: cache em memória limpo.'
			: 'opcache_reset() devolveu falso (opcache pode estar inativo).';
	} else {
		$acoes[] = 'opcache não está disponível neste PHP.';
	}
	/* O file_cache guarda bytecode em DISCO e sobrevive ao reinicio do
	 * Apache. E a causa de "reiniciei e o erro continuou". */
	$fc = ini_get('opcache.file_cache');
	if ($fc) {
		$n = 0;
		$it = @opcache_invalidate('', true);
		$acoes[] = 'opcache.file_cache está em ' . $fc
			. '. Apague o conteúdo dessa pasta com o Apache parado, ou desative'
			. ' a diretiva opcache.file_cache no php.ini e reinicie.';
	}
	clearstatcache(true);
}

/* ------------------------------------------------------------------ *
 * Carrega o chamada.php sem passar pelo config.php.
 * ------------------------------------------------------------------ */
if (!defined('ABSPATH')) define('ABSPATH', $raiz . '/');
if (!defined('DBAPI'))   define('DBAPI', ABSPATH . 'inc/database.php');
if (!defined('BASEURL')) define('BASEURL', '/Chamada/');

$antes = get_included_files();
$erro_carga = '';
$carregou = false;
try {
	require_once ABSPATH . 'inc/chamada.php';
	$carregou = true;
} catch (Throwable $e) {
	$erro_carga = get_class($e) . ': ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine();
}
$depois = get_included_files();
$novos  = array_values(array_diff($depois, $antes));

/* ------------------------------------------------------------------ *
 * Confere as funcoes que as telas reclamam.
 * ------------------------------------------------------------------ */
$esperadas = array(
	'eq_tem_saude'            => 'alunos/index.php:55',
	'eq_papel_rotulo'         => 'turmas/index.php:65',
	'eq_professores_opcoes'   => 'turmas/processa/salvar_processa.php:159',
	'eq_turnos'               => '', 'eq_validar_equipe' => '', 'eq_ler_post' => '',
	'eq_equipe'               => '', 'eq_turmas_ids_em' => '', 'eq_caixa_saude' => '',
	'eq_saude_resumo'         => '', 'eq_saude_ficha' => '', 'eq_regente_legado' => '',
	'tr_vinculos'             => '', 'tr_filtro_na_data' => '', 'tr_pendente' => '',
	'ch_db'                   => '', 'ch_h' => '', 'ch_pode_turma' => '',
);
$faltando = array();
foreach ($esperadas as $f => $onde) {
	if (!function_exists($f)) $faltando[$f] = $onde;
}

/* ------------------------------------------------------------------ *
 * Opcache: configuracao e o que esta guardado dos nossos arquivos.
 * ------------------------------------------------------------------ */
$op_on = function_exists('opcache_get_status');
$op_cfg = array();
foreach (array('opcache.enable', 'opcache.enable_cli', 'opcache.validate_timestamps',
               'opcache.revalidate_freq', 'opcache.file_cache', 'opcache.file_cache_only',
               'opcache.max_accelerated_files', 'opcache.memory_consumption') as $k) {
	$op_cfg[$k] = ini_get($k);
}
$op_scripts = array();
$op_stat = null;
if ($op_on) {
	$op_stat = @opcache_get_status(false);
	if (is_array($op_stat) && !empty($op_stat['scripts'])) {
		foreach ($op_stat['scripts'] as $sc) {
			if (stripos($sc['full_path'], 'chamada') !== false
				|| stripos($sc['full_path'], 'equipe') !== false
				|| stripos($sc['full_path'], 'transferencias') !== false) {
				$op_scripts[] = $sc;
			}
		}
	}
}

/* Alerta especifico do file_cache. */
$alertas = array();
if ($op_on && $op_cfg['opcache.file_cache'] !== '' && $op_cfg['opcache.file_cache'] !== false) {
	$alertas[] = 'opcache.file_cache está LIGADO (' . $op_cfg['opcache.file_cache'] . '). '
		. 'Ele guarda o código compilado em DISCO e continua valendo depois de reiniciar o Apache. '
		. 'É a explicação clássica para "o arquivo está certo e o erro não sai". '
		. 'Apague o conteúdo dessa pasta com o Apache parado, ou comente a linha opcache.file_cache no php.ini.';
}
if ($op_on && !$op_cfg['opcache.validate_timestamps']) {
	$alertas[] = 'opcache.validate_timestamps está DESLIGADO: o PHP não percebe arquivo alterado '
		. 'enquanto o processo não for reiniciado.';
}
if ($carregou && !$faltando) {
	$alertas[] = 'Todas as funções existem nesta sondagem. Se a tela ainda falha, o problema é '
		. 'cache do PHP específico daquele arquivo, ou o Apache está servindo outra pasta. '
		. 'Use ?limpar=1 e recarregue a tela.';
}

$sapi = PHP_SAPI;
$ini  = php_ini_loaded_file();

/* ================================================================== *
 * Saida em texto puro
 * ================================================================== */
if (isset($_GET['texto'])) {
	header('Content-Type: text/plain; charset=utf-8');
	echo "SONDA DE RUNTIME\n";
	echo 'Pasta: ' . $raiz . "\n";
	echo 'Data: ' . date('d/m/Y H:i:s') . "\n";
	echo 'PHP: ' . PHP_VERSION . ' | SAPI: ' . $sapi . "\n";
	echo 'php.ini carregado: ' . ($ini ?: '(nenhum)') . "\n";
	echo 'sqlsrv: ' . (extension_loaded('sqlsrv') ? 'sim' : 'NAO') . "\n";
	echo str_repeat('=', 64) . "\n\n";

	foreach ($acoes as $a) echo "ACAO: " . $a . "\n";
	if ($acoes) echo "\n";

	echo 'CARREGOU inc/chamada.php? ' . ($carregou ? 'SIM' : 'NAO') . "\n";
	if ($erro_carga !== '') echo 'ERRO: ' . $erro_carga . "\n";
	echo "\nFUNCOES QUE AS TELAS RECLAMAM\n";
	foreach ($esperadas as $f => $onde) {
		echo '  ' . str_pad($f . '()', 26) . (function_exists($f) ? 'EXISTE' : 'NAO EXISTE')
			. ($onde && !function_exists($f) ? '   <- usada em ' . $onde : '') . "\n";
	}
	echo "\nRESULTADO: " . ($faltando ? count($faltando) . ' funcao(oes) AUSENTE(S)' : 'todas presentes') . "\n";

	echo "\nARQUIVOS REALMENTE INCLUIDOS NESTA SONDAGEM\n";
	foreach ($novos as $f) echo '  ' . $f . '  (' . (is_file($f) ? number_format(filesize($f), 0, ',', '.') . ' B' : '?') . ")\n";
	if (!$novos) echo "  (nenhum arquivo novo — o require_once achou tudo ja carregado)\n";

	echo "\nCAMINHOS REAIS\n";
	foreach (array('inc/chamada.php', 'inc/equipe.php', 'inc/transferencias.php') as $r) {
		$c = $raiz . '/' . $r;
		echo '  ' . str_pad($r, 26) . (file_exists($c) ? realpath($c) : 'AUSENTE') . "\n";
	}

	echo "\nOPCACHE\n";
	foreach ($op_cfg as $k => $v) echo '  ' . str_pad($k, 32) . ($v === '' ? '(vazio)' : $v) . "\n";
	if (is_array($op_stat)) {
		echo '  scripts em cache: ' . count($op_stat['scripts'] ?? array()) . "\n";
		echo '  memoria usada: ' . round(($op_stat['memory_usage']['used_memory'] ?? 0) / 1048576, 1) . " MB\n";
	}
	if ($op_scripts) {
		echo "\n  NOSSOS ARQUIVOS NO CACHE\n";
		foreach ($op_scripts as $sc) {
			echo '    ' . $sc['full_path'] . "\n";
			echo '      guardado em: ' . date('d/m/Y H:i:s', $sc['timestamp'])
				. ' | arquivo no disco: ' . (is_file($sc['full_path']) ? date('d/m/Y H:i:s', filemtime($sc['full_path'])) : '?') . "\n";
			if (is_file($sc['full_path']) && filemtime($sc['full_path']) > $sc['timestamp']) {
				echo "      ^ CACHE MAIS ANTIGO QUE O ARQUIVO: esta servindo codigo velho\n";
			}
		}
	}

	if ($alertas) { echo "\nALERTAS\n"; foreach ($alertas as $a) echo '  - ' . $a . "\n"; }
	exit;
}
?>
<!doctype html>
<html lang="pt-BR"><head><meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Sonda de runtime</title>
<style>
*{box-sizing:border-box}
body{margin:0;font-family:system-ui,"Segoe UI",sans-serif;background:#eef2f7;color:#243b53;line-height:1.6}
.f{max-width:920px;margin:0 auto;padding:26px 18px 60px}
h1{font-size:23px;margin:0 0 4px}.sub{color:#627d98;font-size:13px;margin:0 0 18px}
.p{background:#fff;border-radius:13px;padding:20px 24px;margin-bottom:16px;box-shadow:0 2px 10px rgba(16,42,67,.07)}
.p h2{font-size:16px;margin:0 0 12px;padding-bottom:8px;border-bottom:2px solid #eef2f7}
.v{border-left:6px solid <?php echo (!$carregou || $faltando) ? '#b42318' : '#127a4a'; ?>}
.v strong{font-size:19px;color:<?php echo (!$carregou || $faltando) ? '#b42318' : '#127a4a'; ?>}
table{width:100%;border-collapse:collapse;font-size:13.5px}
th,td{text-align:left;padding:7px 9px;border-bottom:1px solid #f2f5f9}
th{background:#f7f9fc;color:#627d98;font-size:12px;text-transform:uppercase}
.sim{color:#127a4a;font-weight:600}.nao{color:#b42318;font-weight:700}
.al{background:#fff8e6;border-left:4px solid #f0b429;padding:12px 15px;border-radius:0 8px 8px 0;margin-bottom:10px;font-size:14px}
code{background:#eef2f7;padding:2px 6px;border-radius:4px;font-size:13px;font-family:ui-monospace,Consolas,monospace}
pre{background:#1e293b;color:#e2e8f0;padding:12px 15px;border-radius:9px;overflow-x:auto;font-size:12.5px}
.btn{display:inline-block;background:#2f6fed;color:#fff;padding:9px 17px;border-radius:8px;text-decoration:none;font-weight:600;font-size:14px;margin-right:8px}
.btn.c{background:#627d98}
</style></head><body><div class="f">

<h1>Sonda de runtime</h1>
<p class="sub"><?php echo so_h($raiz); ?> &middot; PHP <?php echo so_h(PHP_VERSION); ?>
 &middot; <?php echo so_h($sapi); ?> &middot; <?php echo so_h(date('d/m/Y H:i:s')); ?><br>
 php.ini: <code><?php echo so_h($ini ?: '(nenhum)'); ?></code></p>

<?php foreach ($acoes as $a): ?><div class="p"><span class="sim"><?php echo so_h($a); ?></span></div><?php endforeach; ?>

<div class="p v">
<strong><?php
	if (!$carregou) echo 'O inc/chamada.php não carregou';
	elseif ($faltando) echo count($faltando) . ' função(ões) ainda ausente(s)';
	else echo 'Todas as funções existem — o código no disco está certo';
?></strong>
<p style="margin:8px 0 0;font-size:14px"><?php
	if ($carregou && !$faltando)
		echo 'Esta sonda carregou o mesmo arquivo que as telas carregam, no mesmo PHP do Apache, e as funções apareceram. Se a tela ainda mostra erro, é cache do PHP ou outra pasta sendo servida.';
	else
		echo 'Veja abaixo o erro e a lista de arquivos realmente incluídos.';
?></p>
<?php if ($erro_carga !== ''): ?><pre><?php echo so_h($erro_carga); ?></pre><?php endif; ?>
</div>

<?php if ($alertas): ?><div class="p"><h2>Atenção</h2>
<?php foreach ($alertas as $a): ?><div class="al"><?php echo so_h($a); ?></div><?php endforeach; ?>
</div><?php endif; ?>

<div class="p"><h2>Funções que as telas reclamam</h2>
<table><tr><th>Função</th><th>Estado</th><th>Usada em</th></tr>
<?php foreach ($esperadas as $f => $onde): $ex = function_exists($f); ?>
<tr><td><code><?php echo so_h($f); ?>()</code></td>
<td class="<?php echo $ex ? 'sim' : 'nao'; ?>"><?php echo $ex ? 'existe' : 'NÃO EXISTE'; ?></td>
<td style="font-size:12.5px;color:#627d98"><?php echo so_h($onde); ?></td></tr>
<?php endforeach; ?></table></div>

<div class="p"><h2>Arquivos realmente incluídos</h2>
<table><tr><th>Arquivo</th><th>Tamanho</th></tr>
<?php foreach ($novos as $n): ?>
<tr><td><?php echo so_h($n); ?></td><td><?php echo is_file($n) ? so_h(number_format(filesize($n), 0, ',', '.')) . ' B' : '?'; ?></td></tr>
<?php endforeach; ?>
<?php if (!$novos): ?><tr><td colspan="2">Nenhum arquivo novo: o <code>require_once</code> achou tudo já carregado.</td></tr><?php endif; ?>
</table></div>

<div class="p"><h2>Opcache</h2>
<table><?php foreach ($op_cfg as $k => $v): ?>
<tr><td><code><?php echo so_h($k); ?></code></td><td><?php echo so_h($v === '' ? '(vazio)' : $v); ?></td></tr>
<?php endforeach; ?></table>
<?php if ($op_scripts): ?>
<h2 style="margin-top:18px">Nossos arquivos no cache</h2>
<table><tr><th>Arquivo</th><th>Guardado em</th><th>No disco</th></tr>
<?php foreach ($op_scripts as $sc): $velho = is_file($sc['full_path']) && filemtime($sc['full_path']) > $sc['timestamp']; ?>
<tr><td style="font-size:12.5px"><?php echo so_h($sc['full_path']); ?></td>
<td><?php echo so_h(date('d/m H:i:s', $sc['timestamp'])); ?></td>
<td class="<?php echo $velho ? 'nao' : ''; ?>"><?php echo is_file($sc['full_path']) ? so_h(date('d/m H:i:s', filemtime($sc['full_path']))) : '?'; ?><?php echo $velho ? ' ← cache velho' : ''; ?></td></tr>
<?php endforeach; ?></table>
<?php endif; ?>
<p style="margin:14px 0 0">
<a class="btn" href="?limpar=1">Limpar o cache do PHP</a>
<a class="btn c" href="?texto=1">Versão em texto para copiar</a>
</p></div>

<div class="p"><h2>Depois de usar</h2>
<p style="font-size:14px;margin:0">Copie a <a href="?texto=1">versão em texto</a> e cole na conversa.
<strong>Apague o <code>sonda.php</code> e o <code>diagnostico.php</code></strong> quando terminarmos.</p></div>

</div></body></html>
