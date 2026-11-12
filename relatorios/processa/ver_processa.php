<?php
require_once(dirname(__DIR__, 2) . '/config.php');
require_once(DBAPI);
require_once(ABSPATH . 'inc/chamada.php');
ch_db();
ch_require_relatorios();
$alunoId = (int)($_POST['aluno_id'] ?? $_GET['aluno_id'] ?? 0);
$ano     = (int)($_POST['ano'] ?? $_GET['ano'] ?? (int)date('Y'));

$aluno = ch_exige_relatorio_aluno($alunoId);
$podeEditarParecer = !ch_secretario_escolar() && (int)$aluno['ATIVO'] === 1;

/* Transferências: o relatório é do ANO INTEIRO do aluno, mesmo que ele tenha
   mudado de escola no meio do ano. A chamada, as observações e o parecer já são
   consultados por ID_ALUNO, então nada precisa ser somado — só explicitar em
   que escola e turma ele estava em cada período. */
$vinculosAno = tr_vinculos_no_ano($alunoId, $ano);
$houveTransferencia = count($vinculosAno) > 1;

$inicio = $ano . '-01-01';
$fim    = $ano . '-12-31';

$totais = ch_row(
    "SELECT COUNT(*) AS DIAS,
            SUM(CASE WHEN STATUS = 'P' THEN 1 ELSE 0 END) AS P,
            SUM(CASE WHEN STATUS = 'F' THEN 1 ELSE 0 END) AS F,
            SUM(CASE WHEN STATUS = 'J' THEN 1 ELSE 0 END) AS J,
            SUM(CASE WHEN STATUS = 'A' THEN 1 ELSE 0 END) AS A
     FROM TB_CHAMADA WHERE ID_ALUNO = ? AND DATA >= ? AND DATA <= ?",
    [$alunoId, $inicio, $fim]
);

$porMes = ch_rows(
    "SELECT MONTH(DATA) AS MES, COUNT(*) AS DIAS,
            SUM(CASE WHEN STATUS = 'P' THEN 1 ELSE 0 END) AS P,
            SUM(CASE WHEN STATUS = 'F' THEN 1 ELSE 0 END) AS F,
            SUM(CASE WHEN STATUS = 'J' THEN 1 ELSE 0 END) AS J,
            SUM(CASE WHEN STATUS = 'A' THEN 1 ELSE 0 END) AS A
     FROM TB_CHAMADA WHERE ID_ALUNO = ? AND DATA >= ? AND DATA <= ?
     GROUP BY MONTH(DATA) ORDER BY MONTH(DATA)",
    [$alunoId, $inicio, $fim]
);
$meses = [];
foreach ($porMes as $m) { $meses[(int)$m['MES']] = $m; }

/* Relatório anual, inclusive na impressão: o substituto sem liberação não
   pode receber o texto dos outros professores por este caminho. */
$fObsRel = eq_obs_filtro_aluno($alunoId, 'o');
$fObsPermissaoRel = ch_filtro_observacao_aluno('a');
$observacoes = ch_rows(
    'SELECT o.ID, CONVERT(varchar(10), o.DATA, 23) AS DATA, o.CATEGORIA, o.TEXTO, u.NOME AS AUTOR
     FROM TB_OBSERVACOES o
     INNER JOIN TB_USUARIOS u ON u.ID = o.ID_USUARIO
     INNER JOIN TB_ALUNOS a ON a.ID = o.ID_ALUNO
     WHERE o.ID_ALUNO = ? AND o.DATA >= ? AND o.DATA <= ?' . $fObsRel['sql'] . $fObsPermissaoRel['sql'] . '
     ORDER BY o.DATA, o.ID',
    array_merge([$alunoId, $inicio, $fim], $fObsRel['params'], $fObsPermissaoRel['params'])
);
$obsLeituraPermitida = ch_pode_ler_observacao_aluno($alunoId);
$obsOcultas = $fObsRel['sql'] !== '' || !$obsLeituraPermitida;
$avisoObs   = ch_secretario_escolar() && !$obsLeituraPermitida
    ? 'A Direção não autorizou a consulta de observações nesta unidade.'
    : eq_obs_aviso_bloqueio();

$porCategoria = [];
foreach ($observacoes as $o) {
    $porCategoria[$o['CATEGORIA']] = ($porCategoria[$o['CATEGORIA']] ?? 0) + 1;
}

$faltasDetalhadas = ch_rows(
    "SELECT CONVERT(varchar(10), DATA, 23) AS DATA, STATUS, ISNULL(JUSTIFICATIVA, '') AS JUSTIFICATIVA
     FROM TB_CHAMADA WHERE ID_ALUNO = ? AND DATA >= ? AND DATA <= ? AND STATUS IN ('F', 'J')
     ORDER BY DATA",
    [$alunoId, $inicio, $fim]
);

$parecer = ch_row(
    'SELECT r.ID, ISNULL(r.PARECER, \'\') AS PARECER,
            ISNULL(r.ENCAMINHAMENTOS, \'\') AS ENCAMINHAMENTOS, r.ID_USUARIO,
            u.NOME AS AUTOR, CONVERT(varchar(16), r.CREATED, 120) AS CRIADO_EM,
            CONVERT(varchar(16), r.MODIFIED, 120) AS MODIFIED
     FROM TB_RELATORIOS_ANUAIS r
     INNER JOIN TB_USUARIOS u ON u.ID = r.ID_USUARIO
     WHERE r.ID_ALUNO = ? AND r.ANO = ?',
    [$alunoId, $ano]
);
$historicoParecer = $parecer
    ? ch_rows(
        'SELECT h.ID_USUARIO_AUTOR, ua.NOME AS AUTOR,
                h.ID_USUARIO_ALTERACAO, ue.NOME AS ALTERADO_POR,
                ISNULL(h.PARECER, \'\') AS PARECER,
                ISNULL(h.ENCAMINHAMENTOS, \'\') AS ENCAMINHAMENTOS,
                CONVERT(varchar(16), h.ALTERADA_EM, 120) AS ALTERADA_EM
         FROM TB_HISTORICO_RELATORIOS h
         INNER JOIN TB_USUARIOS ua ON ua.ID = h.ID_USUARIO_AUTOR
         INNER JOIN TB_USUARIOS ue ON ue.ID = h.ID_USUARIO_ALTERACAO
         WHERE h.ID_RELATORIO = ? ORDER BY h.ID DESC',
        [(int)$parecer['ID']]
    )
    : [];

$dias       = (int)($totais['DIAS'] ?? 0);
$presencas  = (int)($totais['P'] ?? 0) + (int)($totais['A'] ?? 0);
$frequencia = $dias > 0 ? round($presencas * 100 / $dias, 1) : null;
/* A turma pode ter vários professores: mostra a equipe inteira. */
$professor  = ch_turma_equipe_texto((int)$aluno['ID_TURMA']);
