<?php
/* ============================================================================
   ETAPA 2 — a escola de destino escolhe a turma e a data de início
   ----------------------------------------------------------------------------
   É aqui, e só aqui, que o aluno muda de turma. A data escolhida separa os dois
   períodos: até a véspera ele pertence à turma de origem, daquele dia em diante
   à turma de destino. A chamada de datas passadas não muda.
   ============================================================================ */
require_once(dirname(__DIR__, 2) . '/config.php');
require_once(DBAPI);
require_once(ABSPATH . 'inc/chamada.php');
ch_db();
ch_require_gestor();

$id = (int)($_POST['id'] ?? $_GET['id'] ?? 0);
$t  = tr_transferencia($id);

if (!$t || !tr_pode_ver($t)) {
    ch_flash('Transferência não encontrada ou fora do seu alcance.', 'danger');
    header('Location: ' . ch_url('transferencias/index.php'));
    exit;
}
if ($t['SITUACAO'] !== 'PENDENTE') {
    ch_flash('Esta transferência não está mais aguardando.', 'warning');
    header('Location: ' . ch_url('transferencias/ver.php?id=' . $id));
    exit;
}
if (!tr_pode_receber($t)) {
    ch_flash('Só a gestão da escola de destino pode escolher a turma e a data de início.', 'danger');
    header('Location: ' . ch_url('transferencias/ver.php?id=' . $id));
    exit;
}

/* de propósito NÃO é ch_exige_aluno(): o aluno ainda está na escola de origem,
   que em geral não é a de quem está recebendo. O acesso aqui vem da
   transferência, conferido acima. */
$aluno   = ch_aluno($t['ID_ALUNO']);
$turmas  = tr_turmas_destino($t['ID_UNIDADE_DESTINO']);
$dataMin = tr_data_minima($aluno);
$erros   = [];
$d = ['ID_TURMA_DESTINO' => 0, 'DATA_INICIO' => '', 'OBSERVACAO_DESTINO' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!ch_csrf_ok()) {
        header('Location: ' . ch_url('transferencias/receber.php?id=' . $id));
        exit;
    }

    $d['ID_TURMA_DESTINO']    = (int)($_POST['id_turma_destino'] ?? 0);
    $d['DATA_INICIO']         = ch_data_iso($_POST['data_inicio'] ?? '');
    $d['OBSERVACAO_DESTINO']  = strip_tags(trim((string)($_POST['observacao_destino'] ?? '')));

    $turma = null;
    foreach ($turmas as $x) {
        if ((int)$x['ID'] === $d['ID_TURMA_DESTINO']) { $turma = $x; break; }
    }
    if (!$turma) {
        $erros[] = 'Selecione uma turma ativa da sua escola.';
    }
    if (!$d['DATA_INICIO']) {
        $erros[] = 'Informe a data de início do aluno na nova turma.';
    } elseif ($d['DATA_INICIO'] < $dataMin) {
        $erros[] = 'A data de início não pode ser anterior a ' . ch_data_br($dataMin)
                 . ' (data de matrícula ou dia seguinte ao período anterior).';
    } elseif ((int)substr($d['DATA_INICIO'], 0, 4) > ((int)date('Y') + 1)) {
        $erros[] = 'A data de início está muito distante. Confira o ano.';
    }
    if (mb_strlen($d['OBSERVACAO_DESTINO']) > 500) {
        $erros[] = 'A observação deve ter no máximo 500 caracteres.';
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
    if (!$erros && (int)$aluno['ATIVO'] !== 1) {
        $erros[] = 'O aluno está inativo. Peça à escola de origem para reativar o cadastro.';
    }

    if (!$erros) {
        sqlsrv_begin_transaction(ch_db());

        /* O WHERE inclui a situação: se outra pessoa aceitou esta transferência
           um instante antes, nenhuma linha é alterada e o processo para. */
        $n = ch_afetadas(
            "UPDATE TB_TRANSFERENCIAS
             SET ID_TURMA_DESTINO = ?, DATA_INICIO = ?, OBSERVACAO_DESTINO = ?,
                 ID_USUARIO_EFETIVA = ?, EFETIVADA_EM = GETDATE(), SITUACAO = 'CONCLUIDA'
             WHERE ID = ? AND SITUACAO = 'PENDENTE'",
            [(int)$turma['ID'], $d['DATA_INICIO'],
             $d['OBSERVACAO_DESTINO'] !== '' ? $d['OBSERVACAO_DESTINO'] : null,
             ch_uid(), $id]
        );

        $ok = ($n === 1);
        if ($ok) {
            $ok = ch_exec('UPDATE TB_ALUNOS SET ID_TURMA = ? WHERE ID = ?',
                          [(int)$turma['ID'], (int)$aluno['ID']]);
        }

        if ($ok) {
            sqlsrv_commit(ch_db());

            /* Registros já lançados a partir da data escolhida continuam com o
               aluno — o histórico não é apagado. Vale avisar quem recebeu. */
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
            header('Location: ' . ch_url('transferencias/ver.php?id=' . $id));
            exit;
        }

        sqlsrv_rollback(ch_db());
        $erros[] = $n === 0
            ? 'Esta transferência acabou de ser tratada por outra pessoa. Recarregue a página.'
            : 'Não foi possível concluir a transferência.';
        if (ch_admin() && $n !== 0) { $erros[] = ch_erro_sql(); }
    }
}