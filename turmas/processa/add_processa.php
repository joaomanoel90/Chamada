<?php
require_once(dirname(__DIR__, 2) . '/config.php');
require_once(DBAPI);
require_once(ABSPATH . 'inc/chamada.php');
ch_db();
ch_require_gestor();
require_once(__DIR__ . '/salvar_processa.php');

$erros = [];
$op = turma_opcoes();
$d = ['ID_UNIDADE' => 0, 'NOME' => '', 'ANO_LETIVO' => (int)date('Y'),
      'ID_PERIODO' => 0, 'ID_PROFESSOR' => 0, 'CAPACIDADE' => 20];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    [$d, $erros] = turma_validar($_POST);
    if (!$erros) {
        sqlsrv_begin_transaction(ch_db());
        $ok = ch_exec(
            'INSERT INTO TB_TURMAS (ID_UNIDADE, NOME, ANO_LETIVO, ID_PERIODO, ID_PROFESSOR, CAPACIDADE)
             VALUES (?, ?, ?, ?, ?, ?)',
            [$d['ID_UNIDADE'], $d['NOME'], $d['ANO_LETIVO'], $d['ID_PERIODO'],
             $d['ID_PROFESSOR'] > 0 ? $d['ID_PROFESSOR'] : null, $d['CAPACIDADE']]
        );
        if ($ok) {
            sqlsrv_commit(ch_db());
            ch_flash('Turma cadastrada com sucesso.');
            header('Location: index.php');
            exit;
        }
        sqlsrv_rollback(ch_db());
        $erros[] = 'Não foi possível salvar a turma.';
    }
}
