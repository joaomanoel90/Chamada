<?php
/* Dados do card de calendário do dashboard. Também é usado pelo endpoint
   dashboard_calendario.php, que devolve só o HTML do card para o filtro AJAX.
   Espera $hoje, $ano e $mes já definidos pelo chamador. */
if (!isset($hoje)) $hoje = ch_hoje();
if (!isset($ano)) $ano = (int)date('Y');
if (!isset($mes)) $mes = (int)date('n');

$calAno = (int)($_GET['cal_ano'] ?? $ano);
if ($calAno < 2000 || $calAno > 2100) $calAno = $ano;
/* cal_mes = 0 pede o ano inteiro; os demais valores são meses civis. */
$calMes = (int)($_GET['cal_mes'] ?? $mes);
if ($calMes < 0 || $calMes > 12) $calMes = $mes;
$calAnoTodo = $calMes === 0;
$calOpcoes = ch_calendarios_opcoes();
$calFiltroBruto = $_GET['calendario'] ?? '';
$calCodigo = strtoupper(trim(is_string($calFiltroBruto) ? $calFiltroBruto : ''));
if (!isset($calOpcoes[$calCodigo])) $calCodigo = '';
$calCodigos = $calCodigo !== '' ? [$calCodigo] : array_keys($calOpcoes);
if ($calAnoTodo) {
    $calPeriodoInicio = sprintf('%04d-01-01', $calAno);
    $calPeriodoFim = sprintf('%04d-12-31', $calAno);
} else {
    $calPeriodoInicio = sprintf('%04d-%02d-01', $calAno, $calMes);
    $calPeriodoFim = date('Y-m-t', strtotime($calPeriodoInicio));
}
/* Inclui a unidade das turmas do professor, que não tem vínculo em TB_ACESSO. */
$idsUnidade = ch_unidades_alcance();

$calRegras = [];
foreach ($calCodigos as $codigo) {
    $dados = ch_calendario_dados($calAno, $codigo);
    $calRegras[$codigo] = [
        'ROTULO' => ch_calendario_rotulo($codigo),
        'DADOS' => $dados,
        'TOTAL' => (int)ch_calendario_contador($calAno, $codigo)['TOTAL'],
    ];
}

/* Feriados do mês dentro do alcance do usuário. Instalações anteriores à
   migração não têm a coluna CALENDARIO: nela, a regra vale para ambos. */
$colCalendarioFeriado = ch_feriados_tem_calendario()
    ? 'CALENDARIO'
    : "CAST('AMBOS' AS nvarchar(20))";
$sqlFeriados = 'SELECT CONVERT(varchar(10), DATA, 23) AS DATA, DESCRICAO, TIPO,
                       HORA_INICIO, HORA_FIM, ' . $colCalendarioFeriado . ' AS CALENDARIO
                  FROM TB_FERIADOS
                 WHERE DATA >= ? AND DATA <= ? AND (ID_UNIDADE IS NULL';
$paramsFeriados = [$calPeriodoInicio, $calPeriodoFim];
if ($idsUnidade) {
    $sqlFeriados .= ' OR ID_UNIDADE IN (' . implode(',', array_fill(0, count($idsUnidade), '?')) . ')';
    $paramsFeriados = array_merge($paramsFeriados, $idsUnidade);
}
$sqlFeriados .= ')';
if ($calCodigo !== '' && ch_feriados_tem_calendario()) {
    $sqlFeriados .= " AND (CALENDARIO = ? OR CALENDARIO = 'AMBOS')";
    $paramsFeriados[] = $calCodigo;
}
$sqlFeriados .= ' ORDER BY DATA, TIPO, ID';
$feriadosMes = ch_rows($sqlFeriados, $paramsFeriados);

/* Uma data pode ter várias marcações (dois calendários, feriado + exceção,
   exceção global + de unidade + de turma). */
$calMarcas = [];
foreach ($calRegras as $codigo => $info) {
    $rotulo = $info['ROTULO'];
    foreach (['INICIO' => 'Início do ano letivo', 'FIM' => 'Fim do ano letivo'] as $chave => $descricao) {
        $dataIso = $info['DADOS'][$chave];
        if ($dataIso === null || $dataIso < $calPeriodoInicio || $dataIso > $calPeriodoFim) continue;
        $calMarcas[$dataIso][] = [
            'TIPO' => $chave,
            'SIGLA' => $chave === 'INICIO' ? 'I' : 'T',
            'TITULO' => $descricao . ' — ' . $rotulo . ' (' . ch_data_br($dataIso) . ')',
            'CALENDARIO' => $codigo,
            'ESCOPO' => 'Todas as unidades',
        ];
    }
    foreach (ch_excecoes_do_periodo($info['DADOS']['ID'], $calPeriodoInicio, $calPeriodoFim, $idsUnidade, ch_turmas_ids()) as $exc) {
        if ($exc['DATA'] < $calPeriodoInicio || $exc['DATA'] > $calPeriodoFim) continue;
        $letivo = $exc['TIPO'] === 'LETIVO';
        $calMarcas[$exc['DATA']][] = [
            'TIPO' => $letivo ? 'EXCECAO_LETIVO' : 'EXCECAO_NAO_LETIVO',
            'SIGLA' => $letivo ? 'L' : 'N',
            'TITULO' => 'Exceção ' . ($letivo ? 'letiva' : 'não letiva') . ' — ' . $rotulo
                      . ' · ' . $exc['ROTULO_ESCOPO']
                      . ($exc['MOTIVO'] !== '' ? ': ' . $exc['MOTIVO'] : ''),
            'CALENDARIO' => $codigo,
            'ESCOPO' => $exc['ROTULO_ESCOPO'],
        ];
    }
}
foreach ($feriadosMes as $f) {
    $aplica = $f['CALENDARIO'] === 'AMBOS' ? array_keys($calOpcoes) : [(string)$f['CALENDARIO']];
    $parcial = $f['TIPO'] === 'PARCIAL';
    $detalhe = $parcial
        ? 'Feriado parcial: ' . $f['DESCRICAO'] . ' (' . $f['HORA_INICIO'] . '–' . $f['HORA_FIM'] . ')'
        : 'Feriado integral: ' . $f['DESCRICAO'];
    foreach ($aplica as $codigo) {
        if (!isset($calRegras[$codigo])) continue;
        $calMarcas[(string)$f['DATA']][] = [
            'TIPO' => $parcial ? 'FERIADO_PARCIAL' : 'FERIADO_INTEGRAL',
            'SIGLA' => $parcial ? 'P' : 'F',
            'TITULO' => $detalhe . ' — ' . $calRegras[$codigo]['ROTULO'],
            'CALENDARIO' => $codigo,
            'ESCOPO' => 'Todas as unidades',
        ];
    }
}
ksort($calMarcas);

/* Grade semanal começando no domingo, com os dias vizinhos esmaecidos.
   A mesma lista serve para o cabeçalho e para a conferência do alinhamento. */
$calDiasSemana = ['Dom', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb'];
$calPrioridade = [
    'INICIO' => 6, 'FIM' => 6,
    'EXCECAO_NAO_LETIVO' => 5, 'EXCECAO_LETIVO' => 5,
    'FERIADO_INTEGRAL' => 4, 'FERIADO_PARCIAL' => 3,
];
$calClasseTipo = [
    'INICIO' => 'cal-inicio', 'FIM' => 'cal-fim',
    'EXCECAO_LETIVO' => 'cal-exletivo', 'EXCECAO_NAO_LETIVO' => 'cal-exnaoletivo',
    'FERIADO_INTEGRAL' => 'cal-feriado', 'FERIADO_PARCIAL' => 'cal-parcial',
];
/* Uma grade por mês. No modo "ano todo" são doze, mantendo a mesma
   precedência de marcações e a semana começando no domingo (N = 7). */
$calMeses = $calAnoTodo ? range(1, 12) : [$calMes];
$calGrades = [];
foreach ($calMeses as $mesGrade) {
    $inicioMes = sprintf('%04d-%02d-01', $calAno, $mesGrade);
    $fimMes = date('Y-m-t', strtotime($inicioMes));
    $inicioGrade = new DateTime($inicioMes);
    while ((int)$inicioGrade->format('N') !== 7) {
        $inicioGrade->modify('-1 day');
    }
    $fimGrade = new DateTime($fimMes);
    while ((int)$fimGrade->format('N') !== 6) {
        $fimGrade->modify('+1 day');
    }
    $celulas = [];
    for ($diaAtual = clone $inicioGrade; $diaAtual <= $fimGrade; $diaAtual->modify('+1 day')) {
        $iso = $diaAtual->format('Y-m-d');
        $marcas = $calMarcas[$iso] ?? [];
        $tipoPrincipal = '';
        foreach ($marcas as $marca) {
            if ($tipoPrincipal === '' || $calPrioridade[$marca['TIPO']] > $calPrioridade[$tipoPrincipal]) {
                $tipoPrincipal = $marca['TIPO'];
            }
        }
        $classes = 'cal-dia';
        if ($tipoPrincipal !== '') $classes .= ' ' . $calClasseTipo[$tipoPrincipal];
        elseif ((int)$diaAtual->format('N') >= 6) $classes .= ' cal-fds';
        if ($iso === $hoje) $classes .= ' cal-hoje';
        if ((int)$diaAtual->format('m') !== $mesGrade) $classes .= ' cal-fora';
        $celulas[] = [
            'ISO' => $iso,
            'DIA' => (int)$diaAtual->format('d'),
            'MARCAS' => $marcas,
            'CLASSE' => $classes,
        ];
    }
    $calGrades[] = [
        'MES' => $mesGrade,
        'ROTULO' => ch_mes_nome($mesGrade),
        'CELULAS' => $celulas,
    ];
}

$calMesAnterior = $calMes - 1;
$calAnoAnterior = $calAno;
if ($calMesAnterior < 1) { $calMesAnterior = 12; $calAnoAnterior--; }
$calMesSeguinte = $calMes + 1;
$calAnoSeguinte = $calAno;
if ($calMesSeguinte > 12) { $calMesSeguinte = 1; $calAnoSeguinte++; }
$calUrlMes = function ($anoParam, $mesParam) use ($calCodigo) {
    return ch_url('index.php?cal_ano=' . $anoParam . '&cal_mes=' . $mesParam
        . '&calendario=' . rawurlencode($calCodigo));
};
