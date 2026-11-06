<?php
require_once(dirname(__DIR__, 2) . '/config.php');
require_once(DBAPI);
require_once(ABSPATH . 'inc/chamada.php');
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

$observacoes = ch_rows(
    'SELECT TOP 10 o.ID, CONVERT(varchar(10), o.DATA, 23) AS DATA, o.CATEGORIA, o.TEXTO, u.NOME AS AUTOR
     FROM TB_OBSERVACOES o INNER JOIN TB_USUARIOS u ON u.ID = o.ID_USUARIO
     WHERE o.ID_ALUNO = ? ORDER BY o.DATA DESC, o.ID DESC',
    [$id]
);

/* Transferências: linha do tempo, histórico e o que ainda está aguardando. */
$vinculos                = tr_vinculos($id);
$historicoTransferencias = tr_historico_aluno($id);
$transferenciaPendente   = tr_pendente($id);
$podeTransferir          = tr_pode_solicitar($aluno);
/* quem vê esta ficha só porque o aluno já foi da sua escola */
$apenasHistorico         = !ch_pode_turma($aluno['ID_TURMA']);

$dias = (int)($resumo['DIAS'] ?? 0);
$presencas = (int)($resumo['P'] ?? 0) + (int)($resumo['A'] ?? 0);
$frequencia = $dias > 0 ? round($presencas * 100 / $dias, 1) : null;
