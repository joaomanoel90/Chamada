<?php
require_once(dirname(__DIR__, 2) . '/config.php');
require_once(DBAPI);
require_once(ABSPATH . 'inc/chamada.php');
ch_db();
ch_require_gestor();
require_once(__DIR__ . '/salvar_processa.php');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../index.php');
    exit;
}
if (!ch_csrf_ok()) {
    header('Location: ../index.php');
    exit;
}

$id = (int)($_POST['id'] ?? 0);
$colCalendario = ch_feriados_tem_calendario()
    ? 'CALENDARIO'
    : "CAST('AMBOS' AS nvarchar(20)) AS CALENDARIO";
$f = ch_row('SELECT ID, CONVERT(varchar(10), DATA, 23) AS DATA, DESCRICAO, ID_UNIDADE, '
            . $colCalendario . ' FROM TB_FERIADOS WHERE ID = ?', [$id]);

if (!$f) {
    ch_flash('Feriado não encontrado.', 'danger');
} elseif (!feriado_pode_unidade($f['ID_UNIDADE'])) {
    ch_flash('Feriado fora do seu alcance.', 'danger');
} elseif (ch_exec('DELETE FROM TB_FERIADOS WHERE ID = ?', [$id])) {
    ch_auditar('Feriados', 'Excluir feriado', 'FERIADO', $id, [
        'DATA'       => $f['DATA'],
        'DESCRICAO'  => $f['DESCRICAO'],
        'CALENDARIO' => (string)($f['CALENDARIO'] ?? 'AMBOS'),
        'ID_UNIDADE' => (int)($f['ID_UNIDADE'] ?? 0),
    ]);
    ch_flash('Feriado excluído. As chamadas já lançadas foram mantidas.');
} else {
    ch_flash('Não foi possível excluir o feriado.', 'danger');
}

header('Location: ../index.php');
exit;
