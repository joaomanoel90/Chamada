<?php
require_once(dirname(__DIR__, 2) . '/config.php');
require_once(DBAPI);
require_once(ABSPATH . 'inc/chamada.php');
ch_db();
ch_require_gestor();
require_once(__DIR__ . '/salvar_processa.php');

$id = (int)($_POST['id'] ?? $_GET['id'] ?? 0);
$atual = ch_row(
    "SELECT tp.ID, tp.ID_TURMA, tp.ID_USUARIO, tp.ID_SUBSTITUIDO,
            CONVERT(varchar(10), tp.DATA_INICIO, 23) AS DATA_INICIO,
            CONVERT(varchar(10), tp.DATA_FIM, 23) AS DATA_FIM,
            ISNULL(tp.MOTIVO, '') AS MOTIVO,
            ISNULL(tp.VER_OBSERVACOES, 0) AS VER_OBSERVACOES,
            t.ID_UNIDADE
       FROM TB_TURMA_PROFESSORES tp
       INNER JOIN TB_TURMAS t ON t.ID = tp.ID_TURMA
      WHERE tp.ID = ? AND tp.PAPEL = 'SUBSTITUTO'",
    [$id]
);

if (!$atual || !ch_pode_unidade((int)$atual['ID_UNIDADE'])) {
    ch_flash('Substituição não encontrada ou fora da sua unidade.', 'danger');
    header('Location: index.php');
    exit;
}

$erros = [];
$op = subst_opcoes_form((int)$atual['ID_TURMA']);
$d = $atual;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!ch_csrf_ok()) {
        header('Location: edit.php?id=' . $id);
        exit;
    }
    [$d, $erros] = subst_validar($_POST, $op, $id);
    if (!$erros) {
        sqlsrv_begin_transaction(ch_db());
        $ok = subst_salvar($d, $id);
        if ($ok) {
            sqlsrv_commit(ch_db());
            ch_flash('Substituição atualizada com sucesso.');
            header('Location: index.php');
            exit;
        }
        sqlsrv_rollback(ch_db());
        $erros[] = 'Não foi possível atualizar a substituição.';
    }
}
