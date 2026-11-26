<?php
require_once(dirname(__DIR__, 2) . '/config.php');
require_once(DBAPI);
require_once(ABSPATH . 'inc/chamada.php');
ch_db();
require_once(__DIR__ . '/salvar_processa.php');

$erros = [];
$unidades = ch_unidades_opcoes();
$calInicial = strtoupper(trim((string)($_GET['calendario'] ?? '')));
if (!in_array($calInicial, ['AMBOS', 'CRECHE', 'FUNDAMENTAL'], true)) $calInicial = 'AMBOS';
$d = ['DATA' => '', 'DESCRICAO' => '', 'TIPO' => 'INTEGRAL', 'HORA_INICIO' => '12:00', 'HORA_FIM' => '18:00',
      'ID_UNIDADE' => 0, 'CALENDARIO' => $calInicial];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!ch_csrf_ok()) {
        header('Location: index.php');
        exit;
    }
    [$d, $erros] = feriado_validar($_POST);
    if (!$erros) {
        sqlsrv_begin_transaction(ch_db());

        /* Banco ainda sem a migração continua aceitando feriado como AMBOS. */
        $temCalendario = ch_feriados_tem_calendario();
        $sql = 'INSERT INTO TB_FERIADOS (DATA, DESCRICAO, TIPO, HORA_INICIO, HORA_FIM, ID_UNIDADE'
             . ($temCalendario ? ', CALENDARIO' : '') . ')
              OUTPUT INSERTED.ID
              VALUES (?, ?, ?, ?, ?, ?' . ($temCalendario ? ', ?' : '') . ')';
        $params = [$d['DATA'], $d['DESCRICAO'], $d['TIPO'], $d['HORA_INICIO'], $d['HORA_FIM'],
                   $d['ID_UNIDADE'] > 0 ? $d['ID_UNIDADE'] : null];
        if ($temCalendario) $params[] = $d['CALENDARIO'];

        $idFeriado = ch_insert_id($sql, $params);
        if ($idFeriado > 0) {
            sqlsrv_commit(ch_db());
            ch_auditar('Feriados', 'Criar feriado', 'FERIADO', $idFeriado, [
                'DATA'        => $d['DATA'],
                'DESCRICAO'   => $d['DESCRICAO'],
                'TIPO'        => $d['TIPO'],
                'HORA_INICIO' => $d['HORA_INICIO'],
                'HORA_FIM'    => $d['HORA_FIM'],
                'CALENDARIO'  => $d['CALENDARIO'],
                'ID_UNIDADE'  => $d['ID_UNIDADE'] > 0 ? $d['ID_UNIDADE'] : null,
            ]);
            ch_flash('Feriado cadastrado com sucesso.');
            header('Location: index.php');
            exit;
        }
        sqlsrv_rollback(ch_db());
        $erros[] = 'Não foi possível salvar o feriado.';
    }
}
