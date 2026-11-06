<?php
require_once(dirname(__DIR__, 2) . '/config.php');
require_once(DBAPI);
require_once(ABSPATH . 'inc/chamada.php');
ch_db();
ch_require_gestor();

$erro = '';
$nome = '';
$inicio = '07:00';
$fim = '12:00';
$turno = 'MANHA';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!ch_csrf_ok()) {
        header('Location: add.php');
        exit;
    }
    $nome   = strip_tags(trim($_POST['nome'] ?? ''));
    $inicio = trim($_POST['hora_inicio'] ?? '');
    $fim    = trim($_POST['hora_fim'] ?? '');
    $turno  = strtoupper(trim($_POST['turno'] ?? ''));

    $horaOk = function ($h) { return preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $h); };

    if (!isset(eq_turnos()[$turno])) {
        $erro = 'Escolha o turno do período: manhã, tarde ou integral.';
    } elseif ($nome === '' || mb_strlen($nome) > 60) {
        $erro = 'Informe o nome do período (até 60 caracteres).';
    } elseif (!$horaOk($inicio) || !$horaOk($fim)) {
        $erro = 'Informe horários válidos no formato HH:MM.';
    } elseif ($fim <= $inicio) {
        $erro = 'O horário final precisa ser maior que o inicial.';
    } elseif (ch_val('SELECT ID FROM TB_PERIODOS WHERE NOME = ?', [$nome])) {
        $erro = 'Já existe um período com esse nome.';
    } else {
        sqlsrv_begin_transaction(ch_db());
        $ok = ch_exec('INSERT INTO TB_PERIODOS (NOME, HORA_INICIO, HORA_FIM, TURNO) VALUES (?, ?, ?, ?)',
                      [$nome, $inicio, $fim, $turno]);
        if ($ok) {
            sqlsrv_commit(ch_db());
            ch_flash('Período cadastrado com sucesso.');
            header('Location: index.php');
            exit;
        }
        sqlsrv_rollback(ch_db());
        $erro = 'Não foi possível salvar o período.';
    }
}
