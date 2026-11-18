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
                        ISNULL(o.RESTRITA_AEE, 0) AS RESTRITA_AEE,
                        CONVERT(varchar(10), o.DATA, 23) AS DATA
                 FROM TB_OBSERVACOES o WHERE o.ID = ?', [$id]);

if (!$atual || !ch_pode_ler_obs_restrita_aee($atual) || !ch_pode_escrever_observacao_aluno($atual['ID_ALUNO'])) {
    if ($atual && !ch_pode_ler_obs_restrita_aee($atual)) {
        ch_flash('Esta observação é restrita ao professor de AEE.', 'danger');
        header('Location: index.php');
        exit;
    }
    if ($atual && ch_secretario_escolar()) {
        ch_flash('O Diretor precisa autorizar o registro de observações nesta unidade.', 'danger');
        header('Location: index.php');
        exit;
    }
    ch_flash('Observação não encontrada.', 'danger');
    header('Location: index.php');
    exit;
}

// Somente o autor ou a gestão podem editar (nas restritas ao AEE, somente o próprio professor de AEE).
if ((int)$atual['RESTRITA_AEE'] === 1 && (int)$atual['ID_USUARIO'] !== ch_uid()) {
    ch_flash('Apenas o professor de AEE autor pode editar uma observação restrita ao AEE.', 'warning');
    header('Location: index.php');
    exit;
}
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
    if (!ch_csrf_ok()) {
        header('Location: ' . ch_url('observacoes/index.php'));
        exit;
    }
    [$d, $erros] = observacao_validar($_POST);
    if (!$erros) {
        $conn = ch_db();
        $transacao = sqlsrv_begin_transaction($conn);
        $ok = $transacao;
        if ($ok) {
            $ok = ch_exec(
                'INSERT INTO TB_HISTORICO_OBSERVACOES
                    (ID_OBSERVACAO, ID_USUARIO_AUTOR, ID_USUARIO_ALTERACAO, ID_ALUNO, DATA, CATEGORIA, TEXTO, RESTRITA_AEE)
                 SELECT ID, ID_USUARIO, ?, ID_ALUNO, DATA, CATEGORIA, TEXTO, ISNULL(RESTRITA_AEE, 0)
                 FROM TB_OBSERVACOES WHERE ID = ?',
                [ch_uid(), $id]
            );
        }
        if ($ok) {
            $ok = ch_exec(
                'UPDATE TB_OBSERVACOES
                 SET ID_ALUNO = ?, DATA = ?, CATEGORIA = ?, TEXTO = ?, RESTRITA_AEE = ?, ID_USUARIO = ?, MODIFIED = GETDATE()
                 WHERE ID = ?',
                [$d['ID_ALUNO'], $d['DATA'], $d['CATEGORIA'], $d['TEXTO'], (int)$d['RESTRITA_AEE'], ch_uid(), $id]
            );
        }
        if ($ok && sqlsrv_commit($conn)) {
            ch_flash('Observação atualizada.');
            header('Location: ' . ch_url('alunos/view.php?id=' . $d['ID_ALUNO']));
            exit;
        }
        if ($transacao) sqlsrv_rollback($conn);
        $erros[] = 'Não foi possível atualizar a observação.';
    }
}
