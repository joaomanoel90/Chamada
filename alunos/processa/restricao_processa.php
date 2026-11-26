<?php
/* ============================================================================
   RESTRIÇÃO FAMILIAR — AÇÃO PRÓPRIA, FORA DA EDIÇÃO DO CADASTRO
   ----------------------------------------------------------------------------
   Cada restringir/liberar é uma transação com: UPDATE do vínculo, INSERT no
   histórico (data, ação, motivo e usuário), auditoria e — só ao restringir —
   uma observação orientando a emitir outro crachá sem aquele contato.
   ============================================================================ */
require_once(dirname(__DIR__, 2) . '/config.php');
require_once(DBAPI);
require_once(ABSPATH . 'inc/chamada.php');
ch_db();
ch_require_gestao_alunos();
require_once(ABSPATH . 'observacoes/processa/salvar_processa.php');

$idAluno  = (int)($_POST['aluno_id'] ?? $_GET['aluno_id'] ?? 0);
$aluno    = ch_exige_aluno_atual($idAluno);
$erros    = [];
$d        = ['ACAO' => '', 'VINCULO_ID' => 0, 'MOTIVO' => ''];
$vinculos = fam_responsaveis_aluno($idAluno);
$historico = fam_historico_restricoes($idAluno);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!ch_csrf_ok()) {
        header('Location: ' . ch_url('alunos/restricao.php?aluno_id=' . $idAluno));
        exit;
    }

    $d['ACAO']      = strtoupper(trim((string)($_POST['acao'] ?? '')));
    $d['VINCULO_ID'] = (int)($_POST['vinculo_id'] ?? 0);
    $d['MOTIVO']    = trim(strip_tags((string)($_POST['motivo'] ?? '')));

    /* O ID do vínculo vem de um campo oculto, mas só é aceito se pertencer
       a este aluno: não se confia no que o navegador enviou. */
    $vinculoEscolhido = null;
    foreach ($vinculos as $v) {
        if ((int)$v['ID'] === $d['VINCULO_ID']) {
            $vinculoEscolhido = $v;
            break;
        }
    }

    if (!in_array($d['ACAO'], ['RESTRINGIR', 'LIBERAR'], true)) {
        $erros[] = 'Selecione uma ação válida.';
    }
    if (!$vinculoEscolhido) {
        $erros[] = 'Selecione um contato vinculado a este aluno.';
    }
    if ($d['MOTIVO'] === '') {
        $erros[] = 'Informe o motivo. Ele fica registrado no histórico.';
    } elseif (mb_strlen($d['MOTIVO']) > 500) {
        $erros[] = 'O motivo deve ter no máximo 500 caracteres.';
    }

    if (!$erros) {
        $conn = ch_db();
        if (!sqlsrv_begin_transaction($conn)) {
            $erros[] = 'Não foi possível iniciar o registro da restrição.';
        } else {
            $res = fam_restricao_aplicar($idAluno, $d['VINCULO_ID'], $d['ACAO'], $d['MOTIVO']);
            if (!$res['ok']) {
                sqlsrv_rollback($conn);
                $erros[] = $res['erro'];
            } else {
                $obsOk = true;
                if ($d['ACAO'] === 'RESTRINGIR' && !$res['ja_estava']) {
                    $texto = fam_restricao_texto_observacao(
                        $res['parentesco'],
                        $res['descricao_outro'],
                        $res['nome'],
                        $d['MOTIVO']
                    );
                    $obsOk = observacao_inserir_restricao_familiar($idAluno, $texto);
                }

                ch_auditar(
                    'Família',
                    $d['ACAO'] === 'RESTRINGIR' ? 'Restringir contato familiar' : 'Liberar contato familiar',
                    'ALUNO_RESPONSAVEL',
                    (string)$d['VINCULO_ID'],
                    [
                        'ID_ALUNO'           => (int)$idAluno,
                        'ACAO'               => $d['ACAO'],
                        'PARENTESCO'         => $res['parentesco'],
                        'NOME'               => $res['nome'],
                        'MOTIVO'             => $d['MOTIVO'],
                        'JA_ESTAVA'          => $res['ja_estava'] ? 1 : 0,
                        'OBSERVACAO_GRAVADA' => $obsOk ? 1 : 0,
                    ]
                );

                if (!sqlsrv_commit($conn)) {
                    sqlsrv_rollback($conn);
                    $erros[] = 'Não foi possível salvar a restrição. Tente novamente.';
                    if (ch_admin()) $erros[] = ch_erro_sql();
                } else {
                    $rotulo = fam_parentesco_rotulo($res['parentesco'], $res['descricao_outro']);
                    if ($d['ACAO'] === 'RESTRINGIR') {
                        $msg = $res['ja_estava']
                            ? 'O contato ' . $rotulo . ' já estava restrito; nada foi alterado.'
                            : 'Contato ' . $rotulo . ' restrito no crachá. Histórico e observação registrados.';
                    } else {
                        $msg = $res['ja_estava']
                            ? 'O contato ' . $rotulo . ' já estava liberado; nada foi alterado.'
                            : 'Restrição do contato ' . $rotulo . ' retirada. Histórico registrado.';
                    }
                    $tipo = 'success';
                    if ($d['ACAO'] === 'RESTRINGIR' && !$res['ja_estava'] && !$obsOk) {
                        $msg .= ' A observação automática não pôde ser gravada: o Diretor precisa autorizar observações nesta unidade.';
                        $tipo = 'warning';
                    }
                    ch_flash($msg, $tipo);
                    header('Location: ' . ch_url('alunos/restricao.php?aluno_id=' . $idAluno));
                    exit;
                }
            }
        }
    }

    $vinculos  = fam_responsaveis_aluno($idAluno);
    $historico = fam_historico_restricoes($idAluno);
}
