<?php
/**
 * VERIFICA INSTALACAO
 *
 * Pagina de conferencia. Abre no navegador e diz, em portugues, o que
 * esta faltando ou atrasado nesta instalacao: arquivo, funcao e banco.
 *
 *     http://localhost/Chamada/verifica_instalacao.php
 *
 * Por que existe: quando se copia so as telas novas por cima de uma
 * instalacao antiga, o PHP reclama "Call to undefined function" numa
 * tela qualquer. O erro aponta a tela, mas o culpado e um include
 * antigo. Esta pagina aponta o arquivo certo.
 *
 * Nao depende do sistema estar funcionando: le os arquivos direto do
 * disco e so carrega o config.php para achar o banco. Por isso continua
 * util justamente quando o sistema esta quebrado.
 *
 * So o nivel 1 (administrador) ve o resultado.
 */

$raiz = __DIR__;

/* ------------------------------------------------------------------ *
 * Sessao e porteiro.
 *
 * O sistema usa nome de sessao proprio (derivado de BASEURL), por isso
 * quem abre a sessao tem de ser o config.php: um session_start() solto
 * aqui criaria OUTRA sessao e o login nunca seria reconhecido.
 *
 * Carregar o config.php agora tambem evita que ele rode de novo quando
 * o inc/chamada.php for carregado la embaixo (require_once), o que
 * dispararia aviso de sessao ja iniciada.
 *
 * O porteiro usa $_SESSION direto, sem ch_admin(): essa funcao mora no
 * arquivo que pode estar atrasado, e o verificador nao pode depender
 * justamente do que esta conferindo.
 * ------------------------------------------------------------------ */
if (!file_exists($raiz . '/config.php')) {
	http_response_code(500);
	echo '<!doctype html><html lang="pt-BR"><meta charset="utf-8">'
		. '<title>Verificação da instalação</title>'
		. '<body style="font-family:system-ui,Segoe UI,sans-serif;background:#f4f6fb;padding:40px 20px;color:#243b53">'
		. '<div style="max-width:560px;margin:0 auto;background:#fff;border-radius:14px;padding:28px">'
		. '<h1 style="font-size:20px;margin:0 0 12px">Falta o config.php</h1>'
		. '<p style="line-height:1.6">O arquivo <code>config.php</code> não está em <code>'
		. htmlspecialchars($raiz, ENT_QUOTES, 'UTF-8') . '</code>. Copie o pacote inteiro do sistema '
		. 'para essa pasta e ajuste <code>DB_NAME</code>, <code>DB_USER</code>, <code>DB_PASSWORD</code> '
		. 'e <code>BASEURL</code>. Depois recarregue esta página.</p></div></body></html>';
	exit;
}

require_once $raiz . '/config.php';   /* abre a sessao certa e exige login */

$nivel = isset($_SESSION['usuarioNiveisAcessoId']) ? (int) $_SESSION['usuarioNiveisAcessoId'] : 0;

if ($nivel !== 1) {
	http_response_code(403);
	$volta = (defined('BASEURL') ? BASEURL : '/Chamada/') . 'index.php';
	echo '<!doctype html><html lang="pt-BR"><meta charset="utf-8">'
		. '<meta name="viewport" content="width=device-width,initial-scale=1">'
		. '<title>Verificação da instalação</title>'
		. '<body style="font-family:system-ui,Segoe UI,sans-serif;background:#f4f6fb;padding:40px 20px;color:#243b53">'
		. '<div style="max-width:560px;margin:0 auto;background:#fff;border-radius:14px;padding:28px;box-shadow:0 6px 24px rgba(0,0,0,.08)">'
		. '<h1 style="font-size:20px;margin:0 0 12px">Entre como administrador</h1>'
		. '<p style="line-height:1.6;margin:0 0 20px">Esta página mostra detalhes da instalação e só o '
		. '<strong>nível 1</strong> pode abrir. Faça login com o usuário administrador e volte aqui.</p>'
		. '<a href="' . $volta . '" style="display:inline-block;background:#2f6fed;color:#fff;padding:10px 18px;'
		. 'border-radius:8px;text-decoration:none;font-weight:600">Voltar ao sistema</a>'
		. '</div></body></html>';
	exit;
}

/* ------------------------------------------------------------------ *
 * Helpers locais
 * ------------------------------------------------------------------ */
function vi_h($s) { return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8'); }

$resultados = array();   /* blocos: titulo, itens, resumo */
$bloqueios  = 0;
$avisos     = 0;

function vi_item($estado, $titulo, $detalhe = '', $acao = '') {
	return array('estado' => $estado, 'titulo' => $titulo, 'detalhe' => $detalhe, 'acao' => $acao);
}

/* ================================================================== *
 * 1. AMBIENTE
 * ================================================================== */
$itens = array();

if (version_compare(PHP_VERSION, '8.1.0', '>=')) {
	$itens[] = vi_item('ok', 'PHP ' . PHP_VERSION);
} else {
	$itens[] = vi_item('erro', 'PHP ' . PHP_VERSION . ' é antigo', 'O sistema foi escrito para PHP 8.1 ou mais novo.', 'Atualize o PHP do WAMP.');
	$bloqueios++;
}

if (extension_loaded('sqlsrv')) {
	$ext = new ReflectionExtension('sqlsrv');
	$itens[] = vi_item('ok', 'Extensão sqlsrv ' . $ext->getVersion());
} else {
	$itens[] = vi_item('erro', 'Extensão sqlsrv não carregada',
		'O sistema fala com o SQL Server por sqlsrv. Sem ela, nada conecta.',
		'No WAMP: menu do ícone > PHP > PHP extensions > marque php_sqlsrv e reinicie. Use a versão Thread Safe (TS), mesma arquitetura (x64) e mesmo PHP do Apache.');
	$bloqueios++;
}

if (is_writable($raiz)) {
	$itens[] = vi_item('aviso', 'A pasta do sistema tem permissão de escrita', 'Não impede o uso. Em produção o ideal é somente leitura.', '');
	$avisos++;
} else {
	$itens[] = vi_item('ok', 'Pasta do sistema somente leitura');
}

$resultados[] = array('titulo' => 'Ambiente', 'itens' => $itens);

/* ================================================================== *
 * 2. ARQUIVOS: faltando e atrasado
 * ================================================================== */
$itens     = array();
$manifesto = $raiz . '/inc/manifesto.php';

if (!file_exists($manifesto)) {
	$itens[] = vi_item('erro', 'inc/manifesto.php não encontrado',
		'É a lista de conferência dos arquivos. Sem ele não dá para saber o que está atrasado.',
		'Copie a pasta inc inteira do pacote novo.');
	$bloqueios++;
} else {
	$esperados = require $manifesto;
	$faltando  = array();
	$atrasados = array();
	$apoio     = array();
	$certos    = 0;

	foreach ($esperados as $rel => $info) {
		list($hash, $bytes, $critico) = $info;
		$caminho = $raiz . '/' . $rel;

		if (!file_exists($caminho)) {
			$faltando[] = $rel;
			continue;
		}
		if (hash_file('sha256', $caminho) === $hash) {
			$certos++;
			continue;
		}
		if ($critico) $atrasados[] = $rel;
		else          $apoio[]     = $rel;
	}

	if ($faltando) {
		sort($faltando);
		$itens[] = vi_item('erro', count($faltando) . ' arquivo(s) faltando',
			implode(', ', $faltando),
			'Copie esses arquivos do pacote novo. Se for tela nova sem o include novo, o erro aparece como "undefined function".');
		$bloqueios++;
	}

	if ($atrasados) {
		sort($atrasados);
		$itens[] = vi_item('erro', count($atrasados) . ' arquivo(s) de código em versão diferente',
			implode(', ', $atrasados),
			'ESTES SÃO OS CULPADOS de "Call to undefined function". Copie por cima, exatamente esses, do pacote novo. Depois reinicie o Apache.');
		$bloqueios++;
	}

	if ($apoio) {
		sort($apoio);
		$itens[] = vi_item('aviso', count($apoio) . ' arquivo(s) de apoio diferentes',
			implode(', ', $apoio),
			'SQL, texto, estilo ou script. Não derruba o sistema, mas copie para ficar tudo na mesma versão.');
		$avisos++;
	}

	if (!$faltando && !$atrasados) {
		$itens[] = vi_item('ok', $certos . ' arquivo(s) conferidos, todos na versão certa');
	}

	/* config.php e editado por voce: confere so se existe. */
	$itens[] = file_exists($raiz . '/config.php')
		? vi_item('ok', 'config.php presente', 'Não é conferido por hash: é o arquivo que você edita (senha do banco, pasta).')
		: vi_item('erro', 'config.php não encontrado', '', 'Copie e ajuste DB_NAME, DB_USER, DB_PASSWORD e BASEURL.');
	if (!file_exists($raiz . '/config.php')) $bloqueios++;
}

$resultados[] = array('titulo' => 'Arquivos', 'itens' => $itens);

/* ================================================================== *
 * 3. FUNCOES: o include carrega inteiro?
 * ================================================================== */
$itens = array();

$funcoes = array(
	'inc/chamada.php' => array(
		'ch_db', 'ch_q', 'ch_rows', 'ch_row', 'ch_val', 'ch_exec', 'ch_afetadas', 'ch_insert_id',
		'ch_uid', 'ch_nivel', 'ch_admin', 'ch_diretor', 'ch_professor', 'ch_gestor',
		'ch_unidades_ids', 'ch_turmas_ids', 'ch_filtro_turma', 'ch_pode_unidade', 'ch_pode_turma',
		'ch_aluno', 'ch_pode_aluno', 'ch_pode_aluno_atual', 'ch_exige_aluno_atual',
		'ch_csrf_token', 'ch_csrf_campo', 'ch_csrf_ok', 'ch_flash', 'ch_bloqueio_chamada',
		'ch_feriados_do_periodo', 'ch_turma_equipe_texto',
	),
	'inc/transferencias.php' => array(
		'tr_vinculos', 'tr_vinculos_no_ano', 'tr_turma_na_data', 'tr_filtro_na_data',
		'tr_pendente', 'tr_transferencia', 'tr_historico_aluno', 'tr_situacao_badge',
		'tr_pendentes_recebidas', 'tr_unidades_destino', 'tr_turmas_destino', 'tr_data_minima',
	),
	'inc/equipe.php' => array(
		'eq_turnos', 'eq_turno_rotulo', 'eq_papel_rotulo', 'eq_validar_equipe',
		'eq_tem_saude', 'eq_tem_alergia', 'eq_saude_resumo', 'eq_caixa_saude',
	),
);

$carregou = false;
$erro_carga = '';

if (file_exists($raiz . '/inc/chamada.php')) {
	/* O include pode morrer num require de arquivo faltando. */
	try {
		require_once $raiz . '/inc/chamada.php';
		$carregou = true;
	} catch (Throwable $e) {
		$erro_carga = $e->getMessage();
	}
}

if (!$carregou) {
	$itens[] = vi_item('erro', 'inc/chamada.php não carregou',
		$erro_carga !== '' ? $erro_carga : 'Arquivo ausente.',
		'Resolva o bloco Arquivos acima primeiro: ele diz o que copiar.');
	$bloqueios++;
} else {
	foreach ($funcoes as $arquivo => $lista) {
		$ausentes = array();
		foreach ($lista as $f) if (!function_exists($f)) $ausentes[] = $f . '()';

		if (!$ausentes) {
			$itens[] = vi_item('ok', $arquivo . ': ' . count($lista) . ' funções conferidas');
		} else {
			$itens[] = vi_item('erro', $arquivo . ': ' . count($ausentes) . ' função(ões) não definida(s)',
				implode(', ', $ausentes),
				file_exists($raiz . '/' . $arquivo)
					? 'O arquivo existe mas é de versão antiga. Copie o ' . $arquivo . ' novo por cima e reinicie o Apache.'
					: 'Copie o arquivo ' . $arquivo . ' do pacote novo.');
			$bloqueios++;
		}
	}

	/* A causa classica: chamada.php antigo nao puxa os modulos novos. */
	foreach (array('inc/transferencias.php', 'inc/equipe.php') as $modulo) {
		if (!file_exists($raiz . '/' . $modulo)) continue;
		$fonte = (string) file_get_contents($raiz . '/inc/chamada.php');
		$nome  = basename($modulo);
		if (strpos($fonte, $nome) === false) {
			$itens[] = vi_item('erro', 'inc/chamada.php não carrega o ' . $nome,
				'O arquivo ' . $nome . ' está na pasta, mas o chamada.php é antigo e nunca o inclui. É exatamente isso que produz "Call to undefined function eq_..." ou "tr_...".',
				'Copie o inc/chamada.php novo por cima e reinicie o Apache.');
			$bloqueios++;
		}
	}
}

$resultados[] = array('titulo' => 'Funções', 'itens' => $itens);

/* ================================================================== *
 * 4. BANCO
 * ================================================================== */
$itens = array();

if (!$carregou || !function_exists('sqlsrv_connect')) {
	$itens[] = vi_item('aviso', 'Conferência do banco pulada',
		'Precisa do sqlsrv carregado e do inc/chamada.php funcionando.',
		'Resolva os blocos acima e recarregue esta página.');
	$avisos++;
} else {
	$conn = null;
	try { $conn = ch_db(); } catch (Throwable $e) { $conn = null; }

	if (!$conn) {
		$det = '';
		if (function_exists('sqlsrv_errors')) {
			$erros = sqlsrv_errors();
			if (is_array($erros)) {
				$partes = array();
				foreach ($erros as $e) $partes[] = '[' . $e['SQLSTATE'] . '] ' . $e['message'];
				$det = implode(' | ', $partes);
			}
		}
		$itens[] = vi_item('erro', 'Não conectou no banco', $det,
			'Confira DB_NAME, DB_USER, DB_PASSWORD e DB_HOST no config.php. Depois confira se o serviço do SQL Server está rodando e se o TCP/IP está habilitado na porta 1433 (SQL Server Configuration Manager).');
		$bloqueios++;
	} else {
		$itens[]  = vi_item('ok', 'Conectado em ' . (defined('DB_NAME') ? DB_NAME : '?'));

		/* Consulta tudo de uma vez e compara em memoria. */
		$tem_tabela = array();
		$tem_coluna = array();
		$tem_indice = array();
		$tem_view   = array();

		$rs = sqlsrv_query($conn, "SELECT name FROM sys.tables");
		if ($rs) while ($r = sqlsrv_fetch_array($rs, SQLSRV_FETCH_NUMERIC)) $tem_tabela[] = $r[0];

		$rs = sqlsrv_query($conn, "SELECT t.name + '.' + c.name FROM sys.columns c JOIN sys.tables t ON t.object_id = c.object_id");
		if ($rs) while ($r = sqlsrv_fetch_array($rs, SQLSRV_FETCH_NUMERIC)) $tem_coluna[] = $r[0];

		$rs = sqlsrv_query($conn, "SELECT name FROM sys.indexes WHERE name IS NOT NULL");
		if ($rs) while ($r = sqlsrv_fetch_array($rs, SQLSRV_FETCH_NUMERIC)) $tem_indice[] = $r[0];

		$rs = sqlsrv_query($conn, "SELECT name FROM sys.views");
		if ($rs) while ($r = sqlsrv_fetch_array($rs, SQLSRV_FETCH_NUMERIC)) $tem_view[] = $r[0];

		$tabelas = array('TB_UNIDADES', 'TB_USUARIOS', 'TB_ACESSO', 'TB_PERIODOS', 'TB_TURMAS',
			'TB_TURMA_PROFESSORES', 'TB_ALUNOS', 'TB_FERIADOS', 'TB_CHAMADA', 'TB_OBSERVACOES',
			'TB_RELATORIOS_ANUAIS', 'TB_CHAMADA_RETIFICACOES', 'TB_TRANSFERENCIAS');

		$falta = array_values(array_diff($tabelas, $tem_tabela));
		if ($falta) {
			$itens[] = vi_item('erro', 'Tabela(s) faltando: ' . implode(', ', $falta),
				'', 'Rode sql_inicial/INSTALACAO_COMPLETA.sql no banco (cria tudo). Se a base já tem dados, rode só a atualização que falta.');
			$bloqueios++;
		} else {
			$itens[] = vi_item('ok', count($tabelas) . ' tabelas conferidas');
		}

		/* Colunas por rodada, para dizer QUAL script rodar. */
		$rodadas = array(
			array(
				'nome'   => 'Transferência de alunos',
				'script' => 'sql_inicial/ATUALIZAR_TRANSFERENCIAS.sql',
				'cols'   => array('TB_ALUNOS.DATA_MATRICULA'),
				'objs'   => array('TB_TRANSFERENCIAS', 'VW_ALUNO_VINCULO', 'UX_TRANSFER_PENDENTE'),
			),
			array(
				'nome'   => 'Equipe por turno e saúde do aluno',
				'script' => 'sql_inicial/ATUALIZAR_EQUIPE_SAUDE.sql',
				'cols'   => array('TB_USUARIOS.TURNO', 'TB_PERIODOS.TURNO', 'TB_TURMA_PROFESSORES.TURNO',
					'TB_TURMA_PROFESSORES.DATA_INICIO', 'TB_TURMA_PROFESSORES.DATA_FIM',
					'TB_TURMA_PROFESSORES.ID_SUBSTITUIDO', 'TB_TURMA_PROFESSORES.MOTIVO',
					'TB_ALUNOS.ALERGIAS', 'TB_ALUNOS.RESTRICOES_ALIMENTARES', 'TB_ALUNOS.MEDICACAO'),
				'objs'   => array('UX_TURMAPROF_RESP_TURNO', 'IX_TURMAPROF_SUBSTITUTO'),
			),
		);

		foreach ($rodadas as $rodada) {
			$falta_c = array_values(array_diff($rodada['cols'], $tem_coluna));
			$falta_o = array();
			foreach ($rodada['objs'] as $o) {
				if (!in_array($o, $tem_tabela, true) && !in_array($o, $tem_view, true) && !in_array($o, $tem_indice, true)) {
					$falta_o[] = $o;
				}
			}
			$falta_tudo = array_merge($falta_c, $falta_o);

			if (!$falta_tudo) {
				$itens[] = vi_item('ok', $rodada['nome'] . ': banco atualizado');
			} else {
				$itens[] = vi_item('erro', $rodada['nome'] . ': banco NÃO atualizado',
					'Falta: ' . implode(', ', $falta_tudo),
					'Rode ' . $rodada['script'] . ' no SQL Server Management Studio. Ele pode ser executado mais de uma vez sem estragar nada.');
				$bloqueios++;
			}
		}

		/* Papel SUBSTITUTO precisa do CHECK novo. */
		/* Dois cuidados nesta consulta:
		 *
		 * 1. CAST porque definition e nvarchar(max), e o sqlsrv devolve
		 *    tipo max como stream, nao como texto.
		 * 2. O usuario do banco normalmente NAO tem VIEW DEFINITION. Sem
		 *    essa permissao o SQL Server mostra a linha da constraint mas
		 *    devolve definition = NULL. Isso e o comportamento correto e
		 *    mais seguro, nao e defeito: por isso NULL aqui nao vira erro.
		 *    As colunas e o indice conferidos acima ja provam que a
		 *    atualizacao rodou. */
		$rs = sqlsrv_query($conn, "SELECT CAST(definition AS nvarchar(4000)) FROM sys.check_constraints WHERE name = 'CK_TURMAPROF_PAPEL'");
		$r  = $rs ? sqlsrv_fetch_array($rs, SQLSRV_FETCH_NUMERIC) : null;

		if (!$r) {
			$itens[] = vi_item('erro', 'Constraint CK_TURMAPROF_PAPEL não existe',
				'Ela é quem limita os papéis da equipe.',
				'Rode sql_inicial/ATUALIZAR_EQUIPE_SAUDE.sql.');
			$bloqueios++;
		} elseif ($r[0] === null) {
			$itens[] = vi_item('ok', 'Constraint CK_TURMAPROF_PAPEL presente',
				'O texto dela não é legível para o usuário do banco (sem VIEW DEFINITION, o que é normal). As colunas conferidas acima já confirmam a atualização.');
		} elseif (strpos(strtoupper($r[0]), 'SUBSTITUTO') === false) {
			$itens[] = vi_item('erro', 'CK_TURMAPROF_PAPEL ainda não aceita SUBSTITUTO',
				'Cadastrar substituto vai dar erro de constraint.',
				'Rode sql_inicial/ATUALIZAR_EQUIPE_SAUDE.sql.');
			$bloqueios++;
		} else {
			$itens[] = vi_item('ok', 'Papel SUBSTITUTO aceito pelo banco');
		}

		/* Dados minimos para o sistema abrir. */
		$contagens = array(
			'TB_UNIDADES' => 'unidade(s)',
			'TB_USUARIOS' => 'usuário(s)',
			'TB_PERIODOS' => 'período(s)',
			'TB_TURMAS'   => 'turma(s)',
			'TB_ALUNOS'   => 'aluno(s)',
		);
		foreach ($contagens as $tab => $rotulo) {
			if (!in_array($tab, $tem_tabela, true)) continue;
			$rs = sqlsrv_query($conn, "SELECT COUNT(*) FROM dbo.{$tab}");
			if (!$rs) continue;
			$r = sqlsrv_fetch_array($rs, SQLSRV_FETCH_NUMERIC);
			$n = (int) $r[0];
			if ($n === 0 && $tab !== 'TB_ALUNOS') {
				$itens[] = vi_item('aviso', "Nenhuma {$rotulo} cadastrada", '', 'Cadastre pelo sistema antes de usar.');
				$avisos++;
			} else {
				$itens[] = vi_item('ok', $n . ' ' . $rotulo);
			}
		}
	}
}

$resultados[] = array('titulo' => 'Banco de dados', 'itens' => $itens);

/* ================================================================== *
 * Saida
 * ================================================================== */
$pronto = ($bloqueios === 0);
$titulo = $pronto ? 'Instalação conferida' : 'Encontrei ' . $bloqueios . ' problema(s)';
$cor    = $pronto ? '#127a4a' : '#b42318';
$volta  = defined('BASEURL') ? BASEURL : '/Chamada/';
?>
<!doctype html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Verificação da instalação</title>
<style>
	*{box-sizing:border-box}
	body{margin:0;font-family:system-ui,"Segoe UI",Roboto,sans-serif;background:#f4f6fb;color:#243b53;line-height:1.55}
	.faixa{max-width:900px;margin:0 auto;padding:28px 18px 60px}
	h1{font-size:24px;margin:0 0 6px}
	.sub{color:#627d98;font-size:14px;margin:0 0 22px}
	.painel{background:#fff;border-radius:14px;padding:22px;margin:0 0 18px;box-shadow:0 2px 10px rgba(16,42,67,.06)}
	.painel h2{font-size:16px;margin:0 0 14px;padding-bottom:10px;border-bottom:2px solid #eef2f7}
	.veredito{border-left:6px solid <?php echo $cor; ?>}
	.veredito strong{color:<?php echo $cor; ?>;font-size:19px}
	ul{list-style:none;margin:0;padding:0}
	li{padding:11px 0 11px 30px;position:relative;border-bottom:1px solid #f2f5f9;font-size:14px}
	li:last-child{border-bottom:0}
	li::before{position:absolute;left:0;top:10px;font-weight:700;font-size:15px}
	li.ok::before{content:"✓";color:#127a4a}
	li.erro::before{content:"✕";color:#b42318}
	li.aviso::before{content:"!";color:#b26a00}
	.det{display:block;color:#627d98;font-size:13px;margin-top:3px;word-break:break-word}
	.acao{display:block;background:#fff8e6;border-left:3px solid #f0b429;padding:8px 11px;margin-top:7px;font-size:13px;border-radius:0 6px 6px 0}
	.erro .acao{background:#fdf0ef;border-left-color:#e0655b}
	.passos{background:#eef4ff;border-radius:10px;padding:16px 18px;margin-top:6px;font-size:14px}
	.passos ol{margin:8px 0 0;padding-left:22px}
	.passos li{border:0;padding:5px 0;list-style:decimal}
	.passos li::before{content:none}
	code{background:#eef2f7;padding:1px 6px;border-radius:4px;font-size:13px}
	.rodape{text-align:center;margin-top:24px}
	.rodape a{display:inline-block;background:#2f6fed;color:#fff;padding:10px 20px;border-radius:8px;text-decoration:none;font-weight:600;font-size:14px}
</style>
</head>
<body>
<div class="faixa">

	<h1>Verificação da instalação</h1>
	<p class="sub">Chamada Escolar &middot; conferido em <?php echo vi_h(date('d/m/Y H:i')); ?> &middot; pasta <code><?php echo vi_h($raiz); ?></code></p>

	<div class="painel veredito">
		<strong><?php echo vi_h($titulo); ?></strong>
		<p style="margin:8px 0 0;font-size:14px">
		<?php if ($pronto): ?>
			Arquivos, funções e banco estão na versão certa.
			<?php if ($avisos): ?>Há <?php echo (int) $avisos; ?> aviso(s) abaixo, nenhum impede o uso.<?php endif; ?>
		<?php else: ?>
			Cada item com ✕ abaixo traz o que fazer. Resolva de cima para baixo:
			geralmente copiar os arquivos apontados resolve todos os erros de uma vez.
		<?php endif; ?>
		</p>
	</div>

	<?php foreach ($resultados as $bloco): ?>
	<div class="painel">
		<h2><?php echo vi_h($bloco['titulo']); ?></h2>
		<ul>
		<?php foreach ($bloco['itens'] as $i): ?>
			<li class="<?php echo vi_h($i['estado']); ?>">
				<?php echo vi_h($i['titulo']); ?>
				<?php if ($i['detalhe'] !== ''): ?><span class="det"><?php echo vi_h($i['detalhe']); ?></span><?php endif; ?>
				<?php if ($i['acao'] !== ''): ?><span class="acao"><?php echo vi_h($i['acao']); ?></span><?php endif; ?>
			</li>
		<?php endforeach; ?>
		</ul>
	</div>
	<?php endforeach; ?>

	<?php if (!$pronto): ?>
	<div class="painel">
		<h2>Como atualizar sem deixar arquivo velho</h2>
		<div class="passos">
			<strong>O jeito que evita esse problema:</strong>
			<ol>
				<li>No WAMP, pare o Apache.</li>
				<li>Renomeie <code>C:\wamp64\www\Chamada</code> para <code>Chamada-antigo</code>.</li>
				<li>Crie uma pasta <code>Chamada</code> nova e extraia <strong>o pacote inteiro</strong> dentro dela.</li>
				<li>Copie o <code>config.php</code> da pasta antiga para a nova, ou refaça os ajustes:
					<code>DB_NAME</code>, <code>DB_USER</code>, <code>DB_PASSWORD</code>, <code>BASEURL</code>.</li>
				<li>Rode no Management Studio os scripts de <code>sql_inicial\</code> que esta página apontar como faltando.</li>
				<li>Inicie o Apache e recarregue esta página.</li>
			</ol>
			<p style="margin:12px 0 0">Copiar só as telas por cima não funciona: as telas novas chamam funções
			que moram nos arquivos de <code>inc\</code>. Se o <code>inc\</code> fica antigo, aparece
			<em>Call to undefined function</em>.</p>
		</div>
	</div>
	<?php endif; ?>

	<div class="rodape"><a href="<?php echo vi_h($volta); ?>index.php">Voltar ao sistema</a></div>
</div>
</body>
</html>
