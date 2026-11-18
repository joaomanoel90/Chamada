<?php
/* ============================================================================
   PAINEL DE ATRIBUIÇÃO DE PROFESSORES
   ----------------------------------------------------------------------------
   A turma define as matérias e o TOTAL de aulas de cada uma (ex.: 10 de
   Matemática). As aulas são divididas entre os professores (ex.: 4 + 6),
   e o painel mostra quantas aulas ainda faltam ser atribuídas. Cada
   professor tem teto de 36 h/a por semana.

   - A gestão escolhe primeiro o PROFESSOR: aparecem só as turmas em que ele
     pode ser atribuído — as turmas da equipe dele MAIS as turmas compatíveis
     com a categoria do concurso dele (PDI / PEB I / PEB II, do cadastro).
     No começo do ano ele ainda não tem equipe, e a categoria é o que
     permite atribuir. As turmas já COMPLETAS (todas as aulas atribuídas)
     vêm marcadas. Quem recebe aulas entra na equipe da turma
     automaticamente.
   - O professor vê, em modo leitura, só as turmas e matérias dele.
   ============================================================================ */
require_once(dirname(__DIR__, 2) . '/config.php');
require_once(DBAPI);
require_once(ABSPATH . 'inc/chamada.php');
ch_db();

$ehGestao = ch_gestor();
$ehProfessor = ch_professor();
if (!$ehGestao && !$ehProfessor) {
    ch_flash('Acesso restrito à gestão e aos professores.', 'danger');
    header('Location: ' . BASEURL . 'index.php');
    exit;
}

$fAno     = (int)($_GET['ano'] ?? $_POST['ano'] ?? (int)date('Y'));
$fUnidade = (int)($_GET['unidade'] ?? $_POST['unidade'] ?? 0);
$unidades = ch_unidades_opcoes();
$anos     = ch_rows('SELECT DISTINCT ANO_LETIVO FROM TB_TURMAS ORDER BY ANO_LETIVO DESC') ?: [];

/* Filtro por categoria da turma (PDI-Creche, PDI-Pré-escola, PEB I, PEB II). */
$fCategoria = strtoupper(trim((string)($_GET['categoria'] ?? $_POST['categoria'] ?? '')));
if (!isset(eq_categorias_turma()[$fCategoria])) { $fCategoria = ''; }
$categoriasTurma = eq_categorias_turma();

/* A gestão escolhe primeiro o professor: o painel passa a mostrar só as
   turmas em que ele pode ser atribuído (equipe dele OU categoria do
   concurso compatível com a etapa). Professores de AEE são vinculados na
   ficha do aluno, não em matérias regulares de turma. */
$fProfessor = $ehGestao ? (int)($_GET['professor'] ?? $_POST['professor'] ?? 0) : 0;
$professores = $ehGestao
    ? (ch_rows("SELECT ID, NOME, TURNO FROM TB_USUARIOS
                 WHERE NIVEL = 3 AND ISNULL(ATIVO, 1) = 1 AND ISNULL(CATEGORIA, '') <> 'AEE'
                 ORDER BY NOME") ?: [])
    : [];
$nomeProfessor = '';
$categoriaProfessor = null;
if ($fProfessor > 0) {
    foreach ($professores as $p) {
        if ((int)$p['ID'] === $fProfessor) { $nomeProfessor = $p['NOME']; break; }
    }
    if ($nomeProfessor === '') { $fProfessor = 0; }
    else { $categoriaProfessor = eq_professor_categoria($fProfessor); }
}
$series = ch_series_ensino();

/* Turmas do painel. */
$sqlTurmas =
    'SELECT t.ID, t.NOME, t.SERIE, t.ETAPA_ENSINO, t.ANO_LETIVO, t.ID_UNIDADE,
            u.unidade AS ESCOLA, p.NOME AS PERIODO
     FROM TB_TURMAS t
     INNER JOIN TB_UNIDADES u ON u.id = t.ID_UNIDADE
     INNER JOIN TB_PERIODOS p ON p.ID = t.ID_PERIODO
     WHERE t.ATIVO = 1 AND t.ANO_LETIVO = ?';
$params = [$fAno];

if ($ehProfessor && !$ehGestao) {
    /* O professor só vê as turmas em que tem aulas atribuídas. */
    $sqlTurmas .= ' AND EXISTS (SELECT 1 FROM TB_ATRIBUICOES a
                                INNER JOIN TB_TURMA_DISCIPLINAS d ON d.ID = a.ID_DISCIPLINA
                                WHERE d.ID_TURMA = t.ID AND a.ID_PROFESSOR = ?)';
    $params[] = ch_uid();
} else {
    $filtro = ch_filtro_turma('t');
    if ($filtro['sql'] !== '') {
        $sqlTurmas .= $filtro['sql'];
        $params = array_merge($params, $filtro['params']);
    }
}
if ($fUnidade > 0) {
    $sqlTurmas .= ' AND t.ID_UNIDADE = ?';
    $params[] = $fUnidade;
}
$sqlTurmas .= ' ORDER BY t.SERIE, u.unidade, t.NOME, p.NOME';

$turmas = ch_rows($sqlTurmas, $params) ?: [];

/* Professor escolhido: só as turmas em que ele pode ser atribuído — as da
   equipe dele OU as compatíveis com a categoria do concurso. A etapa da
   turma pode vir da série, então o filtro é feito aqui, em PHP. */
if ($fProfessor > 0) {
    $idsEquipeProf = [];
    foreach (ch_rows(
        "SELECT ID_TURMA FROM TB_TURMA_PROFESSORES
         WHERE ID_USUARIO = ? AND ATIVO = 1 AND PAPEL <> 'SUBSTITUTO'",
        [$fProfessor]
    ) ?: [] as $te) {
        $idsEquipeProf[(int)$te['ID_TURMA']] = true;
    }
    $turmas = array_values(array_filter($turmas, function ($t) use ($idsEquipeProf, $categoriaProfessor, $series) {
        if (isset($idsEquipeProf[(int)$t['ID']])) return true;
        $etapa = $t['ETAPA_ENSINO'] ?: ($series[$t['SERIE'] ?? '']['etapa'] ?? '');
        return eq_professor_pode_turma($categoriaProfessor, $etapa);
    }));
}

/* Categoria da turma escolhida: só as turmas daquela etapa. A etapa pode
   vir da série, então o filtro é feito aqui, em PHP. */
if ($fCategoria !== '') {
    $etapasCategoria = eq_categoria_etapas($fCategoria) ?: [];
    $turmas = array_values(array_filter($turmas, function ($t) use ($etapasCategoria, $series) {
        $etapa = $t['ETAPA_ENSINO'] ?: ($series[$t['SERIE'] ?? '']['etapa'] ?? '');
        return in_array($etapa, $etapasCategoria, true);
    }));
}

/* Matérias e atribuições de todas as turmas do painel, de uma vez. */
$idsTurmas = array_map(function ($t) { return (int)$t['ID']; }, $turmas);
$disciplinasPorTurma = [];
$atribuicoesPorDisc = [];
if ($idsTurmas) {
    $ph = implode(',', array_fill(0, count($idsTurmas), '?'));
    $discs = ch_rows(
        "SELECT d.ID_TURMA, d.ID, d.NOME, d.AULAS
         FROM TB_TURMA_DISCIPLINAS d
         WHERE d.ID_TURMA IN ({$ph}) ORDER BY d.NOME",
        $idsTurmas
    ) ?: [];
    $idsDiscs = [];
    foreach ($discs as $d) {
        $disciplinasPorTurma[(int)$d['ID_TURMA']][] = $d;
        $idsDiscs[] = (int)$d['ID'];
    }
    if ($idsDiscs) {
        $phD = implode(',', array_fill(0, count($idsDiscs), '?'));
        foreach (ch_rows(
            "SELECT a.ID_DISCIPLINA, a.ID, a.ID_PROFESSOR, a.AULAS, u.NOME AS PROFESSOR
             FROM TB_ATRIBUICOES a
             INNER JOIN TB_USUARIOS u ON u.ID = a.ID_PROFESSOR
             WHERE a.ID_DISCIPLINA IN ({$phD}) ORDER BY u.NOME",
            $idsDiscs
        ) ?: [] as $a) {
            $atribuicoesPorDisc[(int)$a['ID_DISCIPLINA']][] = $a;
        }
    }
}

/* Professores e matérias de todos, de uma vez (para montar os elegíveis). */
$todosProfs = [];      // id => ['NOME' => ..., 'CATEGORIA' => ...]
$materiasPorProf = []; // id => mapa [nome em minúsculas => nome original]
if ($ehGestao) {
    foreach (ch_rows(
        'SELECT ID, NOME, CATEGORIA FROM TB_USUARIOS
         WHERE NIVEL = 3 AND ISNULL(ATIVO, 1) = 1 ORDER BY NOME'
    ) ?: [] as $p) {
        $todosProfs[(int)$p['ID']] = [
            'NOME'      => (string)$p['NOME'],
            'CATEGORIA' => $p['CATEGORIA'] ?? null,
        ];
    }
    if ($todosProfs) {
        $phP = implode(',', array_fill(0, count($todosProfs), '?'));
        foreach (ch_rows(
            "SELECT ID_USUARIO, NOME FROM TB_PROFESSOR_MATERIAS WHERE ID_USUARIO IN ({$phP}) ORDER BY NOME",
            array_keys($todosProfs)
        ) ?: [] as $mp) {
            $materiasPorProf[(int)$mp['ID_USUARIO']][mb_strtolower((string)$mp['NOME'], 'UTF-8')] = (string)$mp['NOME'];
        }
    }
}

/* Elegíveis por turma: mapa id => ['NOME', 'MATERIAS'] (equipe + categoria). */
$elegiveisPorTurma = [];
foreach ($turmas as $t) {
    $idT = (int)$t['ID'];
    $etapa = $t['ETAPA_ENSINO'] ?: ($series[$t['SERIE'] ?? '']['etapa'] ?? '');
    $mapa = [];
    foreach (ch_rows(
        "SELECT DISTINCT u.ID, u.NOME
         FROM TB_TURMA_PROFESSORES tp
         INNER JOIN TB_USUARIOS u ON u.ID = tp.ID_USUARIO
         WHERE tp.ID_TURMA = ? AND tp.ATIVO = 1 AND ISNULL(u.ATIVO, 1) = 1
           AND u.NIVEL = 3 AND tp.PAPEL <> 'SUBSTITUTO'",
        [$idT]
    ) ?: [] as $m) {
        $idP = (int)$m['ID'];
        $mapa[$idP] = ['NOME' => (string)$m['NOME'], 'MATERIAS' => $materiasPorProf[$idP] ?? []];
    }
    foreach ($todosProfs as $idP => $p) {
        if (!isset($mapa[$idP]) && eq_professor_pode_turma($p['CATEGORIA'], $etapa)) {
            $mapa[$idP] = ['NOME' => $p['NOME'], 'MATERIAS' => $materiasPorProf[$idP] ?? []];
        }
    }
    uasort($mapa, function ($a, $b) { return strcasecmp($a['NOME'], $b['NOME']); });
    $elegiveisPorTurma[$idT] = $mapa;
}

/* Status por matéria e por turma: quantas aulas ainda faltam. */
$statusPorTurma = [];
foreach ($turmas as $t) {
    $idT = (int)$t['ID'];
    $discsT = $disciplinasPorTurma[$idT] ?? [];
    $totalAulas = 0;
    $faltam = 0;
    $completas = 0;
    foreach ($discsT as $i => $d) {
        $idD = (int)$d['ID'];
        $atribuidas = 0;
        foreach ($atribuicoesPorDisc[$idD] ?? [] as $a) {
            $atribuidas += (int)$a['AULAS'];
        }
        $total = $d['AULAS'] === null ? null : (int)$d['AULAS'];
        $restam = $total === null ? null : max(0, $total - $atribuidas);
        $disciplinasPorTurma[$idT][$i]['ATRIBUIDAS'] = $atribuidas;
        $disciplinasPorTurma[$idT][$i]['RESTAM'] = $restam;
        if ($total !== null) {
            $totalAulas += $total;
            $faltam += $restam;
            if ($restam === 0) { $completas++; }
        }
    }
    $total = count($discsT);
    $statusPorTurma[$idT] = [
        'total'        => $total,
        'total_aulas'  => $totalAulas,
        'faltam'       => $faltam,
        'completas'    => $completas,
        'completa'     => $total > 0 && $completas === $total,
    ];
}

/* Resumo do professor escolhido: turmas completas + carga semanal. */
$resumoProfessor = null;
if ($fProfessor > 0) {
    $resumoProfessor = ['turmas' => count($turmas), 'completas' => 0, 'incompletas' => 0, 'sem_materias' => 0];
    foreach ($statusPorTurma as $s) {
        if ($s['total'] === 0) { $resumoProfessor['sem_materias']++; }
        elseif ($s['completa']) { $resumoProfessor['completas']++; }
        else { $resumoProfessor['incompletas']++; }
    }
    $resumoProfessor['categoria'] = eq_categorias_concurso()[$categoriaProfessor]
        ?? 'Sem restrição (todas as etapas)';
    $resumoProfessor['materias'] = array_values($materiasPorProf[$fProfessor] ?? []);
    $resumoProfessor['carga'] = eq_professor_carga($fProfessor, $fAno);
    $resumoProfessor['carga_max'] = eq_carga_maxima();
}

/* Agrupa por etapa e série, na ordem de progressão. */
$etapas = [
    'INFANTIL_CRECHE'      => 'Educação Infantil — Creche',
    'INFANTIL_PRE_ESCOLA'  => 'Educação Infantil — Pré-escola',
    'FUNDAMENTAL_1_5'      => 'Ensino Fundamental — Anos Iniciais',
    'FUNDAMENTAL_6_9'      => 'Ensino Fundamental — Anos Finais',
];
$grupos = [];
foreach ($turmas as $t) {
    $etapa = $t['ETAPA_ENSINO'] ?: ($series[$t['SERIE'] ?? '']['etapa'] ?? 'SEM_ETAPA');
    $serie = $t['SERIE'] ?: '';
    $rotuloSerie = $series[$serie]['rotulo'] ?? 'Série não classificada';
    $ordem = $series[$serie]['ordem'] ?? 999;
    $grupos[$etapa][$ordem . '|' . $rotuloSerie][] = $t;
}
foreach ($grupos as $etapa => $porSerie) {
    ksort($porSerie);
    $grupos[$etapa] = $porSerie;
}

/* ==========================================================================
   SALVAMENTO (só a gestão)
   ========================================================================== */
$erros = [];
$voltar = 'atribuicoes/index.php?ano=' . $fAno
    . ($fUnidade ? '&unidade=' . $fUnidade : '')
    . ($fProfessor ? '&professor=' . $fProfessor : '')
    . ($fCategoria ? '&categoria=' . $fCategoria : '');
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$ehGestao) {
        ch_flash('Somente a gestão pode atribuir professores.', 'danger');
        header('Location: ' . ch_url('atribuicoes/index.php'));
        exit;
    }
    if (!ch_csrf_ok()) {
        header('Location: ' . ch_url('atribuicoes/index.php'));
        exit;
    }
    $acao = is_string($_POST['acao'] ?? null) ? $_POST['acao'] : '';

    if ($acao === 'atribuir') {
        /* Atribui N aulas de uma matéria a um professor. */
        $idDisc = (int)($_POST['id_disciplina'] ?? 0);
        $idProf = (int)($_POST['id_professor'] ?? 0);
        $aulas  = (int)($_POST['aulas'] ?? 0);
        $disc = ch_row(
            'SELECT d.ID, d.ID_TURMA FROM TB_TURMA_DISCIPLINAS d WHERE d.ID = ?',
            [$idDisc]
        );
        $turmaOk = $disc && in_array((int)$disc['ID_TURMA'], $idsTurmas, true);
        if (!$turmaOk) {
            ch_flash('Matéria não encontrada ou fora do seu alcance.', 'danger');
            header('Location: ' . ch_url($voltar));
            exit;
        }
        $idTurma = (int)$disc['ID_TURMA'];
        sqlsrv_begin_transaction(ch_db());
        [$ok, $erros] = eq_atribuir($idDisc, $idProf, $aulas, $elegiveisPorTurma[$idTurma] ?? [], $fAno);
        if ($ok && sqlsrv_commit(ch_db())) {
            ch_flash('Aulas atribuídas.');
            header('Location: ' . ch_url($voltar));
            exit;
        }
        sqlsrv_rollback(ch_db());
        if (!$erros) { $erros[] = 'Não foi possível gravar a atribuição.'; }
    } elseif ($acao === 'remover_atribuicao') {
        $idAtribuicao = (int)($_POST['id_atribuicao'] ?? 0);
        $linha = ch_row(
            'SELECT a.ID, d.ID_TURMA FROM TB_ATRIBUICOES a
             INNER JOIN TB_TURMA_DISCIPLINAS d ON d.ID = a.ID_DISCIPLINA
             WHERE a.ID = ?',
            [$idAtribuicao]
        );
        if (!$linha || !in_array((int)$linha['ID_TURMA'], $idsTurmas, true)) {
            ch_flash('Atribuição não encontrada ou fora do seu alcance.', 'danger');
            header('Location: ' . ch_url($voltar));
            exit;
        }
        sqlsrv_begin_transaction(ch_db());
        $ok = eq_atribuicao_remover($idAtribuicao);
        if ($ok && sqlsrv_commit(ch_db())) {
            ch_flash('Atribuição removida.');
            header('Location: ' . ch_url($voltar));
            exit;
        }
        sqlsrv_rollback(ch_db());
        $erros[] = 'Não foi possível remover a atribuição.';
    }
}
