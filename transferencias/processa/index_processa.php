<?php
/* ============================================================================
   TRANSFERÊNCIAS — listagem
   ----------------------------------------------------------------------------
   Cada escola vê as duas pontas: o que ela enviou e o que está chegando.
   O padrão da tela é "Aguardando esta escola", que é o que exige ação.
   ============================================================================ */
require_once(dirname(__DIR__, 2) . '/config.php');
require_once(DBAPI);
require_once(ABSPATH . 'inc/chamada.php');
ch_db();
ch_require_gestao_alunos();

$papel = $_GET['papel'] ?? 'recebidas';
if (!in_array($papel, ['recebidas', 'enviadas', 'todas'], true)) { $papel = 'recebidas'; }

$situacao = strtoupper(trim((string)($_GET['situacao'] ?? 'PENDENTE')));
if (!in_array($situacao, ['PENDENTE', 'CONCLUIDA', 'CANCELADA', 'TODAS'], true)) { $situacao = 'PENDENTE'; }

/* O administrador não tem "a minha escola": para ele as abas de papel não
   fazem sentido, e ele vê a rede inteira. */
$mostrarPapel = !ch_rede();
if (!$mostrarPapel) { $papel = 'todas'; }

$where  = [];
$params = [];

if (!ch_rede()) {
    $ids = ch_unidades_ids();
    if (!$ids) {
        $where[] = '1 = 0';
    } else {
        $ph = implode(',', array_fill(0, count($ids), '?'));
        if ($papel === 'recebidas') {
            $where[] = "tr.ID_UNIDADE_DESTINO IN ({$ph})";
            $params = array_merge($params, $ids);
        } elseif ($papel === 'enviadas') {
            $where[] = "tor.ID_UNIDADE IN ({$ph})";
            $params = array_merge($params, $ids);
        } else {
            $where[] = "(tr.ID_UNIDADE_DESTINO IN ({$ph}) OR tor.ID_UNIDADE IN ({$ph}))";
            $params = array_merge($params, $ids, $ids);
        }
    }
}

if ($situacao !== 'TODAS') {
    $where[] = 'tr.SITUACAO = ?';
    $params[] = $situacao;
}

$sqlWhere = $where ? (' WHERE ' . implode(' AND ', $where)) : '';

$transferencias = ch_rows(
    'SELECT tr.ID, tr.ID_ALUNO, tr.SITUACAO, tr.MOTIVO,
            tr.ID_UNIDADE_DESTINO, tor.ID_UNIDADE AS ID_UNIDADE_ORIGEM,
            CONVERT(varchar(10), tr.DATA_INICIO, 23)    AS DATA_INICIO,
            CONVERT(varchar(16), tr.SOLICITADA_EM, 120) AS SOLICITADA_EM,
            a.NOME AS ALUNO, a.MATRICULA,
            tor.NOME AS TURMA_ORIGEM, uor.unidade AS UNIDADE_ORIGEM,
            ude.unidade AS UNIDADE_DESTINO, tde.NOME AS TURMA_DESTINO,
            us.NOME AS SOLICITADA_POR
     FROM TB_TRANSFERENCIAS tr
     INNER JOIN TB_ALUNOS a     ON a.ID = tr.ID_ALUNO
     INNER JOIN TB_TURMAS tor   ON tor.ID = tr.ID_TURMA_ORIGEM
     INNER JOIN TB_UNIDADES uor ON uor.id = tor.ID_UNIDADE
     INNER JOIN TB_UNIDADES ude ON ude.id = tr.ID_UNIDADE_DESTINO
     INNER JOIN TB_USUARIOS us  ON us.ID = tr.ID_USUARIO_SOLIC
     LEFT JOIN TB_TURMAS tde    ON tde.ID = tr.ID_TURMA_DESTINO'
    . $sqlWhere .
    ' ORDER BY CASE WHEN tr.SITUACAO = \'PENDENTE\' THEN 0 ELSE 1 END,
              tr.SOLICITADA_EM DESC, tr.ID DESC',
    $params
) ?: [];

$aguardando = tr_pendentes_recebidas();