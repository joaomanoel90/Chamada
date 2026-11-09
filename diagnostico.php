<?php
/**
 * DIAGNOSTICO — arquivo unico, nao depende de mais nada.
 *
 *     http://localhost/Chamada/diagnostico.php
 *
 * Diferenca para o verifica_instalacao.php: aquele precisa do config.php, da
 * sessao e do inc/manifesto.php. Este nao precisa de NADA. Basta ele existir na
 * pasta. Serve justamente para quando a atualizacao deu errado e voce nao sabe
 * qual arquivo ficou velho.
 *
 * Ele nao executa o sistema: LE o texto dos arquivos e procura as funcoes e as
 * linhas de include. Assim consegue diagnosticar um arquivo que, se fosse
 * carregado, mataria a pagina com erro fatal.
 *
 * Mostra tambem a data de cada arquivo, que e a prova mais direta de qual
 * ficou para tras, e o estado do opcache, com botao para limpar.
 *
 * SEGURANCA: nao mostra senha nem dado de aluno, so nome, tamanho e data de
 * arquivo. Mesmo assim, responde apenas para a propria maquina e rede local.
 * APAGUE ESTE ARQUIVO quando terminar o diagnostico.
 */

/* ------------------------------------------------------------------ *
 * So responde para a maquina local / rede privada.
 * ------------------------------------------------------------------ */
$ip = $_SERVER['REMOTE_ADDR'] ?? '';
$local = in_array($ip, array('127.0.0.1', '::1', 'localhost'), true)
	|| preg_match('/^(10\.|192\.168\.|172\.(1[6-9]|2\d|3[01])\.|fe80:|fc00:|fd)/', $ip);

if (!$local) {
	http_response_code(403);
	exit('Diagnóstico disponível apenas na máquina local ou na rede interna.');
}

$raiz = __DIR__;
function dg_h($s) { return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8'); }

/* ------------------------------------------------------------------ *
 * Limpar o opcache, se pedido. Codigo velho em cache e causa real de
 * "copiei o arquivo e o erro continuou".
 * ------------------------------------------------------------------ */
$cache_limpo = null;
if (isset($_GET['limpar_cache'])) {
	$cache_limpo = function_exists('opcache_reset') ? opcache_reset() : false;
}

/* ------------------------------------------------------------------ *
 * O que cada arquivo tem que ter.
 * ------------------------------------------------------------------ */
$modulos = array(
	'inc/equipe.php' => array(
		'rotulo' => 'Equipe por turno, substituto e saúde',
		'funcoes' => array('eq_turnos', 'eq_turno_rotulo', 'eq_turno_cor', 'eq_turno_selo',
			'eq_turno_cobre', 'eq_turno_sobrepoe', 'eq_professor_turno', 'eq_turma_turno',
			'eq_papeis', 'eq_papel_rotulo', 'eq_papel_cor', 'eq_ler_post', 'eq_validar_equipe',
			'eq_regente_legado', 'eq_equipe', 'eq_vigente', 'eq_situacao_prazo', 'eq_equipe_em',
			'eq_responsavel', 'eq_texto', 'eq_substitutos_em', 'eq_turmas_ids_em', 'eq_na_equipe_em',
			'eq_professores_opcoes', 'eq_saude_campos', 'eq_saude_alertas', 'eq_tem_saude',
			'eq_tem_alergia', 'eq_saude_resumo', 'eq_caixa_saude', 'eq_saude_ficha'),
	),
	'inc/transferencias.php' => array(
		'rotulo' => 'Transferência de aluno entre escolas',
		'funcoes' => array('tr_situacoes', 'tr_situacao_badge', 'tr_vinculos', 'tr_vinculos_no_ano',
			'tr_turma_na_data', 'tr_filtro_na_data', 'tr_aluno_ja_no_alcance', 'tr_pendente',
			'tr_transferencia', 'tr_historico_aluno', 'tr_pode_solicitar', 'tr_pode_receber',
			'tr_pode_cancelar', 'tr_pode_ver', 'tr_pendentes_recebidas', 'tr_unidades_destino',
			'tr_turmas_destino', 'tr_data_minima'),
	),
);

$culpa   = array();   /* o que esta causando o erro, em ordem de importancia */
$avisos  = array();
$linhas  = array();   /* tabela de arquivos */

/* ------------------------------------------------------------------ *
 * 1. O carregador: inc/chamada.php inclui os modulos?
 * ------------------------------------------------------------------ */
$chamada = $raiz . '/inc/chamada.php';
$fonte_chamada = file_exists($chamada) ? (string) file_get_contents($chamada) : null;

if ($fonte_chamada === null) {
	$culpa[] = array(
		'inc/chamada.php não existe',
		'É o arquivo que toda tela carrega. Sem ele nada funciona.',
		'Copie a pasta inc inteira do pacote novo.');
} else {
	foreach ($modulos as $arquivo => $def) {
		$nome = basename($arquivo);
		/* Procura a linha de include, nao o nome solto em comentario. */
		$tem = (bool) preg_match('/(require|include)(_once)?\s*\(?\s*__DIR__\s*\.\s*[\'"]\/' . preg_quote($nome, '/') . '/', $fonte_chamada);
		if (!$tem) {
			$culpa[] = array(
				'inc/chamada.php NÃO carrega o ' . $nome,
				'Esta é a causa do erro "Call to undefined function". O arquivo '
					. $nome . ' pode até estar na pasta, certo, mas nunca é incluído. '
					. 'Falta no fim do chamada.php a linha: require_once(__DIR__ . \'/' . $nome . '\');',
				'Copie o inc/chamada.php do pacote novo por cima deste. É UM arquivo.');
		}
	}
}

/* ------------------------------------------------------------------ *
 * 2. Cada modulo existe e define as funcoes esperadas?
 * ------------------------------------------------------------------ */
foreach ($modulos as $arquivo => $def) {
	$caminho = $raiz . '/' . $arquivo;
	if (!file_exists($caminho)) {
		$culpa[] = array(
			$arquivo . ' não existe',
			'Módulo de ' . mb_strtolower($def['rotulo']) . '.',
			'Copie o arquivo ' . $arquivo . ' do pacote novo.');
		continue;
	}
	$fonte = (string) file_get_contents($caminho);
	$faltam = array();
	foreach ($def['funcoes'] as $f) {
		if (!preg_match('/function\s+' . preg_quote($f, '/') . '\s*\(/', $fonte)) $faltam[] = $f . '()';
	}
	if ($faltam) {
		$culpa[] = array(
			$arquivo . ' é de versão antiga: faltam ' . count($faltam) . ' funções',
			implode(', ', $faltam),
			'Copie o ' . $arquivo . ' do pacote novo por cima deste.');
	}
}

/* ------------------------------------------------------------------ *
 * 3. Tabela de arquivos com data — a prova visual do que ficou velho
 * ------------------------------------------------------------------ */
$esperados_inc = array('chamada.php', 'equipe.php', 'transferencias.php', 'database.php',
	'sidebar.php', 'tema.php', 'header.php', 'header2.php', 'footer.php',
	'chamada_categorias.php', 'auth_periodo.php', 'functions.php');

foreach ($esperados_inc as $nome) {
	$c = $raiz . '/inc/' . $nome;
	$linhas[] = array(
		'inc/' . $nome,
		file_exists($c) ? number_format(filesize($c), 0, ',', '.') . ' B' : '—',
		file_exists($c) ? date('d/m/Y H:i', filemtime($c)) : 'AUSENTE',
		file_exists($c),
	);
}

/* Data mais recente entre as telas: se as telas sao mais novas que os
 * includes, e a assinatura classica de copia parcial. */
$telas = array('alunos/index.php', 'turmas/index.php', 'turmas/processa/salvar_processa.php',
	'chamada/index.php', 'alunos/formulario.php');
$tela_mais_nova = 0;
foreach ($telas as $t) {
	$c = $raiz . '/' . $t;
	if (file_exists($c)) $tela_mais_nova = max($tela_mais_nova, filemtime($c));
}
$inc_mais_novo = 0;
foreach (array('chamada.php', 'equipe.php', 'transferencias.php') as $n) {
	$c = $raiz . '/inc/' . $n;
	if (file_exists($c)) $inc_mais_novo = max($inc_mais_novo, filemtime($c));
}
if ($tela_mais_nova && $inc_mais_novo && $tela_mais_nova > $inc_mais_novo + 60) {
	$avisos[] = 'As telas foram copiadas em ' . date('d/m/Y H:i', $tela_mais_nova)
		. ', mas os arquivos de inc em ' . date('d/m/Y H:i', $inc_mais_novo)
		. '. Isso é a marca de uma cópia parcial: as telas vieram, o inc ficou.';
}

/* ------------------------------------------------------------------ *
 * 4. Opcache
 * ------------------------------------------------------------------ */
$op_ativo = function_exists('opcache_get_status') && ini_get('opcache.enable');
$op_estado = null;
if ($op_ativo) {
	$op_estado = @opcache_get_status(false);
	if (is_array($op_estado)) {
		$val = ini_get('opcache.validate_timestamps');
		if (!$val) {
			$avisos[] = 'O opcache está com validate_timestamps desligado: o PHP NÃO percebe '
				. 'arquivo novo enquanto o Apache não for reiniciado. Use o botão abaixo ou reinicie o Apache.';
		}
	}
}

/* ------------------------------------------------------------------ *
 * 5. Ambiente
 * ------------------------------------------------------------------ */
$sqlsrv = extension_loaded('sqlsrv');
if (!$sqlsrv) {
	$culpa[] = array('Extensão sqlsrv não carregada',
		'O sistema fala com o SQL Server por sqlsrv.',
		'No WAMP: ícone > PHP > PHP extensions > marque php_sqlsrv e reinicie. Precisa ser Thread Safe (TS), x64, da mesma versão do PHP do Apache.');
}

/* Copia aninhada: ZIP extraido para uma subpasta em vez de substituir.
 * É o engano que faz a pessoa jurar que copiou e o erro continuar. */
$aninhadas = array();
foreach ((array) @scandir($raiz) as $nome) {
	if ($nome === '.' || $nome === '..' || $nome === 'inc') continue;
	$c = $raiz . '/' . $nome . '/inc/chamada.php';
	if (is_dir($raiz . '/' . $nome) && is_file($c)) $aninhadas[] = $nome . '/inc/chamada.php';
}
if ($aninhadas) {
	$culpa[] = array(
		'O ZIP foi extraído numa subpasta, não no lugar certo',
		'Encontrei: ' . implode(', ', $aninhadas) . '. Os arquivos novos estão dentro dessa subpasta e NENHUM arquivo do sistema foi substituído.',
		'Entre nessa subpasta, recorte a pasta inc que está lá dentro e cole em ' . $raiz . ', mandando substituir. Depois apague a subpasta.');
}

$pronto = !$culpa;

/* ------------------------------------------------------------------ *
 * Modo texto: /diagnostico.php?texto=1
 *
 * Devolve tudo em texto puro, para a pessoa selecionar, copiar e colar
 * num chamado. Sem HTML, sem precisar de print de tela.
 * ------------------------------------------------------------------ */
if (isset($_GET['texto'])) {
	header('Content-Type: text/plain; charset=utf-8');
	echo "DIAGNOSTICO DA INSTALACAO\n";
	echo 'Pasta: ' . $raiz . "\n";
	echo 'Data: ' . date('d/m/Y H:i') . "\n";
	echo 'PHP: ' . PHP_VERSION . ' | sqlsrv: ' . ($sqlsrv ? 'sim' : 'NAO') . "\n";
	echo 'Opcache: ' . ($op_ativo ? 'ativo' : 'inativo')
		. ' | revalida: ' . (ini_get('opcache.validate_timestamps') ? ini_get('opcache.revalidate_freq') . 's' : 'NAO') . "\n";
	echo str_repeat('=', 64) . "\n\n";

	echo ($pronto ? "RESULTADO: nenhum problema de arquivo\n" : 'RESULTADO: ' . count($culpa) . " problema(s)\n");
	foreach ($culpa as $i => $c) {
		echo "\n" . ($i + 1) . ') ' . $c[0] . "\n";
		echo '   ' . $c[1] . "\n";
		echo '   FAZER: ' . $c[2] . "\n";
	}
	foreach ($avisos as $a) echo "\nAVISO: " . $a . "\n";

	echo "\n" . str_repeat('-', 64) . "\n";
	echo "ARQUIVOS DE inc\n";
	foreach ($linhas as $l) {
		echo str_pad($l[0], 30) . str_pad($l[1], 14) . $l[2] . "\n";
	}
	if ($tela_mais_nova) echo "\nTela mais recente: " . date('d/m/Y H:i', $tela_mais_nova) . "\n";

	echo "\n" . str_repeat('-', 64) . "\n";
	echo "ULTIMAS LINHAS DE inc/chamada.php\n";
	if ($fonte_chamada !== null) {
		$ult = array_slice(explode("\n", str_replace("\r\n", "\n", $fonte_chamada)), -12);
		foreach ($ult as $l) echo '  ' . $l . "\n";
	} else {
		echo "  (arquivo ausente)\n";
	}

	echo "\n" . str_repeat('-', 64) . "\n";
	echo "ONDE O PHP ESTA PROCURANDO\n";
	echo 'ABSPATH do config: ';
	$cfg = $raiz . '/config.php';
	if (file_exists($cfg)) {
		$fc = (string) file_get_contents($cfg);
		if (preg_match("/define\s*\(\s*'ABSPATH'\s*,\s*(.+)\)/", $fc, $m)) echo trim($m[1]) . "\n";
		else echo "(nao definido explicitamente)\n";
		if (preg_match("/define\s*\(\s*'BASEURL'\s*,\s*'([^']*)'/", $fc, $m)) echo 'BASEURL: ' . $m[1] . "\n";
	} else {
		echo "(config.php ausente)\n";
	}
	echo 'Arquivo carregado por esta pagina: ' . __FILE__ . "\n";
	echo 'include_path: ' . get_include_path() . "\n";

	/* Outras copias do sistema por perto. Pega o engano mais comum de
	 * extracao: o ZIP foi extraido "para uma pasta propria", criando
	 * Chamada\inc-atualizado\inc em vez de substituir Chamada\inc.
	 * Por isso procura em pastas FILHAS, nao so nas irmas. */
	echo "\nOUTRAS COPIAS DE inc/chamada.php POR PERTO\n";
	$achou = false;
	$vizinhos = array();
	foreach ((array) @scandir(dirname($raiz)) as $nome) {
		if ($nome !== '.' && $nome !== '..') $vizinhos[] = dirname($raiz) . '/' . $nome;
	}
	foreach ((array) @scandir($raiz) as $nome) {
		if ($nome !== '.' && $nome !== '..' && $nome !== 'inc' && is_dir($raiz . '/' . $nome)) {
			$vizinhos[] = $raiz . '/' . $nome;
		}
	}
	foreach ($vizinhos as $pasta) {
		if (!is_dir($pasta)) continue;
		foreach (array('/inc/chamada.php', '/chamada.php') as $fim) {
			$c = $pasta . $fim;
			if (is_file($c) && realpath($c) !== realpath($raiz . '/inc/chamada.php')) {
				$achou = true;
				echo '  ' . $c . '  (' . number_format(filesize($c), 0, ',', '.') . ' B, '
					. date('d/m/Y H:i', filemtime($c)) . ")\n";
			}
		}
	}
	if (!$achou) echo "  nenhuma\n";
	else echo "\n  Se aparece uma pasta DENTRO de Chamada contendo inc, o ZIP foi\n"
		. "  extraido no lugar errado e nenhum arquivo foi substituido.\n";
	exit;
}
?>
<!doctype html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Diagnóstico da instalação</title>
<style>
*{box-sizing:border-box}
body{margin:0;font-family:system-ui,"Segoe UI",Roboto,sans-serif;background:#eef2f7;color:#243b53;line-height:1.6}
.faixa{max-width:920px;margin:0 auto;padding:28px 18px 60px}
h1{font-size:24px;margin:0 0 4px}
.sub{color:#627d98;font-size:13.5px;margin:0 0 20px}
.painel{background:#fff;border-radius:14px;padding:22px 26px;margin-bottom:18px;box-shadow:0 2px 10px rgba(16,42,67,.07)}
.painel h2{font-size:17px;margin:0 0 14px;padding-bottom:9px;border-bottom:2px solid #eef2f7}
.veredito{border-left:6px solid <?php echo $pronto ? '#127a4a' : '#b42318'; ?>}
.veredito strong{font-size:20px;color:<?php echo $pronto ? '#127a4a' : '#b42318'; ?>}
.problema{border:1px solid #f0c4c0;background:#fdf4f3;border-radius:10px;padding:15px 18px;margin-bottom:13px}
.problema b{display:block;color:#b42318;font-size:15px;margin-bottom:5px}
.problema .det{font-size:13.5px;color:#4a5c6e;margin-bottom:9px}
.problema .acao{background:#fff;border-left:3px solid #d64545;padding:9px 12px;border-radius:0 6px 6px 0;font-size:13.5px}
.aviso{background:#fff8e6;border-left:4px solid #f0b429;padding:12px 16px;border-radius:0 8px 8px 0;margin-bottom:11px;font-size:14px}
table{width:100%;border-collapse:collapse;font-size:13.5px}
th,td{text-align:left;padding:8px 10px;border-bottom:1px solid #f2f5f9}
th{background:#f7f9fc;color:#627d98;font-size:12px;text-transform:uppercase;letter-spacing:.4px}
td.falta{color:#b42318;font-weight:700}
code{background:#eef2f7;padding:2px 6px;border-radius:4px;font-size:13px;font-family:ui-monospace,Consolas,monospace}
pre{background:#1e293b;color:#e2e8f0;padding:13px 16px;border-radius:9px;overflow-x:auto;font-size:12.5px;margin:10px 0}
pre code{background:none;color:inherit;padding:0}
ol{padding-left:22px;margin:8px 0}
li{margin-bottom:8px}
.btn{display:inline-block;background:#2f6fed;color:#fff;padding:9px 18px;border-radius:8px;
     text-decoration:none;font-weight:600;font-size:14px;border:0;cursor:pointer}
.btn.cinza{background:#627d98}
.ok-linha{color:#127a4a;font-weight:600}
.rodape{text-align:center;color:#829ab1;font-size:12.5px;margin-top:22px}
</style>
</head>
<body>
<div class="faixa">

<h1>Diagnóstico da instalação</h1>
<p class="sub">Pasta <code><?php echo dg_h($raiz); ?></code> &middot; <?php echo dg_h(date('d/m/Y H:i')); ?>
   &middot; PHP <?php echo dg_h(PHP_VERSION); ?> &middot; sqlsrv <?php echo $sqlsrv ? 'carregado' : 'AUSENTE'; ?></p>

<?php if ($cache_limpo !== null): ?>
	<div class="painel"><span class="<?php echo $cache_limpo ? 'ok-linha' : ''; ?>">
	<?php echo $cache_limpo ? '✓ Cache do PHP limpo. Recarregue as telas do sistema.' : 'Não foi possível limpar o cache (opcache inativo). Reinicie o Apache.'; ?>
	</span></div>
<?php endif; ?>

<div class="painel veredito">
	<strong><?php echo $pronto ? 'Nenhum problema de arquivo encontrado' : count($culpa) . ' problema(s) encontrado(s)'; ?></strong>
	<p style="margin:8px 0 0;font-size:14.5px">
	<?php if ($pronto): ?>
		Os arquivos estão certos. Se alguma tela ainda mostra erro, é cache do PHP:
		use o botão mais abaixo ou reinicie o Apache.
	<?php else: ?>
		Cada item abaixo diz o arquivo exato e o que fazer. Este diagnóstico não
		depende de nenhum outro arquivo do sistema, então o resultado é confiável
		mesmo com a instalação quebrada.
	<?php endif; ?>
	</p>
</div>

<?php if ($culpa): ?>
<div class="painel">
	<h2>O que está causando o erro</h2>
	<?php foreach ($culpa as $c): ?>
	<div class="problema">
		<b><?php echo dg_h($c[0]); ?></b>
		<div class="det"><?php echo dg_h($c[1]); ?></div>
		<div class="acao"><strong>O que fazer:</strong> <?php echo dg_h($c[2]); ?></div>
	</div>
	<?php endforeach; ?>
</div>
<?php endif; ?>

<?php if ($avisos): ?>
<div class="painel">
	<h2>Atenção</h2>
	<?php foreach ($avisos as $a): ?><div class="aviso"><?php echo dg_h($a); ?></div><?php endforeach; ?>
</div>
<?php endif; ?>

<div class="painel">
	<h2>Data dos arquivos de <code>inc</code></h2>
	<p style="font-size:13.5px;color:#627d98;margin:0 0 12px">
		A data é a prova mais direta. Se as telas são de um dia e estes arquivos são
		de outro, a cópia foi parcial.</p>
	<table>
		<tr><th>Arquivo</th><th>Tamanho</th><th>Alterado em</th></tr>
		<?php foreach ($linhas as $l): ?>
		<tr>
			<td><?php echo dg_h($l[0]); ?></td>
			<td><?php echo dg_h($l[1]); ?></td>
			<td class="<?php echo $l[3] ? '' : 'falta'; ?>"><?php echo dg_h($l[2]); ?></td>
		</tr>
		<?php endforeach; ?>
	</table>
	<?php if ($tela_mais_nova): ?>
	<p style="font-size:13.5px;color:#627d98;margin:12px 0 0">
		Tela mais recente: <strong><?php echo dg_h(date('d/m/Y H:i', $tela_mais_nova)); ?></strong></p>
	<?php endif; ?>
</div>

<div class="painel">
	<h2>Cache do PHP (opcache)</h2>
	<p style="font-size:14px;margin:0 0 12px">
	<?php if (!$op_ativo): ?>
		O opcache não está ativo. O PHP lê o arquivo do disco a cada acesso.
	<?php else: ?>
		O opcache está <strong>ativo</strong>. Ele guarda o código compilado em memória:
		depois de copiar um arquivo, o Apache pode continuar usando a versão antiga.
		Revalidação: <?php echo ini_get('opcache.validate_timestamps') ? 'a cada ' . dg_h(ini_get('opcache.revalidate_freq')) . ' s' : '<strong>desligada</strong> (só reiniciando)'; ?>.
	<?php endif; ?>
	</p>
	<a class="btn" href="?limpar_cache=1">Limpar o cache do PHP</a>
	<a class="btn cinza" href="diagnostico.php">Conferir de novo</a>
</div>

<div class="painel">
	<h2>Como corrigir de uma vez</h2>
	<p style="font-size:14px">O caminho mais curto, se o problema é o <code>inc/chamada.php</code> antigo:</p>
	<ol style="font-size:14px">
		<li>Pare o Apache no WAMP.</li>
		<li>Copie a pasta <code>inc</code> <strong>inteira</strong> do pacote novo para
			<code><?php echo dg_h($raiz); ?>\inc</code>, mandando substituir tudo.</li>
		<li>Inicie o Apache.</li>
		<li>Recarregue esta página.</li>
	</ol>
	<p style="font-size:14px">Copiar a pasta <code>inc</code> inteira resolve os três erros de
		uma vez, porque todos vêm dela. Não precisa mexer nas telas.</p>
	<p style="font-size:14px">O que <strong>tem</strong> que existir no fim do <code>inc/chamada.php</code>:</p>
<pre><code>require_once(__DIR__ . '/transferencias.php');
require_once(__DIR__ . '/equipe.php');</code></pre>
	<p style="font-size:14px">Se essas duas linhas não estiverem lá, o arquivo é antigo.</p>
</div>

<div class="painel">
	<h2>Depois de resolver</h2>
	<p style="font-size:14px;margin:0">
		<strong>Apague este <code>diagnostico.php</code></strong> e use o
		<code>verifica_instalacao.php</code> no dia a dia — aquele também confere o
		banco de dados e exige login de administrador. Este aqui é só para a
		emergência, quando nem o sistema abre.</p>
</div>

<p class="rodape">Chamada Escolar &middot; diagnóstico de arquivos, sem acesso ao banco</p>
</div>
</body>
</html>
