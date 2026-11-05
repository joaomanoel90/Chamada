<?php
require_once(dirname(__DIR__, 2) . '/config.php');
require_once(DBAPI);
require_once(ABSPATH . 'inc/chamada.php');
ch_db();
require_once(__DIR__ . '/salvar_processa.php');

$erros = [];
$alunosDisponiveis = observacao_alunos();
$d = ['ID_ALUNO' => (int)($_GET['aluno_id'] ?? 0),
      'DATA' => ch_data_iso($_GET['data'] ?? '', ch_hoje()),
      'CATEGORIA' => 'Desenvolvimento geral', 'TEXTO' => ''];
$voltar = trim($_GET['voltar'] ?? $_POST['voltar'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    [$d, $erros] = observacao_validar($_POST);
    if (!$erros) {
        sqlsrv_begin_transaction(ch_db());
        $ok = ch_exec('INSERT INTO TB_OBSERVACOES (ID_ALUNO, DATA, CATEGORIA, TEXTO, ID_USUARIO) VALUES (?, ?, ?, ?, ?)',
                      [$d['ID_ALUNO'], $d['DATA'], $d['CATEGORIA'], $d['TEXTO'], ch_uid()]);
        if ($ok) {
            sqlsrv_commit(ch_db());
            ch_flash('Observação registrada com sucesso.');
            if ($voltar !== '' && strpos($voltar, '://') === false) {
                header('Location: ' . ch_url($voltar));
            } else {
                header('Location: ' . ch_url('alunos/view.php?id=' . $d['ID_ALUNO']));
            }
            exit;
        }
        sqlsrv_rollback(ch_db());
        $erros[] = 'Não foi possível salvar a observação.';
    }
}
