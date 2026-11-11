<?php
require_once(dirname(__DIR__, 2) . '/config.php');
require_once(DBAPI);
require_once(ABSPATH . 'inc/chamada.php');
ch_db();
ch_require_gestao_alunos();
require_once(__DIR__ . '/salvar_processa.php');

$erros = [];
$turmasDisponiveis = aluno_turmas_disponiveis();
$d = ['ID_TURMA' => (int)($_GET['turma_id'] ?? 0), 'MATRICULA' => '', 'NOME' => '', 'NASCIMENTO' => '',
      'CPF' => '',
      'DATA_MATRICULA' => ch_hoje(), 'RESPONSAVEL' => '', 'TELEFONE' => '', 'EMAIL' => '',
      'ALERGIAS' => '', 'RESTRICOES_ALIMENTARES' => '', 'MEDICACAO' => '', 'TEM_FOTO' => false];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!ch_csrf_ok()) {
        header('Location: ' . ch_url('alunos/add.php'));
        exit;
    }
    [$d, $erros] = aluno_validar($_POST);
    $fotoNova = !$erros ? aluno_foto_comprimir_upload($erros) : null;
    $d['TEM_FOTO'] = $fotoNova !== null;
    if (!$erros) {
        sqlsrv_begin_transaction(ch_db());
        $ok = ch_exec(
            'INSERT INTO TB_ALUNOS (ID_TURMA, MATRICULA, CPF, NOME, NASCIMENTO, DATA_MATRICULA, RESPONSAVEL, TELEFONE, EMAIL,
                                    ALERGIAS, RESTRICOES_ALIMENTARES, MEDICACAO, FOTO_DADOS, FOTO_MIME)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [$d['ID_TURMA'], $d['MATRICULA'], $d['CPF'], $d['NOME'], $d['NASCIMENTO'], $d['DATA_MATRICULA'],
             $d['RESPONSAVEL'], $d['TELEFONE'], $d['EMAIL'] !== '' ? $d['EMAIL'] : null,
             $d['ALERGIAS'] !== '' ? $d['ALERGIAS'] : null,
             $d['RESTRICOES_ALIMENTARES'] !== '' ? $d['RESTRICOES_ALIMENTARES'] : null,
             $d['MEDICACAO'] !== '' ? $d['MEDICACAO'] : null,
             aluno_foto_parametro($fotoNova['dados'] ?? null), $fotoNova['mime'] ?? null]
        );
        if ($ok) {
            sqlsrv_commit(ch_db());
            ch_flash('Aluno cadastrado com sucesso.');
            header('Location: index.php');
            exit;
        }
        sqlsrv_rollback(ch_db());
        $erros[] = 'Não foi possível salvar o aluno.';
    }
}
