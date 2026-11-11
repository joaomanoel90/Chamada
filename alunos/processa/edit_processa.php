<?php
require_once(dirname(__DIR__, 2) . '/config.php');
require_once(DBAPI);
require_once(ABSPATH . 'inc/chamada.php');
ch_db();
ch_require_gestao_alunos();
require_once(__DIR__ . '/salvar_processa.php');

$id = (int)($_POST['id'] ?? $_GET['id'] ?? 0);
$aluno = ch_exige_aluno_atual($id);
$erros = [];
$turmasDisponiveis = aluno_turmas_disponiveis();
$d = $aluno;
$d['EMAIL'] = (string)($aluno['EMAIL'] ?? '');
$d['TEM_FOTO'] = !empty($aluno['TEM_FOTO']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!ch_csrf_ok()) {
        header('Location: ' . ch_url('alunos/edit.php?id=' . $id));
        exit;
    }
    /* A turma atual é passada como fixa: o UPDATE abaixo nem toca em ID_TURMA.
       Mudar de turma/escola é a transferência, não a edição do cadastro. */
    [$d, $erros] = aluno_validar($_POST, $id, (int)$aluno['ID_TURMA']);
    $fotoNova = !$erros ? aluno_foto_comprimir_upload($erros) : null;
    $removerFoto = !$erros && !$fotoNova && !empty($_POST['remover_foto']) && !empty($aluno['TEM_FOTO']);
    $d['TEM_FOTO'] = $fotoNova !== null || !empty($aluno['TEM_FOTO']);
    if (!$erros) {
        sqlsrv_begin_transaction(ch_db());
        $sqlFoto = '';
        $paramsFoto = [];
        if ($fotoNova) {
            $sqlFoto = ', FOTO_DADOS = ?, FOTO_MIME = ?';
            $paramsFoto = [aluno_foto_parametro($fotoNova['dados']), $fotoNova['mime']];
        } elseif ($removerFoto) {
            $sqlFoto = ', FOTO_DADOS = NULL, FOTO_MIME = NULL';
        }
        $ok = ch_exec(
            'UPDATE TB_ALUNOS SET MATRICULA = ?, CPF = ?, NOME = ?, NASCIMENTO = ?,
                    DATA_MATRICULA = ?, RESPONSAVEL = ?, TELEFONE = ?, EMAIL = ?,
                    ALERGIAS = ?, RESTRICOES_ALIMENTARES = ?, MEDICACAO = ?' . $sqlFoto . ' WHERE ID = ?',
            array_merge([$d['MATRICULA'], $d['CPF'], $d['NOME'], $d['NASCIMENTO'], $d['DATA_MATRICULA'],
             $d['RESPONSAVEL'], $d['TELEFONE'], $d['EMAIL'] !== '' ? $d['EMAIL'] : null,
             $d['ALERGIAS'] !== '' ? $d['ALERGIAS'] : null,
             $d['RESTRICOES_ALIMENTARES'] !== '' ? $d['RESTRICOES_ALIMENTARES'] : null,
             $d['MEDICACAO'] !== '' ? $d['MEDICACAO'] : null], $paramsFoto, [$id])
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
