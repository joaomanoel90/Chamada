<?php
require_once(dirname(__DIR__, 2) . '/config.php');
require_once(DBAPI);
require_once(ABSPATH . 'inc/chamada.php');
ch_db();
ch_require_gestor();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . ch_url('promocoes/index.php'));
    exit;
}
if (!ch_csrf_ok()) {
    ch_flash('A sessão do formulário expirou. Tente novamente.', 'danger');
    header('Location: ' . ch_url('promocoes/index.php'));
    exit;
}

$alunoId = (int)($_POST['aluno_id'] ?? 0);
$turmaOrigemId = (int)($_POST['turma_id'] ?? 0);
$turmaDestinoId = (int)($_POST['id_turma_destino'] ?? 0);
$dataInicio = ch_data_iso($_POST['data_inicio'] ?? '');
$individual = $alunoId > 0;
$destinoErro = $individual
    ? ch_url('promocoes/aluno.php?aluno_id=' . $alunoId)
    : ch_url('promocoes/turma.php?turma_id=' . $turmaOrigemId);

if (($individual && !$alunoId) || (!$individual && !$turmaOrigemId) || !$turmaDestinoId || !$dataInicio) {
    ch_flash('Informe uma turma de destino e uma data de início válida.', 'danger');
    header('Location: ' . $destinoErro);
    exit;
}

$conn = ch_db();
if (!sqlsrv_begin_transaction($conn)) {
    ch_flash('Não foi possível iniciar a promoção. Tente novamente.', 'danger');
    header('Location: ' . $destinoErro);
    exit;
}

$erro = '';
$origem = null;
$destino = null;
$alunos = [];

if ($individual) {
    $aluno = ch_row(
        'SELECT a.ID, a.ID_TURMA, a.ATIVO, t.ID_UNIDADE, t.ANO_LETIVO
         FROM TB_ALUNOS a WITH (UPDLOCK, HOLDLOCK)
         INNER JOIN TB_TURMAS t ON t.ID = a.ID_TURMA
         WHERE a.ID = ?',
        [$alunoId]
    );
    if (!$aluno || (int)$aluno['ATIVO'] !== 1 || !ch_pode_turma((int)$aluno['ID_TURMA'])) {
        $erro = 'Aluno inativo, fora da sua unidade ou com cadastro alterado.';
    } else {
        $origem = [
            'ID' => (int)$aluno['ID_TURMA'],
            'ID_UNIDADE' => (int)$aluno['ID_UNIDADE'],
            'ANO_LETIVO' => (int)$aluno['ANO_LETIVO'],
        ];
        $alunos = [$aluno];
    }
} else {
    $origem = ch_row(
        'SELECT ID, ID_UNIDADE, ANO_LETIVO, ATIVO
         FROM TB_TURMAS WITH (UPDLOCK, HOLDLOCK) WHERE ID = ?',
        [$turmaOrigemId]
    );
    if (!$origem || (int)$origem['ATIVO'] !== 1 || !ch_pode_turma($turmaOrigemId)) {
        $erro = 'Turma de origem inativa, não encontrada ou fora da sua unidade.';
    } else {
        $alunos = ch_rows(
            'SELECT ID, ID_TURMA, ATIVO FROM TB_ALUNOS WITH (UPDLOCK, HOLDLOCK)
             WHERE ID_TURMA = ? AND ATIVO = 1 ORDER BY ID',
            [$turmaOrigemId]
        );
    }
}

if (!$erro && !$alunos) {
    $erro = 'Não há alunos ativos na turma selecionada.';
}

if (!$erro) {
    $destino = ch_row(
        'SELECT ID, ID_UNIDADE, ANO_LETIVO, CAPACIDADE, ATIVO
         FROM TB_TURMAS WITH (UPDLOCK, HOLDLOCK) WHERE ID = ?',
        [$turmaDestinoId]
    );
    if (!$destino || (int)$destino['ATIVO'] !== 1
        || (int)$destino['ID_UNIDADE'] !== (int)$origem['ID_UNIDADE']
        || (int)$destino['ANO_LETIVO'] !== (int)$origem['ANO_LETIVO'] + 1
        || (int)$destino['ID'] === (int)$origem['ID']) {
        $erro = 'A turma de destino precisa ser ativa, da mesma unidade e do ano letivo seguinte.';
    } elseif (substr($dataInicio, 0, 4) !== (string)((int)$destino['ANO_LETIVO'])) {
        $erro = 'A data de início deve estar dentro do ano letivo da turma de destino.';
    }
}

if (!$erro) {
    $ocupacao = (int)ch_val(
        'SELECT COUNT(*) FROM TB_ALUNOS WITH (UPDLOCK, HOLDLOCK)
         WHERE ID_TURMA = ? AND ATIVO = 1',
        [$turmaDestinoId]
    );
    if ($ocupacao + count($alunos) > (int)$destino['CAPACIDADE']) {
        $erro = 'A turma de destino não tem capacidade para todos os alunos selecionados.';
    }
}

if (!$erro) {
    foreach ($alunos as $alunoMovido) {
        $idMovido = (int)$alunoMovido['ID'];
        if (ch_val("SELECT TOP 1 ID FROM TB_TRANSFERENCIAS WHERE ID_ALUNO = ? AND SITUACAO = 'PENDENTE'", [$idMovido])) {
            $erro = 'Um dos alunos possui transferência pendente. Trate essa movimentação antes da promoção.';
            break;
        }
        if (ch_val('SELECT TOP 1 ID FROM TB_ALUNO_SAIDAS_EXTERNAS WHERE ID_ALUNO = ? AND DATA_RETORNO IS NULL', [$idMovido])) {
            $erro = 'Um dos alunos está com saída da rede em aberto e não pode ser promovido.';
            break;
        }
        $ultimaData = ch_val(
            'SELECT CONVERT(varchar(10), MAX(M.DATA_INICIO), 23) FROM (
                SELECT ID_ALUNO, DATA_INICIO FROM TB_TRANSFERENCIAS WHERE SITUACAO = \'CONCLUIDA\'
                UNION ALL SELECT ID_ALUNO, DATA_INICIO FROM TB_PROMOCOES
                UNION ALL SELECT ID_ALUNO, DATA_RETORNO FROM TB_ALUNO_SAIDAS_EXTERNAS WHERE DATA_RETORNO IS NOT NULL
             ) M WHERE M.ID_ALUNO = ?',
            [$idMovido]
        );
        if ($ultimaData && $dataInicio <= $ultimaData) {
            $erro = 'A data de promoção deve ser posterior ao último vínculo registrado de cada aluno.';
            break;
        }
        $vinculoAtual = ch_val(
            'SELECT TOP 1 ID_TURMA FROM VW_ALUNO_VINCULO
             WHERE ID_ALUNO = ? AND DATA_FIM IS NULL',
            [$idMovido]
        );
        if ((int)$vinculoAtual !== (int)$origem['ID']) {
            $erro = 'O histórico de turma de um dos alunos mudou. Atualize a página antes de tentar novamente.';
            break;
        }
    }
}

if (!$erro) {
    foreach ($alunos as $alunoMovido) {
        $idMovido = (int)$alunoMovido['ID'];
        $ok = ch_exec(
            'INSERT INTO TB_PROMOCOES
                (ID_ALUNO, ID_TURMA_ORIGEM, ID_TURMA_DESTINO, DATA_INICIO, ID_USUARIO)
             VALUES (?, ?, ?, ?, ?)',
            [$idMovido, (int)$origem['ID'], $turmaDestinoId, $dataInicio, ch_uid()]
        );
        if ($ok) {
            $ok = ch_afetadas(
                'UPDATE TB_ALUNOS SET ID_TURMA = ? WHERE ID = ? AND ID_TURMA = ? AND ATIVO = 1',
                [$turmaDestinoId, $idMovido, (int)$origem['ID']]
            ) === 1;
        }
        if (!$ok) {
            $erro = 'Não foi possível registrar todas as promoções. Nenhum aluno foi alterado.';
            if (ch_admin()) $erro .= ' ' . ch_erro_sql();
            break;
        }
    }
}

if ($erro === '' && sqlsrv_commit($conn)) {
    $nomeDestino = ch_val('SELECT NOME FROM TB_TURMAS WHERE ID = ?', [$turmaDestinoId]);
    $aviso = count($alunos) . ' aluno(s) promovido(s) para ' . $nomeDestino
           . ' a partir de ' . ch_data_br($dataInicio) . '. O histórico escolar foi preservado.';
    ch_flash($aviso);
    header('Location: ' . ($individual
        ? ch_url('alunos/view.php?id=' . $alunoId)
        : ch_url('promocoes/index.php')));
    exit;
}

sqlsrv_rollback($conn);
ch_flash($erro !== '' ? $erro : 'Não foi possível concluir a promoção.', 'danger');
header('Location: ' . $destinoErro);
exit;
