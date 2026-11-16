<?php
/* ============================================================================
   PAINEL DE ATRIBUIÇÃO DE PROFESSORES
   ----------------------------------------------------------------------------
   Mostra as turmas agrupadas por etapa (da Creche ao Fundamental) e, dentro
   de cada turma, as matérias com as aulas semanais e o professor que as dá.

   - A gestão escolhe primeiro o PROFESSOR: aparecem só as turmas em que ele
     pode ser atribuído — as turmas da equipe dele MAIS as turmas compatíveis
     com a categoria do concurso dele (PEB I / PEB II, do cadastro). No
     começo do ano ele ainda não tem equipe, e a categoria é o que permite
     atribuir. As turmas já COMPLETAS (todas as matérias com professor) vêm
     marcadas. A gestão atribui: troca o professor, ajusta as aulas e
     adiciona ou remove matérias (a mesma tela que o formulário da turma
     usa). Quem recebe matéria entra na equipe da turma automaticamente.
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

/* A gestão escolhe primeiro o professor: o painel passa a mostrar só as
   turmas em que ele pode ser atribuído (equipe dele OU categoria do
   concurso compatível com a etapa). */
$fProfessor = $ehGestao ? (int)($_GET['professor'] ?? $_POST['professor'] ?? 0) : 0;
$professores = $ehGestao ? eq_professores_opcoes() : [];
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

/* Turmas do painel, já com as matérias e o professor de cada uma. */
$sqlTurmas =
    'SELECT t.ID, t.NOME, t.SERIE, t.ETAPA_ENSINO, t.ANO_LETIVO, t.ID_UNIDADE,
            u.unidade AS ESCOLA, p.NOME AS PERIODO
     FROM TB_TURMAS t
     INNER JOIN TB_UNIDADES u ON u.id = t.ID_UNIDADE
     INNER JOIN TB_PERIODOS p ON p.ID = t.ID_PERIODO
     WHERE t.ATIVO = 1 AND t.ANO_LETIVO = ?';
$params = [$fAno];

if ($ehProfessor && !$ehGestao) {
    /* O professor só vê as turmas em que dá alguma matéria. */
    $sqlTurmas .= ' AND EXISTS (SELECT 1 FROM TB_TURMA_DISCIPLINAS d
                                WHERE d.ID_TURMA = t.ID AND d.ID_PROFESSOR = ?)';
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

/* Matérias de todas as turmas do painel, de uma vez. */
$idsTurmas = array_map(function ($t) { return (int)$t['ID']; }, $turmas);
$disciplinasPorTurma = [];
$professoresPorTurma = [];
if ($idsTurmas) {
    $ph = implode(',', array_fill(0, count($idsTurmas), '?'));
    $discs = ch_rows(
        "SELECT d.ID_TURMA, d.ID, d.NOME, d.AULAS, d.ID_PROFESSOR, u.NOME AS PROFESSOR
         FROM TB_TURMA_DISCIPLINAS d
         LEFT JOIN TB_USUARIOS u ON u.ID = d.ID_PROFESSOR
         WHERE d.ID_TURMA IN ({$ph}) ORDER BY d.NOME",
        $idsTurmas
    ) ?: [];
    foreach ($discs as $d) {
        $disciplinasPorTurma[(int)$d['ID_TURMA']][] = $d;
    }
    /* Quem pode dar matéria em cada turma: a equipe fixa OU quem tem
       categoria do concurso compatível com a etapa da turma. */
    $membros = ch_rows(
        "SELECT DISTINCT tp.ID_TURMA, u.ID, u.NOME
         FROM TB_TURMA_PROFESSORES tp
         INNER JOIN TB_USUARIOS u ON u.ID = tp.ID_USUARIO
         WHERE tp.ID_TURMA IN ({$ph}) AND tp.ATIVO = 1 AND ISNULL(u.ATIVO, 1) = 1
           AND u.NIVEL = 3 AND tp.PAPEL <> 'SUBSTITUTO'
         ORDER BY u.NOME",
        $idsTurmas
    ) ?: [];
    foreach ($membros as $m) {
        $professoresPorTurma[(int)$m['ID_TURMA']][] = $m;
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

/* Elegíveis por turma: mapa id => ['NOME', 'MATERIAS'] (equipe + categoria).
   É o que alimenta os selects e a validação do salvamento. */
$elegiveisPorTurma = [];
foreach ($turmas as $t) {
    $idT = (int)$t['ID'];
    $etapa = $t['ETAPA_ENSINO'] ?: ($series[$t['SERIE'] ?? '']['etapa'] ?? '');
    $mapa = [];
    foreach ($professoresPorTurma[$idT] ?? [] as $m) {
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
    /* O select da turma lista os elegíveis. */
    $professoresPorTurma[$idT] = [];
    foreach ($mapa as $idP => $e) {
        $professoresPorTurma[$idT][] = ['ID' => $idP, 'NOME' => $e['NOME']];
    }
}

/* Turma COMPLETA = todas as matérias já têm professor atribuído. */
$statusPorTurma = [];
foreach ($turmas as $t) {
    $idT = (int)$t['ID'];
    $discsT = $disciplinasPorTurma[$idT] ?? [];
    $comProfessor = 0;
    foreach ($discsT as $d) {
        if (!empty($d['ID_PROFESSOR'])) { $comProfessor++; }
    }
    $total = count($discsT);
    $statusPorTurma[$idT] = [
        'total'         => $total,
        'com_professor' => $comProfessor,
        'sem_professor' => $total - $comProfessor,
        'completa'      => $total > 0 && $comProfessor === $total,
    ];
}

/* Resumo do professor escolhido: quantas turmas dele estão completas. */
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

/* Salvamento (só a gestão). Um formulário por turma. */
$erros = [];
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
    $idTurma = (int)($_POST['id_turma'] ?? 0);
    $turma = null;
    foreach ($turmas as $t) {
        if ((int)$t['ID'] === $idTurma) { $turma = $t; break; }
    }
    if (!$turma) {
        ch_flash('Turma não encontrada ou fora do seu alcance.', 'danger');
        header('Location: ' . ch_url('atribuicoes/index.php'));
        exit;
    }
    $lista = eq_disciplinas_ler_post($_POST);
    $erros = eq_disciplinas_validar($lista, $elegiveisPorTurma[$idTurma] ?? []);
    if (!$erros) {
        sqlsrv_begin_transaction(ch_db());
        $ok = eq_disciplinas_salvar($idTurma, $lista);
        if ($ok && sqlsrv_commit(ch_db())) {
            ch_flash('Atribuição da turma ' . $turma['NOME'] . ' salva.');
            header('Location: ' . ch_url('atribuicoes/index.php?ano=' . $fAno
                . ($fUnidade ? '&unidade=' . $fUnidade : '')
                . ($fProfessor ? '&professor=' . $fProfessor : '')));
            exit;
        }
        sqlsrv_rollback(ch_db());
        $erros[] = 'Não foi possível salvar a atribuição.';
        if (ch_admin()) { $erros[] = ch_erro_sql(); }
    }
}
