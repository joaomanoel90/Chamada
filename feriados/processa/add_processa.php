<?php
require_once(dirname(__DIR__, 2) . '/config.php');
require_once(DBAPI);
require_once(ABSPATH . 'inc/chamada.php');
ch_db();
require_once(__DIR__ . '/salvar_processa.php');

$erros = [];
$unidades = ch_unidades_opcoes();
$d = ['DATA' => '', 'DESCRICAO' => '', 'TIPO' => 'INTEGRAL', 'HORA_INICIO' => '12:00', 'HORA_FIM' => '18:00', 'ID_UNIDADE' => 0];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!ch_csrf_ok()) {
        header('Location: index.php');
        exit;
    }
    [$d, $erros] = feriado_validar($_POST);
    if (!$erros) {
        sqlsrv_begin_transaction(ch_db());
        $ok = ch_exec('INSERT INTO TB_FERIADOS (DATA, DESCRICAO, TIPO, HORA_INICIO, HORA_FIM, ID_UNIDADE) VALUES (?, ?, ?, ?, ?, ?)',
            [$d['DATA'], $d['DESCRICAO'], $d['TIPO'], $d['HORA_INICIO'], $d['HORA_FIM'],
             $d['ID_UNIDADE'] > 0 ? $d['ID_UNIDADE'] : null]);
        if ($ok) {
            sqlsrv_commit(ch_db());
            ch_flash('Feriado cadastrado com sucesso.');
            header('Location: index.php');
            exit;
        }
        sqlsrv_rollback(ch_db());
        $erros[] = 'Não foi possível salvar o feriado.';
    }
}
