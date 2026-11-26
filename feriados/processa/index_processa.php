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

/* Todas as exceções do ano, indexadas por data + calendário. A lista única
   abaixo cruza feriados e exceções para mostrar a situação efetiva de cada dia. */
$todasExcecoes = [];
if (ch_tabela_excecoes_calendario()) {
    foreach (ch_rows(
        'SELECT e.ID, CONVERT(varchar(10), e.DATA, 23) AS DATA, e.TIPO, e.MOTIVO, c.CALENDARIO
           FROM dbo.TB_CALENDARIO_EXCECOES e
           JOIN dbo.TB_CALENDARIOS c ON c.ID = e.ID_CALENDARIO
          WHERE c.ANO_LETIVO = ?
          ORDER BY e.DATA, e.ID',
        [$fAno]
    ) as $e) {
        $item = [
            'ID' => (int)$e['ID'],
            'DATA' => (string)$e['DATA'],
            'TIPO' => (string)$e['TIPO'],
            'MOTIVO' => (string)($e['MOTIVO'] ?? ''),
            'CALENDARIO' => (string)$e['CALENDARIO'],
        ];
        $todasExcecoes[$item['DATA'] . '|' . $item['CALENDARIO']] = $item;
    }
}

$dias = [];
$excecoesUsadas = [];
foreach ($feriados as $f) {
    $calendarios = $f['CALENDARIO'] === 'AMBOS' ? ['CRECHE', 'FUNDAMENTAL'] : [(string)$f['CALENDARIO']];
    foreach ($calendarios as $cal) {
        if ($fCalendario !== '' && $fCalendario !== 'AMBOS' && $cal !== $fCalendario) continue;

        $chave = (string)$f['DATA'] . '|' . $cal;
        $exc = $todasExcecoes[$chave] ?? null;
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
            'EXCECAO' => $exc,
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
