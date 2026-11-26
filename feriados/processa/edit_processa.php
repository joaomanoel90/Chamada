<?php
require_once(dirname(__DIR__, 2) . '/config.php');
require_once(DBAPI);
require_once(ABSPATH . 'inc/chamada.php');
ch_db();
require_once(__DIR__ . '/salvar_processa.php');

$id = (int)($_POST['id'] ?? $_GET['id'] ?? 0);
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !ch_csrf_ok()) {
    header('Location: index.php');
    exit;
}

$temCalendario = ch_feriados_tem_calendario();
$colCalendario = $temCalendario
    ? 'CALENDARIO'
    : "CAST('AMBOS' AS nvarchar(20)) AS CALENDARIO";
$atual = ch_row(
    'SELECT ID, CONVERT(varchar(10), DATA, 23) AS DATA, DESCRICAO, TIPO, HORA_INICIO, HORA_FIM, ID_UNIDADE, '
    . $colCalendario . ' FROM TB_FERIADOS WHERE ID = ?',
    [$id]
);
if (!$atual) {
    ch_flash('Feriado não encontrado.', 'danger');
    header('Location: index.php');
    exit;
}
if (!feriado_pode_unidade($atual['ID_UNIDADE'])) {
    ch_flash('Feriado fora do seu alcance.', 'danger');
    header('Location: index.php');
    exit;
}

$erros = [];
$unidades = ch_unidades_opcoes();
$d = $atual;
$d['ID_UNIDADE'] = (int)($atual['ID_UNIDADE'] ?? 0);
$d['CALENDARIO'] = (string)($atual['CALENDARIO'] ?? 'AMBOS');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    [$d, $erros] = feriado_validar($_POST, $id);
    if (!$erros) {
        sqlsrv_begin_transaction(ch_db());
        $ok = ch_exec(
            'UPDATE TB_FERIADOS SET DATA = ?, DESCRICAO = ?, TIPO = ?, HORA_INICIO = ?, HORA_FIM = ?, ID_UNIDADE = ?'
            . ($temCalendario ? ', CALENDARIO = ?' : '') . ' WHERE ID = ?',
            array_merge(
                [$d['DATA'], $d['DESCRICAO'], $d['TIPO'], $d['HORA_INICIO'], $d['HORA_FIM'],
                 $d['ID_UNIDADE'] > 0 ? $d['ID_UNIDADE'] : null],
                $temCalendario ? [$d['CALENDARIO']] : [],
                [$id]
            )
        );
        if ($ok) {
            sqlsrv_commit(ch_db());
            ch_auditar('Feriados', 'Atualizar feriado', 'FERIADO', $id, [
                'ANTES' => [
                    'DATA'        => $atual['DATA'],
                    'DESCRICAO'   => $atual['DESCRICAO'],
                    'TIPO'        => $atual['TIPO'],
                    'HORA_INICIO' => $atual['HORA_INICIO'],
                    'HORA_FIM'    => $atual['HORA_FIM'],
                    'CALENDARIO'  => (string)($atual['CALENDARIO'] ?? 'AMBOS'),
                    'ID_UNIDADE'  => (int)($atual['ID_UNIDADE'] ?? 0),
                ],
                'DEPOIS' => [
                    'DATA'        => $d['DATA'],
                    'DESCRICAO'   => $d['DESCRICAO'],
                    'TIPO'        => $d['TIPO'],
                    'HORA_INICIO' => $d['HORA_INICIO'],
                    'HORA_FIM'    => $d['HORA_FIM'],
                    'CALENDARIO'  => $d['CALENDARIO'],
                    'ID_UNIDADE'  => $d['ID_UNIDADE'],
                ],
            ]);
            ch_flash('Feriado atualizado com sucesso.');
            header('Location: index.php');
            exit;
        }
        sqlsrv_rollback(ch_db());
        $erros[] = 'Não foi possível atualizar o feriado.';
    }
}
