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
function ch_pode_escrever_observacao_turma($idTurma, $idUnidade)
{
    if (ch_secretario_escolar()) {
        return in_array((int)$idUnidade, ch_unidades_observacoes_ids(2), true);
    }
    return ch_pode_turma($idTurma);
}

function ch_pode_escrever_observacao_aluno($idAluno)
{
    $aluno = ch_aluno($idAluno);
    return $aluno && ch_pode_escrever_observacao_turma($aluno['ID_TURMA'], $aluno['ID_UNIDADE']);
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
    $cache = eq_turmas_ids_em(ch_uid(), ch_hoje());
    return $cache;
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
        'SELECT t.ID, t.ID_UNIDADE, t.NOME, t.ANO_LETIVO, t.ETAPA_ENSINO, t.ID_PERIODO, t.ID_PROFESSOR,
                t.CAPACIDADE, t.NOTAS_ATIVAS, t.ATIVO,
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

/** Aluno completo, com turma/unidade/período. */
function ch_aluno($idAluno)
{
    return ch_row(
        'SELECT a.ID, a.ID_TURMA, a.MATRICULA, a.CPF, a.NOME, a.RESPONSAVEL, a.TELEFONE, a.EMAIL,
                CASE WHEN a.FOTO_DADOS IS NULL THEN 0 ELSE 1 END AS TEM_FOTO, a.FOTO_MIME, a.ATIVO,
                a.ALERGIAS, a.RESTRICOES_ALIMENTARES, a.MEDICACAO,
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
         WHERE a.ID = ?',
        [(int)$idAluno]
    );
}

function ch_pode_aluno($idAluno)
{
    $a = ch_aluno($idAluno);
    if (!$a) return false;
    if (ch_pode_turma($a['ID_TURMA'])) return true;

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
