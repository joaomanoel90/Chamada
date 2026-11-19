<?php
/* ============================================================================
   PAINEL DE ATRIBUIÇÃO DE PROFESSORES
   ----------------------------------------------------------------------------
   - Creche (Berçário I ao Maternal II): turma Integral sem matéria obrigatória;
     cada professor PDI — Creche assume 1 período (Manhã ou Tarde) e o
     Professor de Apoio — Creche assume o dia todo (Integral).
   - Pré-escola (Pré I e Pré II): atribui professor PEB I como regente da turma
     sem matéria.
   - Em todas as etapas (inclusive Creche e Pré I/II), se a turma tiver matérias
     específicas cadastradas em Turmas (ex.: Educação Física, Artes, Inglês),
     qualquer professor ativo que tenha aquela matéria no seu cadastro pode
     receber aulas dela.
   - Anos Iniciais / Finais: divisão de aulas por matéria (teto de 36 h/a).
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

/* Filtro por categoria da turma. */
$fCategoria = strtoupper(trim((string)($_GET['categoria'] ?? $_POST['categoria'] ?? '')));
if (!isset(eq_categorias_turma()[$fCategoria]) && !in_array($fCategoria, ['PDI', 'PEB_I'], true)) {
    $fCategoria = '';
}
$categoriasTurma = eq_categorias_turma();

/* Filtro por professor (AEE fica fora pois é vinculado na ficha do aluno). */
$fProfessor = $ehGestao ? (int)($_GET['professor'] ?? $_POST['professor'] ?? 0) : 0;
$professores = $ehGestao
    ? (ch_rows("SELECT ID, NOME, TURNO, CATEGORIA FROM TB_USUARIOS
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
            u.unidade AS ESCOLA, p.NOME AS PERIODO, p.TURNO AS TURNO_TURMA
     FROM TB_TURMAS t
     INNER JOIN TB_UNIDADES u ON u.id = t.ID_UNIDADE
     INNER JOIN TB_PERIODOS p ON p.ID = t.ID_PERIODO
     WHERE t.ATIVO = 1 AND t.ANO_LETIVO = ?';
$params = [$fAno];

if ($ehProfessor && !$ehGestao) {
    $sqlTurmas .= " AND (
        EXISTS (SELECT 1 FROM TB_ATRIBUICOES a
                INNER JOIN TB_TURMA_DISCIPLINAS d ON d.ID = a.ID_DISCIPLINA
                WHERE d.ID_TURMA = t.ID AND a.ID_PROFESSOR = ?)
        OR EXISTS (SELECT 1 FROM TB_TURMA_PROFESSORES tp
                   WHERE tp.ID_TURMA = t.ID AND tp.ID_USUARIO = ?
                     AND tp.ATIVO = 1 AND tp.PAPEL IN ('REGENTE', 'AUXILIAR'))
    )";
    $params[] = ch_uid();
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

/* Professores ativos (exceto AEE) e suas matérias cadastradas. */
$todosProfs = [];      // id => ['NOME' => ..., 'CATEGORIA' => ..., 'TURNO' => ...]
$materiasPorProf = []; // id => mapa [nome minúsculo => nome original]
if ($ehGestao) {
    foreach (ch_rows(
        "SELECT ID, NOME, CATEGORIA, TURNO FROM TB_USUARIOS
          WHERE NIVEL = 3 AND ISNULL(ATIVO, 1) = 1 AND ISNULL(CATEGORIA, '') <> 'AEE'
          ORDER BY NOME"
    ) ?: [] as $p) {
        $todosProfs[(int)$p['ID']] = [
            'NOME'      => (string)$p['NOME'],
            'CATEGORIA' => $p['CATEGORIA'] ?? null,
            'TURNO'     => $p['TURNO'] ?? 'INTEGRAL',
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

/* Filtro por categoria da turma. */
if ($fCategoria !== '') {
    $etapasCategoria = eq_etapas_filtro_turma($fCategoria);
    $turmas = array_values(array_filter($turmas, function ($t) use ($etapasCategoria, $series) {
        $etapa = $t['ETAPA_ENSINO'] ?: ($series[$t['SERIE'] ?? '']['etapa'] ?? '');
        return in_array($etapa, $etapasCategoria, true);
    }));
}

/* Pré-carrega matérias de todas as turmas candidatas para permitir filtrar também
   pelas matérias específicas que o professor selecionado leciona. */
$idsTurmasCand = array_map(fn($t) => (int)$t['ID'], $turmas);
$disciplinasPorTurma = [];
if ($idsTurmasCand) {
    $phC = implode(',', array_fill(0, count($idsTurmasCand), '?'));
    foreach (ch_rows(
        "SELECT d.ID_TURMA, d.ID, d.NOME, d.AULAS
           FROM TB_TURMA_DISCIPLINAS d
          WHERE d.ID_TURMA IN ({$phC}) ORDER BY d.NOME",
        $idsTurmasCand
    ) ?: [] as $d) {
        $disciplinasPorTurma[(int)$d['ID_TURMA']][] = $d;
    }
}

/* Se um professor foi selecionado no filtro: mostra turmas da equipe dele,
   turmas compatíveis com a categoria dele OU turmas que tenham alguma matéria
   específica que ele leciona (ex.: Educação Física na Creche/Pré). */
if ($fProfessor > 0) {
    $idsEquipeProf = [];
    foreach (ch_rows(
        "SELECT ID_TURMA FROM TB_TURMA_PROFESSORES
         WHERE ID_USUARIO = ? AND ATIVO = 1 AND PAPEL <> 'SUBSTITUTO'",
        [$fProfessor]
    ) ?: [] as $te) {
        $idsEquipeProf[(int)$te['ID_TURMA']] = true;
    }
    $matsProfSel = $materiasPorProf[$fProfessor] ?? [];
    $turmas = array_values(array_filter($turmas, function ($t) use ($idsEquipeProf, $categoriaProfessor, $series, $matsProfSel, $disciplinasPorTurma) {
        $idT = (int)$t['ID'];
        if (isset($idsEquipeProf[$idT])) return true;
        $etapa = $t['ETAPA_ENSINO'] ?: ($series[$t['SERIE'] ?? '']['etapa'] ?? '');
        if (eq_professor_pode_turma($categoriaProfessor, $etapa)) return true;
        if ($matsProfSel) {
            foreach ($disciplinasPorTurma[$idT] ?? [] as $disc) {
                if (eq_professor_pode_materia($matsProfSel, $disc['NOME'])) return true;
            }
        }
        return false;
    }));
}

$idsTurmas = array_map(fn($t) => (int)$t['ID'], $turmas);
$atribuicoesPorDisc = [];
$vinculosDiretosPorTurma = []; // idTurma => lista de linhas REGENTE / AUXILIAR ativas
if ($idsTurmas) {
    $idsDiscs = [];
    foreach ($idsTurmas as $idT) {
        foreach ($disciplinasPorTurma[$idT] ?? [] as $d) {
            $idsDiscs[] = (int)$d['ID'];
        }
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

    $phT = implode(',', array_fill(0, count($idsTurmas), '?'));
    foreach (ch_rows(
        "SELECT tp.ID, tp.ID_TURMA, tp.ID_USUARIO, tp.PAPEL, tp.TURNO,
                ISNULL(tp.VINCULO_DIRETO, 0) AS VINCULO_DIRETO,
                u.NOME AS PROFESSOR, u.CATEGORIA
           FROM TB_TURMA_PROFESSORES tp
           INNER JOIN TB_USUARIOS u ON u.ID = tp.ID_USUARIO
          WHERE tp.ID_TURMA IN ({$phT}) AND tp.ATIVO = 1 AND tp.PAPEL IN ('REGENTE', 'AUXILIAR')
          ORDER BY CASE tp.PAPEL WHEN 'REGENTE' THEN 1 ELSE 2 END,
                   CASE tp.TURNO WHEN 'MANHA' THEN 1 WHEN 'TARDE' THEN 2 ELSE 3 END,
                   u.NOME",
        $idsTurmas
    ) ?: [] as $vd) {
        /* Em Creche e Pré-escola exibimos os regentes e os apoios diretos (VINCULO_DIRETO = 1
           ou PAPEL = 'REGENTE'). Professores que entraram apenas por aulas de matéria
           específica (VINCULO_DIRETO = 0 e PAPEL = 'AUXILIAR') aparecem no card da matéria. */
        if ($vd['PAPEL'] === 'REGENTE' || (int)$vd['VINCULO_DIRETO'] === 1) {
            $vinculosDiretosPorTurma[(int)$vd['ID_TURMA']][] = $vd;
        }
    }
}

/* Opções de professores para vínculos sem matéria e para matérias específicas. */
$profsCrecheRegente = [];
$profsCrecheApoio   = [];
$profsPreRegente    = [];
$elegiveisPorTurma  = [];

if ($ehGestao) {
    foreach ($todosProfs as $idP => $p) {
        $cat = (string)($p['CATEGORIA'] ?? '');
        if ($cat === '' || $cat === 'PDI_CRECHE') {
            $profsCrecheRegente[$idP] = $p['NOME'];
        }
        if ($cat === '' || $cat === 'APOIO_CRECHE') {
            $profsCrecheApoio[$idP] = $p['NOME'];
        }
        if ($cat === '' || in_array($cat, ['PEB_I', 'PDI'], true)) {
            $profsPreRegente[$idP] = $p['NOME'];
        }
    }

    /* Para atribuição de matérias (inclusive matérias específicas como Educação
       Física na Creche/Pré), qualquer professor ativo que tenha matérias no
       cadastro é elegível na disciplina correspondente. */
    foreach ($turmas as $t) {
        $idT = (int)$t['ID'];
        $mapa = [];
        foreach ($todosProfs as $idP => $p) {
            if (!empty($materiasPorProf[$idP])) {
                $mapa[$idP] = ['NOME' => $p['NOME'], 'MATERIAS' => $materiasPorProf[$idP]];
            }
        }
        $elegiveisPorTurma[$idT] = $mapa;
    }
}

/* Status por turma (considerando regência sem matéria em Creche/Pré + matérias). */
$statusPorTurma = [];
foreach ($turmas as $t) {
    $idT = (int)$t['ID'];
    $etapa = $t['ETAPA_ENSINO'] ?: ($series[$t['SERIE'] ?? '']['etapa'] ?? '');
    $turnoTurma = $t['TURNO_TURMA'] ?: 'INTEGRAL';
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

    $vincs = $vinculosDiretosPorTurma[$idT] ?? [];
    $temManha = false;
    $temTarde = false;
    $apoios = [];
    $regentes = [];
    foreach ($vincs as $v) {
        if ($v['PAPEL'] === 'REGENTE') {
            $regentes[] = $v;
            if (in_array($v['TURNO'], ['MANHA', 'INTEGRAL'], true)) $temManha = true;
            if (in_array($v['TURNO'], ['TARDE', 'INTEGRAL'], true)) $temTarde = true;
        } else {
            $apoios[] = $v;
        }
    }

    if ($etapa === 'INFANTIL_CRECHE') {
        $regenciaOk = $temManha && $temTarde;
        $apoioOk = count($apoios) > 0;
        $turmaCompleta = $regenciaOk && $apoioOk && ($total === 0 || $completas === $total);
    } elseif ($etapa === 'INFANTIL_PRE_ESCOLA') {
        $regenciaOk = $turnoTurma === 'INTEGRAL' ? ($temManha && $temTarde) : (count($regentes) > 0);
        $apoioOk = true;
        $turmaCompleta = $regenciaOk && ($total === 0 || $completas === $total);
    } else {
        $regenciaOk = true;
        $apoioOk = true;
        $turmaCompleta = $total > 0 && $completas === $total;
    }

    $statusPorTurma[$idT] = [
        'etapa'        => $etapa,
        'turno_turma'  => $turnoTurma,
        'total'        => $total,
        'total_aulas'  => $totalAulas,
        'faltam'       => $faltam,
        'completas'    => $completas,
        'regencia_ok'  => $regenciaOk,
        'apoio_ok'     => $apoioOk,
        'tem_manha'    => $temManha,
        'tem_tarde'    => $temTarde,
        'regentes'     => $regentes,
        'apoios'       => $apoios,
        'completa'     => $turmaCompleta,
    ];
}

/* Resumo do professor escolhido. */
$resumoProfessor = null;
if ($fProfessor > 0) {
    $resumoProfessor = ['turmas' => count($turmas), 'completas' => 0, 'incompletas' => 0, 'sem_materias' => 0];
    foreach ($statusPorTurma as $s) {
        if ($s['completa']) {
            $resumoProfessor['completas']++;
        } elseif (!in_array($s['etapa'], ['INFANTIL_CRECHE', 'INFANTIL_PRE_ESCOLA'], true) && $s['total'] === 0) {
            $resumoProfessor['sem_materias']++;
        } else {
            $resumoProfessor['incompletas']++;
        }
    }
    $resumoProfessor['categoria'] = eq_categorias_concurso()[$categoriaProfessor]
        ?? 'Sem restrição (todas as etapas)';
    $resumoProfessor['materias'] = array_values($materiasPorProf[$fProfessor] ?? []);
    $resumoProfessor['carga'] = eq_professor_carga($fProfessor, $fAno);
    $resumoProfessor['carga_max'] = eq_carga_maxima();
}

/* Agrupa por etapa e série, na ordem de progressão. */
$etapas = [
    'INFANTIL_CRECHE'      => 'Educação Infantil — Creche (Integral: 1 período por regente + apoio o dia todo)',
    'INFANTIL_PRE_ESCOLA'  => 'Educação Infantil — Pré-escola (Pré I e Pré II: regente PEB I sem matéria)',
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

    if ($acao === 'atribuir_direto') {
        $idTurma = (int)($_POST['id_turma'] ?? 0);
        $idProf  = (int)($_POST['id_professor'] ?? 0);
        $papel   = (string)($_POST['papel'] ?? 'REGENTE');
        $turno   = (string)($_POST['turno'] ?? 'INTEGRAL');
        if (!in_array($idTurma, $idsTurmas, true)) {
            ch_flash('Turma não encontrada ou fora do seu alcance.', 'danger');
            header('Location: ' . ch_url($voltar));
            exit;
        }
        sqlsrv_begin_transaction(ch_db());
        [$ok, $erros] = eq_atribuir_vinculo_direto($idTurma, $idProf, $papel, $turno);
        if ($ok && sqlsrv_commit(ch_db())) {
            ch_flash('Professor atribuído à turma.');
            header('Location: ' . ch_url($voltar));
            exit;
        }
        sqlsrv_rollback(ch_db());
        if (!$erros) { $erros[] = 'Não foi possível gravar a atribuição.'; }
    } elseif ($acao === 'remover_direto') {
        $idTp = (int)($_POST['id_turma_prof'] ?? 0);
        $linha = ch_row('SELECT ID, ID_TURMA FROM TB_TURMA_PROFESSORES WHERE ID = ?', [$idTp]);
        if (!$linha || !in_array((int)$linha['ID_TURMA'], $idsTurmas, true)) {
            ch_flash('Vínculo não encontrado ou fora do seu alcance.', 'danger');
            header('Location: ' . ch_url($voltar));
            exit;
        }
        sqlsrv_begin_transaction(ch_db());
        $ok = eq_remover_vinculo_direto($idTp);
        if ($ok && sqlsrv_commit(ch_db())) {
            ch_flash('Professor removido da turma.');
            header('Location: ' . ch_url($voltar));
            exit;
        }
        sqlsrv_rollback(ch_db());
        $erros[] = 'Não foi possível remover o vínculo.';
    } elseif ($acao === 'atribuir') {
        $idDisc = (int)($_POST['id_disciplina'] ?? 0);
        $idProf = (int)($_POST['id_professor'] ?? 0);
        $aulas  = (int)($_POST['aulas'] ?? 0);
        $disc = ch_row('SELECT d.ID, d.ID_TURMA FROM TB_TURMA_DISCIPLINAS d WHERE d.ID = ?', [$idDisc]);
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
