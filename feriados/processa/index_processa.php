<?php
require_once(dirname(__DIR__, 2) . '/config.php');
require_once(DBAPI);
require_once(ABSPATH . 'inc/chamada.php');
ch_db();

$fAno = (int)($_GET['ano'] ?? (int)date('Y'));
$fCalendario = strtoupper(trim((string)($_GET['calendario'] ?? '')));
if (!in_array($fCalendario, ['', 'CRECHE', 'FUNDAMENTAL', 'AMBOS'], true)) {
    $fCalendario = '';
}

/* Instalação anterior à migração continua mostrando os feriados como AMBOS. */
$temCalendario = ch_feriados_tem_calendario();
$colCalendario = $temCalendario
    ? 'f.CALENDARIO'
    : "CAST('AMBOS' AS nvarchar(20))";
$params = [$fAno];
$where = '';
/* Feriados de uma única unidade só aparecem para quem alcança essa unidade;
   gestão da rede vê todos, professor alcança a unidade das suas turmas. */
if (!ch_rede()) {
    $unidadesFeriado = ch_unidades_alcance();
    if ($unidadesFeriado) {
        $where .= ' AND (f.ID_UNIDADE IS NULL OR f.ID_UNIDADE IN ('
                . implode(',', array_fill(0, count($unidadesFeriado), '?')) . '))';
        $params = array_merge($params, $unidadesFeriado);
    } else {
        $where .= ' AND f.ID_UNIDADE IS NULL';
    }
}
if ($temCalendario && $fCalendario === 'AMBOS') {
    $where = ' AND f.CALENDARIO = ?';
    $params[] = 'AMBOS';
} elseif ($temCalendario && $fCalendario !== '') {
    /* Um feriado AMBOS também vale para o calendário filtrado. */
    $where = ' AND (f.CALENDARIO = ? OR f.CALENDARIO = ?)';
    $params[] = $fCalendario;
    $params[] = 'AMBOS';
}

$feriados = ch_rows(
    'SELECT f.ID, CONVERT(varchar(10), f.DATA, 23) AS DATA, f.DESCRICAO, f.TIPO,
            f.HORA_INICIO, f.HORA_FIM, f.ID_UNIDADE, u.unidade AS UNIDADE, '
    . $colCalendario . ' AS CALENDARIO
     FROM TB_FERIADOS f LEFT JOIN TB_UNIDADES u ON u.id = f.ID_UNIDADE
     WHERE YEAR(f.DATA) = ?' . $where . '
     ORDER BY f.DATA, f.ID',
    $params
);

/* Anos com turma cadastrada, mais o ano corrente e o ano filtrado. */
$anos = [];
foreach (ch_rows('SELECT DISTINCT ANO_LETIVO FROM TB_TURMAS ORDER BY ANO_LETIVO DESC') as $row) {
    $anos[] = (int)$row['ANO_LETIVO'];
}
foreach ([(int)date('Y'), $fAno] as $anoExtra) {
    if (!in_array($anoExtra, $anos, true)) $anos[] = $anoExtra;
}
rsort($anos);
$anos = array_slice($anos, 0, 12);

$opcoesCalendario = ch_calendarios_opcoes();
$calendariosDoAno = ch_calendarios_do_ano($fAno);
$contadores = [];
foreach ($opcoesCalendario as $codigo => $rotulo) {
    $contadores[$codigo] = ch_calendario_contador($fAno, $codigo);
}

/* Todas as exceções do ano dentro do alcance do usuário, indexadas por data +
   calendário + abrangência. A lista abaixo cruza feriados e exceções para
   mostrar a situação efetiva de cada dia sem misturar regras de outras escolas. */
$todasExcecoes = [];
$temEscopo = ch_excecoes_tem_escopo();
if (ch_tabela_excecoes_calendario()) {
    $colEscopo = $temEscopo
        ? ', ISNULL(e.ID_UNIDADE, 0) AS ID_UNIDADE, ISNULL(e.ID_TURMA, 0) AS ID_TURMA'
        : ', CAST(0 AS int) AS ID_UNIDADE, CAST(0 AS int) AS ID_TURMA';
    $sqlExc = 'SELECT e.ID, CONVERT(varchar(10), e.DATA, 23) AS DATA, e.TIPO, e.MOTIVO, c.CALENDARIO'
            . $colEscopo . '
                FROM dbo.TB_CALENDARIO_EXCECOES e
                JOIN dbo.TB_CALENDARIOS c ON c.ID = e.ID_CALENDARIO
               WHERE c.ANO_LETIVO = ?';
    $paramsExc = [$fAno];
    if ($temEscopo) {
        $idsAlcance = ch_unidades_alcance();
        $sqlExc .= ' AND (e.ID_UNIDADE IS NULL';
        if ($idsAlcance) {
            $sqlExc .= ' OR e.ID_UNIDADE IN (' . implode(',', array_fill(0, count($idsAlcance), '?')) . ')';
            $paramsExc = array_merge($paramsExc, $idsAlcance);
        }
        $sqlExc .= ')';
    }
    $sqlExc .= ' ORDER BY e.DATA, e.ID';
    foreach (ch_rows($sqlExc, $paramsExc) as $e) {
        $escopo = ch_excecao_escopo_dados($e['ID_UNIDADE'], $e['ID_TURMA']);
        $item = [
            'ID' => (int)$e['ID'],
            'DATA' => (string)$e['DATA'],
            'TIPO' => (string)$e['TIPO'],
            'MOTIVO' => (string)($e['MOTIVO'] ?? ''),
            'CALENDARIO' => (string)$e['CALENDARIO'],
            'ESCOPO' => $escopo['ESCOPO'],
            'ROTULO_ESCOPO' => $escopo['ROTULO'],
            'ID_UNIDADE' => (int)$e['ID_UNIDADE'],
            'ID_TURMA' => (int)$e['ID_TURMA'],
            'PODE_GERENCIAR' => ch_pode_gerenciar_excecao($e['ID_UNIDADE'], $e['ID_TURMA']),
        ];
        $item['CHAVE'] = $item['DATA'] . '|' . $item['CALENDARIO'] . '|' . $item['ESCOPO']
                       . '|' . $item['ID_UNIDADE'] . '|' . $item['ID_TURMA'];
        $todasExcecoes[$item['CHAVE']] = $item;
    }
}

/** Exceção aplicável a um feriado: a da própria unidade ou, na falta dela, a global. */
function feriado_excecao_aplicavel($idUnidade, $calendario, $dataIso, $todasExcecoes)
{
    $unidade = (int)$idUnidade;
    if ($unidade > 0) {
        $chave = $dataIso . '|' . $calendario . '|UNIDADE|' . $unidade . '|0';
        if (isset($todasExcecoes[$chave])) return $todasExcecoes[$chave];
    }
    $chaveGlobal = $dataIso . '|' . $calendario . '|GLOBAL|0|0';
    return $todasExcecoes[$chaveGlobal] ?? null;
}

$dias = [];
$excecoesUsadas = [];
foreach ($feriados as $f) {
    $calendarios = $f['CALENDARIO'] === 'AMBOS' ? ['CRECHE', 'FUNDAMENTAL'] : [(string)$f['CALENDARIO']];
    foreach ($calendarios as $cal) {
        if ($fCalendario !== '' && $fCalendario !== 'AMBOS' && $cal !== $fCalendario) continue;

        $exc = feriado_excecao_aplicavel($f['ID_UNIDADE'], $cal, (string)$f['DATA'], $todasExcecoes);
        if ($exc) $excecoesUsadas[$exc['ID']] = true;

        if ($exc) {
            $efetivo = $exc['TIPO'];
        } elseif ($f['TIPO'] === 'INTEGRAL') {
            $efetivo = 'NAO_LETIVO';
        } else {
            $efetivo = 'PARCIAL';
        }

        $dias[] = [
            'ORIGEM' => 'FERIADO',
            'DATA' => (string)$f['DATA'],
            'CALENDARIO' => $cal,
            'FERIADO_ID' => (int)$f['ID'],
            'FERIADO_TIPO' => (string)$f['TIPO'],
            'DESCRICAO' => (string)$f['DESCRICAO'],
            'HORA_INICIO' => (string)($f['HORA_INICIO'] ?? ''),
            'HORA_FIM' => (string)($f['HORA_FIM'] ?? ''),
            'UNIDADE' => (string)($f['UNIDADE'] ?? ''),
            'ID_UNIDADE_FERIADO' => (int)($f['ID_UNIDADE'] ?? 0),
            'EXCECAO' => $exc,
            'ESCOPO' => $exc ? $exc['ESCOPO'] : '',
            'ROTULO_ESCOPO' => $exc ? $exc['ROTULO_ESCOPO'] : '',
            'ID_UNIDADE_EXCECAO' => $exc ? $exc['ID_UNIDADE'] : 0,
            'ID_TURMA_EXCECAO' => $exc ? $exc['ID_TURMA'] : 0,
            'TIPO_EXCECAO' => $exc ? $exc['TIPO'] : '',
            'MOTIVO' => $exc ? $exc['MOTIVO'] : '',
            'EFETIVO' => $efetivo,
        ];
    }
}

foreach ($todasExcecoes as $e) {
    if (isset($excecoesUsadas[$e['ID']])) continue;
    if ($fCalendario !== '' && $fCalendario !== 'AMBOS' && $e['CALENDARIO'] !== $fCalendario) continue;

    $dias[] = [
        'ORIGEM' => 'EXCECAO',
        'DATA' => $e['DATA'],
        'CALENDARIO' => $e['CALENDARIO'],
        'FERIADO_ID' => 0,
        'FERIADO_TIPO' => '',
        'DESCRICAO' => '',
        'HORA_INICIO' => '',
        'HORA_FIM' => '',
        'UNIDADE' => '',
        'EXCECAO' => $e,
        'ESCOPO' => $e['ESCOPO'],
        'ROTULO_ESCOPO' => $e['ROTULO_ESCOPO'],
        'ID_UNIDADE_EXCECAO' => $e['ID_UNIDADE'],
        'ID_TURMA_EXCECAO' => $e['ID_TURMA'],
        'TIPO_EXCECAO' => $e['TIPO'],
        'MOTIVO' => $e['MOTIVO'],
        'EFETIVO' => $e['TIPO'],
    ];
}

usort($dias, function ($a, $b) {
    $porData = strcmp($a['DATA'], $b['DATA']);
    if ($porData !== 0) return $porData;
    return strcmp($a['CALENDARIO'], $b['CALENDARIO']);
});

$unidades = ch_unidades_opcoes();

/* Turmas do ano no alcance do usuário, agrupadas por unidade para o select de
   abrangência. A compatibilidade com o calendário é validada no processador. */
$turmasPorUnidade = [];
$sqlTurmasOpcoes = 'SELECT t.ID, t.ID_UNIDADE, t.NOME, t.SERIE, t.ETAPA_ENSINO, u.unidade AS UNIDADE
                      FROM TB_TURMAS t INNER JOIN TB_UNIDADES u ON u.id = t.ID_UNIDADE
                     WHERE t.ANO_LETIVO = ?';
$paramsTurmasOpcoes = [$fAno];
$filtroTurmasOpcoes = ch_filtro_turma('t');
if ($filtroTurmasOpcoes['sql'] !== '') {
    $sqlTurmasOpcoes .= $filtroTurmasOpcoes['sql'];
    $paramsTurmasOpcoes = array_merge($paramsTurmasOpcoes, $filtroTurmasOpcoes['params']);
}
$sqlTurmasOpcoes .= ' ORDER BY u.unidade, t.NOME, t.ID';
foreach (ch_rows($sqlTurmasOpcoes, $paramsTurmasOpcoes) as $turmaOpcao) {
    $turmasPorUnidade[(int)$turmaOpcao['ID_UNIDADE']][] = $turmaOpcao;
}

