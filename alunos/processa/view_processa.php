<?php
require_once(dirname(__DIR__, 2) . '/config.php');
require_once(DBAPI);
require_once(ABSPATH . 'inc/chamada.php');
require_once(ABSPATH . 'alunos/fotos.php');
ch_db();
$id = (int)($_GET['id'] ?? 0);
$aluno = ch_exige_aluno($id);

$resumo = ch_row(
    "SELECT COUNT(*) AS DIAS,
            SUM(CASE WHEN STATUS = 'P' THEN 1 ELSE 0 END) AS P,
            SUM(CASE WHEN STATUS = 'F' THEN 1 ELSE 0 END) AS F,
            SUM(CASE WHEN STATUS = 'J' THEN 1 ELSE 0 END) AS J,
            SUM(CASE WHEN STATUS = 'A' THEN 1 ELSE 0 END) AS A
     FROM TB_CHAMADA
     WHERE ID_ALUNO = ? AND YEAR(DATA) = ?",
    [$id, (int)$aluno['ANO_LETIVO']]
);

$ultimas = ch_rows(
    'SELECT TOP 15 CONVERT(varchar(10), c.DATA, 23) AS DATA, c.STATUS, c.JUSTIFICATIVA, u.NOME AS LANCADO_POR
     FROM TB_CHAMADA c INNER JOIN TB_USUARIOS u ON u.ID = c.ID_USUARIO
     WHERE c.ID_ALUNO = ? ORDER BY c.DATA DESC',
    [$id]
);

/* Substituto sem liberação vê só as próprias observações nesta ficha. */
$fObsAluno = eq_obs_filtro_aluno($id, 'o');
$fObsPermissaoAluno = ch_filtro_observacao_aluno('a');
$observacoes = ch_rows(
    'SELECT TOP 10 o.ID, CONVERT(varchar(10), o.DATA, 23) AS DATA, o.CATEGORIA, o.TEXTO, u.NOME AS AUTOR
     FROM TB_OBSERVACOES o INNER JOIN TB_USUARIOS u ON u.ID = o.ID_USUARIO
     INNER JOIN TB_ALUNOS a ON a.ID = o.ID_ALUNO
     WHERE o.ID_ALUNO = ?' . $fObsAluno['sql'] . $fObsPermissaoAluno['sql'] . ' ORDER BY o.DATA DESC, o.ID DESC',
    array_merge([$id], $fObsAluno['params'], $fObsPermissaoAluno['params'])
);
$obsLeituraPermitida = ch_pode_ler_observacao_aluno($id);
$obsOcultas = $fObsAluno['sql'] !== '' || !$obsLeituraPermitida;
$avisoObs   = ch_secretario_escolar() && !$obsLeituraPermitida
    ? 'A Direção não autorizou a consulta de observações nesta unidade.'
    : eq_obs_aviso_bloqueio();

/* Transferências: linha do tempo, histórico e o que ainda está aguardando. */
$vinculos                = tr_vinculos($id);
$historicoTransferencias = tr_historico_aluno($id);
$saidasExternas           = tr_saidas_externas($id);
$saidaExternaAberta       = tr_saida_externa_aberta($id);
$transferenciaPendente   = tr_pendente($id);
$podeTransferir          = tr_pode_solicitar($aluno);
/* quem vê esta ficha só porque o aluno já foi da sua escola */
$apenasHistorico         = !ch_pode_turma($aluno['ID_TURMA']);

$dias = (int)($resumo['DIAS'] ?? 0);
$presencas = (int)($resumo['P'] ?? 0) + (int)($resumo['A'] ?? 0);
$frequencia = $dias > 0 ? round($presencas * 100 / $dias, 1) : null;
