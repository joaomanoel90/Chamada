<?php
require_once(dirname(__DIR__, 2) . '/config.php');
require_once(DBAPI);
require_once(ABSPATH . 'inc/chamada.php');
ch_db();
ch_require_gestao_alunos();

$id = (int)($_POST['id'] ?? $_GET['id'] ?? 0);
$aluno = ch_exige_aluno_atual($id);
$erros = [];
$saidaAberta = tr_saida_externa_aberta($id);
$ultimaTransferencia = ch_val(
    "SELECT CONVERT(varchar(10), MAX(DATA_INICIO), 23)
     FROM TB_TRANSFERENCIAS WHERE ID_ALUNO = ? AND SITUACAO = 'CONCLUIDA'",
    [$id]
);
$dataMinima = $aluno['DATA_MATRICULA'];
if ($ultimaTransferencia && $ultimaTransferencia > $dataMinima) {
    $dataMinima = $ultimaTransferencia;
}
$d = ['DATA_SAIDA' => ch_hoje(), 'ESCOLA_DESTINO' => '', 'MUNICIPIO_DESTINO' => '',
      'UF_DESTINO' => '', 'MOTIVO' => ''];

if ((int)$aluno['ATIVO'] !== 1) {
    ch_flash('Somente alunos ativos podem registrar saída da rede.', 'warning');
    header('Location: ' . ch_url('alunos/view.php?id=' . $id));
    exit;
}
if ($saidaAberta) {
    ch_flash('Este aluno já tem uma saída externa aguardando retorno.', 'warning');
    header('Location: ' . ch_url('alunos/view.php?id=' . $id));
    exit;
}
if (tr_pendente($id)) {
    ch_flash('Resolva a transferência pendente antes de registrar saída da rede.', 'warning');
    header('Location: ' . ch_url('alunos/view.php?id=' . $id));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!ch_csrf_ok()) {
        header('Location: ' . ch_url('alunos/saida.php?id=' . $id));
        exit;
    }

    $d['DATA_SAIDA'] = ch_data_iso($_POST['data_saida'] ?? '');
    $d['ESCOLA_DESTINO'] = strip_tags(trim((string)($_POST['escola_destino'] ?? '')));
    $d['MUNICIPIO_DESTINO'] = strip_tags(trim((string)($_POST['municipio_destino'] ?? '')));
    $d['UF_DESTINO'] = strtoupper(trim((string)($_POST['uf_destino'] ?? '')));
    $d['MOTIVO'] = strip_tags(trim((string)($_POST['motivo'] ?? '')));

    if (!$d['DATA_SAIDA']) {
        $erros[] = 'Informe a data do último dia na escola.';
    } elseif ($d['DATA_SAIDA'] < $dataMinima) {
        $erros[] = 'A data de saída não pode ser anterior ao início da matrícula atual (' . ch_data_br($dataMinima) . ').';
    } elseif ($d['DATA_SAIDA'] > ch_hoje()) {
        $erros[] = 'A data de saída não pode ser futura.';
    }
    if ($d['ESCOLA_DESTINO'] === '' || mb_strlen($d['ESCOLA_DESTINO']) > 150) {
        $erros[] = 'Informe o nome da escola de destino (até 150 caracteres).';
    }
    if ($d['MUNICIPIO_DESTINO'] === '' || mb_strlen($d['MUNICIPIO_DESTINO']) > 100) {
        $erros[] = 'Informe o município de destino (até 100 caracteres).';
    }
    if (!preg_match('/^[A-Z]{2}$/D', $d['UF_DESTINO'])) {
        $erros[] = 'Informe a sigla do estado de destino com duas letras.';
    }
    if ($d['MOTIVO'] === '' || mb_strlen($d['MOTIVO']) > 500) {
        $erros[] = 'Informe o motivo da saída (até 500 caracteres).';
    }

    if (!$erros) {
        $conn = ch_db();
        if (!sqlsrv_begin_transaction($conn)) {
            $erros[] = 'Não foi possível iniciar o registro da saída da rede.';
        } else {
            $alunoBloqueado = ch_row(
                'SELECT ID, ID_TURMA, ATIVO FROM TB_ALUNOS WITH (UPDLOCK, HOLDLOCK) WHERE ID = ?',
                [$id]
            );
            if (!$alunoBloqueado || (int)$alunoBloqueado['ATIVO'] !== 1
                || (int)$alunoBloqueado['ID_TURMA'] !== (int)$aluno['ID_TURMA']
                || tr_pendente($id) || tr_saida_externa_aberta($id)) {
                sqlsrv_rollback($conn);
                $erros[] = 'O cadastro mudou ou há uma transferência em andamento. Atualize a ficha antes de registrar a saída.';
            } else {
                $saidaId = ch_insert_id(
                    'INSERT INTO TB_ALUNO_SAIDAS_EXTERNAS
                        (ID_ALUNO, ID_TURMA_ORIGEM, DATA_SAIDA, ESCOLA_DESTINO,
                         MUNICIPIO_DESTINO, UF_DESTINO, MOTIVO, ID_USUARIO_SAIDA)
                     OUTPUT INSERTED.ID
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
                    [$id, (int)$alunoBloqueado['ID_TURMA'], $d['DATA_SAIDA'], $d['ESCOLA_DESTINO'],
                     $d['MUNICIPIO_DESTINO'], $d['UF_DESTINO'], $d['MOTIVO'], ch_uid()]
                );
                $afetadas = $saidaId > 0
                    ? ch_afetadas('UPDATE TB_ALUNOS SET ATIVO = 0 WHERE ID = ? AND ATIVO = 1', [$id])
                    : false;
                if ($saidaId > 0 && $afetadas === 1) {
                    if (sqlsrv_commit($conn)) {
                        ch_flash('Saída da rede registrada. O cadastro e o histórico foram preservados; use Retorno à rede quando o aluno voltar.');
                        header('Location: ' . ch_url('alunos/view.php?id=' . $id));
                        exit;
                    }
                }

                sqlsrv_rollback($conn);
                $erros[] = 'Não foi possível registrar a saída da rede.';
                if (ch_admin()) { $erros[] = ch_erro_sql(); }
            }
        }
    }
}
