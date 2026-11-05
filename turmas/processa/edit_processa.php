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

$erros = [];
$op = turma_opcoes();
$d = $atual;
$d['ID_PROFESSOR'] = (int)($atual['ID_PROFESSOR'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    [$d, $erros] = turma_validar($_POST, $id);
    if (!$erros) {
        sqlsrv_begin_transaction(ch_db());
        $ok = ch_exec(
            'UPDATE TB_TURMAS SET ID_UNIDADE = ?, NOME = ?, ANO_LETIVO = ?, ID_PERIODO = ?,
                    ID_PROFESSOR = ?, CAPACIDADE = ? WHERE ID = ?',
            [$d['ID_UNIDADE'], $d['NOME'], $d['ANO_LETIVO'], $d['ID_PERIODO'],
             $d['ID_PROFESSOR'] > 0 ? $d['ID_PROFESSOR'] : null, $d['CAPACIDADE'], $id]
        );
        if ($ok) {
            sqlsrv_commit(ch_db());
            ch_flash('Turma atualizada com sucesso.');
            header('Location: index.php');
            exit;
        }
        sqlsrv_rollback(ch_db());
        $erros[] = 'Não foi possível atualizar a turma.';
    }
}
