<?php
/* ============================================================================
   PAINEL DE ATRIBUIÇÃO DE PROFESSORES
   ----------------------------------------------------------------------------
   Mostra as turmas agrupadas por etapa (da Creche ao Fundamental) e, dentro
   de cada turma, as matérias com as aulas semanais e o professor que as dá.

   - A gestão escolhe primeiro o PROFESSOR: aparecem só as turmas em que ele
     pode ser atribuído (as turmas da equipe dele), com as turmas já
     COMPLETAS marcadas (todas as matérias com professor). A gestão atribui:
     troca o professor, ajusta as aulas e adiciona ou remove matérias (a
     mesma tela que o formulário da turma usa).
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
   turmas em que ele pode ser atribuído (as turmas da equipe dele). */
$fProfessor = $ehGestao ? (int)($_GET['professor'] ?? $_POST['professor'] ?? 0) : 0;
$professores = $ehGestao ? eq_professores_opcoes() : [];
$nomeProfessor = '';
if ($fProfessor > 0) {
    foreach ($professores as $p) {
        if ((int)$p['ID'] === $fProfessor) { $nomeProfessor = $p['NOME']; break; }
    }
    if ($nomeProfessor === '') { $fProfessor = 0; }
}

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
if ($fProfessor > 0) {
    /* Só as turmas em que o professor escolhido é da equipe (pode ser
       atribuído às matérias delas). */
    $sqlTurmas .= ' AND EXISTS (SELECT 1 FROM TB_TURMA_PROFESSORES tp
                                WHERE tp.ID_TURMA = t.ID AND tp.ID_USUARIO = ?
                                  AND tp.ATIVO = 1 AND tp.PAPEL <> \'SUBSTITUTO\')';
    $params[] = $fProfessor;
}
$sqlTurmas .= ' ORDER BY t.SERIE, u.unidade, t.NOME, p.NOME';

$turmas = ch_rows($sqlTurmas, $params) ?: [];

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
    /* Quem pode dar matéria em cada turma (a equipe fixa). */
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
}

/* Agrupa por etapa e série, na ordem de progressão. */
$etapas = [
    'INFANTIL_CRECHE'      => 'Educação Infantil — Creche',
    'INFANTIL_PRE_ESCOLA'  => 'Educação Infantil — Pré-escola',
    'FUNDAMENTAL_1_5'      => 'Ensino Fundamental — Anos Iniciais',
    'FUNDAMENTAL_6_9'      => 'Ensino Fundamental — Anos Finais',
];
$series = ch_series_ensino();
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
    $idsEquipe = [];
    foreach ($professoresPorTurma[$idTurma] ?? [] as $m) {
        $idsEquipe[(int)$m['ID']] = true;
    }
    $erros = eq_disciplinas_validar($lista, $idsEquipe);
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
