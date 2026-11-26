<?php
/* Início e fim do ano letivo dos dois calendários globais. Somente a rede
   (níveis 1 e 4) altera; diretor e secretaria apenas consultam a tela. */
require_once(dirname(__DIR__, 2) . '/config.php');
require_once(DBAPI);
require_once(ABSPATH . 'inc/chamada.php');
ch_db();
ch_require_rede();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../index.php');
    exit;
}
if (!ch_csrf_ok()) {
    header('Location: ../index.php');
    exit;
}

$ano = (int)($_POST['ano'] ?? 0);
$calendario = strtoupper(trim((string)($_POST['calendario'] ?? '')));
$inicio = ch_data_iso((string)($_POST['inicio'] ?? ''));
$fim = ch_data_iso((string)($_POST['fim'] ?? ''));
$erros = [];
$opcoes = ch_calendarios_opcoes();

if ($ano < 2000 || $ano > 2100) {
    $erros[] = 'Ano letivo inválido.';
}
if (!isset($opcoes[$calendario])) {
    $erros[] = 'Calendário inválido.';
}
if (!$inicio) {
    $erros[] = 'Informe uma data de início válida.';
} elseif ((int)substr($inicio, 0, 4) !== $ano) {
    $erros[] = 'A data de início deve ser do ano letivo informado.';
}
if (!$fim) {
    $erros[] = 'Informe uma data final válida.';
} elseif ((int)substr($fim, 0, 4) !== $ano) {
    $erros[] = 'A data final deve ser do ano letivo informado.';
} elseif ($inicio && $fim < $inicio) {
    $erros[] = 'A data final não pode ser anterior ao início.';
}
if (!$erros && (!ch_tabela_calendarios() || !ch_calendario_tem_fim())) {
    $erros[] = 'Banco desatualizado. Rode sql_inicial/ATUALIZAR_CALENDARIO_ESCOLAR.sql.';
}

if (!$erros) {
    sqlsrv_begin_transaction(ch_db());
    $atual = ch_row(
        'SELECT ID, CONVERT(varchar(10), INICIO_ANO, 23) AS INICIO,
                CONVERT(varchar(10), FIM_ANO, 23) AS FIM
           FROM dbo.TB_CALENDARIOS WHERE ANO_LETIVO = ? AND CALENDARIO = ?',
        [$ano, $calendario]
    );
    $antes = $atual
        ? ['INICIO' => (string)$atual['INICIO'], 'FIM' => (string)$atual['FIM']]
        : null;

    if ($atual) {
        $id = (int)$atual['ID'];
        $ok = ch_exec(
            'UPDATE dbo.TB_CALENDARIOS SET INICIO_ANO = ?, FIM_ANO = ?, UPDATED = GETDATE() WHERE ID = ?',
            [$inicio, $fim, $id]
        );
        $acao = 'Atualizar período letivo';
    } else {
        $id = (int)ch_insert_id(
            'INSERT INTO dbo.TB_CALENDARIOS (ANO_LETIVO, CALENDARIO, INICIO_ANO, FIM_ANO)
             OUTPUT INSERTED.ID VALUES (?, ?, ?, ?)',
            [$ano, $calendario, $inicio, $fim]
        );
        $ok = $id > 0;
        $acao = 'Definir período letivo';
    }

    if ($ok && $id > 0) {
        sqlsrv_commit(ch_db());
        ch_auditar('Calendário Escolar', $acao, 'CALENDARIO', $id, [
            'ANTES'  => $antes,
            'DEPOIS' => [
                'ANO_LETIVO' => $ano,
                'CALENDARIO' => $calendario,
                'INICIO'     => $inicio,
                'FIM'        => $fim,
            ],
        ]);
        ch_flash('Período letivo salvo no calendário ' . $opcoes[$calendario] . '.');
        header('Location: ../index.php?ano=' . $ano);
        exit;
    }
    sqlsrv_rollback(ch_db());
    $erros[] = 'Não foi possível salvar o período letivo.';
}

if ($erros) {
    ch_flash(implode(' ', $erros), 'danger');
}
header('Location: ../index.php?ano=' . ($ano > 0 ? $ano : (int)date('Y')));
exit;
