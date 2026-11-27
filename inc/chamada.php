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

require_once(__DIR__ . '/auth_security.php');
ch_auth_security_headers();
ch_auth_session_guard();
ch_auth_revalidar_usuario();

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

/**
 * UPDATE/DELETE que devolve QUANTAS linhas foram atingidas (-1 se falhou).
 * Use quando a cláusula WHERE carrega uma condição de estado e você precisa
 * saber se ganhou a corrida — por exemplo aceitar uma transferência que outro
 * usuário pode ter aceitado um instante antes:
 *   UPDATE ... WHERE ID = ? AND SITUACAO = 'PENDENTE'
 * ch_exec() devolve true mesmo quando nenhuma linha foi alterada.
 */
function ch_afetadas($sql, $params = [])
{
    $stmt = ch_q($sql, $params);
    if ($stmt === false) return -1;
    $n = sqlsrv_rows_affected($stmt);
    sqlsrv_free_stmt($stmt);
    return $n;
}

/**
 * INSERT que devolve o ID gerado, ou 0 se falhou.
 *
 * O SQL precisa trazer "OUTPUT INSERTED.ID" antes do VALUES. NÃO use
 * SELECT SCOPE_IDENTITY() em outra chamada: SCOPE_IDENTITY() só vale dentro do
 * mesmo lote do INSERT, e cada sqlsrv_query é um lote novo (com parâmetros o
 * driver ainda usa sp_executesql, que é outro escopo). Chamado separado, ele
 * devolve NULL no SQL Server — foi o que impediu o cadastro de turma.
 */
function ch_insert_id($sql, $params = [])
{
    $stmt = ch_q($sql, $params);
    if ($stmt === false) return 0;
    $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
    sqlsrv_free_stmt($stmt);
    return $row ? (int)reset($row) : 0;
}

/**
 * Auditoria genérica de operações sensíveis (quem, quando, o quê).
 * ID_USUARIO tem FK para TB_USUARIOS: a conta é desativada, nunca removida.
 *
 * Nunca deve quebrar a operação principal: se TB_AUDITORIA ainda não existe
 * ou o banco falha, registra apenas no error_log. A consulta à tabela é
 * cacheada por requisição para não repetir o teste a cada gravação.
 */
function ch_auditar($modulo, $acao, $registroTipo, $registroId, $detalhes = [])
{
    static $tabelaOk = null;
    global $conn;
    if (empty($conn)) return false;

    if ($tabelaOk === null) {
        $stmt = sqlsrv_query(
            $conn,
            "SELECT 1 FROM sys.objects WHERE object_id = OBJECT_ID(N'dbo.TB_AUDITORIA') AND type = 'U'"
        );
        if ($stmt === false) {
            error_log('Auditoria: falha ao verificar TB_AUDITORIA. ' . print_r(sqlsrv_errors(), true));
            $tabelaOk = false;
        } else {
            $tabelaOk = sqlsrv_has_rows($stmt);
            sqlsrv_free_stmt($stmt);
            if (!$tabelaOk) {
                error_log('Auditoria: TB_AUDITORIA não existe. Rode sql_inicial/ATUALIZAR_AUDITORIA.sql.');
            }
        }
    }
    if (!$tabelaOk) return false;

    $uid = function_exists('ch_uid') ? ch_uid() : (int)($_SESSION['usuarioId'] ?? 0);
    $nome = null;
    if ($uid > 0) {
        $stmt = sqlsrv_query($conn, 'SELECT NOME FROM dbo.TB_USUARIOS WHERE ID = ?', [$uid]);
        if ($stmt !== false) {
            $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
            if ($row) $nome = (string)$row['NOME'];
            sqlsrv_free_stmt($stmt);
        }
    }

    $ip = (string)($_SERVER['REMOTE_ADDR'] ?? '');
    if (!filter_var($ip, FILTER_VALIDATE_IP)) $ip = null;

    $json = json_encode(
        is_scalar($detalhes) ? ['valor' => $detalhes] : (array)$detalhes,
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE
    );
    if ($json === false) $json = '';
    $json = mb_substr($json, 0, 2000);

    $stmt = sqlsrv_query(
        $conn,
        'INSERT INTO dbo.TB_AUDITORIA
             (MODULO, ACAO, REGISTRO_TIPO, REGISTRO_ID, ID_USUARIO, USUARIO_NOME, IP, DETALHES, CRIADO_EM)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, GETDATE())',
        [
            mb_substr((string)$modulo, 0, 40),
            mb_substr((string)$acao, 0, 40),
            mb_substr((string)$registroTipo, 0, 40),
            mb_substr((string)$registroId, 0, 60),
            $uid > 0 ? $uid : null,
            $nome !== null ? mb_substr($nome, 0, 150) : null,
            $ip !== null ? mb_substr($ip, 0, 45) : null,
            $json
        ]
    );
    if ($stmt === false) {
        error_log('Auditoria: falha ao gravar. ' . print_r(sqlsrv_errors(), true));
        return false;
    }
    sqlsrv_free_stmt($stmt);
    return true;
}

/** Última mensagem de erro do banco, para diagnóstico. Vazio se não houve. */
function ch_erro_sql()
{
    if (!function_exists('sqlsrv_errors')) return '';
    $e = sqlsrv_errors();
    if (!$e) return '';
    $m = [];
    foreach ($e as $x) {
        if (!empty($x['message'])) $m[] = trim($x['message']);
    }
    return implode(' | ', array_unique($m));
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

/** Nível 4 — Secretaria de Educação: gestão escolar em toda a rede. */
function ch_secretaria_educacao()
{
    return ch_nivel() === 4;
}

/** Nível 5 — Secretário Escolar: cadastros da(s) unidade(s), sem lançar chamada. */
function ch_secretario_escolar()
{
    return ch_nivel() === 5;
}

/** Administração de rede: administrador do sistema e Secretaria de Educação. */
function ch_rede()
{
    return ch_admin() || ch_secretaria_educacao();
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

/** Administração escolar: níveis 1, 2 e 4 podem configurar unidades. */
function ch_gestor()
{
    return in_array(ch_nivel(), [1, 2, 4], true);
}

/** Gestão de alunos e transferências, incluindo a secretaria escolar. */
function ch_gestao_alunos()
{
    return ch_gestor() || ch_secretario_escolar();
}

/** Interrompe a tela se o usuário não puder gerenciar alunos. */
function ch_require_gestao_alunos()
{
    if (!ch_gestao_alunos()) {
        ch_flash('Acesso restrito à gestão escolar.', 'danger');
        header('Location: ' . BASEURL . 'index.php');
        exit;
    }
}

/** A secretaria escolar pode consultar turmas, mas não configurá-las. */
function ch_require_consulta_turmas()
{
    if (!ch_gestao_alunos()) {
        ch_flash('Acesso restrito à equipe escolar.', 'danger');
        header('Location: ' . BASEURL . 'index.php');
        exit;
    }
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

/** Interrompe a tela se o usuário não for da gestão da rede (nível 1 ou 4). */
function ch_require_rede()
{
    if (!ch_rede()) {
        ch_flash('Acesso restrito à gestão da rede.', 'danger');
        header('Location: ' . BASEURL . 'index.php');
        exit;
    }
}

/** Só o nível 1 administra contas de usuário. */
function ch_require_admin()
{
    if (!ch_admin()) {
        ch_flash('Acesso restrito à administração da rede.', 'danger');
        header('Location: ' . BASEURL . 'index.php');
        exit;
    }
}

/** Quem pode gerenciar os vínculos de unidade de um usuário específico? */
function ch_pode_gerenciar_vinculos_usuario($idUsuario)
{
    $idUsuario = (int)$idUsuario;
    if ($idUsuario <= 0) {
        return false;
    }
    if (ch_admin()) {
        return true;
    }
    if (!ch_secretaria_educacao()) {
        return false;
    }
    $nivel = (int)ch_val('SELECT NIVEL FROM TB_USUARIOS WHERE ID = ?', [$idUsuario]);
    return in_array($nivel, [2, 5], true);
}

/** Todos os perfis escolares podem consultar relatórios no próprio escopo. */
function ch_pode_ver_relatorios()
{
    return in_array(ch_nivel(), [1, 2, 3, 4, 5], true);
}

/** Unidades em que o Diretor autorizou este Secretário Escolar a ler relatórios. */
function ch_unidades_relatorios_ids()
{
    if (!ch_secretario_escolar()) {
        return ch_unidades_ids();
    }

    $rows = ch_rows(
        'SELECT p.ID_UNIDADE
           FROM TB_SECRETARIO_RELATORIOS p
           INNER JOIN TB_ACESSO a
                   ON a.id_usuario = p.ID_USUARIO
                  AND a.id_unidade = p.ID_UNIDADE
                  AND ISNULL(a.ativo, 1) = 1
          WHERE p.ID_USUARIO = ? AND p.PERMITIDO = 1
          ORDER BY p.ID_UNIDADE',
        [ch_uid()]
    );
    return array_map(function ($row) { return (int)$row['ID_UNIDADE']; }, $rows);
}

/** Unidades em que o Diretor autorizou leitura ou inserção de observações. */
function ch_unidades_observacoes_ids($permissaoMinima = 1)
{
    static $cache = [];
    $permissaoMinima = max(1, min(2, (int)$permissaoMinima));
    if (isset($cache[$permissaoMinima])) {
        return $cache[$permissaoMinima];
    }

    if (!ch_secretario_escolar()) {
        $cache[$permissaoMinima] = ch_unidades_ids();
        return $cache[$permissaoMinima];
    }

    $rows = ch_rows(
        'SELECT p.ID_UNIDADE
           FROM TB_SECRETARIO_OBSERVACOES p
           INNER JOIN TB_ACESSO a
                   ON a.id_usuario = p.ID_USUARIO
                  AND a.id_unidade = p.ID_UNIDADE
                  AND ISNULL(a.ativo, 1) = 1
          WHERE p.ID_USUARIO = ? AND p.PERMITIDO >= ?
          ORDER BY p.ID_UNIDADE',
        [ch_uid(), $permissaoMinima]
    );
    $cache[$permissaoMinima] = array_map(function ($row) { return (int)$row['ID_UNIDADE']; }, $rows);
    return $cache[$permissaoMinima];
}

/** Filtro das unidades em que o Secretário pode consultar observações. */
function ch_filtro_observacao_aluno($aliasAluno = 'a')
{
    if (!ch_secretario_escolar()) {
        return ['sql' => '', 'params' => []];
    }

    $ids = ch_unidades_observacoes_ids(1);
    if (!$ids) {
        return ['sql' => ' AND 1 = 0', 'params' => []];
    }
    $ph = implode(',', array_fill(0, count($ids), '?'));
    return [
        'sql' => " AND EXISTS (SELECT 1 FROM TB_TURMAS tobs
                              WHERE tobs.ID = {$aliasAluno}.ID_TURMA
                                AND tobs.ID_UNIDADE IN ({$ph}))",
        'params' => $ids,
    ];
}

/** Secretários só inserem observações em unidades liberadas pelo Diretor. */
function ch_pode_escrever_observacao_turma($idTurma, $idUnidade, $data = null)
{
    if (ch_secretario_escolar()) {
        return in_array((int)$idUnidade, ch_unidades_observacoes_ids(2), true);
    }
    /* Professor substituto só escreve na data em que está vigente na equipe. */
    if (ch_professor() && $data !== null && !eq_na_equipe_em($idTurma, ch_uid(), $data)) {
        return false;
    }
    return ch_pode_turma($idTurma);
}

function ch_pode_escrever_observacao_aluno($idAluno, $data = null)
{
    $aluno = ch_aluno($idAluno);
    if (!$aluno) {
        return false;
    }
    if (ch_eh_professor_aee_aluno($aluno)) {
        return true;
    }
    return ch_pode_escrever_observacao_turma($aluno['ID_TURMA'], $aluno['ID_UNIDADE'], $data);
}

function ch_pode_escrever_observacoes()
{
    return !ch_secretario_escolar() || count(ch_unidades_observacoes_ids(2)) > 0;
}

function ch_pode_ler_observacao_aluno($idAluno)
{
    if (!ch_secretario_escolar()) {
        return true;
    }
    $aluno = ch_aluno($idAluno);
    return $aluno && in_array((int)$aluno['ID_UNIDADE'], ch_unidades_observacoes_ids(1), true);
}

/** Filtro de turma próprio dos relatórios: o Secretário só vê unidades liberadas. */
function ch_filtro_turma_relatorios($alias = 't')
{
    if (!ch_secretario_escolar()) {
        return ch_filtro_turma($alias);
    }

    $ids = ch_unidades_relatorios_ids();
    if (!$ids) {
        return ['sql' => ' AND 1 = 0', 'params' => []];
    }
    $ph = implode(',', array_fill(0, count($ids), '?'));
    return ['sql' => " AND {$alias}.ID_UNIDADE IN ({$ph})", 'params' => $ids];
}

/** Um relatório só é visível se a unidade do aluno estiver autorizada. */
function ch_pode_relatorio_aluno($idAluno)
{
    $aluno = ch_aluno($idAluno);
    if (!$aluno) {
        return false;
    }

    if (ch_secretario_escolar()) {
        return in_array((int)$aluno['ID_UNIDADE'], ch_unidades_relatorios_ids(), true);
    }
    return ch_pode_aluno($idAluno);
}

function ch_exige_relatorio_aluno($idAluno)
{
    $aluno = ch_aluno($idAluno);
    if (!$aluno || !ch_pode_relatorio_aluno($idAluno)) {
        ch_flash('Relatório não autorizado. A Direção precisa liberar a leitura para esta unidade.', 'danger');
        header('Location: ' . BASEURL . 'relatorios/index.php');
        exit;
    }
    return $aluno;
}

function ch_require_relatorios()
{
    if (!ch_pode_ver_relatorios()) {
        ch_flash('Acesso restrito à equipe escolar.', 'danger');
        header('Location: ' . BASEURL . 'index.php');
        exit;
    }
}

/* ------------------------------------------------------- controle de acesso */

/** IDs de unidade do usuário. Rede = todas; os demais dependem de vínculo. */
function ch_unidades_ids()
{
    static $cache = null;
    if ($cache !== null) return $cache;

    if (ch_rede()) {
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

    /* Vai para inc/equipe.php: o substituto só alcança a turma dentro do prazo
       da substituição. Como todas as telas de professor passam por aqui, o
       prazo vale de uma vez para chamada, observações, relatório e retificação. */
    $ids = eq_turmas_ids_em(ch_uid(), ch_hoje());

    /* Professor: o contexto escolhido no login (escola + turma) restringe o
       alcance. Turmas de outras escolas e de anos anteriores somem de todas
       as telas de uma vez — é o que esconde as turmas antigas quando a
       secretaria encerra o ano letivo e cria as turmas do ano seguinte.
       A conferência é feita no banco (e não só na sessão) para que uma turma
       movida de escola/ano pela gestão não continue valendo por engano. */
    if (ch_professor()) {
        $ctx = ch_contexto();
        if ($ctx['turma'] > 0 && $ids) {
            $ph = implode(',', array_fill(0, count($ids), '?'));
            $rows = ch_rows(
                'SELECT ID FROM TB_TURMAS
                  WHERE ID IN (' . $ph . ') AND ID = ? AND ID_UNIDADE = ? AND ANO_LETIVO = ?',
                array_merge($ids, [$ctx['turma'], $ctx['unidade'], $ctx['ano']])
            );
            $ids = array_map(function ($r) { return (int)$r['ID']; }, $rows ?: []);
        } else {
            $ids = [];
        }
    }

    $cache = $ids;
    return $cache;
}

/** Unidades do alcance do usuário: vínculo direto ou turmas em que atua.
 *  Professores não têm vínculo em TB_ACESSO, mas precisam ver as regras da
 *  unidade das suas turmas no calendário. */
function ch_unidades_alcance()
{
    $ids = ch_unidades_ids();
    $turmas = ch_turmas_ids();
    if ($turmas) {
        $ph = implode(',', array_fill(0, count($turmas), '?'));
        $rows = ch_rows('SELECT DISTINCT ID_UNIDADE FROM dbo.TB_TURMAS WHERE ID IN (' . $ph . ')', $turmas);
        foreach ($rows ?: [] as $r) {
            $id = (int)$r['ID_UNIDADE'];
            if ($id > 0 && !in_array($id, $ids, true)) $ids[] = $id;
        }
    }
    return $ids;
}

/* ------------------------------------------------- contexto do professor
   ----------------------------------------------------------------------------
   O professor pode lecionar em mais de uma escola e em mais de uma turma.
   Ao entrar, ele escolhe onde está trabalhando (escola + turma); a partir
   daí o sistema mostra só esse recorte. A troca pode ser feita a qualquer
   momento pelo menu lateral (contexto.php).

   O contexto fica só na sessão: não há tabela nova nem migração de banco.
   O ano padrão é o ano corrente; se ele ainda não tem turma no ano atual
   (a secretaria ainda não criou as turmas novas), vale o ano mais recente
   em que ele tem turma — assim ele consegue terminar os relatórios do ano
   anterior sem ver a bagunça dos anos passados no dia a dia.
   ---------------------------------------------------------------------------- */

/** Contexto de trabalho do professor: ['unidade', 'ano', 'turma'].
 *  Zeros quando nada foi escolhido (professor sem turma ainda). */
function ch_contexto()
{
    $c = $_SESSION['ch_contexto'] ?? null;
    if (!is_array($c)) {
        return ['unidade' => 0, 'ano' => 0, 'turma' => 0];
    }
    return [
        'unidade' => (int)($c['unidade'] ?? 0),
        'ano'     => (int)($c['ano'] ?? 0),
        'turma'   => (int)($c['turma'] ?? 0),
    ];
}

/** A turma do contexto, já conferida com escola e ano. Null se não houver. */
function ch_contexto_turma()
{
    $ctx = ch_contexto();
    if ($ctx['turma'] <= 0) return null;
    $t = ch_turma($ctx['turma']);
    if (!$t || (int)$t['ID_UNIDADE'] !== $ctx['unidade'] || (int)$t['ANO_LETIVO'] !== $ctx['ano']) {
        return null;
    }
    return $t;
}

/** Turmas ativas em que o professor está na equipe, num ano letivo.
 *  Respeita o prazo do substituto, igual a eq_turmas_ids_em(). */
function ch_professor_turmas_por_escola($idUsuario, $ano)
{
    $idUsuario = (int)$idUsuario;
    $ano = (int)$ano;
    if ($idUsuario <= 0 || $ano <= 0) return [];
    return ch_rows(
        'SELECT t.ID, t.ID_UNIDADE, t.NOME, t.SERIE, t.ANO_LETIVO,
                u.unidade AS UNIDADE, p.NOME AS PERIODO,
                tp.PAPEL, tp.TURNO
           FROM TB_TURMA_PROFESSORES tp
           INNER JOIN TB_TURMAS t ON t.ID = tp.ID_TURMA
           INNER JOIN TB_UNIDADES u ON u.id = t.ID_UNIDADE
           INNER JOIN TB_PERIODOS p ON p.ID = t.ID_PERIODO
          WHERE tp.ID_USUARIO = ? AND tp.ATIVO = 1 AND t.ATIVO = 1 AND t.ANO_LETIVO = ?
            AND (tp.PAPEL <> \'SUBSTITUTO\'
                 OR (tp.DATA_INICIO IS NOT NULL AND tp.DATA_FIM IS NOT NULL
                     AND ? >= tp.DATA_INICIO AND ? <= tp.DATA_FIM))
          ORDER BY u.unidade, t.SERIE, t.NOME, p.NOME',
        [$idUsuario, $ano, ch_hoje(), ch_hoje()]
    ) ?: [];
}

/** Anos letivos em que o professor tem turma ativa na equipe, do mais novo. */
function ch_professor_anos($idUsuario)
{
    $idUsuario = (int)$idUsuario;
    if ($idUsuario <= 0) return [];
    $rows = ch_rows(
        'SELECT DISTINCT t.ANO_LETIVO
           FROM TB_TURMA_PROFESSORES tp
           INNER JOIN TB_TURMAS t ON t.ID = tp.ID_TURMA
          WHERE tp.ID_USUARIO = ? AND tp.ATIVO = 1 AND t.ATIVO = 1
            AND (tp.PAPEL <> \'SUBSTITUTO\'
                 OR (tp.DATA_INICIO IS NOT NULL AND tp.DATA_FIM IS NOT NULL
                     AND ? >= tp.DATA_INICIO AND ? <= tp.DATA_FIM))
          ORDER BY t.ANO_LETIVO DESC',
        [$idUsuario, ch_hoje(), ch_hoje()]
    ) ?: [];
    return array_map(function ($r) { return (int)$r['ANO_LETIVO']; }, $rows);
}

/** Contexto padrão no login: a primeira turma do ano corrente; se não houver,
 *  a primeira turma do ano mais recente em que ele tem turma. */
function ch_contexto_padrao($idUsuario)
{
    $idUsuario = (int)$idUsuario;
    $anoAtual = (int)date('Y');
    $turmas = ch_professor_turmas_por_escola($idUsuario, $anoAtual);
    if ($turmas) {
        return ['unidade' => (int)$turmas[0]['ID_UNIDADE'], 'ano' => $anoAtual, 'turma' => (int)$turmas[0]['ID']];
    }
    foreach (ch_professor_anos($idUsuario) as $ano) {
        $turmas = ch_professor_turmas_por_escola($idUsuario, $ano);
        if ($turmas) {
            return ['unidade' => (int)$turmas[0]['ID_UNIDADE'], 'ano' => $ano, 'turma' => (int)$turmas[0]['ID']];
        }
    }
    return ['unidade' => 0, 'ano' => $anoAtual, 'turma' => 0];
}

/** Filtro SQL para restringir uma consulta de TURMAS ao alcance do usuário.
 *  $alias = alias da TB_TURMAS na consulta (ex.: 't').
 *  Rede: sem filtro. Gestão escolar: unidades vinculadas. Professor: equipe.
 *  Devolve ['sql' => string, 'params' => array]. */
function ch_filtro_turma($alias = 't')
{
    if (ch_rede()) {
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
    if (ch_rede()) {
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
    if (ch_rede()) {
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
        'SELECT t.ID, t.ID_UNIDADE, t.NOME, t.SERIE, t.ANO_LETIVO, t.ETAPA_ENSINO, t.ID_PERIODO, t.ID_PROFESSOR,
                t.CAPACIDADE, t.NOTAS_ATIVAS, t.ATIVO, t.GRADE_BLOQUEADA,
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
    /* Só quem vale hoje: substituto com prazo vencido não aparece como equipe. */
    return eq_equipe_em($idTurma, ch_hoje());
}

/** Nomes da equipe em uma linha, para exibição em listagens. */
function ch_turma_equipe_texto($idTurma)
{
    /* Agora com papel e turno: "Ana (responsável · manhã), Bruno (apoio)". */
    return eq_texto($idTurma, ch_hoje());
}

/** O usuário faz parte da equipe desta turma? */
function ch_na_equipe($idTurma, $idUsuario = null)
{
    /* Mesmo motivo de ch_turmas_ids(): o prazo do substituto é conferido aqui. */
    return eq_na_equipe_em($idTurma, $idUsuario, ch_hoje());
}

/** Acesso à turma: rede = todas; gestão escolar = unidades vinculadas;
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

/** Quem pode retificar uma chamada já confirmada: gestão ou equipe docente. */
function ch_pode_retificar($idTurma)
{
    return !ch_secretario_escolar() && ch_pode_turma($idTurma);
}

/** A secretaria escolar consulta a chamada, mas não confirma presenças. */
function ch_pode_lancar_chamada($idTurma)
{
    return !ch_secretario_escolar() && ch_pode_turma($idTurma);
}

/** Aluno completo, com turma/unidade/período e dados internos de AEE. */
function ch_aluno($idAluno)
{
    return ch_row(
        'SELECT a.ID, a.ID_TURMA, a.MATRICULA, a.CPF, a.NOME, a.RESPONSAVEL, a.TELEFONE, a.EMAIL,
                CASE WHEN a.FOTO_DADOS IS NULL THEN 0 ELSE 1 END AS TEM_FOTO, a.FOTO_MIME, a.ATIVO,
                a.ALERGIAS, a.RESTRICOES_ALIMENTARES, a.MEDICACAO,
                a.CONDICAO_ESPECIFICA, a.OUTROS_ACOMPANHAMENTOS,
                a.DEFICIENCIAS_INCLUSAO, a.SIMBOLO_TEA_INCLUSAO,
                ISNULL(a.AUTORIZA_MARCADOR_INCLUSAO, 0) AS AUTORIZA_MARCADOR_INCLUSAO,
                ISNULL(a.PASSA_AEE, 0) AS PASSA_AEE, a.ID_PROFESSOR_AEE, a.AEE_OBSERVACOES,
                paee.NOME AS PROFESSOR_AEE_NOME,
                CONVERT(varchar(10), a.NASCIMENTO, 23)     AS NASCIMENTO,
                CONVERT(varchar(10), a.DATA_MATRICULA, 23) AS DATA_MATRICULA,
                t.NOME AS TURMA, t.SERIE, t.ANO_LETIVO, t.ID_UNIDADE, t.ID_PERIODO,
                t.HORA_INICIO, t.HORA_FIM, t.PERIODO, t.UNIDADE
         FROM TB_ALUNOS a
         INNER JOIN (
            SELECT tt.ID, tt.NOME, tt.SERIE, tt.ANO_LETIVO, tt.ID_UNIDADE, tt.ID_PERIODO,
                   p.HORA_INICIO, p.HORA_FIM, p.NOME AS PERIODO, u.unidade AS UNIDADE
            FROM TB_TURMAS tt
            INNER JOIN TB_PERIODOS p ON p.ID = tt.ID_PERIODO
            INNER JOIN TB_UNIDADES u ON u.id = tt.ID_UNIDADE
         ) t ON t.ID = a.ID_TURMA
         LEFT JOIN TB_USUARIOS paee ON paee.ID = a.ID_PROFESSOR_AEE
         WHERE a.ID = ?',
        [(int)$idAluno]
    );
}

function ch_pode_aluno($idAluno)
{
    $a = ch_aluno($idAluno);
    if (!$a) return false;
    if (ch_pode_turma($a['ID_TURMA'])) return true;
    if (ch_eh_professor_aee_aluno($a)) return true;

    /* Transferido: a escola de origem continua podendo CONSULTAR o histórico
       de quem já foi seu aluno. Isto só amplia leitura — editar aluno, lançar
       chamada e escrever parecer continuam exigindo a turma atual. */
    return tr_aluno_ja_no_alcance($idAluno);
}

/** Só a escola em que o aluno está HOJE. É a permissão das telas que ESCREVEM:
    editar cadastro, lançar observação, gravar parecer. Depois de uma
    transferência a escola de origem fica só com a consulta ao histórico. */
function ch_pode_aluno_atual($idAluno)
{
    $a = ch_aluno($idAluno);
    return $a && ch_pode_turma($a['ID_TURMA']);
}

/** Como ch_exige_aluno, mas exige a turma atual — para escrita. */
function ch_exige_aluno_atual($idAluno)
{
    $a = ch_aluno($idAluno);
    if (!$a || !ch_pode_turma($a['ID_TURMA'])) {
        ch_flash('Este aluno não está mais na sua escola. Só a escola atual pode alterar o cadastro.', 'danger');
        header('Location: ' . BASEURL . 'alunos/index.php');
        exit;
    }
    return $a;
}

/** Bloqueia o acesso a aluno fora do alcance do usuário. */
function ch_exige_aluno($idAluno)
{
    $a = ch_aluno($idAluno);
    /* Usa ch_pode_aluno, e não ch_pode_turma direto: depois de uma
       transferência a escola de origem ainda pode CONSULTAR a ficha de quem
       foi seu aluno. As telas de escrita continuam exigindo a turma atual. */
    if (!$a || !ch_pode_aluno($idAluno)) {
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

/** Valida CPF de 11 dígitos, incluindo os dígitos verificadores. */
function ch_cpf_valido($s)
{
    $cpf = ch_digitos($s);
    if (!preg_match('/^[0-9]{11}$/D', $cpf) || preg_match('/^([0-9])\1{10}$/D', $cpf)) {
        return false;
    }
    for ($t = 9; $t < 11; $t++) {
        $soma = 0;
        for ($i = 0; $i < $t; $i++) {
            $soma += (int)$cpf[$i] * (($t + 1) - $i);
        }
        $dig = ($soma * 10) % 11;
        if ($dig === 10) $dig = 0;
        if ((int)$cpf[$t] !== $dig) return false;
    }
    return true;
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
    return [
        1 => 'Administrador do sistema',
        2 => 'Diretor Escolar',
        3 => 'Professor',
        4 => 'Secretaria de Educação',
        5 => 'Secretário Escolar',
    ][(int)$n] ?? 'Indefinido';
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

/** Etapas de ensino disponíveis para classificação das turmas. */
function ch_etapas_ensino()
{
    return [
        'INFANTIL_CRECHE' => 'Infantil — Creche',
        'FUNDAMENTAL_1_5' => 'Fundamental — 1º ao 5º Ano',
        'FUNDAMENTAL_6_9' => 'Fundamental — 6º ao 9º Ano',
        'INFANTIL_PRE_ESCOLA' => 'Infantil — Pré-Escola',
    ];
}

function ch_etapa_ensino_rotulo($etapa)
{
    $etapas = ch_etapas_ensino();
    return is_string($etapa) && isset($etapas[$etapa])
        ? $etapas[$etapa]
        : 'Etapa não classificada';
}

/** Séries em ordem de progressão escolar. A série é independente da sala A/B/C. */
function ch_series_ensino()
{
    return [
        'BERCARIO_I' => ['rotulo' => 'Berçário I', 'etapa' => 'INFANTIL_CRECHE', 'ordem' => 1],
        'BERCARIO_II' => ['rotulo' => 'Berçário II', 'etapa' => 'INFANTIL_CRECHE', 'ordem' => 2],
        'MATERNAL_I' => ['rotulo' => 'Maternal I', 'etapa' => 'INFANTIL_CRECHE', 'ordem' => 3],
        'MATERNAL_II' => ['rotulo' => 'Maternal II', 'etapa' => 'INFANTIL_CRECHE', 'ordem' => 4],
        'PRE_I' => ['rotulo' => 'Pré I', 'etapa' => 'INFANTIL_PRE_ESCOLA', 'ordem' => 5],
        'PRE_II' => ['rotulo' => 'Pré II', 'etapa' => 'INFANTIL_PRE_ESCOLA', 'ordem' => 6],
        'ANO_1' => ['rotulo' => '1º ano', 'etapa' => 'FUNDAMENTAL_1_5', 'ordem' => 7],
        'ANO_2' => ['rotulo' => '2º ano', 'etapa' => 'FUNDAMENTAL_1_5', 'ordem' => 8],
        'ANO_3' => ['rotulo' => '3º ano', 'etapa' => 'FUNDAMENTAL_1_5', 'ordem' => 9],
        'ANO_4' => ['rotulo' => '4º ano', 'etapa' => 'FUNDAMENTAL_1_5', 'ordem' => 10],
        'ANO_5' => ['rotulo' => '5º ano', 'etapa' => 'FUNDAMENTAL_1_5', 'ordem' => 11],
        'ANO_6' => ['rotulo' => '6º ano', 'etapa' => 'FUNDAMENTAL_6_9', 'ordem' => 12],
        'ANO_7' => ['rotulo' => '7º ano', 'etapa' => 'FUNDAMENTAL_6_9', 'ordem' => 13],
        'ANO_8' => ['rotulo' => '8º ano', 'etapa' => 'FUNDAMENTAL_6_9', 'ordem' => 14],
        'ANO_9' => ['rotulo' => '9º ano', 'etapa' => 'FUNDAMENTAL_6_9', 'ordem' => 15],
    ];
}

function ch_serie_ordem($codigo)
{
    $series = ch_series_ensino();
    return isset($series[$codigo]) ? (int)$series[$codigo]['ordem'] : null;
}

function ch_serie_seguinte($codigo)
{
    $ordem = ch_serie_ordem($codigo);
    if ($ordem === null) return null;
    foreach (ch_series_ensino() as $serie => $dados) {
        if ((int)$dados['ordem'] === $ordem + 1) return $serie;
    }
    return null;
}

function ch_resultados_escolares()
{
    return [
        'PROMOVIDO' => 'Promovido',
        'RETIDO' => 'Retido',
        'TRANSFERENCIA' => 'Transferência durante o ano',
    ];
}

function ch_resultado_escolar_rotulo($resultado)
{
    $resultados = ch_resultados_escolares();
    return isset($resultados[$resultado]) ? $resultados[$resultado] : 'Não informado';
}

function ch_motivos_retencao()
{
    return [
        'FALTAS' => 'Faltas',
        'NOTAS' => 'Notas',
        'FALTAS_NOTAS' => 'Faltas e notas',
    ];
}

function ch_motivo_retencao_rotulo($motivo)
{
    $motivos = ch_motivos_retencao();
    return isset($motivos[$motivo]) ? $motivos[$motivo] : 'Não informado';
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

/* -------------------------------------------------------- aula do dia */

/** Bases antigas não tinham AULA_SEQ: todo registro valia como aula 1. */
function ch_chamada_tem_aula()
{
    static $tem = null;
    if ($tem === null) {
        $tem = (bool)ch_val(
            "SELECT 1 FROM sys.columns
              WHERE object_id = OBJECT_ID(N'dbo.TB_CHAMADA') AND name = N'AULA_SEQ'"
        );
    }
    return $tem;
}

/** Coluna segura para leituras em bancos migrados e não migrados. */
function ch_aula_col($alias = '')
{
    $prefix = $alias !== '' ? rtrim((string)$alias, '.') . '.' : '';
    return ch_chamada_tem_aula() ? $prefix . 'AULA_SEQ' : 'CAST(1 AS int)';
}

/** Limite de posições por dia aceito pelo banco (AULA_SEQ de 1 a 6). */
function ch_aula_max()
{
    return 6;
}

/** A quantidade é definida manualmente em TB_TURMAS.AULAS_DIA (padrão 2). */
function ch_turma_aulas_dia($idTurma)
{
    $idTurma = (int)$idTurma;
    static $cache = [];
    if (!array_key_exists($idTurma, $cache)) {
        $valor = $idTurma > 0
            ? ch_val('SELECT AULAS_DIA FROM TB_TURMAS WHERE ID = ?', [$idTurma])
            : null;
        $cache[$idTurma] = max(1, min(ch_aula_max(), (int)($valor ?: 2)));
    }
    return $cache[$idTurma];
}

/** A grade fica travada depois de salva; "Editar grade" libera nova alteração. */
function ch_turma_grade_bloqueada($idTurma)
{
    $idTurma = (int)$idTurma;
    if ($idTurma <= 0) return false;
    static $cache = [];
    if (!array_key_exists($idTurma, $cache)) {
        $valor = ch_val('SELECT GRADE_BLOQUEADA FROM TB_TURMAS WHERE ID = ?', [$idTurma]);
        $cache[$idTurma] = (int)$valor === 1;
    }
    return $cache[$idTurma];
}

/** Lista [aula => rótulo] até a quantidade pedida (padrão 2, compatibility). */
function ch_aula_lista($aulasDia = 2)
{
    $n = max(1, min(ch_aula_max(), (int)$aulasDia ?: 2));
    $lista = [];
    for ($aula = 1; $aula <= $n; $aula++) {
        $lista[$aula] = $aula . 'ª aula';
    }
    return $lista;
}

/** Lista de aulas já no tamanho configurado da turma. */
function ch_aula_lista_turma($idTurma)
{
    return ch_aula_lista(ch_turma_aulas_dia($idTurma));
}

/** Normaliza para a faixa válida. Sem turma, aceita até o limite do banco. */
function ch_aula_normalizar($valor, $aulasDia = 0)
{
    $n = $aulasDia > 0 ? max(1, min(ch_aula_max(), (int)$aulasDia)) : ch_aula_max();
    $aula = (int)$valor;
    if ($aula < 1) return 1;
    return $aula > $n ? $n : $aula;
}

function ch_aula_rotulo($aula)
{
    $lista = ch_aula_lista(ch_aula_max());
    return $lista[ch_aula_normalizar($aula)] ?? '1ª aula';
}

/* ------------------------------------------------------------- grade diária */

/** Dia da semana no mesmo padrão de DateTime::format('N'): 1 = segunda. */
function ch_grade_dias()
{
    return [1 => 'Segunda', 2 => 'Terça', 3 => 'Quarta', 4 => 'Quinta',
            5 => 'Sexta', 6 => 'Sábado', 7 => 'Domingo'];
}

/** Instalações antigas podem não ter a tabela ainda. */
function ch_grade_tem_tabela()
{
    static $tem = null;
    if ($tem === null) {
        $tem = (bool)ch_val(
            "SELECT 1 FROM sys.tables WHERE object_id = OBJECT_ID(N'dbo.TB_GRADE_AULAS')"
        );
    }
    return $tem;
}

/** Grade [dia][aula] da turma, com disciplina e professor já nominados. */
function ch_grade_da_turma($idTurma)
{
    if (!ch_grade_tem_tabela()) return [];
    $linhas = ch_rows(
        'SELECT g.DIA_SEMANA, g.AULA_SEQ, g.ID_DISCIPLINA, d.NOME AS DISCIPLINA,
                g.ID_PROFESSOR, u.NOME AS PROFESSOR
           FROM TB_GRADE_AULAS g
           LEFT JOIN TB_TURMA_DISCIPLINAS d ON d.ID = g.ID_DISCIPLINA
           LEFT JOIN TB_USUARIOS u ON u.ID = g.ID_PROFESSOR
          WHERE g.ID_TURMA = ?',
        [(int)$idTurma]
    ) ?: [];
    $grade = [];
    foreach ($linhas as $linha) {
        $grade[(int)$linha['DIA_SEMANA']][(int)$linha['AULA_SEQ']] = [
            'ID_DISCIPLINA' => (int)$linha['ID_DISCIPLINA'],
            'DISCIPLINA'    => (string)$linha['DISCIPLINA'],
            'ID_PROFESSOR'  => (int)$linha['ID_PROFESSOR'],
            'PROFESSOR'     => (string)$linha['PROFESSOR'],
        ];
    }
    return $grade;
}

/**
 * Conflito de professor na mesma posição (dia + aula) em OUTRA turma do mesmo
 * ano letivo. A regra é global: unidades diferentes também entram no conflito.
 */
function ch_grade_conflito_professor($idTurma, $dia, $aula, $idProfessor, $anoLetivo = 0)
{
    $idProfessor = (int)$idProfessor;
    if ($idProfessor <= 0) return null;
    $idTurma = (int)$idTurma;
    if ($anoLetivo <= 0) {
        $anoLetivo = (int)ch_val('SELECT ANO_LETIVO FROM TB_TURMAS WHERE ID = ?', [$idTurma]);
    }
    if ($anoLetivo <= 0) return null;

    $linha = ch_row(
        'SELECT t.ID, t.NOME, u.unidade AS UNIDADE, p.NOME AS PERIODO
           FROM TB_GRADE_AULAS g
          INNER JOIN TB_TURMAS t ON t.ID = g.ID_TURMA
          INNER JOIN TB_UNIDADES u ON u.id = t.ID_UNIDADE
          INNER JOIN TB_PERIODOS p ON p.ID = t.ID_PERIODO
          WHERE g.ID_PROFESSOR = ? AND g.DIA_SEMANA = ? AND g.AULA_SEQ = ?
            AND g.ID_TURMA <> ? AND t.ANO_LETIVO = ?',
        [$idProfessor, (int)$dia, (int)$aula, $idTurma, $anoLetivo]
    );
    if (!$linha) return null;
    return [
        'ID_TURMA' => (int)$linha['ID'],
        'LOCAL'    => trim((string)$linha['UNIDADE'] . ' — Sala ' . $linha['NOME'] . ' (' . $linha['PERIODO'] . ')'),
    ];
}

/** Mapa dia:aula:professor => conflito já existente, para avisar na tela. */
function ch_grade_conflitos_da_turma($idTurma)
{
    if (!ch_grade_tem_tabela()) return [];
    $grade = ch_grade_da_turma($idTurma);
    $ano = (int)ch_val('SELECT ANO_LETIVO FROM TB_TURMAS WHERE ID = ?', [(int)$idTurma]);
    $conflitos = [];
    foreach ($grade as $dia => $aulas) {
        foreach ($aulas as $aula => $slot) {
            if ((int)$slot['ID_PROFESSOR'] <= 0) continue;
            $outro = ch_grade_conflito_professor($idTurma, $dia, $aula, $slot['ID_PROFESSOR'], $ano);
            if (!$outro) continue;
            $conflitos[$dia . ':' . $aula . ':' . (int)$slot['ID_PROFESSOR']] =
                ch_aula_rotulo($aula) . ' de ' . ch_grade_dias()[$dia]
                . ': já está em ' . $outro['LOCAL'];
        }
    }
    return $conflitos;
}

/** "Matemática · Ana" (ou "—" quando a aula não está configurada). */
function ch_grade_rotulo($slot)
{
    if (!$slot || ($slot['DISCIPLINA'] === '' && $slot['PROFESSOR'] === '')) return '—';
    $partes = array_filter([$slot['DISCIPLINA'], $slot['PROFESSOR']], function ($p) {
        return $p !== '';
    });
    return implode(' · ', $partes);
}

/** Sigla curta para o cabeçalho estreito da ficha (nome completo no title). */
function ch_grade_curto($texto, $tamanho = 3)
{
    $texto = trim((string)$texto);
    if ($texto === '') return '—';
    if (function_exists('mb_substr') && function_exists('mb_strtoupper')) {
        return mb_strtoupper(mb_substr($texto, 0, $tamanho, 'UTF-8'), 'UTF-8');
    }
    return strtoupper(substr($texto, 0, $tamanho));
}

/** Texto completo do cabeçalho: "1ª aula: Matemática · Ana". */
function ch_grade_titulo($slot, $aula)
{
    $prefixo = ch_aula_rotulo($aula) . ': ';
    if (!$slot) return $prefixo . 'sem grade cadastrada';
    $partes = [];
    if ((string)($slot['DISCIPLINA'] ?? '') !== '') $partes[] = (string)$slot['DISCIPLINA'];
    if ((string)($slot['PROFESSOR'] ?? '') !== '') {
        $professor = (string)$slot['PROFESSOR'];
        if (!empty($slot['PROFESSOR_SUBSTITUTO'])) {
            $professor .= ' (substituiu ' . $slot['PROFESSOR_SUBSTITUTO'] . ')';
        }
        $partes[] = $professor;
    }
    return $prefixo . ($partes ? implode(' · ', $partes) : 'sem disciplina/professor definidos');
}

/* --------------------------------------------------- calendário escolar */

/** Calendários da rede. Pré-escola segue o ensino fundamental. */
function ch_calendarios_opcoes()
{
    return [
        'CRECHE'      => 'Creche (Berçário e Maternal)',
        'FUNDAMENTAL' => 'Ensino Fundamental e Pré-Escola',
    ];
}

function ch_calendario_rotulo($codigo)
{
    $opcoes = ch_calendarios_opcoes();
    return isset($opcoes[$codigo]) ? $opcoes[$codigo] : 'Calendário não informado';
}

/** Etapa/série da turma -> calendário que ela segue. */
function ch_calendario_da_turma($turma)
{
    $etapa = is_array($turma) ? (string)($turma['ETAPA_ENSINO'] ?? '') : '';
    $serie = is_array($turma) ? (string)($turma['SERIE'] ?? '') : '';
    if ($etapa === 'INFANTIL_CRECHE') return 'CRECHE';

    /* Turmas antigas podem não ter ETAPA_ENSINO preenchida. */
    $series = ch_series_ensino();
    if ($serie !== '' && isset($series[$serie]) && $series[$serie]['etapa'] === 'INFANTIL_CRECHE') {
        return 'CRECHE';
    }
    /* INFANTIL_PRE_ESCOLA e FUNDAMENTAL_* usam o mesmo calendário. */
    return 'FUNDAMENTAL';
}

/** Instalações antigas podem não ter a coluna CALENDARIO em TB_FERIADOS. */
function ch_feriados_tem_calendario()
{
    static $tem = null;
    if ($tem === null) {
        $tem = (bool)ch_val(
            "SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
              WHERE TABLE_NAME = 'TB_FERIADOS' AND COLUMN_NAME = 'CALENDARIO'"
        );
    }
    return $tem;
}

/** A tabela de calendários pode não existir antes da migração. */
function ch_tabela_calendarios()
{
    static $tem = null;
    if ($tem === null) {
        $tem = (bool)ch_val(
            "SELECT 1 FROM sys.objects WHERE object_id = OBJECT_ID(N'dbo.TB_CALENDARIOS') AND type = 'U'"
        );
    }
    return $tem;
}

/** A coluna FIM_ANO pode não existir em um banco ainda não migrado. */
function ch_calendario_tem_fim()
{
    static $tem = null;
    if ($tem === null) {
        $tem = (bool)ch_val(
            "SELECT 1 FROM sys.columns
              WHERE object_id = OBJECT_ID(N'dbo.TB_CALENDARIOS') AND name = N'FIM_ANO'"
        );
    }
    return $tem;
}

/** A tabela de exceções pode não existir antes da migração. */
function ch_tabela_excecoes_calendario()
{
    static $tem = null;
    if ($tem === null) {
        $tem = (bool)ch_val(
            "SELECT 1 FROM sys.objects WHERE object_id = OBJECT_ID(N'dbo.TB_CALENDARIO_EXCECOES') AND type = 'U'"
        );
    }
    return $tem;
}

/** Instalações antigas podem não ter a abrangência por unidade/turma. */
function ch_excecoes_tem_escopo()
{
    static $tem = null;
    if ($tem === null) {
        $tem = (bool)ch_val(
            "SELECT 1 FROM sys.columns
              WHERE object_id = OBJECT_ID(N'dbo.TB_CALENDARIO_EXCECOES')
                AND name IN (N'ID_UNIDADE', N'ID_TURMA')"
        );
    }
    return $tem;
}

/** Rótulo da abrangência de uma exceção, para listas e mensagens. */
function ch_excecao_escopo_dados($idUnidade, $idTurma)
{
    $idUnidade = (int)$idUnidade;
    $idTurma = (int)$idTurma;
    if ($idTurma > 0) {
        static $turmas = [];
        if (!array_key_exists($idTurma, $turmas)) {
            $turmas[$idTurma] = ch_val('SELECT NOME FROM dbo.TB_TURMAS WHERE ID = ?', [$idTurma]);
        }
        return [
            'ESCOPO' => 'TURMA',
            'ROTULO' => 'Turma ' . ($turmas[$idTurma] !== null ? (string)$turmas[$idTurma] : '#' . $idTurma),
            'ID_UNIDADE' => $idUnidade,
            'ID_TURMA' => $idTurma,
        ];
    }
    if ($idUnidade > 0) {
        static $unidades = [];
        if (!array_key_exists($idUnidade, $unidades)) {
            $unidades[$idUnidade] = ch_val('SELECT unidade FROM dbo.TB_UNIDADES WHERE id = ?', [$idUnidade]);
        }
        return [
            'ESCOPO' => 'UNIDADE',
            'ROTULO' => 'Unidade ' . ($unidades[$idUnidade] !== null ? (string)$unidades[$idUnidade] : '#' . $idUnidade),
            'ID_UNIDADE' => $idUnidade,
            'ID_TURMA' => 0,
        ];
    }
    return ['ESCOPO' => 'GLOBAL', 'ROTULO' => 'Todas as unidades', 'ID_UNIDADE' => 0, 'ID_TURMA' => 0];
}

/** Quem pode alterar uma exceção nesta abrangência. Global só para a rede. */
function ch_pode_gerenciar_excecao($idUnidade, $idTurma)
{
    if (ch_rede()) return true;
    if (!ch_gestor()) return false;
    if ((int)$idTurma > 0) return ch_pode_turma((int)$idTurma);
    if ((int)$idUnidade > 0) return ch_pode_unidade((int)$idUnidade);
    return false;
}

/**
 * Regras completas de um calendário, cacheadas por requisição.
 *
 * Devolve INICIO/FIM em ISO, os feriados integrais globais do calendário
 * (data => descrição) e as exceções efetivas (data => ID/TIPO/MOTIVO/ESCOPO).
 * Com unidade/turma informados, vale a regra mais específica: turma > unidade
 * > global. Sem eles, mantém-se o comportamento antigo (só exceções globais).
 */
function ch_calendario_dados($anoLetivo, $calendario, $idUnidade = 0, $idTurma = 0)
{
    static $cache = [];
    $idUnidade = (int)$idUnidade;
    $idTurma = (int)$idTurma;
    if ($idTurma > 0 && $idUnidade <= 0) {
        $turmaEscopo = ch_turma($idTurma);
        if ($turmaEscopo) $idUnidade = (int)$turmaEscopo['ID_UNIDADE'];
    }
    $chave = (int)$anoLetivo . '|' . (string)$calendario . '|' . $idUnidade . '|' . $idTurma;
    if (isset($cache[$chave])) return $cache[$chave];

    $dados = [
        'ID' => 0, 'INICIO' => null, 'FIM' => null, 'EXISTE' => false,
        'FERIADOS' => [], 'EXCECOES' => [],
    ];
    if (!ch_tabela_calendarios()) return $cache[$chave] = $dados;

    $colFim = ch_calendario_tem_fim()
        ? 'CONVERT(varchar(10), FIM_ANO, 23)'
        : "CAST(NULL AS varchar(10))";
    $row = ch_row(
        'SELECT ID, CONVERT(varchar(10), INICIO_ANO, 23) AS INICIO, ' . $colFim . ' AS FIM
           FROM dbo.TB_CALENDARIOS WHERE ANO_LETIVO = ? AND CALENDARIO = ?',
        [(int)$anoLetivo, (string)$calendario]
    );
    if (!$row) return $cache[$chave] = $dados;

    $dados['EXISTE'] = true;
    $dados['ID'] = (int)$row['ID'];
    $dados['INICIO'] = $row['INICIO'] !== null ? (string)$row['INICIO'] : null;
    $dados['FIM'] = $row['FIM'] !== null ? (string)$row['FIM'] : null;

    if ($dados['INICIO'] !== null && $dados['FIM'] !== null) {
        $sql = "SELECT CONVERT(varchar(10), DATA, 23) AS DATA, DESCRICAO
                  FROM TB_FERIADOS
                 WHERE DATA >= ? AND DATA <= ? AND ID_UNIDADE IS NULL AND TIPO = 'INTEGRAL'";
        $params = [$dados['INICIO'], $dados['FIM']];
        if (ch_feriados_tem_calendario()) {
            $sql .= " AND (CALENDARIO = ? OR CALENDARIO = 'AMBOS')";
            $params[] = (string)$calendario;
        }
        foreach (ch_rows($sql, $params) as $f) {
            $dados['FERIADOS'][(string)$f['DATA']] = (string)$f['DESCRICAO'];
        }
    }

    if (ch_tabela_excecoes_calendario()) {
        $comEscopo = ch_excecoes_tem_escopo();
        $sql = 'SELECT e.ID, CONVERT(varchar(10), e.DATA, 23) AS DATA, e.TIPO, e.MOTIVO, '
             . ($comEscopo
                 ? 'ISNULL(e.ID_UNIDADE, 0) AS ID_UNIDADE, ISNULL(e.ID_TURMA, 0) AS ID_TURMA'
                 : 'CAST(0 AS int) AS ID_UNIDADE, CAST(0 AS int) AS ID_TURMA')
             . ' FROM dbo.TB_CALENDARIO_EXCECOES e WHERE e.ID_CALENDARIO = ?';
        $paramsExcecoes = [$dados['ID']];
        if ($comEscopo) {
            $sql .= ' AND (e.ID_UNIDADE IS NULL OR e.ID_UNIDADE = ?)'
                  . ' AND (e.ID_TURMA IS NULL OR e.ID_TURMA = ?)'
                  . ' ORDER BY CASE WHEN e.ID_TURMA = ? THEN 0'
                  . ' WHEN e.ID_UNIDADE = ? AND e.ID_TURMA IS NULL THEN 1'
                  . ' WHEN e.ID_UNIDADE IS NULL AND e.ID_TURMA IS NULL THEN 2'
                  . ' ELSE 3 END, e.DATA, e.ID';
            $paramsExcecoes = array_merge($paramsExcecoes, [$idUnidade, $idTurma, $idTurma, $idUnidade]);
        } else {
            $sql .= ' ORDER BY e.DATA, e.ID';
        }
        foreach (ch_rows($sql, $paramsExcecoes) as $e) {
            $dataIso = (string)$e['DATA'];
            /* ORDER BY já colocou a regra mais específica primeiro. */
            if (isset($dados['EXCECOES'][$dataIso])) continue;
            $escopo = ch_excecao_escopo_dados($e['ID_UNIDADE'], $e['ID_TURMA']);
            $dados['EXCECOES'][$dataIso] = [
                'ID' => (int)$e['ID'],
                'TIPO' => (string)$e['TIPO'],
                'MOTIVO' => (string)($e['MOTIVO'] ?? ''),
                'ESCOPO' => $escopo['ESCOPO'],
                'ROTULO_ESCOPO' => $escopo['ROTULO'],
                'ID_UNIDADE' => (int)$e['ID_UNIDADE'],
                'ID_TURMA' => (int)$e['ID_TURMA'],
            ];
        }
    }
    return $cache[$chave] = $dados;
}

/**
 * Exceções de um calendário dentro de um período, já recortadas pelo alcance
 * do usuário. Diferente de ch_calendario_dados(), devolve todas as abrangências
 * (global, unidade e turma), sem escolher uma como efetiva — é o que o painel
 * precisa para mostrar cada marcação separadamente.
 */
function ch_excecoes_do_periodo($idCalendario, $deIso, $ateIso, $idsUnidade = [], $idsTurma = [])
{
    if (!ch_tabela_excecoes_calendario()) return [];
    $comEscopo = ch_excecoes_tem_escopo();
    $sql = 'SELECT e.ID, CONVERT(varchar(10), e.DATA, 23) AS DATA, e.TIPO, e.MOTIVO, '
         . ($comEscopo
                 ? 'ISNULL(e.ID_UNIDADE, 0) AS ID_UNIDADE, ISNULL(e.ID_TURMA, 0) AS ID_TURMA'
                 : 'CAST(0 AS int) AS ID_UNIDADE, CAST(0 AS int) AS ID_TURMA')
         . ' FROM dbo.TB_CALENDARIO_EXCECOES e'
         . ' WHERE e.ID_CALENDARIO = ? AND e.DATA >= ? AND e.DATA <= ?';
    $params = [(int)$idCalendario, (string)$deIso, (string)$ateIso];
    if ($comEscopo) {
        $sql .= ' AND (e.ID_UNIDADE IS NULL';
        if ($idsUnidade) {
            $sql .= ' OR e.ID_UNIDADE IN (' . implode(',', array_fill(0, count($idsUnidade), '?')) . ')';
            $params = array_merge($params, $idsUnidade);
        }
        /* A turma também é alcançada pela unidade; o IN de turmas cobre quem
           não tem vínculo de unidade, como professor alocado em uma turma. */
        if ($idsTurma) {
            $sql .= ' OR e.ID_TURMA IN (' . implode(',', array_fill(0, count($idsTurma), '?')) . ')';
            $params = array_merge($params, $idsTurma);
        }
        $sql .= ')';
    }
    $sql .= ' ORDER BY e.DATA, e.ID';
    $out = [];
    foreach (ch_rows($sql, $params) as $e) {
        $escopo = ch_excecao_escopo_dados($e['ID_UNIDADE'], $e['ID_TURMA']);
        $out[] = [
            'ID' => (int)$e['ID'],
            'DATA' => (string)$e['DATA'],
            'TIPO' => (string)$e['TIPO'],
            'MOTIVO' => (string)($e['MOTIVO'] ?? ''),
            'ESCOPO' => $escopo['ESCOPO'],
            'ROTULO_ESCOPO' => $escopo['ROTULO'],
            'ID_UNIDADE' => (int)$e['ID_UNIDADE'],
            'ID_TURMA' => (int)$e['ID_TURMA'],
        ];
    }
    return $out;
}

/** Início do calendário em ISO; null se não cadastrado. */
function ch_calendario_inicio($anoLetivo, $calendario)
{
    return ch_calendario_dados($anoLetivo, $calendario)['INICIO'];
}

/** Fim do calendário em ISO; null se não cadastrado. */
function ch_calendario_fim($anoLetivo, $calendario)
{
    return ch_calendario_dados($anoLetivo, $calendario)['FIM'];
}

/** Dados completos dos dois calendários de um ano letivo. */
function ch_calendarios_do_ano($anoLetivo)
{
    if (!ch_tabela_calendarios()) return [];
    $out = [];
    foreach (ch_rows(
        'SELECT CALENDARIO FROM dbo.TB_CALENDARIOS WHERE ANO_LETIVO = ?',
        [(int)$anoLetivo]
    ) as $row) {
        $out[(string)$row['CALENDARIO']] = ch_calendario_dados($anoLetivo, (string)$row['CALENDARIO']);
    }
    return $out;
}

/**
 * Conta os dias letivos entre início e fim.
 *
 * Parte dos dias úteis (segunda a sexta), desconta feriados integrais globais
 * e fins de semana, e aplica as exceções nos dois sentidos: LETIVO acrescenta
 * um dia e NAO_LETIVO remove um dia útil.
 */
function ch_calendario_contador($anoLetivo, $calendario, $idUnidade = 0, $idTurma = 0)
{
    $dados = ch_calendario_dados($anoLetivo, $calendario, $idUnidade, $idTurma);
    $cont = [
        'INICIO' => $dados['INICIO'], 'FIM' => $dados['FIM'], 'TOTAL' => 0,
        'DIAS_UTEIS' => 0, 'FERIADOS' => 0, 'ACRESCIDOS' => 0, 'RETIRADOS' => 0,
    ];
    if ($dados['INICIO'] === null || $dados['FIM'] === null || $dados['FIM'] < $dados['INICIO']) {
        return $cont;
    }

    $inicio = new DateTime($dados['INICIO']);
    $fim = (new DateTime($dados['FIM']))->modify('+1 day');
    foreach (new DatePeriod($inicio, new DateInterval('P1D'), $fim) as $dia) {
        $iso = $dia->format('Y-m-d');
        $util = (int)$dia->format('N') < 6;
        $feriado = isset($dados['FERIADOS'][$iso]);
        $excecao = $dados['EXCECOES'][$iso] ?? null;

        if ($util) $cont['DIAS_UTEIS']++;
        if ($util && $feriado) $cont['FERIADOS']++;

        if ($excecao && $excecao['TIPO'] === 'LETIVO') {
            if (!$util || $feriado) $cont['ACRESCIDOS']++;
            $letivo = true;
        } elseif ($excecao && $excecao['TIPO'] === 'NAO_LETIVO') {
            if ($util && !$feriado) $cont['RETIRADOS']++;
            $letivo = false;
        } else {
            $letivo = $util && !$feriado;
        }
        if ($letivo) $cont['TOTAL']++;
    }
    return $cont;
}

/* --------------------------------------------------- regras de bloqueio */

/**
 * Verifica se a chamada pode ser lançada naquela data para aquela turma.
 * Devolve null quando está liberado, ou uma string com o motivo do bloqueio.
 *
 * Ordem: data futura -> ano letivo -> não letiva -> início/fim do calendário
 * -> final de semana -> feriado integral -> feriado parcial. Exceção LETIVO
 * libera fim de semana e feriado.
 *
 * $ignorarFuturo/$ignorarEquipe são usados pela suspensão: ela cobre datas
 * vindouras e é registrada pela gestão, não pelo professor da aula.
 */
function ch_bloqueio_chamada($dataIso, $turma, $ignorarFuturo = false, $ignorarEquipe = false)
{
    if (!$turma) return 'Turma não encontrada.';

    if (!$ignorarFuturo && $dataIso > ch_hoje()) {
        return 'Não é possível lançar chamada em data futura.';
    }

    if ((int)substr($dataIso, 0, 4) !== (int)$turma['ANO_LETIVO']) {
        return 'A data escolhida não pertence ao ano letivo da turma (' . $turma['ANO_LETIVO'] . ').';
    }

    if (isset($turma['ATIVO']) && (int)$turma['ATIVO'] === 0) {
        return 'Esta turma está inativa e não aceita novos lançamentos.';
    }

    /* A vigência do substituto vale na data do lançamento, não na data de hoje. */
    if (!$ignorarEquipe && ch_professor() && !eq_na_equipe_em((int)$turma['ID'], ch_uid(), $dataIso)) {
        return 'Você não está na equipe desta turma na data escolhida.';
    }

    $calendario = ch_calendario_da_turma($turma);
    $regras = ch_calendario_dados(
        (int)$turma['ANO_LETIVO'], $calendario,
        (int)$turma['ID_UNIDADE'], (int)$turma['ID']
    );
    $excecao = $regras['EXCECOES'][$dataIso] ?? null;
    $liberadoPorExcecao = $excecao && $excecao['TIPO'] === 'LETIVO';

    if ($excecao && $excecao['TIPO'] === 'NAO_LETIVO') {
        $detalheEscopo = ($excecao['ESCOPO'] ?? 'GLOBAL') === 'GLOBAL'
            ? ''
            : ' para ' . $excecao['ROTULO_ESCOPO'];
        return 'Data marcada como não letiva' . $detalheEscopo . ' no calendário '
             . ch_calendario_rotulo($calendario)
             . ($excecao['MOTIVO'] !== '' ? ': ' . $excecao['MOTIVO'] : '.');
    }
    if ($regras['INICIO'] !== null && $dataIso < $regras['INICIO']) {
        return 'A data escolhida é anterior ao início do ano letivo do calendário '
             . ch_calendario_rotulo($calendario) . ' (' . ch_data_br($regras['INICIO']) . ').';
    }
    if ($regras['FIM'] !== null && $dataIso > $regras['FIM']) {
        return 'A data escolhida é posterior ao fim do ano letivo do calendário '
             . ch_calendario_rotulo($calendario) . ' (' . ch_data_br($regras['FIM']) . ').';
    }
    if (!$liberadoPorExcecao && $regras['EXISTE'] && (int)date('N', strtotime($dataIso)) >= 6) {
        return 'Final de semana: não há aula nesta data. Se precisar de aula, marque a data como letiva no Calendário Escolar.';
    }

    /* Uma exceção LETIVO também libera feriados integrais e parciais. */
    if ($liberadoPorExcecao) return null;

    $sql = 'SELECT DESCRICAO, TIPO, HORA_INICIO, HORA_FIM, ID_UNIDADE
              FROM TB_FERIADOS
             WHERE DATA = ? AND (ID_UNIDADE IS NULL OR ID_UNIDADE = ?)';
    $params = [$dataIso, (int)$turma['ID_UNIDADE']];
    if (ch_feriados_tem_calendario()) {
        $sql .= " AND (CALENDARIO = ? OR CALENDARIO = 'AMBOS')";
        $params[] = $calendario;
    }
    /* Integral primeiro; entre iguais, a regra específica da unidade vale
       antes da regra global, para não ser mascarada por esta última. */
    $sql .= " ORDER BY CASE WHEN TIPO = 'INTEGRAL' THEN 0 ELSE 1 END,
                      CASE WHEN ID_UNIDADE IS NULL THEN 1 ELSE 0 END, ID";

    foreach (ch_rows($sql, $params) as $feriado) {
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
    }

    return null;
}

/** Feriados que atingem uma turma dentro de um intervalo (para avisos na tela). */
function ch_feriados_do_periodo($inicio, $fim, $idUnidade, $calendario = null)
{
    $sql = 'SELECT ID, CONVERT(varchar(10), DATA, 23) AS DATA, DESCRICAO, TIPO, HORA_INICIO, HORA_FIM
              FROM TB_FERIADOS
             WHERE DATA >= ? AND DATA <= ? AND (ID_UNIDADE IS NULL OR ID_UNIDADE = ?)';
    $params = [$inicio, $fim, (int)$idUnidade];
    if ($calendario !== null && ch_feriados_tem_calendario()) {
        $sql .= " AND (CALENDARIO = ? OR CALENDARIO = 'AMBOS')";
        $params[] = (string)$calendario;
    }
    $sql .= ' ORDER BY DATA';
    return ch_rows($sql, $params);
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

/**
 * Aceita somente caminhos internos simples vindos de formulário/URL.
 *
 * É usado no campo "voltar" das observações: o valor recebido é concatenado
 * por ch_url(), então precisa ser um caminho relativo da própria aplicação.
 * A inspeção é feita sobre o texto DECODIFICADO, para que %2f / %5c não se
 * tornem separadores no navegador. São rejeitados:
 *   - esquema (http:, https:), barras duplas, barra invertida, '@' e '#';
 *   - segmentos '..' ou '.' (path traversal) e segmentos vazios no meio;
 *   - espaços, caracteres de controle e caracteres fora do alfabeto de URLs
 *     usado pelo sistema.
 *
 * O texto original é devolvido para não alterar parâmetros já codificados.
 */
function ch_caminho_interno($valor, $padrao = '')
{
    $valor = trim((string)$valor);
    if ($valor === '') return $padrao;

    $decodificado = rawurldecode($valor);
    if (preg_match('/[\x00-\x20\x7f\\\\@#]/', $decodificado)) return $padrao;
    if (!preg_match('/\A\/?[A-Za-z0-9][A-Za-z0-9_.?&\/=%-]*\z/D', $decodificado)) return $padrao;
    if (strpos($decodificado, '://') !== false || strpos($decodificado, '//') === 0) return $padrao;
    if (strpos($decodificado, '..') !== false) return $padrao;

    $caminho = explode('?', $decodificado, 2)[0];
    $caminho = ltrim($caminho, '/');
    foreach (explode('/', $caminho) as $segmento) {
        if ($segmento === '' || $segmento === '.') return $padrao;
    }

    return $valor;
}

/** Nome do mês em português (1-12). */
function ch_mes_nome($n)
{
    $meses = [1 => 'Janeiro', 'Fevereiro', 'Março', 'Abril', 'Maio', 'Junho',
              'Julho', 'Agosto', 'Setembro', 'Outubro', 'Novembro', 'Dezembro'];
    return $meses[(int)$n] ?? '';
}

/* ============================================================================
   PERÍODOS DO RELATÓRIO DE DESENVOLVIMENTO
   ----------------------------------------------------------------------------
   O relatório é bimestral (6 períodos de 2 meses) ou semestral (2 períodos
   de 6 meses), à escolha na tela. O código do período é 'B1'..'B6' ou
   'S1'/'S2' (gravado em TB_RELATORIOS_ANUAIS.PERIODO).
   ============================================================================ */

/** Períodos do relatório: código => rótulo e meses (inclusive). */
function ch_periodos_relatorio()
{
    return [
        'B1' => ['rotulo' => '1º bimestre',  'mes_ini' => 1,  'mes_fim' => 2,  'tipo' => 'Bimestral'],
        'B2' => ['rotulo' => '2º bimestre',  'mes_ini' => 3,  'mes_fim' => 4,  'tipo' => 'Bimestral'],
        'B3' => ['rotulo' => '3º bimestre',  'mes_ini' => 5,  'mes_fim' => 6,  'tipo' => 'Bimestral'],
        'B4' => ['rotulo' => '4º bimestre',  'mes_ini' => 7,  'mes_fim' => 8,  'tipo' => 'Bimestral'],
        'B5' => ['rotulo' => '5º bimestre',  'mes_ini' => 9,  'mes_fim' => 10, 'tipo' => 'Bimestral'],
        'B6' => ['rotulo' => '6º bimestre',  'mes_ini' => 11, 'mes_fim' => 12, 'tipo' => 'Bimestral'],
        'S1' => ['rotulo' => '1º semestre',  'mes_ini' => 1,  'mes_fim' => 6,  'tipo' => 'Semestral'],
        'S2' => ['rotulo' => '2º semestre',  'mes_ini' => 7,  'mes_fim' => 12, 'tipo' => 'Semestral'],
    ];
}

/** O código de período é válido? */
function ch_periodo_valido($cod)
{
    return isset(ch_periodos_relatorio()[(string)$cod]);
}

/** Rótulo do período ('1º bimestre', '2º semestre'...). */
function ch_periodo_rotulo($cod)
{
    return ch_periodos_relatorio()[(string)$cod]['rotulo'] ?? '';
}

/** Intervalo [início, fim] (ISO) do período no ano. null se inválido. */
function ch_periodo_intervalo($ano, $cod)
{
    $p = ch_periodos_relatorio()[(string)$cod] ?? null;
    if (!$p) return null;
    $ano = (int)$ano;
    $inicio = sprintf('%04d-%02d-01', $ano, $p['mes_ini']);
    $fim = date('Y-m-t', strtotime(sprintf('%04d-%02d-01', $ano, $p['mes_fim'])));
    return [$inicio, $fim];
}

/** Período bimestral em que estamos hoje (padrão dos filtros). */
function ch_periodo_atual()
{
    $mes = (int)date('n');
    return 'B' . (int)ceil($mes / 2);
}

/* ============================================================================
   EDUCAÇÃO ESPECIAL, AEE E OUTROS ACOMPANHAMENTOS DO ALUNO
   ----------------------------------------------------------------------------
   Os dados (condição/diagnóstico, outros acompanhamentos e AEE) são exibidos
   apenas na ficha interna do aluno. Quando um professor do sistema é vinculado
   como Professor de AEE do aluno, ele passa a enxergar a ficha e a registrar
   observações desse aluno no ano letivo de referência.
   ============================================================================ */

/** O aluno possui algum registro de condição específica, AEE ou acompanhamento? */
function ch_tem_acompanhamento_especial($aluno)
{
    if (!$aluno) return false;
    return trim((string)($aluno['CONDICAO_ESPECIFICA'] ?? '')) !== ''
        || trim((string)($aluno['OUTROS_ACOMPANHAMENTOS'] ?? '')) !== ''
        || (int)($aluno['PASSA_AEE'] ?? 0) === 1
        || (!empty($aluno['DEFICIENCIAS_INCLUSAO']) && $aluno['DEFICIENCIAS_INCLUSAO'] !== '[]');
}

/** Lista de professores ativos cadastrados na categoria AEE. */
function ch_professores_aee_opcoes($idIncluir = 0)
{
    $idIncluir = (int)$idIncluir;
    if ($idIncluir > 0) {
        return ch_rows(
            "SELECT ID, NOME, CATEGORIA FROM TB_USUARIOS
              WHERE NIVEL = 3 AND ATIVO = 1 AND (CATEGORIA = 'AEE' OR ID = ?)
              ORDER BY NOME",
            [$idIncluir]
        ) ?: [];
    }
    return ch_rows(
        "SELECT ID, NOME, CATEGORIA FROM TB_USUARIOS
          WHERE NIVEL = 3 AND ATIVO = 1 AND CATEGORIA = 'AEE'
          ORDER BY NOME"
    ) ?: [];
}

/** O usuário é professor da categoria AEE (ou atende algum aluno no AEE)? */
function ch_usuario_eh_professor_aee($idUsuario = null)
{
    $uid = $idUsuario === null ? ch_uid() : (int)$idUsuario;
    if ($uid <= 0) return false;
    $cat = eq_professor_categoria($uid);
    if ($cat === 'AEE') return true;
    return ch_professor_qtd_alunos_aee($uid) > 0;
}

/** O usuário logado pode marcar observação como restrita ao AEE? */
function ch_pode_marcar_obs_restrita_aee($idAluno = 0)
{
    if (!ch_professor()) return false;
    if ((int)$idAluno > 0) {
        return ch_eh_professor_aee_aluno((int)$idAluno) || eq_professor_categoria(ch_uid()) === 'AEE';
    }
    return ch_usuario_eh_professor_aee(ch_uid());
}

/** O usuário logado pode ler esta linha de observação (considerando RESTRITA_AEE)? */
function ch_pode_ler_obs_restrita_aee($obs)
{
    if (!$obs) return false;
    if ((int)($obs['RESTRITA_AEE'] ?? 0) === 0) return true;
    if (!ch_professor()) return false;
    $uid = ch_uid();
    if ((int)($obs['ID_USUARIO'] ?? 0) === $uid) return true;
    return !empty($obs['ID_ALUNO']) && ch_eh_professor_aee_aluno((int)$obs['ID_ALUNO'], $uid);
}

/** Filtro SQL para observações restritas ao AEE.
 *  Quando $apenasCompartilhadas = true (relatórios e chamada), oculta todas as
 *  restritas inclusive do próprio autor; caso contrário, só o professor de AEE
 *  autor (ou professor de AEE atual do aluno) enxerga as restritas. */
function ch_obs_restrita_aee_filtro($aliasObs = 'o', $aliasAluno = 'a', $apenasCompartilhadas = false)
{
    if ($apenasCompartilhadas || !ch_professor()) {
        return ['sql' => " AND ISNULL({$aliasObs}.RESTRITA_AEE, 0) = 0", 'params' => []];
    }
    $uid = ch_uid();
    return [
        'sql'    => " AND (ISNULL({$aliasObs}.RESTRITA_AEE, 0) = 0 OR {$aliasObs}.ID_USUARIO = ? OR ({$aliasAluno}.PASSA_AEE = 1 AND {$aliasAluno}.ID_PROFESSOR_AEE = ?))",
        'params' => [$uid, $uid],
    ];
}

/** Ano letivo de referência para os alunos de AEE de um professor. */
function ch_ano_professor_aee($idUsuario = null)
{
    static $cache = [];
    $uid = $idUsuario === null ? ch_uid() : (int)$idUsuario;
    if ($uid <= 0) return (int)date('Y');
    if (isset($cache[$uid])) return $cache[$uid];

    if ($uid === ch_uid() && ch_professor()) {
        $ctx = ch_contexto();
        if (!empty($ctx['turma']) && !empty($ctx['ano'])) {
            return $cache[$uid] = (int)$ctx['ano'];
        }
    }

    $anoAtual = (int)date('Y');
    $temAtual = (int)ch_val(
        'SELECT COUNT(*) FROM TB_ALUNOS a
         INNER JOIN TB_TURMAS t ON t.ID = a.ID_TURMA
         WHERE a.ATIVO = 1 AND a.PASSA_AEE = 1 AND a.ID_PROFESSOR_AEE = ?
           AND t.ATIVO = 1 AND t.ANO_LETIVO = ?',
        [$uid, $anoAtual]
    );
    if ($temAtual > 0) {
        return $cache[$uid] = $anoAtual;
    }

    $ultimo = (int)ch_val(
        'SELECT ISNULL(MAX(t.ANO_LETIVO), 0) FROM TB_ALUNOS a
         INNER JOIN TB_TURMAS t ON t.ID = a.ID_TURMA
         WHERE a.ATIVO = 1 AND a.PASSA_AEE = 1 AND a.ID_PROFESSOR_AEE = ?
           AND t.ATIVO = 1',
        [$uid]
    );
    return $cache[$uid] = ($ultimo > 0 ? $ultimo : $anoAtual);
}

/** Quantos alunos ativos o professor atende hoje no AEE (no ano de referência)? */
function ch_professor_qtd_alunos_aee($idUsuario = null)
{
    $uid = $idUsuario === null ? ch_uid() : (int)$idUsuario;
    if ($uid <= 0) return 0;
    return (int)ch_val(
        'SELECT COUNT(*) FROM TB_ALUNOS a
         INNER JOIN TB_TURMAS t ON t.ID = a.ID_TURMA
         WHERE a.ATIVO = 1 AND a.PASSA_AEE = 1 AND a.ID_PROFESSOR_AEE = ?
           AND t.ATIVO = 1 AND t.ANO_LETIVO = ?',
        [$uid, ch_ano_professor_aee($uid)]
    );
}

/** O professor logado (ou informado) é o Professor de AEE atual deste aluno? */
function ch_eh_professor_aee_aluno($alunoOuId, $idUsuario = null)
{
    $uid = $idUsuario === null ? ch_uid() : (int)$idUsuario;
    if ($uid <= 0) return false;
    if ($idUsuario === null && !ch_professor()) return false;

    $a = is_array($alunoOuId) ? $alunoOuId : ch_aluno((int)$alunoOuId);
    if (!$a) return false;

    return (int)($a['ATIVO'] ?? 0) === 1
        && (int)($a['PASSA_AEE'] ?? 0) === 1
        && (int)($a['ID_PROFESSOR_AEE'] ?? 0) === $uid
        && (int)($a['ANO_LETIVO'] ?? 0) === ch_ano_professor_aee($uid);
}

/** Filtro SQL para consultas de ALUNOS/OBSERVAÇÕES: combina turmas do alcance
 *  com os alunos em que o professor logado é Professor de AEE no ano atual. */
function ch_filtro_turma_ou_aee($aliasTurma = 't', $aliasAluno = 'a')
{
    if (!ch_professor()) {
        return ch_filtro_turma($aliasTurma);
    }

    $uid = ch_uid();
    $anoRef = ch_ano_professor_aee($uid);
    $ids = ch_turmas_ids();

    $condAee = "({$aliasAluno}.ATIVO = 1 AND {$aliasAluno}.PASSA_AEE = 1 AND {$aliasAluno}.ID_PROFESSOR_AEE = ? AND {$aliasTurma}.ATIVO = 1 AND {$aliasTurma}.ANO_LETIVO = ?)";
    if (!$ids) {
        return ['sql' => " AND {$condAee}", 'params' => [$uid, $anoRef]];
    }

    $ph = implode(',', array_fill(0, count($ids), '?'));
    return [
        'sql'    => " AND ({$aliasTurma}.ID IN ({$ph}) OR {$condAee})",
        'params' => array_merge($ids, [$uid, $anoRef]),
    ];
}

/* ============================================================================
   TRANSFERÊNCIAS
   Carregadas aqui para que qualquer tela que já use estes helpers também tenha
   as regras de transferência à mão. O arquivo só define funções, e não inclui
   este de volta — não há ciclo.
   ============================================================================ */
require_once(__DIR__ . '/transferencias.php');

/* ============================================================================
   EQUIPE POR TURNO, SUBSTITUTOS E SAÚDE
   Mesmo arranjo: só define funções, não inclui este arquivo de volta.
   ============================================================================ */
require_once(__DIR__ . '/equipe.php');

/* ============================================================================
   MODELOS DE TURMA (padrão de aulas por dia e matérias)
   ============================================================================ */
require_once(__DIR__ . '/modelos.php');

/* ============================================================================
   FAMÍLIA E RESPONSÁVEIS VINCULADOS POR CPF
   ============================================================================ */
require_once(__DIR__ . '/familia.php');

/* ============================================================================
   ATENDIMENTO EDUCACIONAL ESPECIALIZADO (AEE)
   ============================================================================ */
require_once(__DIR__ . '/aee.php');

/* ============================================================================
   INCLUSÃO E NECESSIDADES ESPECIAIS (CRACHÁS E CATEGORIAS)
   ============================================================================ */
require_once(ABSPATH . 'alunos/inclusao.php');

/* ============================================================================
   SUSPENSÃO DE ALUNOS — faltas por período, liberações e histórico
   Carregado aqui pelo mesmo motivo dos módulos acima: qualquer tela que já
   inclui inc/chamada.php passa a ter as regras à mão. O arquivo só define
   funções e não inclui este de volta, então não há ciclo.
   ============================================================================ */
require_once(__DIR__ . '/suspensao.php');
