<?php
require_once(dirname(__DIR__, 2) . '/config.php');
require_once(DBAPI);
require_once(ABSPATH . 'inc/chamada.php');
ch_db();
ch_require_gestor();
require_once(__DIR__ . '/salvar_processa.php');

$id = (int)($_POST['id'] ?? $_GET['id'] ?? 0);
$aluno = ch_exige_aluno_atual($id);
$erros = [];
$turmasDisponiveis = aluno_turmas_disponiveis();
$d = $aluno;
$d['EMAIL'] = (string)($aluno['EMAIL'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!ch_csrf_ok()) {
        header('Location: ' . ch_url('alunos/edit.php?id=' . $id));
        exit;
    }
    /* A turma atual é passada como fixa: o UPDATE abaixo nem toca em ID_TURMA.
       Mudar de turma/escola é a transferência, não a edição do cadastro. */
    [$d, $erros] = aluno_validar($_POST, $id, (int)$aluno['ID_TURMA']);
    if (!$erros) {
        sqlsrv_begin_transaction(ch_db());
        $ok = ch_exec(
            'UPDATE TB_ALUNOS SET MATRICULA = ?, NOME = ?, NASCIMENTO = ?,
                    DATA_MATRICULA = ?, RESPONSAVEL = ?, TELEFONE = ?, EMAIL = ? WHERE ID = ?',
            [$d['MATRICULA'], $d['NOME'], $d['NASCIMENTO'], $d['DATA_MATRICULA'],
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
