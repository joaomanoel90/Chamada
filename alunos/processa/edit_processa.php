<?php
require_once(dirname(__DIR__, 2) . '/config.php');
require_once(DBAPI);
require_once(ABSPATH . 'inc/chamada.php');
ch_db();
ch_require_gestor();
require_once(__DIR__ . '/salvar_processa.php');

$id = (int)($_POST['id'] ?? $_GET['id'] ?? 0);
$aluno = ch_exige_aluno($id);
$erros = [];
$turmasDisponiveis = aluno_turmas_disponiveis();
$d = $aluno;
$d['EMAIL'] = (string)($aluno['EMAIL'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    [$d, $erros] = aluno_validar($_POST, $id);
    if (!$erros) {
        sqlsrv_begin_transaction(ch_db());
        $ok = ch_exec(
            'UPDATE TB_ALUNOS SET ID_TURMA = ?, MATRICULA = ?, NOME = ?, NASCIMENTO = ?,
                    DATA_MATRICULA = ?, RESPONSAVEL = ?, TELEFONE = ?, EMAIL = ? WHERE ID = ?',
            [$d['ID_TURMA'], $d['MATRICULA'], $d['NOME'], $d['NASCIMENTO'], $d['DATA_MATRICULA'],
             $d['RESPONSAVEL'], $d['TELEFONE'], $d['EMAIL'] !== '' ? $d['EMAIL'] : null, $id]
        );
        if ($ok) {
            sqlsrv_commit(ch_db());
            ch_flash('Cadastro do aluno atualizado.');
            header('Location: view.php?id=' . $id);
            exit;
        }
        sqlsrv_rollback(ch_db());
        $erros[] = 'Não foi possível atualizar o aluno.';
    }
}
