<?php
/* ============================================================================
   TROCA DE TURMA NA MESMA ESCOLA
   ----------------------------------------------------------------------------
   Diferente da transferência entre escolas (duas etapas), aqui a própria
   escola escolhe a turma e a data de início de uma vez — não há quem
   "receber". O registro nasce CONCLUIDO na TB_TRANSFERENCIAS, com a escola
   de destino igual à de origem, e o aluno muda de turma na hora.

   A data escolhida separa os dois períodos: até a véspera ele pertence à
   turma antiga, daquele dia em diante à nova. A chamada de datas passadas
   não muda, e o relatório anual mostra o ano inteiro.
   ============================================================================ */
require_once(dirname(__DIR__, 2) . '/config.php');
require_once(DBAPI);
require_once(ABSPATH . 'inc/chamada.php');
ch_db();
ch_require_gestao_alunos();

$idAluno = (int)($_POST['aluno_id'] ?? $_GET['aluno_id'] ?? 0);
$aluno   = ch_exige_aluno($idAluno);

/* ch_exige_aluno também deixa entrar quem só tem o histórico do aluno. Para
   TROCAR a turma é preciso ter gestão de alunos na escola atual. */
if (!ch_gestor() || !ch_pode_unidade($aluno['ID_UNIDADE'])) {
    ch_flash('Só a gestão da escola em que o aluno está matriculado pode trocar a turma dele.', 'danger');
    header('Location: ' . ch_url('alunos/view.php?id=' . $idAluno));
    exit;
}
if ((int)$aluno['ATIVO'] !== 1) {
    ch_flash('Este aluno está inativo. Reative o cadastro antes de trocar a turma.', 'danger');
    header('Location: ' . ch_url('alunos/view.php?id=' . $idAluno));
    exit;
}
if (tr_saida_externa_aberta($idAluno)) {
    ch_flash('Este aluno está com saída da rede registrada. Use Retorno à rede para matriculá-lo novamente.', 'warning');
    header('Location: ' . ch_url('alunos/view.php?id=' . $idAluno));
    exit;
}

$pendente = tr_pendente($idAluno);
if ($pendente) {
    ch_flash('Já existe uma transferência aguardando para este aluno. Conclua ou cancele-a antes de trocar a turma.', 'warning');
    header('Location: ' . ch_url('transferencias/ver.php?id=' . (int)$pendente['ID']));
    exit;
}

$turmas  = tr_turmas_mesma_escola($aluno['ID_UNIDADE'], $aluno['ANO_LETIVO'], $aluno['ID_TURMA']);
$dataMin = tr_data_minima($aluno);
$erros   = [];
$d = ['ID_TURMA_DESTINO' => 0, 'DATA_INICIO' => '', 'MOTIVO' => '', 'DOCUMENTO' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!ch_csrf_ok()) {
        header('Location: ' . ch_url('transferencias/trocar_turma.php?aluno_id=' . $idAluno));
        exit;
    }

    $d['ID_TURMA_DESTINO'] = (int)($_POST['id_turma_destino'] ?? 0);
    $d['DATA_INICIO']      = ch_data_iso($_POST['data_inicio'] ?? '');
    $d['MOTIVO']           = strip_tags(trim((string)($_POST['motivo'] ?? '')));
    $d['DOCUMENTO']        = strip_tags(trim((string)($_POST['documento'] ?? '')));

    $turma = null;
    foreach ($turmas as $x) {
        if ((int)$x['ID'] === $d['ID_TURMA_DESTINO']) { $turma = $x; break; }
    }
    if (!$turma) {
        $erros[] = 'Selecione uma turma ativa da sua escola (diferente da turma atual).';
    }
    if (!$d['DATA_INICIO']) {
        $erros[] = 'Informe a data de início do aluno na nova turma.';
    } elseif ($d['DATA_INICIO'] < $dataMin) {
        $erros[] = 'A data de início não pode ser anterior a ' . ch_data_br($dataMin)
                 . ' (data de matrícula ou dia seguinte ao período anterior).';
    } elseif ((int)substr($d['DATA_INICIO'], 0, 4) > ((int)date('Y') + 1)) {
        $erros[] = 'A data de início está muito distante. Confira o ano.';
    }
    if ($d['MOTIVO'] === '') {
        $erros[] = 'Informe o motivo da troca de turma.';
    } elseif (mb_strlen($d['MOTIVO']) > 500) {
        $erros[] = 'O motivo deve ter no máximo 500 caracteres.';
    }
    if (mb_strlen($d['DOCUMENTO']) > 100) {
        $erros[] = 'A referência do documento deve ter no máximo 100 caracteres.';
    }
    if (!$erros && $turma) {
        $ocupadas = (int)ch_val(
            'SELECT COUNT(*) FROM TB_ALUNOS WHERE ID_TURMA = ? AND ATIVO = 1 AND ID <> ?',
            [(int)$turma['ID'], (int)$aluno['ID']]
        );
        if ($ocupadas >= (int)$turma['CAPACIDADE']) {
            $erros[] = 'A turma ' . $turma['NOME'] . ' já atingiu a capacidade de '
                     . (int)$turma['CAPACIDADE'] . ' alunos.';
        }
    }

    /* Conferido de novo no servidor: outra pessoa pode ter aberto uma
       transferência para o mesmo aluno enquanto esta tela estava aberta. */
    if (!$erros && tr_pendente($idAluno)) {
        $erros[] = 'Já existe uma transferência aguardando para este aluno.';
    }

    if (!$erros) {
        $conn = ch_db();
        if (!sqlsrv_begin_transaction($conn)) {
            $erros[] = 'Não foi possível iniciar o registro da troca de turma.';
        } else {
            $alunoBloqueado = ch_row(
                'SELECT ID, ID_TURMA, ATIVO FROM TB_ALUNOS WITH (UPDLOCK, HOLDLOCK) WHERE ID = ?',
                [$idAluno]
            );
            $pendenteAtual = tr_pendente($idAluno);
            if (!$alunoBloqueado || (int)$alunoBloqueado['ATIVO'] !== 1
                || (int)$alunoBloqueado['ID_TURMA'] !== (int)$aluno['ID_TURMA']
                || $pendenteAtual || tr_saida_externa_aberta($idAluno)) {
                sqlsrv_rollback($conn);
                $erros[] = 'O cadastro mudou ou já há uma movimentação em andamento. Atualize a página antes de tentar novamente.';
            } else {
                $novoId = ch_insert_id(
                    'INSERT INTO TB_TRANSFERENCIAS
                        (ID_ALUNO, ID_TURMA_ORIGEM, ID_UNIDADE_DESTINO, ID_TURMA_DESTINO,
                         DATA_INICIO, MOTIVO, DOCUMENTO, ID_USUARIO_SOLIC,
                         ID_USUARIO_EFETIVA, EFETIVADA_EM, SITUACAO)
                     OUTPUT INSERTED.ID
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, GETDATE(), \'CONCLUIDA\')',
                    [$idAluno, (int)$alunoBloqueado['ID_TURMA'], (int)$aluno['ID_UNIDADE'],
                     (int)$turma['ID'], $d['DATA_INICIO'], $d['MOTIVO'],
                     $d['DOCUMENTO'] !== '' ? $d['DOCUMENTO'] : null, ch_uid(), ch_uid()]
                );
                $ok = ($novoId > 0);
                if ($ok) {
                    $ok = ch_exec('UPDATE TB_ALUNOS SET ID_TURMA = ? WHERE ID = ?',
                                 [(int)$turma['ID'], (int)$aluno['ID']]);
                }
                if ($ok && sqlsrv_commit($conn)) {
                    /* Registros já lançados a partir da data escolhida continuam
                       com o aluno — o histórico não é apagado. Vale avisar. */
                    $posteriores = (int)ch_val(
                        'SELECT COUNT(*) FROM TB_CHAMADA WHERE ID_ALUNO = ? AND DATA >= ?',
                        [(int)$aluno['ID'], $d['DATA_INICIO']]
                    );
                    $aviso = 'Aluno incluído em ' . $turma['NOME'] . ' a partir de ' . ch_data_br($d['DATA_INICIO']) . '.';
                    if ($posteriores > 0) {
                        $aviso .= ' Atenção: já existiam ' . $posteriores
                                . ' registro(s) de chamada nessa data ou depois. Eles foram mantidos no histórico do aluno.';
                        ch_flash($aviso, 'warning');
                    } else {
                        ch_flash($aviso);
                    }
                    header('Location: ' . ch_url('transferencias/ver.php?id=' . $novoId));
                    exit;
                }
                sqlsrv_rollback($conn);
                $erros[] = 'Não foi possível registrar a troca de turma.';
                if (ch_admin()) { $erros[] = ch_erro_sql(); }
            }
        }
    }
}
