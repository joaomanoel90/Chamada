<?php
/**
 * SONDA DE RUNTIME v2 — separa "arquivo errado" de "bytecode velho em cache".
 *
 *     http://localhost/Chamada/sonda.php
 *     http://localhost/Chamada/sonda.php?texto=1      (copiar e colar)
 *     http://localhost/Chamada/sonda.php?invalidar=1  (forca recompilar)
 *     http://localhost/Chamada/sonda.php?limpar=1     (zera o opcache)
 *
 * O experimento que decide: carrega o inc/chamada.php e ve se as funcoes
 * apareceram. Se nao apareceram, carrega o inc/equipe.php NA MAO.
 *
 *   - apareceu agora  -> o equipe.php esta otimo; quem nao executou o require
 *                        foi o chamada.php, ou seja, o PHP rodou bytecode
 *                        antigo dele. Problema de opcache.
 *   - continua ausente -> o equipe.php em disco nao define as funcoes.
 *   - deu erro         -> o erro aparece aqui, com arquivo e linha.
 *
 * Tambem captura qualquer saida que o include produzir: se o arquivo tiver
 * perdido a tag <?php, o PHP o trataria como HTML e o texto vazaria para a
 * pagina sem definir funcao nenhuma e sem dar erro.
 *
 * Nao precisa de login nem de banco: define ABSPATH e DBAPI antes, o que faz
 * o chamada.php pular o config.php.
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

$arquivos_chave = array(
	'inc/chamada.php', 'inc/equipe.php', 'inc/transferencias.php', 'inc/database.php',
);

/* ------------------------------------------------------------------ *
 * Acoes de cache
 * ------------------------------------------------------------------ */
$acoes = array();
if (isset($_GET['invalidar'])) {
	foreach ($arquivos_chave as $r) {
		$c = $raiz . '/' . $r;
		if (!is_file($c)) continue;
		$ok = function_exists('opcache_invalidate') ? @opcache_invalidate($c, true) : false;
		$acoes[] = 'opcache_invalidate(' . $r . ', force=true): ' . ($ok ? 'ok' : 'não havia nada em cache ou opcache inativo');
	}
	clearstatcache(true);
}
if (isset($_GET['limpar'])) {
	$acoes[] = (function_exists('opcache_reset') && @opcache_reset())
		? 'opcache_reset(): cache em memória zerado.'
		: 'opcache_reset() não fez efeito (opcache inativo ou restrito).';
	clearstatcache(true);
}

/* ------------------------------------------------------------------ *
 * ETAPA A — carrega o chamada.php como as telas fazem
 * ------------------------------------------------------------------ */
if (!defined('ABSPATH')) define('ABSPATH', $raiz . '/');
if (!defined('DBAPI'))   define('DBAPI', ABSPATH . 'inc/database.php');
if (!defined('BASEURL')) define('BASEURL', '/Chamada/');

$alvo_eq = ABSPATH . 'inc/equipe.php';

$antes = get_included_files();
$erro_a = ''; $saida_a = ''; $ok_a = false;
ob_start();
try { require_once ABSPATH . 'inc/chamada.php'; $ok_a = true; }
catch (Throwable $e) { $erro_a = get_class($e) . ': ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine(); }
$saida_a = ob_get_clean();
$depois_a = get_included_files();
$novos_a  = array_values(array_diff($depois_a, $antes));
$eq_entrou_a = false;
foreach ($novos_a as $f) if (realpath($f) === realpath($alvo_eq)) $eq_entrou_a = true;

$funcoes_eq = array('eq_tem_saude', 'eq_papel_rotulo', 'eq_professores_opcoes', 'eq_turnos',
	'eq_validar_equipe', 'eq_ler_post', 'eq_equipe', 'eq_turmas_ids_em', 'eq_caixa_saude',
	'eq_saude_resumo', 'eq_saude_ficha', 'eq_regente_legado');
$outras = array('tr_vinculos' => '', 'tr_filtro_na_data' => '', 'ch_db' => '', 'ch_h' => '');

$ausentes_a = array();
foreach ($funcoes_eq as $f) if (!function_exists($f)) $ausentes_a[] = $f;

/* ------------------------------------------------------------------ *
 * ETAPA B — carrega o equipe.php NA MAO. Este e o teste que decide.
 * ------------------------------------------------------------------ */
$erro_b = ''; $saida_b = ''; $ok_b = false; $rodou_b = false;
$ausentes_b = array();
if ($ausentes_a) {
	$rodou_b = true;
	ob_start();
	try { require_once $alvo_eq; $ok_b = true; }
	catch (Throwable $e) { $erro_b = get_class($e) . ': ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine(); }
	$saida_b = ob_get_clean();
	foreach ($funcoes_eq as $f) if (!function_exists($f)) $ausentes_b[] = $f;
}

/* ------------------------------------------------------------------ *
 * Veredito
 * ------------------------------------------------------------------ */
if (!$ok_a) {
	$veredito = 'erro';
	$titulo = 'O inc/chamada.php não carregou';
	$explica = 'Veja o erro abaixo. Ele diz o arquivo e a linha.';
} elseif (!$ausentes_a) {
	$veredito = 'ok';
	$titulo = 'Tudo certo: as funções existem';
	$explica = 'O include funcionou e todas as funções apareceram. Se alguma tela ainda falha, recarregue agora.';
} elseif ($rodou_b && !$ausentes_b) {
	$veredito = 'cache';
	$titulo = 'Confirmado: o PHP está rodando código antigo do chamada.php';
	$explica = 'O inc/equipe.php está perfeito — carregado na mão, definiu todas as '
		. count($funcoes_eq) . ' funções. Mas o inc/chamada.php, que deveria carregá-lo na linha 691, '
		. 'não o fez. Como o arquivo em disco tem essa linha, o que rodou foi uma versão compilada '
		. 'antiga, guardada pelo opcache. Use os botões abaixo; se voltar, desligue o opcache.';
} elseif ($rodou_b && $erro_b !== '') {
	$veredito = 'erro';
	$titulo = 'O inc/equipe.php tem um erro';
	$explica = 'Ao carregar o arquivo na mão apareceu o erro abaixo. É aí que está o problema.';
} elseif ($rodou_b && $saida_b !== '') {
	$veredito = 'erro';
	$titulo = 'O inc/equipe.php não está sendo lido como PHP';
	$explica = 'O include vazou texto para a página em vez de definir funções. Isso acontece quando '
		. 'o arquivo perdeu a tag <?php do começo, ou foi salvo com outra codificação.';
} else {
	$veredito = 'erro';
	$titulo = 'O inc/equipe.php não define as funções';
	$explica = 'Nem carregado na mão ele definiu as funções. O arquivo em disco não é o esperado.';
}

/* ------------------------------------------------------------------ *
 * ETAPA C — opcache, com a lista de scripts DE VERDADE
 * ------------------------------------------------------------------ */
$op_cfg = array();
foreach (array('opcache.enable', 'opcache.validate_timestamps', 'opcache.revalidate_freq',
               'opcache.file_cache', 'opcache.file_cache_only', 'opcache.consistency_checks',
               'opcache.max_accelerated_files') as $k) {
	$op_cfg[$k] = ini_get($k);
}
/* get_status(true) — com false o PHP NAO devolve a lista de scripts, e a
 * versao anterior desta sonda mostrava "0 em cache" sem significado. */
$op_stat = function_exists('opcache_get_status') ? @opcache_get_status(true) : null;
$op_scripts = array();
$op_total = 0;
if (is_array($op_stat) && !empty($op_stat['scripts'])) {
	$op_total = count($op_stat['scripts']);
	foreach ($op_stat['scripts'] as $sc) {
		foreach ($arquivos_chave as $r) {
			if (isset($sc['full_path']) && stripos(str_replace('\\', '/', $sc['full_path']), $r) !== false) {
				$op_scripts[] = $sc;
			}
		}
	}
}
$op_conf_real = function_exists('opcache_get_configuration') ? @opcache_get_configuration() : null;

$alertas = array();
if (is_array($op_stat) && isset($op_stat['memory_usage']['used_memory'])
	&& $op_stat['memory_usage']['used_memory'] < 0) {
	$alertas[] = 'O opcache informou memória usada NEGATIVA. Isso não é normal: as estatísticas dele '
		. 'estão inconsistentes neste PHP (' . PHP_VERSION . '). É mais um sinal de que o opcache '
		. 'não está confiável aqui, e reforça desligá-lo para testar.';
}
if ($op_cfg['opcache.file_cache']) {
	$alertas[] = 'opcache.file_cache ligado (' . $op_cfg['opcache.file_cache'] . '): guarda bytecode em DISCO '
		. 'e sobrevive ao reinício do Apache. Apague o conteúdo dessa pasta com o Apache parado.';
}
if (!$op_cfg['opcache.validate_timestamps']) {
	$alertas[] = 'opcache.validate_timestamps desligado: o PHP não percebe arquivo alterado.';
}
if (is_array($op_conf_real) && isset($op_conf_real['directives']['opcache.validate_timestamps'])
	&& (string) $op_conf_real['directives']['opcache.validate_timestamps'] !== (string) $op_cfg['opcache.validate_timestamps']) {
	$alertas[] = 'O valor em execução difere do php.ini: o opcache está com configuração própria. '
		. 'Veja a tabela de configuração real abaixo.';
}

$ini = php_ini_loaded_file();
$sapi = PHP_SAPI;

/* ================================================================== *
 * Texto puro
 * ================================================================== */
if (isset($_GET['texto'])) {
	header('Content-Type: text/plain; charset=utf-8');
	echo "SONDA DE RUNTIME v2\n";
	echo 'Pasta: ' . $raiz . "\n";
	echo 'Data: ' . date('d/m/Y H:i:s') . "\n";
	echo 'PHP: ' . PHP_VERSION . ' | SAPI: ' . $sapi . "\n";
	echo 'php.ini: ' . ($ini ?: '(nenhum)') . "\n";
	echo str_repeat('=', 66) . "\n\n";
	foreach ($acoes as $a) echo 'ACAO: ' . $a . "\n";
	if ($acoes) echo "\n";

	echo 'VEREDITO: ' . $titulo . "\n";
	echo wordwrap($explica, 66) . "\n\n";

	echo "ETAPA A — carregou o chamada.php como as telas fazem\n";
	echo '  carregou: ' . ($ok_a ? 'sim' : 'NAO') . "\n";
	if ($erro_a !== '') echo '  ERRO: ' . $erro_a . "\n";
	if ($saida_a !== '') echo '  VAZOU TEXTO (' . strlen($saida_a) . " bytes): " . substr($saida_a, 0, 200) . "\n";
	echo '  equipe.php entrou na lista de incluidos: ' . ($eq_entrou_a ? 'SIM' : 'NAO') . "\n";
	echo '  funcoes eq_* ausentes: ' . count($ausentes_a) . ' de ' . count($funcoes_eq) . "\n";
	echo "  arquivos incluidos:\n";
	foreach ($novos_a as $f) echo '    ' . $f . "\n";

	if ($rodou_b) {
		echo "\nETAPA B — carregou o equipe.php NA MAO\n";
		echo '  carregou: ' . ($ok_b ? 'sim' : 'NAO') . "\n";
		if ($erro_b !== '') echo '  ERRO: ' . $erro_b . "\n";
		if ($saida_b !== '') echo '  VAZOU TEXTO (' . strlen($saida_b) . ' bytes): ' . substr($saida_b, 0, 300) . "\n";
		echo '  funcoes eq_* ainda ausentes: ' . count($ausentes_b) . "\n";
		echo '  CONCLUSAO: ' . (!$ausentes_b
			? 'o arquivo esta BOM; o problema e o chamada.php rodando bytecode velho'
			: 'o arquivo em disco nao define as funcoes') . "\n";
	}

	echo "\nFUNCOES\n";
	foreach ($funcoes_eq as $f) {
		echo '  ' . str_pad($f . '()', 26)
			. 'depois do chamada.php: ' . (in_array($f, $ausentes_a, true) ? 'ausente' : 'existe');
		if ($rodou_b) echo ' | depois do equipe.php na mao: ' . (in_array($f, $ausentes_b, true) ? 'ausente' : 'existe');
		echo "\n";
	}
	foreach ($outras as $f => $_) echo '  ' . str_pad($f . '()', 26) . (function_exists($f) ? 'existe' : 'ausente') . "\n";

	echo "\nOPCACHE (php.ini)\n";
	foreach ($op_cfg as $k => $v) echo '  ' . str_pad($k, 34) . ($v === '' ? '(vazio)' : $v) . "\n";
	if (is_array($op_stat)) {
		echo '  total de scripts em cache: ' . $op_total . "\n";
		$mem = $op_stat['memory_usage']['used_memory'] ?? null;
		echo '  memoria usada: ' . ($mem === null ? '?' : $mem . ' bytes') . ($mem !== null && $mem < 0 ? '  <- NEGATIVO, anomalo' : '') . "\n";
		echo '  opcache ligado de fato: ' . (!empty($op_stat['opcache_enabled']) ? 'sim' : 'NAO') . "\n";
		echo '  restarts por falta de memoria: ' . ($op_stat['opcache_statistics']['oom_restarts'] ?? '?') . "\n";
		echo '  restarts por hash cheio: ' . ($op_stat['opcache_statistics']['hash_restarts'] ?? '?') . "\n";
	}
	if (is_array($op_conf_real) && !empty($op_conf_real['directives'])) {
		echo "\n  CONFIGURACAO REAL DO OPCACHE (em execucao)\n";
		foreach ($op_conf_real['directives'] as $k => $v) {
			if (is_bool($v)) $v = $v ? '1' : '0';
			if (strpos($k, 'opcache.enable') === 0 || strpos($k, 'opcache.validate') === 0
				|| strpos($k, 'opcache.revalidate') === 0 || strpos($k, 'opcache.file_cache') === 0
				|| strpos($k, 'opcache.consistency') === 0) {
				echo '    ' . str_pad($k, 36) . $v . "\n";
			}
		}
	}
	if ($op_scripts) {
		echo "\n  NOSSOS ARQUIVOS EM CACHE\n";
		foreach ($op_scripts as $sc) {
			$disco = is_file($sc['full_path']) ? filemtime($sc['full_path']) : 0;
			echo '    ' . $sc['full_path'] . "\n";
			echo '      bytecode de: ' . date('d/m/Y H:i:s', $sc['timestamp'])
				. ' | arquivo no disco: ' . ($disco ? date('d/m/Y H:i:s', $disco) : '?') . "\n";
			if ($disco > $sc['timestamp']) echo "      ^ CACHE MAIS ANTIGO QUE O ARQUIVO — esta servindo codigo velho\n";
		}
	} else {
		echo "\n  nenhum dos nossos arquivos aparece na lista de cache\n";
	}

	echo "\nO QUE FAZER\n";
	if ($veredito === 'cache') {
		echo wordwrap("1) Abra sonda.php?invalidar=1 e depois recarregue a tela que falha.\n"
			. "2) Se voltar, abra sonda.php?limpar=1 e recarregue.\n"
			. "3) Se ainda voltar, desligue o opcache: no arquivo " . ($ini ?: 'php.ini do Apache')
			. "\n   mude opcache.enable=1 para opcache.enable=0 e reinicie o Apache.\n"
			. "   Em maquina de desenvolvimento o opcache so atrapalha; o sistema funciona sem ele.\n", 66) . "\n";
	} else {
		echo wordwrap($explica, 66) . "\n";
	}
	exit;
}

$cor = $veredito === 'ok' ? '#127a4a' : ($veredito === 'cache' ? '#b26a00' : '#b42318');
?>
<!doctype html>
<html lang="pt-BR"><head><meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Sonda de runtime</title>
<style>
*{box-sizing:border-box}
body{margin:0;font-family:system-ui,"Segoe UI",sans-serif;background:#eef2f7;color:#243b53;line-height:1.6}
.f{max-width:940px;margin:0 auto;padding:26px 18px 60px}
h1{font-size:23px;margin:0 0 4px}.sub{color:#627d98;font-size:13px;margin:0 0 18px}
.p{background:#fff;border-radius:13px;padding:20px 24px;margin-bottom:16px;box-shadow:0 2px 10px rgba(16,42,67,.07)}
.p h2{font-size:16px;margin:0 0 12px;padding-bottom:8px;border-bottom:2px solid #eef2f7}
.v{border-left:6px solid <?php echo $cor; ?>}.v strong{font-size:19px;color:<?php echo $cor; ?>}
table{width:100%;border-collapse:collapse;font-size:13.5px}
th,td{text-align:left;padding:7px 9px;border-bottom:1px solid #f2f5f9}
th{background:#f7f9fc;color:#627d98;font-size:12px;text-transform:uppercase}
.sim{color:#127a4a;font-weight:600}.nao{color:#b42318;font-weight:700}
.al{background:#fff8e6;border-left:4px solid #f0b429;padding:12px 15px;border-radius:0 8px 8px 0;margin-bottom:10px;font-size:14px}
code{background:#eef2f7;padding:2px 6px;border-radius:4px;font-size:13px;font-family:ui-monospace,Consolas,monospace}
pre{background:#1e293b;color:#e2e8f0;padding:12px 15px;border-radius:9px;overflow-x:auto;font-size:12.5px;white-space:pre-wrap}
.btn{display:inline-block;background:#2f6fed;color:#fff;padding:9px 17px;border-radius:8px;text-decoration:none;font-weight:600;font-size:14px;margin:0 8px 8px 0}
.btn.c{background:#627d98}.btn.r{background:#b42318}
ol{padding-left:22px;font-size:14px}li{margin-bottom:8px}
</style></head><body><div class="f">

<h1>Sonda de runtime</h1>
<p class="sub"><?php echo so_h($raiz); ?> &middot; PHP <?php echo so_h(PHP_VERSION); ?>
 &middot; <?php echo so_h($sapi); ?> &middot; <?php echo so_h(date('d/m/Y H:i:s')); ?><br>
 php.ini: <code><?php echo so_h($ini ?: '(nenhum)'); ?></code></p>

<?php foreach ($acoes as $a): ?><div class="p"><span class="sim"><?php echo so_h($a); ?></span></div><?php endforeach; ?>

<div class="p v"><strong><?php echo so_h($titulo); ?></strong>
<p style="margin:9px 0 0;font-size:14.5px"><?php echo so_h($explica); ?></p>
<?php if ($erro_a !== ''): ?><pre><?php echo so_h($erro_a); ?></pre><?php endif; ?>
<?php if ($erro_b !== ''): ?><pre><?php echo so_h($erro_b); ?></pre><?php endif; ?>
<?php if ($saida_b !== ''): ?><pre><?php echo so_h(substr($saida_b, 0, 400)); ?></pre><?php endif; ?>
</div>

<div class="p"><h2>O experimento</h2>
<table>
<tr><th>Etapa</th><th>Resultado</th></tr>
<tr><td>A &mdash; carregou o <code>chamada.php</code> como as telas fazem</td>
    <td class="<?php echo $ok_a ? 'sim' : 'nao'; ?>"><?php echo $ok_a ? 'carregou' : 'falhou'; ?></td></tr>
<tr><td>&nbsp;&nbsp;&nbsp;o <code>equipe.php</code> entrou na lista de incluídos?</td>
    <td class="<?php echo $eq_entrou_a ? 'sim' : 'nao'; ?>"><?php echo $eq_entrou_a ? 'sim' : 'NÃO'; ?></td></tr>
<tr><td>&nbsp;&nbsp;&nbsp;funções <code>eq_*</code> ausentes</td>
    <td class="<?php echo $ausentes_a ? 'nao' : 'sim'; ?>"><?php echo count($ausentes_a); ?> de <?php echo count($funcoes_eq); ?></td></tr>
<?php if ($rodou_b): ?>
<tr><td>B &mdash; carregou o <code>equipe.php</code> <strong>na mão</strong></td>
    <td class="<?php echo $ok_b ? 'sim' : 'nao'; ?>"><?php echo $ok_b ? 'carregou' : 'falhou'; ?></td></tr>
<tr><td>&nbsp;&nbsp;&nbsp;funções <code>eq_*</code> ainda ausentes</td>
    <td class="<?php echo $ausentes_b ? 'nao' : 'sim'; ?>"><?php echo count($ausentes_b); ?></td></tr>
<?php endif; ?>
</table>
<p style="font-size:13.5px;color:#627d98;margin:12px 0 0">Arquivos incluídos na etapa A:</p>
<pre><?php echo so_h(implode("\n", $novos_a) ?: '(nenhum)'); ?></pre>
</div>

<?php if ($alertas): ?><div class="p"><h2>Atenção</h2>
<?php foreach ($alertas as $a): ?><div class="al"><?php echo so_h($a); ?></div><?php endforeach; ?></div><?php endif; ?>

<?php if ($veredito === 'cache'): ?>
<div class="p"><h2>Como resolver, em ordem</h2>
<ol>
<li><a href="?invalidar=1">Forçar o PHP a recompilar estes arquivos</a> e recarregar a tela que falha.</li>
<li>Se voltar: <a href="?limpar=1">zerar o opcache inteiro</a> e recarregar.</li>
<li>Se ainda voltar, <strong>desligue o opcache</strong>. Abra<br>
<code><?php echo so_h($ini ?: 'php.ini do Apache'); ?></code><br>
e troque <code>opcache.enable=1</code> por <code>opcache.enable=0</code>. Reinicie o Apache.
<br>Em máquina de desenvolvimento o opcache só atrapalha: o sistema funciona igual sem ele.</li>
</ol>
<p style="font-size:14px;margin:0">Se depois de desligar o opcache as telas abrirem, estava confirmado.
Você pode religá-lo depois, mas aí com <code>opcache.validate_timestamps=1</code> e
<code>opcache.revalidate_freq=0</code> para ele conferir o arquivo a cada acesso.</p>
</div>
<?php endif; ?>

<div class="p"><h2>Funções</h2>
<table><tr><th>Função</th><th>Depois do chamada.php</th><?php if ($rodou_b): ?><th>Depois do equipe.php na mão</th><?php endif; ?></tr>
<?php foreach ($funcoes_eq as $f): $a = !in_array($f, $ausentes_a, true); ?>
<tr><td><code><?php echo so_h($f); ?>()</code></td>
<td class="<?php echo $a ? 'sim' : 'nao'; ?>"><?php echo $a ? 'existe' : 'ausente'; ?></td>
<?php if ($rodou_b): $b = !in_array($f, $ausentes_b, true); ?>
<td class="<?php echo $b ? 'sim' : 'nao'; ?>"><?php echo $b ? 'existe' : 'ausente'; ?></td><?php endif; ?></tr>
<?php endforeach; ?>
<?php foreach ($outras as $f => $_): ?>
<tr><td><code><?php echo so_h($f); ?>()</code></td>
<td class="<?php echo function_exists($f) ? 'sim' : 'nao'; ?>"><?php echo function_exists($f) ? 'existe' : 'ausente'; ?></td>
<?php if ($rodou_b): ?><td>—</td><?php endif; ?></tr>
<?php endforeach; ?></table></div>

<div class="p"><h2>Opcache</h2>
<table><?php foreach ($op_cfg as $k => $v): ?>
<tr><td><code><?php echo so_h($k); ?></code></td><td><?php echo so_h($v === '' ? '(vazio)' : $v); ?></td></tr>
<?php endforeach; ?>
<?php if (is_array($op_stat)): ?>
<tr><td>ligado de fato</td><td class="<?php echo !empty($op_stat['opcache_enabled']) ? 'sim' : 'nao'; ?>"><?php echo !empty($op_stat['opcache_enabled']) ? 'sim' : 'não'; ?></td></tr>
<tr><td>scripts em cache</td><td><?php echo so_h($op_total); ?></td></tr>
<tr><td>reinícios por falta de memória</td><td><?php echo so_h($op_stat['opcache_statistics']['oom_restarts'] ?? '?'); ?></td></tr>
<?php endif; ?></table>
<?php if ($op_scripts): ?>
<h2 style="margin-top:18px">Nossos arquivos em cache</h2>
<table><tr><th>Arquivo</th><th>Bytecode de</th><th>Arquivo no disco</th></tr>
<?php foreach ($op_scripts as $sc): $d = is_file($sc['full_path']) ? filemtime($sc['full_path']) : 0; $velho = $d > $sc['timestamp']; ?>
<tr><td style="font-size:12.5px"><?php echo so_h($sc['full_path']); ?></td>
<td><?php echo so_h(date('d/m H:i:s', $sc['timestamp'])); ?></td>
<td class="<?php echo $velho ? 'nao' : ''; ?>"><?php echo $d ? so_h(date('d/m H:i:s', $d)) : '?'; ?><?php echo $velho ? ' ← cache velho' : ''; ?></td></tr>
<?php endforeach; ?></table>
<?php endif; ?>
<p style="margin:14px 0 0">
<a class="btn" href="?invalidar=1">Forçar recompilação</a>
<a class="btn r" href="?limpar=1">Zerar o opcache</a>
<a class="btn c" href="?texto=1">Versão em texto para copiar</a>
</p></div>

<div class="p"><h2>Depois de usar</h2>
<p style="font-size:14px;margin:0">Copie a <a href="?texto=1">versão em texto</a> e cole na conversa.
<strong>Apague o <code>sonda.php</code> e o <code>diagnostico.php</code></strong> quando terminarmos.</p></div>

</div></body></html>
