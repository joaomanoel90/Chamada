<?php
/* ============================================================================
   MÓDULO CHAMADA DE ALUNOS — funções compartilhadas
   Sistema de Chamada de Alunos / Prefeitura Municipal de Angatuba

   Requer que config.php e DBAPI já tenham sido carregados.
   Reaproveita TB_UNIDADES, TB_USUARIOS e TB_ACESSO do sistema.
   ============================================================================ */

if (!defined('ABSPATH')) {
    require_once(dirname(__DIR__) . '/config.php');
}
if (!function_exists('open_database')) {
    require_once(DBAPI);
}

date_default_timezone_set('America/Sao_Paulo');

/* ------------------------------------------------------------ banco de dados */

/** Conexão única do módulo (reaproveita a $conn já aberta, se houver). */
function ch_db()
{
    global $conn;
    if (empty($conn)) {
        $conn = open_database();
    }
    if ($conn === false) {
        error_log('Chamada: falha ao conectar. ' . print_r(sqlsrv_errors(), true));
        die('Não foi possível conectar ao banco de dados.');
    }
    return $conn;
}

/** Executa uma consulta parametrizada. Devolve o statement ou false. */
function ch_q($sql, $params = [])
{
    $stmt = sqlsrv_query(ch_db(), $sql, $params);
    if ($stmt === false) {
        error_log('Chamada SQL: ' . $sql . ' | ' . print_r(sqlsrv_errors(), true));
    }
    return $stmt;
}

/** Todas as linhas como array associativo. */
function ch_rows($sql, $params = [])
{
    $stmt = ch_q($sql, $params);
    if ($stmt === false) return [];
    $out = [];
    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $out[] = $row;
    }
    sqlsrv_free_stmt($stmt);
    return $out;
}

/** Primeira linha ou null. */
function ch_row($sql, $params = [])
{
    $rows = ch_rows($sql, $params);
    return $rows ? $rows[0] : null;
}

/** Valor escalar da primeira coluna, ou null. */
function ch_val($sql, $params = [])
{
    $row = ch_row($sql, $params);
    if (!$row) return null;
    return reset($row);
}

/** INSERT/UPDATE/DELETE. Devolve true/false. */
function ch_exec($sql, $params = [])
{
    $stmt = ch_q($sql, $params);
    if ($stmt === false) return false;
    sqlsrv_free_stmt($stmt);
    return true;
}

/* ------------------------------------------------------------------ sessão */

function ch_uid()
{
    return (int)($_SESSION['usuarioId'] ?? 0);
}

function ch_nivel()
{
    return (int)($_SESSION['usuarioNiveisAcessoId'] ?? 0);
}

/** Nível 1 — administração da rede: enxerga todas as unidades. */
function ch_admin()
{
    return ch_nivel() === 1;
}

/** Nível 2 — diretor: enxerga tudo da(s) unidade(s) a que está vinculado. */
function ch_diretor()
{
    return ch_nivel() === 2;
}

/** Nível 3 — professor: enxerga apenas as turmas em que está na equipe. */
function ch_professor()
{
    return ch_nivel() === 3;
}

/** Níveis 1 e 2 mantêm cadastros. O alcance de cada um é dado por
 *  ch_unidades_ids(): admin = rede toda, diretor = somente sua unidade. */
function ch_gestor()
{
    return in_array(ch_nivel(), [1, 2], true);
}

/** Interrompe a tela se o usuário não for da gestão. */
function ch_require_gestor()
{
    if (!ch_gestor()) {
        ch_flash('Acesso restrito à gestão.', 'danger');
        header('Location: ' . BASEURL . 'index.php');
        exit;
    }
}

/** Só o nível 1 administra usuários e vínculos da rede. */
function ch_require_admin()
{
    if (!ch_admin()) {
        ch_flash('Acesso restrito à administração da rede.', 'danger');
        header('Location: ' . BASEURL . 'index.php');
        exit;
    }
}

/* ------------------------------------------------------- controle de acesso */

/** IDs de unidade do usuário.
 *  Nível 1 = todas; níveis 2 e 3 = apenas as vinculadas em TB_ACESSO. */
function ch_unidades_ids()
{
    static $cache = null;
    if ($cache !== null) return $cache;

    if (ch_admin()) {
        $rows = ch_rows('SELECT id FROM TB_UNIDADES ORDER BY unidade');
        $cache = array_map(function ($r) { return (int)$r['id']; }, $rows ?: []);
    } else {
        $rows = ch_rows(
            'SELECT id_unidade FROM TB_ACESSO WHERE id_usuario = ? AND ISNULL(ativo, 1) = 1',
            [ch_uid()]
        );
        $cache = array_map(function ($r) { return (int)$r['id_unidade']; }, $rows ?: []);
    }
    return $cache;
}

/** Turmas em que o usuário faz parte da equipe (vínculo ativo). */
function ch_turmas_ids()
{
    static $cache = null;
    if ($cache !== null) return $cache;

    $rows = ch_rows(
        'SELECT ID_TURMA FROM TB_TURMA_PROFESSORES WHERE ID_USUARIO = ? AND ATIVO = 1',
        [ch_uid()]
    );
    $cache = array_map(function ($r) { return (int)$r['ID_TURMA']; }, $rows ?: []);
    return $cache;
}

/** Filtro SQL para restringir uma consulta de TURMAS ao alcance do usuário.
 *  $alias = alias da TB_TURMAS na consulta (ex.: 't').
 *  Nível 1: sem filtro. Nível 2: unidades vinculadas. Nível 3: turmas da equipe.
 *  Devolve ['sql' => string, 'params' => array]. */
function ch_filtro_turma($alias = 't')
{
    if (ch_admin()) {
        return ['sql' => '', 'params' => []];
    }

    /* O vínculo com a turma é o que dá acesso ao professor: se a gestão o
       incluiu na equipe, ele alcança a turma mesmo sem vínculo em TB_ACESSO. */
    if (ch_professor()) {
        $ids = ch_turmas_ids();
        if (!$ids) return ['sql' => ' AND 1 = 0', 'params' => []];
        $ph = implode(',', array_fill(0, count($ids), '?'));
        return ['sql' => " AND {$alias}.ID IN ({$ph})", 'params' => $ids];
    }

    $ids = ch_unidades_ids();
    if (!$ids) return ['sql' => ' AND 1 = 0', 'params' => []];
    $ph = implode(',', array_fill(0, count($ids), '?'));
    return ['sql' => " AND {$alias}.ID_UNIDADE IN ({$ph})", 'params' => $ids];
}

/** Filtro por UNIDADE apenas (não considera equipe de turma).
 *  Mantido para telas que listam unidades, não turmas. */
function ch_filtro_unidade($alias = 't')
{
    if (ch_admin()) {
        return ['sql' => '', 'params' => []];
    }
    $ids = ch_unidades_ids();
    if (!$ids) {
        return ['sql' => ' AND 1 = 0', 'params' => []];
    }
    $ph = implode(',', array_fill(0, count($ids), '?'));
    return ['sql' => " AND {$alias}.ID_UNIDADE IN ({$ph})", 'params' => $ids];
}

function ch_pode_unidade($idUnidade)
{
    return in_array((int)$idUnidade, ch_unidades_ids(), true);
}

/** Unidades para selects de cadastro, já restritas ao alcance do usuário. */
function ch_unidades_opcoes()
{
    if (ch_admin()) {
        return ch_rows('SELECT id, unidade FROM TB_UNIDADES ORDER BY unidade');
    }
    $ids = ch_unidades_ids();
    if (!$ids) return [];
    $ph = implode(',', array_fill(0, count($ids), '?'));
    return ch_rows("SELECT id, unidade FROM TB_UNIDADES WHERE id IN ({$ph}) ORDER BY unidade", $ids);
}

/** Turma completa, com unidade e período. */
function ch_turma($idTurma)
{
    return ch_row(
        'SELECT t.ID, t.ID_UNIDADE, t.NOME, t.ANO_LETIVO, t.ID_PERIODO, t.ID_PROFESSOR,
                t.CAPACIDADE, t.ATIVO,
                u.unidade AS UNIDADE,
                p.NOME AS PERIODO, p.HORA_INICIO, p.HORA_FIM
         FROM TB_TURMAS t
         INNER JOIN TB_UNIDADES u ON u.id = t.ID_UNIDADE
         INNER JOIN TB_PERIODOS p ON p.ID = t.ID_PERIODO
         WHERE t.ID = ?',
        [(int)$idTurma]
    );
}

/** Equipe da turma: todos os professores ativos, regente primeiro. */
function ch_turma_professores($idTurma)
{
    return ch_rows(
        "SELECT tp.ID_USUARIO, tp.PAPEL, u.NOME
         FROM TB_TURMA_PROFESSORES tp
         INNER JOIN TB_USUARIOS u ON u.ID = tp.ID_USUARIO
         WHERE tp.ID_TURMA = ? AND tp.ATIVO = 1 AND ISNULL(u.ATIVO, 1) = 1
         ORDER BY CASE tp.PAPEL WHEN 'REGENTE' THEN 0 ELSE 1 END, u.NOME",
        [(int)$idTurma]
    );
}

/** Nomes da equipe em uma linha, para exibição em listagens. */
function ch_turma_equipe_texto($idTurma)
{
    $eq = ch_turma_professores($idTurma);
    if (!$eq) return '';
    $partes = [];
    foreach ($eq as $m) {
        $partes[] = $m['NOME'] . ($m['PAPEL'] === 'REGENTE' ? ' (regente)' : '');
    }
    return implode(', ', $partes);
}

/** O usuário faz parte da equipe desta turma? */
function ch_na_equipe($idTurma, $idUsuario = null)
{
    $idUsuario = $idUsuario === null ? ch_uid() : (int)$idUsuario;
    if ($idUsuario <= 0) return false;
    return (bool)ch_val(
        'SELECT ID FROM TB_TURMA_PROFESSORES WHERE ID_TURMA = ? AND ID_USUARIO = ? AND ATIVO = 1',
        [(int)$idTurma, $idUsuario]
    );
}

/** Acesso à turma: admin = todas; diretor = unidades vinculadas;
 *  professor = somente turmas em que está na equipe. */
function ch_pode_turma($idTurma)
{
    $t = ch_turma($idTurma);
    if (!$t) return false;
    if (ch_professor()) {
        return in_array((int)$idTurma, ch_turmas_ids(), true);
    }
    return ch_pode_unidade($t['ID_UNIDADE']);
}

/** Quem pode retificar uma chamada já confirmada: a gestão da unidade
 *  ou qualquer professor da equipe da turma. */
function ch_pode_retificar($idTurma)
{
    return ch_pode_turma($idTurma);
}

/** Aluno completo, com turma/unidade/período. */
function ch_aluno($idAluno)
{
    return ch_row(
        'SELECT a.ID, a.ID_TURMA, a.MATRICULA, a.NOME, a.RESPONSAVEL, a.TELEFONE, a.EMAIL, a.ATIVO,
                CONVERT(varchar(10), a.NASCIMENTO, 23)     AS NASCIMENTO,
                CONVERT(varchar(10), a.DATA_MATRICULA, 23) AS DATA_MATRICULA,
                t.NOME AS TURMA, t.ANO_LETIVO, t.ID_UNIDADE, t.ID_PERIODO,
                t.HORA_INICIO, t.HORA_FIM, t.PERIODO, t.UNIDADE
         FROM TB_ALUNOS a
         INNER JOIN (
            SELECT tt.ID, tt.NOME, tt.ANO_LETIVO, tt.ID_UNIDADE, tt.ID_PERIODO,
                   p.HORA_INICIO, p.HORA_FIM, p.NOME AS PERIODO, u.unidade AS UNIDADE
            FROM TB_TURMAS tt
            INNER JOIN TB_PERIODOS p ON p.ID = tt.ID_PERIODO
            INNER JOIN TB_UNIDADES u ON u.id = tt.ID_UNIDADE
         ) t ON t.ID = a.ID_TURMA
         WHERE a.ID = ?',
        [(int)$idAluno]
    );
}

function ch_pode_aluno($idAluno)
{
    $a = ch_aluno($idAluno);
    return $a && ch_pode_turma($a['ID_TURMA']);
}

/** Bloqueia o acesso a aluno fora do alcance do usuário. */
function ch_exige_aluno($idAluno)
{
    $a = ch_aluno($idAluno);
    if (!$a || !ch_pode_turma($a['ID_TURMA'])) {
        ch_flash('Aluno não encontrado ou fora do seu alcance.', 'danger');
        header('Location: ' . BASEURL . 'alunos/index.php');
        exit;
    }
    return $a;
}

/* ------------------------------------------------------------------ CSRF */

/** Token CSRF da sessão, criado no primeiro uso. */
function ch_csrf_token()
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/** Campo oculto pronto para colocar dentro do <form>. */
function ch_csrf_campo()
{
    return '<input type="hidden" name="csrf_token" value="' . ch_h(ch_csrf_token()) . '">';
}

/** Confere o token do POST. Devolve false (e avisa) em vez de encerrar, para
    quem chamou poder decidir o destino do redirecionamento. */
function ch_csrf_ok()
{
    $enviado = $_POST['csrf_token'] ?? '';
    if (!is_string($enviado) || $enviado === '' || !hash_equals(ch_csrf_token(), $enviado)) {
        ch_flash('O formulário expirou. Recarregue a página e tente de novo.', 'danger');
        return false;
    }
    return true;
}

/* --------------------------------------------------------------- CPF */

/** Mantém apenas os dígitos. */
function ch_digitos($s)
{
    return preg_replace('/\D+/', '', (string)$s);
}

/** 11 dígitos -> 000.000.000-00. Se não tiver 11, devolve o texto como veio.
    O login compara o CPF digitado (com máscara) com o que está gravado, por
    isso o cadastro precisa gravar sempre no mesmo formato. */
function ch_cpf_mask($s)
{
    $d = ch_digitos($s);
    if (strlen($d) !== 11) return trim((string)$s);
    return substr($d, 0, 3) . '.' . substr($d, 3, 3) . '.' . substr($d, 6, 3) . '-' . substr($d, 9, 2);
}

/** Rótulo do nível de acesso. */
function ch_nivel_nome($n)
{
    return [1 => 'Administrador', 2 => 'Diretor', 3 => 'Professor'][(int)$n] ?? 'Indefinido';
}

/* ------------------------------------------------------------ mensagens */

function ch_flash($texto, $tipo = 'success')
{
    $_SESSION['ch_flash'][] = ['texto' => $texto, 'tipo' => $tipo];
}

/** HTML dos avisos pendentes (Bootstrap alert). */
function ch_flash_html()
{
    if (empty($_SESSION['ch_flash'])) return '';
    $html = '';
    foreach ($_SESSION['ch_flash'] as $f) {
        $tipo = preg_replace('/[^a-z]/', '', (string)$f['tipo']);
        $html .= '<div class="alert alert-' . $tipo . ' alert-dismissible fade show" role="alert">'
               . ch_h($f['texto'])
               . '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fechar"></button>'
               . '</div>';
    }
    unset($_SESSION['ch_flash']);
    return $html;
}

/* ------------------------------------------------------------ formatação */

function ch_h($s)
{
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

/** '2026-11-05' -> '05/11/2026' */
function ch_data_br($iso)
{
    $iso = substr((string)$iso, 0, 10);
    if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $iso, $m)) return '';
    return $m[3] . '/' . $m[2] . '/' . $m[1];
}

/** Data por extenso curta: '05/11/2026 (quarta-feira)' */
function ch_data_longa($iso)
{
    $iso = substr((string)$iso, 0, 10);
    $ts = strtotime($iso);
    if (!$ts) return '';
    $dias = ['domingo', 'segunda-feira', 'terça-feira', 'quarta-feira', 'quinta-feira', 'sexta-feira', 'sábado'];
    return ch_data_br($iso) . ' (' . $dias[(int)date('w', $ts)] . ')';
}

function ch_hoje()
{
    return date('Y-m-d');
}

/** Valida e normaliza uma data ISO vinda de formulário. */
function ch_data_iso($valor, $padrao = null)
{
    $valor = trim((string)$valor);
    if (preg_match('/^(\d{2})\/(\d{2})\/(\d{4})$/', $valor, $m)) {
        $valor = $m[3] . '-' . $m[2] . '-' . $m[1];
    }
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $valor)) return $padrao;
    $p = explode('-', $valor);
    if (!checkdate((int)$p[1], (int)$p[2], (int)$p[0])) return $padrao;
    return $valor;
}

/** Idade em anos completos. */
function ch_idade($nascimento, $referencia = null)
{
    $n = strtotime(substr((string)$nascimento, 0, 10));
    if (!$n) return null;
    $ref = $referencia ? strtotime($referencia) : time();
    $anos = (int)date('Y', $ref) - (int)date('Y', $n);
    if (date('md', $ref) < date('md', $n)) $anos--;
    return $anos;
}

/** '3 anos e 2 meses' — útil na ficha e no relatório da creche. */
function ch_idade_texto($nascimento, $referencia = null)
{
    $n = new DateTime(substr((string)$nascimento, 0, 10));
    $r = $referencia ? new DateTime($referencia) : new DateTime('today');
    if ($n > $r) return '—';
    $d = $n->diff($r);
    $p = [];
    if ($d->y > 0) $p[] = $d->y . ($d->y == 1 ? ' ano' : ' anos');
    if ($d->m > 0) $p[] = $d->m . ($d->m == 1 ? ' mês' : ' meses');
    if (!$p) $p[] = $d->d . ($d->d == 1 ? ' dia' : ' dias');
    return implode(' e ', $p);
}

/** Iniciais para o avatar (primeiro e último nome). */
function ch_iniciais($nome)
{
    $nome = trim((string)$nome);
    if ($nome === '') return '?';
    $partes = preg_split('/\s+/u', $nome);
    $partes = array_values(array_filter($partes, function ($x) { return mb_strlen($x) > 2; }));
    if (!$partes) $partes = preg_split('/\s+/u', $nome);
    $ini = mb_substr($partes[0], 0, 1);
    if (count($partes) > 1) $ini .= mb_substr($partes[count($partes) - 1], 0, 1);
    return mb_strtoupper($ini);
}

/** Texto curto de "quando foi confirmado", para o cartão do aluno. */
function ch_confirma_texto($iso)
{
    if (!$iso) return '';
    $iso = (string)$iso;
    $dia = substr($iso, 0, 10);
    $hora = substr($iso, 11, 5);
    return ch_data_br($dia) . ($hora ? ' às ' . $hora : '');
}

/* ------------------------------------------------------- status da chamada */

function ch_status_lista()
{
    return [
        'P' => ['rotulo' => 'Presente',    'cor' => 'success',   'icone' => 'fa-check'],
        'F' => ['rotulo' => 'Falta',       'cor' => 'danger',    'icone' => 'fa-xmark'],
        'J' => ['rotulo' => 'Justificada', 'cor' => 'warning',   'icone' => 'fa-file-lines'],
        'A' => ['rotulo' => 'Atraso',      'cor' => 'info',      'icone' => 'fa-clock'],
    ];
}

function ch_status_rotulo($s)
{
    $l = ch_status_lista();
    return $l[$s]['rotulo'] ?? '—';
}

/* --------------------------------------------------- regras de bloqueio */

/**
 * Verifica se a chamada pode ser lançada naquela data para aquela turma.
 * Devolve null quando está liberado, ou uma string com o motivo do bloqueio.
 *
 * Ordem: data futura -> ano letivo -> feriado integral -> feriado parcial.
 */
function ch_bloqueio_chamada($dataIso, $turma)
{
    if (!$turma) return 'Turma não encontrada.';

    if ($dataIso > ch_hoje()) {
        return 'Não é possível lançar chamada em data futura.';
    }

    if ((int)substr($dataIso, 0, 4) !== (int)$turma['ANO_LETIVO']) {
        return 'A data escolhida não pertence ao ano letivo da turma (' . $turma['ANO_LETIVO'] . ').';
    }

    if (isset($turma['ATIVO']) && (int)$turma['ATIVO'] === 0) {
        return 'Esta turma está inativa e não aceita novos lançamentos.';
    }

    $feriado = ch_row(
        'SELECT DESCRICAO, TIPO, HORA_INICIO, HORA_FIM, ID_UNIDADE
         FROM TB_FERIADOS
         WHERE DATA = ? AND (ID_UNIDADE IS NULL OR ID_UNIDADE = ?)',
        [$dataIso, (int)$turma['ID_UNIDADE']]
    );

    if (!$feriado) return null;

    if ($feriado['TIPO'] === 'INTEGRAL') {
        return 'Feriado de dia inteiro: ' . $feriado['DESCRICAO'] . '.';
    }

    // Parcial: guarda a faixa SEM aula. Bloqueia se cruzar o período da turma.
    $cruza = $feriado['HORA_INICIO'] < $turma['HORA_FIM']
          && $feriado['HORA_FIM']    > $turma['HORA_INICIO'];

    if ($cruza) {
        return 'Feriado parcial (' . $feriado['DESCRICAO'] . '): sem aula das '
             . $feriado['HORA_INICIO'] . ' às ' . $feriado['HORA_FIM']
             . ', o que atinge o período ' . $turma['PERIODO'] . '.';
    }

    return null;
}

/** Feriados que atingem uma turma dentro de um intervalo (para avisos na tela). */
function ch_feriados_do_periodo($inicio, $fim, $idUnidade)
{
    return ch_rows(
        'SELECT ID, CONVERT(varchar(10), DATA, 23) AS DATA, DESCRICAO, TIPO, HORA_INICIO, HORA_FIM
         FROM TB_FERIADOS
         WHERE DATA >= ? AND DATA <= ? AND (ID_UNIDADE IS NULL OR ID_UNIDADE = ?)
         ORDER BY DATA',
        [$inicio, $fim, (int)$idUnidade]
    );
}

/* ------------------------------------------------------------ paginação */

/** Cabeçalho padrão das telas do módulo. */
function ch_cabecalho($titulo, $icone = 'fa-clipboard-user', $botaoHtml = '')
{
    echo '<header><div class="row align-items-center">'
       . '<div class="col-sm-7"><h2><i class="fas ' . ch_h($icone) . '"></i> ' . ch_h($titulo) . '</h2></div>'
       . '<div class="col-sm-5 text-sm-end">' . $botaoHtml . '</div>'
       . '</div></header><hr>';
    echo ch_flash_html();
}

/** Link absoluto respeitando BASEURL. */
function ch_url($caminho)
{
    return BASEURL . ltrim($caminho, '/');
}

/** Nome do mês em português (1-12). */
function ch_mes_nome($n)
{
    $meses = [1 => 'Janeiro', 'Fevereiro', 'Março', 'Abril', 'Maio', 'Junho',
              'Julho', 'Agosto', 'Setembro', 'Outubro', 'Novembro', 'Dezembro'];
    return $meses[(int)$n] ?? '';
}
