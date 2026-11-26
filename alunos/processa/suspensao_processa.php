<?php
/* ============================================================================
   SUSPENSÃO DE ALUNO — AÇÃO PRÓPRIA, FORA DA EDIÇÃO DO CADASTRO
   ----------------------------------------------------------------------------
   A gestão escolar informa início, fim e motivo. As faltas de todas as aulas
   letivas do período são gravadas em TB_CHAMADA (com retificação do que já
   estava lançado) e ligadas à suspensão. Depois é possível liberar datas/aulas
   específicas: nos dias que já passaram, a falta vira presença ou é removida.
   Toda ação vai para auditoria.
   ============================================================================ */
require_once(dirname(__DIR__, 2) . '/config.php');
require_once(DBAPI);
require_once(ABSPATH . 'inc/chamada.php');
require_once(ABSPATH . 'inc/suspensao.php');
ch_db();
ch_require_gestor();

$idAluno   = (int)($_POST['aluno_id'] ?? $_GET['aluno_id'] ?? 0);
$aluno     = ch_exige_aluno_atual($idAluno);
$erros     = [];
$d         = [
    'ACAO' => '', 'DATA_INICIO' => ch_hoje(), 'DATA_FIM' => ch_hoje(),
    'MOTIVO' => '', 'SUSPENSAO_ID' => 0, 'DIAS' => [], 'PRESENCA' => 0,
];
$historico = susp_historico($idAluno);
$emVigor   = susp_em_vigor($idAluno);

function susp_dados_post()
{
    $brutos = $_POST['dias'] ?? [];
    if (!is_array($brutos)) {
        $brutos = $brutos === '' ? [] : [$brutos];
    }
    $dias = [];
    foreach ($brutos as $bruto) {
        $valor = trim(strip_tags((string)$bruto));
        if ($valor !== '') $dias[] = $valor;
    }
    return array_values(array_unique($dias));
}

function susp_monta_liberaveis($historico)
{
    $lista = [];
    foreach ($historico as $s) {
        $itens = susp_itens((int)$s['ID']);
        if (!$itens) continue;
        $lista[] = [
            'ID' => (int)$s['ID'],
            'DATA_INICIO_TXT' => $s['DATA_INICIO_TXT'],
            'DATA_FIM_TXT' => $s['DATA_FIM_TXT'],
            'SITUACAO' => $s['SITUACAO'],
            'MOTIVO' => $s['MOTIVO'],
            'ITENS' => $itens,
        ];
    }
    return $lista;
}

/** Todas as liberações ficam visíveis, mesmo depois da suspensão encerrar. */
function susp_monta_liberacoes($historico)
{
    $lista = [];
    foreach ($historico as $s) {
        foreach (susp_liberacoes((int)$s['ID']) as $linha) {
            $linha['SUSPENSAO_ID'] = (int)$s['ID'];
            $linha['PERIODO'] = ch_data_br($s['DATA_INICIO_TXT'])
                              . ' a ' . ch_data_br($s['DATA_FIM_TXT']);
            $lista[] = $linha;
        }
    }
    return $lista;
}

$liberaveis = susp_monta_liberaveis($historico);
$liberacoes = susp_monta_liberacoes($historico);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!ch_csrf_ok()) {
        header('Location: ' . ch_url('alunos/suspensao.php?aluno_id=' . $idAluno));
        exit;
    }

    $d['ACAO']        = strtoupper(trim((string)($_POST['acao'] ?? '')));
    $d['DATA_INICIO'] = (string)(ch_data_iso((string)($_POST['data_inicio'] ?? '')) ?? '');
    $d['DATA_FIM']    = (string)(ch_data_iso((string)($_POST['data_fim'] ?? '')) ?? '');
    $d['MOTIVO']      = trim(strip_tags((string)($_POST['motivo'] ?? '')));
    $d['SUSPENSAO_ID'] = (int)($_POST['suspensao_id'] ?? 0);
    $d['DIAS']        = susp_dados_post();
    $d['PRESENCA']    = (string)($_POST['presenca'] ?? '') === '1' ? 1 : 0;

    if (!susp_pronto()) {
        $erros[] = 'Banco não atualizado. Execute sql_inicial/ATUALIZAR_SUSPENSAO_ALUNOS.sql no SQL Server.';
    }

    if (!in_array($d['ACAO'], ['SUSPENDER', 'LIBERAR'], true)) {
        $erros[] = 'Selecione uma ação válida.';
    }

    if ($d['MOTIVO'] === '') {
        $erros[] = 'Informe o motivo. Ele fica registrado no histórico.';
    } elseif (mb_strlen($d['MOTIVO']) > 500) {
        $erros[] = 'O motivo deve ter no máximo 500 caracteres.';
    }

    $escolhida = null;
    if ($d['ACAO'] === 'LIBERAR') {
        foreach ($historico as $s) {
            if ((int)$s['ID'] === $d['SUSPENSAO_ID']) {
                $escolhida = $s;
                break;
            }
        }
        if (!$escolhida) {
            $erros[] = 'Selecione uma suspensão deste aluno.';
        } elseif (!$d['DIAS']) {
            $erros[] = 'Marque pelo menos uma data/aula para liberar.';
        } elseif (count($d['DIAS']) > 800) {
            $erros[] = 'Selecione no máximo 800 aulas por liberação.';
        } else {
            $aulasPermitidas = ch_chamada_tem_aula() ? ['1', '2'] : ['1'];
            foreach ($d['DIAS'] as $chave) {
                $partes = explode(':', $chave);
                $data = $partes[0] ?? '';
                $aula = $partes[1] ?? '';
                $pedaco = explode('-', $data);
                if (count($partes) !== 2 || count($pedaco) !== 3
                    || !checkdate((int)$pedaco[1], (int)$pedaco[2], (int)$pedaco[0])
                    || !in_array($aula, $aulasPermitidas, true)
                    || $data < $escolhida['DATA_INICIO_TXT']
                    || $data > $escolhida['DATA_FIM_TXT']) {
                    $erros[] = 'Há data/aula fora do período desta suspensão. Recarregue a página.';
                    break;
                }
            }
        }
    } else {
        if ($d['DATA_INICIO'] === '' || $d['DATA_FIM'] === '') {
            $erros[] = 'Informe a data de início e a data fim.';
        } elseif ($d['DATA_FIM'] < $d['DATA_INICIO']) {
            $erros[] = 'A data fim não pode ser anterior à data de início.';
        } else {
            $dias = (int)floor((strtotime($d['DATA_FIM']) - strtotime($d['DATA_INICIO'])) / 86400);
            if ($dias > 366) {
                $erros[] = 'O período não pode passar de 366 dias.';
            } else {
                $conflito = susp_conflito($idAluno, $d['DATA_INICIO'], $d['DATA_FIM']);
                if ($conflito) {
                    $erros[] = 'Já existe suspensão de ' . ch_data_br($conflito['DATA_INICIO_TXT'])
                             . ' a ' . ch_data_br($conflito['DATA_FIM_TXT'])
                             . ' (' . $conflito['SITUACAO'] . ') que cobre este período.';
                }
            }
        }
    }

    if (!$erros) {
        $conn = ch_db();
        if (!sqlsrv_begin_transaction($conn)) {
            $erros[] = 'Não foi possível iniciar o registro da suspensão.';
        } else {
            if ($d['ACAO'] === 'LIBERAR') {
                $res = susp_liberar($d['SUSPENSAO_ID'], $d['DIAS'], $d['PRESENCA'], $d['MOTIVO']);
            } else {
                $res = susp_aplicar($idAluno, $d['DATA_INICIO'], $d['DATA_FIM'], $d['MOTIVO']);
            }

            if (!$res['ok']) {
                sqlsrv_rollback($conn);
                $erros[] = $res['erro'];
                if (ch_admin()) $erros[] = ch_erro_sql();
            } else {
                if ($d['ACAO'] === 'LIBERAR') {
                    ch_auditar('Alunos', 'Liberar dias da suspensão', 'SUSPENSAO', (string)$res['id'], [
                        'ID_ALUNO' => (int)$res['aluno_id'],
                        'DIAS' => implode(', ', $d['DIAS']),
                        'PRESENCA_NOS_DIAS_PASSADOS' => (bool)$d['PRESENCA'],
                        'PRESENCAS_LANCADAS' => (int)$res['presencas'],
                        'FALTAS_REMOVIDAS' => (int)$res['removidas'],
                        'ENCERRADA' => (bool)$res['encerrada'],
                        'MOTIVO' => $d['MOTIVO'],
                    ]);
                } else {
                    ch_auditar('Alunos', 'Registrar suspensão', 'SUSPENSAO', (string)$res['id'], [
                        'ID_ALUNO' => (int)$idAluno,
                        'DATA_INICIO' => $d['DATA_INICIO'],
                        'DATA_FIM' => $d['DATA_FIM'],
                        'FALTAS_CRIADAS' => (int)$res['criadas'],
                        'FALTAS_RETIFICADAS' => (int)$res['retificadas'],
                        'AULAS_SEM_CHAMADA' => (int)$res['semAula'],
                        'MOTIVO' => $d['MOTIVO'],
                    ]);
                }

                if (!sqlsrv_commit($conn)) {
                    sqlsrv_rollback($conn);
                    $erros[] = 'Não foi possível salvar. Tente novamente.';
                    if (ch_admin()) $erros[] = ch_erro_sql();
                } else {
                    if ($d['ACAO'] === 'LIBERAR') {
                        $msg = (int)($res['presencas'] + $res['removidas'])
                             . ' aula(s) liberada(s): ' . (int)$res['presencas']
                             . ' presença(s) lançada(s) e ' . (int)$res['removidas']
                             . ' falta(s) removida(s).';
                        $msg .= $res['encerrada']
                             ? ' Não restam aulas suspensas: a suspensão foi encerrada.'
                             : ' Ainda há aulas suspensas nas demais datas.';
                        if ($d['PRESENCA'] && $res['presencas'] === 0) {
                            $msg .= ' Nenhuma das aulas escolhidas já passou, então não havia presença para lançar: elas ficaram pendentes.';
                        }
                        ch_flash($msg);
                    } else {
                        ch_flash('Suspensão registrada de ' . ch_data_br($d['DATA_INICIO']) . ' a '
                                 . ch_data_br($d['DATA_FIM']) . '. ' . (int)$res['criadas']
                                 . ' falta(s) gravada(s), ' . (int)$res['retificadas']
                                 . ' retificada(s) e ' . (int)$res['jaFaltas']
                                 . ' já era(m) falta(s). '
                                 . (int)$res['semAula'] . ' data(s) sem aula foram ignoradas.');
                    }
                    header('Location: ' . ch_url('alunos/suspensao.php?aluno_id=' . $idAluno));
                    exit;
                }
            }
        }
    }

    $historico  = susp_historico($idAluno);
    $emVigor    = susp_em_vigor($idAluno);
    $liberaveis = susp_monta_liberaveis($historico);
    $liberacoes = susp_monta_liberacoes($historico);
}
