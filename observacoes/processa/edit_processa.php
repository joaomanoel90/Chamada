<?php
require_once(dirname(__DIR__, 2) . '/config.php');
require_once(DBAPI);
require_once(ABSPATH . 'inc/chamada.php');
ch_db();
require_once(__DIR__ . '/salvar_processa.php');

$id = (int)($_POST['id'] ?? $_GET['id'] ?? 0);
if (ch_secretario_escolar()) {
    ch_flash('O Secretário Escolar pode consultar ou inserir observações conforme autorização, mas não pode editar registros existentes.', 'danger');
    header('Location: index.php');
    exit;
}
$atual = ch_row('SELECT o.ID, o.ID_ALUNO, o.CATEGORIA, o.TEXTO, o.ID_USUARIO,
                        CONVERT(varchar(10), o.DATA, 23) AS DATA
                 FROM TB_OBSERVACOES o WHERE o.ID = ?', [$id]);

if (!$atual || !ch_pode_escrever_observacao_aluno($atual['ID_ALUNO'])) {
    if ($atual && ch_secretario_escolar()) {
        ch_flash('O Diretor precisa autorizar o registro de observações nesta unidade.', 'danger');
        header('Location: index.php');
        exit;
    }
    ch_flash('Observação não encontrada.', 'danger');
    header('Location: index.php');
    exit;
}

// Somente o autor ou a gestão podem editar.
if (!ch_gestor() && (int)$atual['ID_USUARIO'] !== ch_uid()) {
    ch_flash('Apenas o autor ou a gestão podem editar esta observação.', 'warning');
    header('Location: index.php');
    exit;
}

$erros = [];
$alunosDisponiveis = observacao_alunos();
$d = $atual;
$voltar = trim($_POST['voltar'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    [$d, $erros] = observacao_validar($_POST);
    if (!$erros) {
        sqlsrv_begin_transaction(ch_db());
        $ok = ch_exec('UPDATE TB_OBSERVACOES SET ID_ALUNO = ?, DATA = ?, CATEGORIA = ?, TEXTO = ?, MODIFIED = GETDATE() WHERE ID = ?',
                      [$d['ID_ALUNO'], $d['DATA'], $d['CATEGORIA'], $d['TEXTO'], $id]);
        if ($ok) {
            sqlsrv_commit(ch_db());
            ch_flash('Observação atualizada.');
            header('Location: ' . ch_url('alunos/view.php?id=' . $d['ID_ALUNO']));
            exit;
        }
        sqlsrv_rollback(ch_db());
        $erros[] = 'Não foi possível atualizar a observação.';
    }
}
