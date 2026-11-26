<?php
/* ============================================================================
   CONFIRMAÇÃO da chamada — grava apenas alunos AINDA SEM registro na aula.
   ----------------------------------------------------------------------------
   A presença é confirmada uma vez por aluno e por aula (1ª ou 2ª). Se o
   registro daquela aula já existe, este script NÃO sobrescreve: a correção
   acontece em retificar.php, que exige motivo e grava histórico em
   TB_CHAMADA_RETIFICACOES.
   ============================================================================ */
require_once(dirname(__DIR__, 2) . '/config.php');
require_once(DBAPI);
require_once(ABSPATH . 'inc/chamada.php');
ch_db();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}
if (!ch_csrf_ok()) {
    header('Location: index.php');
    exit;
}

$turmaId = (int)($_POST['turma_id'] ?? 0);
$data    = ch_data_iso($_POST['data'] ?? '');
$aula    = ch_aula_normalizar($_POST['aula'] ?? 1);
$voltar  = 'index.php?turma_id=' . $turmaId . '&data=' . $data . '&aula=' . $aula;

if (!$data) {
    ch_flash('Data inválida.', 'danger');
    header('Location: index.php?aula=' . $aula);
    exit;
}

if ($aula === 2 && !ch_chamada_tem_aula()) {
    ch_flash('Este banco ainda não tem chamada por aula. Rode sql_inicial/ATUALIZAR_CHAMADA_POR_AULA.sql.', 'danger');
    header('Location: ' . $voltar);
    exit;
}

if (!ch_pode_lancar_chamada($turmaId)) {
    ch_flash(ch_secretario_escolar()
        ? 'O perfil Secretário Escolar pode consultar a chamada, mas não pode confirmar presenças.'
        : 'Você não tem acesso a essa turma.', 'danger');
    header('Location: ' . $voltar);
    exit;
}

$turma = ch_turma($turmaId);
$bloqueio = ch_bloqueio_chamada($data, $turma);
if ($bloqueio) {
    ch_flash($bloqueio, 'warning');
    header('Location: ' . $voltar);
    exit;
}

/* Só alunos ativos que pertenciam a esta turma NAQUELA data. Um aluno
   transferido não pode receber chamada da turma nova em dia anterior ao
   início, nem da turma antiga em dia posterior à saída. */
$naData = tr_filtro_na_data($turmaId, $data, 'a');
$alunos = ch_rows(
    'SELECT a.ID, a.NOME FROM TB_ALUNOS a WHERE a.ATIVO = 1' . $naData['sql'] . ' ORDER BY a.NOME',
    $naData['params']
);
$validos = [];
foreach ($alunos as $a) { $validos[(int)$a['ID']] = $a['NOME']; }

/* A trava é por aluno + data + aula. Em banco não migrado a expressão vira 1,
   mantendo o comportamento antigo de um registro por dia. */
$colAula = ch_aula_col();
$jaExiste = function ($idAluno) use ($data, $aula, $colAula) {
    return (bool)ch_val(
        'SELECT ID FROM TB_CHAMADA WHERE ID_ALUNO = ? AND DATA = ? AND ' . $colAula . ' = ?',
        [$idAluno, $data, $aula]
    );
};

$status        = (array)($_POST['status'] ?? []);
$justificativa = (array)($_POST['justificativa'] ?? []);

$erros = [];
$paraSalvar = [];
$jaConfirmados = [];

foreach ($status as $idBruto => $sBruto) {
    $id = (int)$idBruto;
    if (!isset($validos[$id])) { continue; }          /* aluno de outra turma: ignora */

    $nome = $validos[$id];
    $s = strtoupper(trim((string)$sBruto));
    $j = strip_tags(trim((string)($justificativa[$id] ?? '')));

    if ($s === '') { continue; }                      /* não marcado: fica pendente */
    if (!in_array($s, ['P', 'F', 'J', 'A'], true)) { $erros[] = $nome . ': situação inválida.'; continue; }
    if ($s === 'J' && $j === '') { $erros[] = $nome . ': a falta justificada exige justificativa.'; continue; }
    if (mb_strlen($j) > 500) { $erros[] = $nome . ': justificativa muito longa (máx. 500).'; continue; }

    /* Trava principal: já existe registro nesta aula -> não confirma de novo. */
    if ($jaExiste($id)) {
        $jaConfirmados[] = $nome;
        continue;
    }

    $paraSalvar[] = ['id' => $id, 'status' => $s, 'just' => $j !== '' ? $j : null];
}

/* Quem ficou sem marcar continua pendente — avisa, mas não bloqueia o resto. */
$semMarcacao = [];
foreach ($validos as $id => $nome) {
    $enviado = strtoupper(trim((string)($status[$id] ?? '')));
    if ($enviado !== '') continue;
    if ($jaExiste($id)) continue;
    $semMarcacao[] = $nome;
}

if ($erros) {
    foreach (array_slice($erros, 0, 8) as $e) { ch_flash($e, 'danger'); }
    if (count($erros) > 8) { ch_flash('E mais ' . (count($erros) - 8) . ' pendências.', 'danger'); }
    header('Location: ' . $voltar);
    exit;
}

if (!$paraSalvar) {
    if ($jaConfirmados) {
        ch_flash('A presença destes alunos já estava confirmada. Use Retificar para corrigir: '
                 . implode(', ', array_slice($jaConfirmados, 0, 5))
                 . (count($jaConfirmados) > 5 ? ' e outros.' : ''), 'warning');
    } else {
        ch_flash('Nenhum aluno marcado para confirmar.', 'warning');
    }
    header('Location: ' . $voltar);
    exit;
}

sqlsrv_begin_transaction(ch_db());
try {
    $novos = 0;
    foreach ($paraSalvar as $item) {
        /* Reconfere dentro da transação contra gravação simultânea. */
        if ($jaExiste($item['id'])) {
            throw new Exception('A presença de um aluno foi confirmada por outro usuário enquanto você preenchia.');
        }
        $colAulaInsert = ch_chamada_tem_aula() ? ', AULA_SEQ' : '';
        $marcaAula = ch_chamada_tem_aula() ? ', ?' : '';
        $params = [$item['id'], $data];
        if (ch_chamada_tem_aula()) $params[] = $aula;
        array_push($params, $item['status'], $item['just'], ch_uid(), ch_uid());
        $ok = ch_exec(
            'INSERT INTO TB_CHAMADA (ID_ALUNO, DATA' . $colAulaInsert . ', STATUS, JUSTIFICATIVA, ID_USUARIO, CONFIRMADA_EM, CONFIRMADA_POR)
             VALUES (?, ?' . $marcaAula . ', ?, ?, ?, GETDATE(), ?)',
            $params
        );
        if (!$ok) { throw new Exception('Falha ao gravar a chamada.'); }
        $novos++;
    }
    sqlsrv_commit(ch_db());

    ch_flash(ch_aula_rotulo($aula) . ' confirmada para ' . $novos . ' aluno(s).'
             . ' Para corrigir, use Retificar.');
    if ($jaConfirmados) {
        ch_flash(count($jaConfirmados) . ' aluno(s) já tinham presença confirmada e não foram alterados.', 'warning');
    }
    if ($semMarcacao) {
        ch_flash(count($semMarcacao) . ' aluno(s) continuam aguardando confirmação.', 'warning');
    }
} catch (Exception $ex) {
    sqlsrv_rollback(ch_db());
    ch_flash('Não foi possível confirmar: ' . $ex->getMessage() . ' Nenhuma alteração foi gravada.', 'danger');
}

header('Location: ' . $voltar);
exit;
