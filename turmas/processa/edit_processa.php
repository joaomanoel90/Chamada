<?php
require_once(dirname(__DIR__, 2) . '/config.php');
require_once(DBAPI);
require_once(ABSPATH . 'inc/chamada.php');
ch_db();
ch_require_gestor();
require_once(__DIR__ . '/salvar_processa.php');

$id = (int)($_POST['id'] ?? $_GET['id'] ?? 0);
$atual = ch_row('SELECT * FROM TB_TURMAS WHERE ID = ?', [$id]);
if (!$atual) {
    ch_flash('Turma não encontrada.', 'danger');
    header('Location: index.php');
    exit;
}
if (!ch_pode_unidade((int)$atual['ID_UNIDADE'])) {
    ch_flash('Você não tem acesso a essa turma.', 'danger');
    header('Location: index.php');
    exit;
}

$erros = [];
$op = turma_opcoes();
$d = array_merge($atual, turma_equipe_atual($id), turma_disciplinas_atual($id));
$d['ID_PROFESSOR'] = (int)($atual['ID_PROFESSOR'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!ch_csrf_ok()) {
        header('Location: edit.php?id=' . $id);
        exit;
    }
    [$d, $erros] = turma_validar($_POST, $id);
    if (!$erros) {
        sqlsrv_begin_transaction(ch_db());

        $ok = ch_exec(
            'UPDATE TB_TURMAS SET ID_UNIDADE = ?, NOME = ?, ANO_LETIVO = ?, ETAPA_ENSINO = ?, SERIE = ?, ID_PERIODO = ?,
                    CAPACIDADE = ?, NOTAS_ATIVAS = ? WHERE ID = ?',
            [$d['ID_UNIDADE'], $d['NOME'], $d['ANO_LETIVO'], $d['ETAPA_ENSINO'], $d['SERIE'] ?: null, $d['ID_PERIODO'],
             $d['CAPACIDADE'], $d['NOTAS_ATIVAS'], $id]
        );

        if ($ok) {
            $ok = eq_disciplinas_salvar($id, $d['DISCIPLINAS']);
        }

        if ($ok) {
            sqlsrv_commit(ch_db());
            ch_flash('Turma atualizada com sucesso.');
            header('Location: index.php');
            exit;
        }
        sqlsrv_rollback(ch_db());
        $detalhe = ch_erro_sql();
        $erros[] = 'Não foi possível atualizar a turma.'
                 . (ch_admin() && $detalhe !== '' ? ' Detalhe do banco: ' . $detalhe : '');
    }
}
