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
      'ID_PERIODO' => 0, 'ID_PROFESSOR' => 0, 'CAPACIDADE' => 20,
      'PROFESSORES' => [], 'REGENTE' => 0];

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

        $novoId = $ok ? (int)ch_val('SELECT SCOPE_IDENTITY() AS ID') : 0;

        if ($ok && $novoId > 0) {
            $ok = turma_equipe_salvar($novoId, $d['PROFESSORES'], $d['REGENTE']);
        }

        if ($ok && $novoId > 0) {
            sqlsrv_commit(ch_db());
            $qtde = count($d['PROFESSORES']);
            ch_flash('Turma cadastrada com sucesso' .
                     ($qtde ? ' com ' . $qtde . ' professor(a/es) na equipe.' : '.'));
            header('Location: index.php');
            exit;
        }
        sqlsrv_rollback(ch_db());
        $erros[] = 'Não foi possível salvar a turma.';
    }
}
