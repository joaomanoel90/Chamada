<?php
require_once(dirname(__DIR__, 2) . '/config.php');
require_once(DBAPI);
require_once(ABSPATH . 'inc/chamada.php');
ch_db();
ch_require_gestor();
require_once(__DIR__ . '/salvar_processa.php');

$erros = [];
$op = turma_opcoes();
$d = ['ID_UNIDADE' => 0, 'NOME' => '', 'SERIE' => '', 'ETAPA_ENSINO' => '', 'ANO_LETIVO' => (int)date('Y'),
      'ID_PERIODO' => 0, 'ID_PROFESSOR' => 0, 'AULAS_DIA' => 2, 'CAPACIDADE' => 20,
      'NOTAS_ATIVAS' => 0,
      'EQUIPE' => [], 'DISCIPLINAS' => []];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!ch_csrf_ok()) {
        header('Location: add.php');
        exit;
    }
    [$d, $erros] = turma_validar($_POST);
    if (!$erros) {
        sqlsrv_begin_transaction(ch_db());

        /* OUTPUT INSERTED.ID no próprio INSERT. Antes o código fazia
           SELECT SCOPE_IDENTITY() em outra chamada, que no SQL Server é outro
           lote/escopo e devolve NULL — a turma nunca era gravada. */
        $novoId = ch_insert_id(
            'INSERT INTO TB_TURMAS (ID_UNIDADE, NOME, ANO_LETIVO, ETAPA_ENSINO, SERIE, ID_PERIODO, ID_PROFESSOR, AULAS_DIA, CAPACIDADE, NOTAS_ATIVAS)
             OUTPUT INSERTED.ID
             VALUES (?, ?, ?, ?, ?, ?, NULL, ?, ?, ?)',
            [$d['ID_UNIDADE'], $d['NOME'], $d['ANO_LETIVO'], $d['ETAPA_ENSINO'], $d['SERIE'] ?: null, $d['ID_PERIODO'],
             $d['AULAS_DIA'], $d['CAPACIDADE'], $d['NOTAS_ATIVAS']]
        );
        $ok = $novoId > 0;

        if ($ok && $novoId > 0) {
            $ok = eq_disciplinas_salvar($novoId, $d['DISCIPLINAS']);
        }

        if ($ok && $novoId > 0) {
            sqlsrv_commit(ch_db());
            ch_flash('Turma cadastrada com sucesso. Atribua os professores no menu Atribuição.');
            header('Location: index.php');
            exit;
        }
        sqlsrv_rollback(ch_db());
        $detalhe = ch_erro_sql();
        $erros[] = 'Não foi possível salvar a turma.'
                 . (ch_admin() && $detalhe !== '' ? ' Detalhe do banco: ' . $detalhe : '');
    }
}
