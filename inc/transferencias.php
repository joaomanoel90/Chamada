<?php
/* ============================================================================
   TRANSFERÊNCIA DE ALUNO ENTRE ESCOLAS — regras e consultas
   ----------------------------------------------------------------------------
   Fluxo em duas etapas, como a rede trabalha:

     1. O diretor da escola de ORIGEM indica a escola de destino e o motivo.
        A transferência fica PENDENTE e o aluno CONTINUA na turma atual — a
        chamada segue normal até a escola de destino aceitar.

     2. A escola de DESTINO escolhe a turma e a DATA DE INÍCIO. Só então o aluno
        muda de turma.

   A data de início separa os dois períodos: até a véspera o aluno pertence à
   turma de origem; daquele dia em diante, à de destino. É por isso que a chamada
   de uma data passada continua mostrando a turma em que o aluno realmente
   estava — nenhum registro antigo "muda de escola".

   Nada de chamada, observação ou parecer é movido ou apagado: as três tabelas
   referenciam ID_ALUNO, não a turma. O histórico acompanha a criança.

   Este arquivo só define funções. Quem inclui precisa já ter carregado
   inc/chamada.php (ele carrega este arquivo no final).
   ============================================================================ */

/* ------------------------------------------------------------- situações */

function tr_situacoes()
{
    return [
        'PENDENTE'  => ['Aguardando a escola de destino', 'warning text-dark'],
        'CONCLUIDA' => ['Concluída',                      'success'],
        'CANCELADA' => ['Cancelada',                      'secondary'],
    ];
}

function tr_situacao_badge($s)
{
    $m = tr_situacoes();
    $s = (string)$s;
    if (!isset($m[$s])) return '<span class="badge bg-secondary">' . ch_h($s) . '</span>';
    return '<span class="badge bg-' . $m[$s][1] . '">' . ch_h($m[$s][0]) . '</span>';
}

/* ------------------------------------------------- linha do tempo do aluno */

/** Todos os períodos do aluno, do mais antigo ao mais recente, com escola,
    turma e período. Vem da VW_ALUNO_VINCULO, que deriva tudo das transferências
    concluídas — não há uma segunda fonte de verdade para manter em sincronia. */
function tr_vinculos($idAluno)
{
    return ch_rows(
        'SELECT v.ID_TURMA,
                CONVERT(varchar(10), v.DATA_INICIO, 23) AS DATA_INICIO,
                CONVERT(varchar(10), v.DATA_FIM, 23)    AS DATA_FIM,
                t.NOME AS TURMA, t.ANO_LETIVO, t.ID_UNIDADE,
                u.unidade AS UNIDADE, p.NOME AS PERIODO
         FROM VW_ALUNO_VINCULO v
         INNER JOIN TB_TURMAS t   ON t.ID = v.ID_TURMA
         INNER JOIN TB_UNIDADES u ON u.id = t.ID_UNIDADE
         INNER JOIN TB_PERIODOS p ON p.ID = t.ID_PERIODO
         WHERE v.ID_ALUNO = ?
         ORDER BY CASE WHEN v.DATA_INICIO IS NULL THEN 0 ELSE 1 END, v.DATA_INICIO',
        [(int)$idAluno]
    ) ?: [];
}

/** Períodos do aluno que tocam o ano informado, já recortados no ano.
    É o quadro "escolas e turmas no ano" do relatório anual. */
function tr_vinculos_no_ano($idAluno, $ano)
{
    $ano = (int)$ano;
    $ini = $ano . '-01-01';
    $fim = $ano . '-12-31';
    $fora = [];
    foreach (tr_vinculos($idAluno) as $v) {
        $de  = $v['DATA_INICIO'];
        $ate = $v['DATA_FIM'];
        if ($ate !== null && $ate < $ini) continue;   // terminou antes do ano
        if ($de  !== null && $de  > $fim) continue;   // começa depois do ano
        $v['DE_NO_ANO']  = ($de  !== null && $de  > $ini) ? $de  : $ini;
        $v['ATE_NO_ANO'] = ($ate !== null && $ate < $fim) ? $ate : $fim;
        $v['EM_ABERTO']  = ($ate === null);
        $fora[] = $v;
    }
    return $fora;
}

/** Em que turma o aluno estava nesta data. null = ainda não tinha vínculo. */
function tr_turma_na_data($idAluno, $dataIso)
{
    $id = ch_val(
        'SELECT ID_TURMA FROM VW_ALUNO_VINCULO
         WHERE ID_ALUNO = ?
           AND (DATA_INICIO IS NULL OR DATA_INICIO <= ?)
           AND (DATA_FIM    IS NULL OR DATA_FIM    >= ?)',
        [(int)$idAluno, $dataIso, $dataIso]
    );
    return $id ? (int)$id : null;
}

/** Trecho de SQL que restringe uma consulta de ALUNOS (alias $alias) aos que
    pertenciam à turma naquela data — não aos que pertencem hoje.
    Devolve ['sql' => ..., 'params' => ...]. */
function tr_filtro_na_data($turmaId, $dataIso, $alias = 'a')
{
    return [
        'sql' => " AND EXISTS (SELECT 1 FROM VW_ALUNO_VINCULO v
                               WHERE v.ID_ALUNO = {$alias}.ID AND v.ID_TURMA = ?
                                 AND (v.DATA_INICIO IS NULL OR v.DATA_INICIO <= ?)
                                 AND (v.DATA_FIM    IS NULL OR v.DATA_FIM    >= ?))",
        'params' => [(int)$turmaId, $dataIso, $dataIso],
    ];
}

/** O aluno já esteve em alguma turma do alcance de quem está logado?
    Serve para a escola de origem continuar consultando o histórico depois que
    o aluno saiu. Só amplia acesso de LEITURA — editar continua exigindo a
    turma atual. */
function tr_aluno_ja_no_alcance($idAluno)
{
    $f = ch_filtro_turma('t');
    return (bool)ch_val(
        'SELECT TOP 1 1 FROM VW_ALUNO_VINCULO v
         INNER JOIN TB_TURMAS t ON t.ID = v.ID_TURMA
         WHERE v.ID_ALUNO = ?' . $f['sql'],
        array_merge([(int)$idAluno], $f['params'])
    );
}

/* ------------------------------------------------------------- consultas */

/** A transferência pendente do aluno, se houver. */
function tr_pendente($idAluno)
{
    return ch_row(
        "SELECT ID, ID_UNIDADE_DESTINO, MOTIVO FROM TB_TRANSFERENCIAS
         WHERE ID_ALUNO = ? AND SITUACAO = 'PENDENTE'",
        [(int)$idAluno]
    );
}

/** Transferência completa, com nomes de escola, turma e pessoas. */
function tr_transferencia($id)
{
    return ch_row(
        'SELECT tr.ID, tr.ID_ALUNO, tr.ID_TURMA_ORIGEM, tr.ID_UNIDADE_DESTINO,
                tr.ID_TURMA_DESTINO, tr.SITUACAO, tr.MOTIVO, tr.DOCUMENTO,
                tr.OBSERVACAO_DESTINO, tr.MOTIVO_CANCELAMENTO,
                CONVERT(varchar(10), tr.DATA_INICIO, 23)   AS DATA_INICIO,
                CONVERT(varchar(16), tr.SOLICITADA_EM, 120) AS SOLICITADA_EM,
                CONVERT(varchar(16), tr.EFETIVADA_EM, 120)  AS EFETIVADA_EM,
                CONVERT(varchar(16), tr.CANCELADA_EM, 120)  AS CANCELADA_EM,
                a.NOME AS ALUNO, a.MATRICULA,
                CONVERT(varchar(10), a.NASCIMENTO, 23) AS NASCIMENTO,
                a.RESPONSAVEL, a.TELEFONE,
                tor.ID_UNIDADE AS ID_UNIDADE_ORIGEM,
                tor.NOME AS TURMA_ORIGEM, tor.ANO_LETIVO AS ANO_ORIGEM,
                uor.unidade AS UNIDADE_ORIGEM,
                ude.unidade AS UNIDADE_DESTINO,
                tde.NOME AS TURMA_DESTINO, tde.ANO_LETIVO AS ANO_DESTINO,
                pde.NOME AS PERIODO_DESTINO,
                us.NOME AS SOLICITADA_POR,
                ue.NOME AS EFETIVADA_POR,
                uc.NOME AS CANCELADA_POR
         FROM TB_TRANSFERENCIAS tr
         INNER JOIN TB_ALUNOS a    ON a.ID = tr.ID_ALUNO
         INNER JOIN TB_TURMAS tor  ON tor.ID = tr.ID_TURMA_ORIGEM
         INNER JOIN TB_UNIDADES uor ON uor.id = tor.ID_UNIDADE
         INNER JOIN TB_UNIDADES ude ON ude.id = tr.ID_UNIDADE_DESTINO
         INNER JOIN TB_USUARIOS us  ON us.ID = tr.ID_USUARIO_SOLIC
         LEFT JOIN TB_TURMAS tde   ON tde.ID = tr.ID_TURMA_DESTINO
         LEFT JOIN TB_PERIODOS pde ON pde.ID = tde.ID_PERIODO
         LEFT JOIN TB_USUARIOS ue  ON ue.ID = tr.ID_USUARIO_EFETIVA
         LEFT JOIN TB_USUARIOS uc  ON uc.ID = tr.ID_USUARIO_CANCEL
         WHERE tr.ID = ?',
        [(int)$id]
    );
}

/** Histórico de transferências do aluno, mais recente primeiro. */
function tr_historico_aluno($idAluno)
{
    return ch_rows(
        'SELECT tr.ID, tr.SITUACAO, tr.MOTIVO, tr.DOCUMENTO, tr.MOTIVO_CANCELAMENTO,
                CONVERT(varchar(10), tr.DATA_INICIO, 23)    AS DATA_INICIO,
                CONVERT(varchar(16), tr.SOLICITADA_EM, 120) AS SOLICITADA_EM,
                CONVERT(varchar(16), tr.EFETIVADA_EM, 120)  AS EFETIVADA_EM,
                tor.NOME AS TURMA_ORIGEM, uor.unidade AS UNIDADE_ORIGEM,
                ude.unidade AS UNIDADE_DESTINO, tde.NOME AS TURMA_DESTINO,
                us.NOME AS SOLICITADA_POR, ue.NOME AS EFETIVADA_POR
         FROM TB_TRANSFERENCIAS tr
         INNER JOIN TB_TURMAS tor   ON tor.ID = tr.ID_TURMA_ORIGEM
         INNER JOIN TB_UNIDADES uor ON uor.id = tor.ID_UNIDADE
         INNER JOIN TB_UNIDADES ude ON ude.id = tr.ID_UNIDADE_DESTINO
         INNER JOIN TB_USUARIOS us  ON us.ID = tr.ID_USUARIO_SOLIC
         LEFT JOIN TB_TURMAS tde    ON tde.ID = tr.ID_TURMA_DESTINO
         LEFT JOIN TB_USUARIOS ue   ON ue.ID = tr.ID_USUARIO_EFETIVA
         WHERE tr.ID_ALUNO = ?
         ORDER BY tr.ID DESC',
        [(int)$idAluno]
    ) ?: [];
}

/* ------------------------------------------------------------ permissões */

/** Indicar a escola de destino: gestão da escola ONDE O ALUNO ESTÁ. */
function tr_pode_solicitar($aluno)
{
    return $aluno
        && ch_gestor()
        && (int)$aluno['ATIVO'] === 1
        && ch_pode_unidade($aluno['ID_UNIDADE'])
        && !tr_pendente($aluno['ID']);
}

/** Escolher turma e data: gestão da escola DE DESTINO. */
function tr_pode_receber($t)
{
    return $t
        && ch_gestor()
        && $t['SITUACAO'] === 'PENDENTE'
        && ch_pode_unidade($t['ID_UNIDADE_DESTINO']);
}

/** Cancelar a pendência: gestão de qualquer uma das duas pontas. */
function tr_pode_cancelar($t)
{
    return $t
        && ch_gestor()
        && $t['SITUACAO'] === 'PENDENTE'
        && (ch_pode_unidade($t['ID_UNIDADE_ORIGEM']) || ch_pode_unidade($t['ID_UNIDADE_DESTINO']));
}

/** Ver a transferência: qualquer das duas pontas. */
function tr_pode_ver($t)
{
    return $t && ch_gestor()
        && (ch_pode_unidade($t['ID_UNIDADE_ORIGEM']) || ch_pode_unidade($t['ID_UNIDADE_DESTINO']));
}

/** Quantas transferências estão aguardando a escola de quem está logado.
    É o número que aparece no menu. */
function tr_pendentes_recebidas()
{
    if (!ch_gestor()) return 0;
    if (ch_admin()) {
        return (int)ch_val("SELECT COUNT(*) FROM TB_TRANSFERENCIAS WHERE SITUACAO = 'PENDENTE'");
    }
    $ids = ch_unidades_ids();
    if (!$ids) return 0;
    $ph = implode(',', array_fill(0, count($ids), '?'));
    return (int)ch_val(
        "SELECT COUNT(*) FROM TB_TRANSFERENCIAS
         WHERE SITUACAO = 'PENDENTE' AND ID_UNIDADE_DESTINO IN ({$ph})",
        $ids
    );
}

/* ------------------------------------------------ opções para os selects */

/** Escolas possíveis de destino. Aqui NÃO se aplica o alcance do usuário de
    propósito: o diretor da escola de origem precisa poder indicar uma escola
    que não é dele — é justamente para lá que o aluno vai. Só se exclui a
    própria escola, porque isso não seria transferência. */
function tr_unidades_destino($idUnidadeAtual)
{
    return ch_rows(
        'SELECT id, unidade FROM TB_UNIDADES WHERE id <> ? ORDER BY unidade',
        [(int)$idUnidadeAtual]
    ) ?: [];
}

/** Turmas ativas da escola de destino, com a lotação, para a escola escolher. */
function tr_turmas_destino($idUnidade)
{
    return ch_rows(
        'SELECT t.ID, t.NOME, t.ANO_LETIVO, t.CAPACIDADE, p.NOME AS PERIODO,
                (SELECT COUNT(*) FROM TB_ALUNOS a WHERE a.ID_TURMA = t.ID AND a.ATIVO = 1) AS OCUPADAS
         FROM TB_TURMAS t
         INNER JOIN TB_PERIODOS p ON p.ID = t.ID_PERIODO
         WHERE t.ATIVO = 1 AND t.ID_UNIDADE = ?
         ORDER BY t.ANO_LETIVO DESC, t.NOME',
        [(int)$idUnidade]
    ) ?: [];
}

/** Data de início mais cedo aceitável: não antes da matrícula nem antes do
    último período já registrado (os períodos não podem se sobrepor). */
function tr_data_minima($aluno)
{
    $min = $aluno['DATA_MATRICULA'];
    $ultima = ch_val(
        "SELECT CONVERT(varchar(10), MAX(DATA_INICIO), 23) FROM TB_TRANSFERENCIAS
         WHERE ID_ALUNO = ? AND SITUACAO = 'CONCLUIDA'",
        [(int)$aluno['ID']]
    );
    if ($ultima) {
        $seguinte = date('Y-m-d', strtotime($ultima . ' +1 day'));
        if ($seguinte > $min) $min = $seguinte;
    }
    return $min;
}