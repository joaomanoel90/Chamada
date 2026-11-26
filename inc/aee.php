<?php
/**
 * MÓDULO DE GESTÃO DO ATENDIMENTO EDUCACIONAL ESPECIALIZADO (AEE)
 * 
 * Gerencia a atribuição de professores de AEE, horários na Sala de Recursos
 * Multifuncionais (SRM) e o histórico de atendimentos por Ano Letivo.
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!function_exists('aluno_inclusao_codigos')) {
    require_once(ABSPATH . 'alunos/inclusao.php');
}

/**
 * Retorna os anos letivos disponíveis com turmas ou atendimentos de AEE.
 */
function aee_anos_disponiveis()
{
    $anoAtual = (int)date('Y');
    $anos = ch_rows(
        'SELECT DISTINCT ANO_LETIVO FROM (
            SELECT ANO_LETIVO FROM TB_TURMAS
            UNION
            SELECT ANO_LETIVO FROM TB_ALUNO_AEE
        ) x WHERE ANO_LETIVO > 0 ORDER BY ANO_LETIVO DESC'
    );
    $lista = array_map(function ($r) { return (int)$r['ANO_LETIVO']; }, $anos ?: []);
    if (!$lista || !in_array($anoAtual, $lista, true)) {
        array_unshift($lista, $anoAtual);
        $lista = array_unique($lista);
        rsort($lista);
    }
    return $lista;
}

/**
 * Busca o atendimento de AEE de um aluno em um ano letivo específico.
 */
function aee_atendimento_aluno($idAluno, $anoLetivo)
{
    $idAluno = (int)$idAluno;
    $anoLetivo = (int)$anoLetivo;
    if ($idAluno <= 0 || $anoLetivo <= 0) return null;

    return ch_row(
        'SELECT ae.ID, ae.ID_ALUNO, ae.ANO_LETIVO, ae.ID_PROFESSOR_AEE, ae.ATIVO,
                ae.DIAS_HORARIOS, ae.SALA_RECURSOS, ae.OBJETIVOS_PLANO,
                ae.CREATED, ae.MODIFIED,
                u.NOME AS PROFESSOR_AEE_NOME, u.EMAIL AS PROFESSOR_AEE_EMAIL
         FROM TB_ALUNO_AEE ae
         LEFT JOIN TB_USUARIOS u ON u.ID = ae.ID_PROFESSOR_AEE
         WHERE ae.ID_ALUNO = ? AND ae.ANO_LETIVO = ?',
        [$idAluno, $anoLetivo]
    );
}

/**
 * Retorna o histórico cronológico de todos os atendimentos de AEE de um aluno.
 */
function aee_historico_aluno($idAluno)
{
    $idAluno = (int)$idAluno;
    if ($idAluno <= 0) return [];

    return ch_rows(
        'SELECT ae.ID, ae.ID_ALUNO, ae.ANO_LETIVO, ae.ID_PROFESSOR_AEE, ae.ATIVO,
                ae.DIAS_HORARIOS, ae.SALA_RECURSOS, ae.OBJETIVOS_PLANO,
                CONVERT(varchar(10), ae.CREATED, 23) AS CRIADO_EM,
                u.NOME AS PROFESSOR_AEE_NOME
         FROM TB_ALUNO_AEE ae
         LEFT JOIN TB_USUARIOS u ON u.ID = ae.ID_PROFESSOR_AEE
         WHERE ae.ID_ALUNO = ?
         ORDER BY ae.ANO_LETIVO DESC, ae.ID DESC',
        [$idAluno]
    );
}

/**
 * Lista alunos para o painel de AEE, com filtros de alcance e permissão.
 */
function aee_painel_listar($anoLetivo, $filtros = [])
{
    $anoLetivo = (int)$anoLetivo;
    $filtroTurma = ch_filtro_turma('t');
    
    $fUnidade = (int)($filtros['unidade_id'] ?? 0);
    $fProf    = (int)($filtros['professor_id'] ?? 0);
    $fStatus  = trim((string)($filtros['status'] ?? ''));
    $fBusca   = trim((string)($filtros['busca'] ?? ''));

    $sql = 'SELECT a.ID, a.MATRICULA, a.NOME, a.CPF, a.ATIVO AS ALUNO_ATIVO,
                   a.DEFICIENCIAS_INCLUSAO, a.SIMBOLO_TEA_INCLUSAO,
                   ISNULL(a.AUTORIZA_MARCADOR_INCLUSAO, 0) AS AUTORIZA_MARCADOR_INCLUSAO,
                   a.CONDICAO_ESPECIFICA, a.OUTROS_ACOMPANHAMENTOS,
                   CONVERT(varchar(10), a.NASCIMENTO, 23) AS NASCIMENTO,
                   t.ID AS ID_TURMA, t.NOME AS TURMA, t.SERIE, t.ANO_LETIVO,
                   u.id AS ID_UNIDADE, u.unidade AS UNIDADE,
                   ae.ID AS ID_AEE, ae.ANO_LETIVO AS AEE_ANO,
                   ae.ID_PROFESSOR_AEE, ae.ATIVO AS AEE_ATIVO,
                   ae.DIAS_HORARIOS, ae.SALA_RECURSOS, ae.OBJETIVOS_PLANO,
                   paee.NOME AS PROFESSOR_AEE_NOME
            FROM TB_ALUNOS a
            INNER JOIN TB_TURMAS t ON t.ID = a.ID_TURMA
            INNER JOIN TB_UNIDADES u ON u.id = t.ID_UNIDADE
            LEFT JOIN TB_ALUNO_AEE ae ON ae.ID_ALUNO = a.ID AND ae.ANO_LETIVO = ?
            LEFT JOIN TB_USUARIOS paee ON paee.ID = ae.ID_PROFESSOR_AEE
            WHERE a.ATIVO = 1 ' . $filtroTurma['sql'];

    $params = array_merge([$anoLetivo], $filtroTurma['params']);

    // O aluno entra no painel se: tem registro em TB_ALUNO_AEE para o ano, ou PASSA_AEE = 1, ou possui deficiência de inclusão
    $sql .= " AND (ae.ID IS NOT NULL OR a.PASSA_AEE = 1 OR (a.DEFICIENCIAS_INCLUSAO IS NOT NULL AND a.DEFICIENCIAS_INCLUSAO <> '[]'))";

    if ($fUnidade > 0) {
        $sql .= ' AND t.ID_UNIDADE = ?';
        $params[] = $fUnidade;
    }

    if ($fProf > 0) {
        $sql .= ' AND ae.ID_PROFESSOR_AEE = ?';
        $params[] = $fProf;
    } elseif ($fProf === -1) {
        // Sem professor atribuído
        $sql .= ' AND (ae.ID_PROFESSOR_AEE IS NULL OR ae.ID_PROFESSOR_AEE = 0)';
    }

    if ($fStatus === 'atendimento') {
        $sql .= ' AND ae.ATIVO = 1 AND ae.ID_PROFESSOR_AEE IS NOT NULL';
    } elseif ($fStatus === 'aguardando') {
        $sql .= ' AND (ae.ID IS NULL OR ae.ID_PROFESSOR_AEE IS NULL OR ae.ID_PROFESSOR_AEE = 0)';
    } elseif ($fStatus === 'inativo') {
        $sql .= ' AND ae.ATIVO = 0';
    }

    if ($fBusca !== '') {
        $like = '%' . $fBusca . '%';
        $sql .= ' AND (a.NOME LIKE ? OR a.MATRICULA LIKE ? OR paee.NOME LIKE ? OR ae.SALA_RECURSOS LIKE ?)';
        array_push($params, $like, $like, $like, $like);
    }

    $sql .= ' ORDER BY u.unidade, t.SERIE, t.NOME, a.NOME';

    return ch_rows($sql, $params);
}

/**
 * Salva ou atualiza a atribuição do AEE para um aluno em um ano letivo.
 */
function aee_salvar_atendimento($idAluno, $anoLetivo, $dados, &$erros = [])
{
    $idAluno = (int)$idAluno;
    $anoLetivo = (int)$anoLetivo;
    if ($idAluno <= 0 || $anoLetivo <= 0) {
        $erros[] = 'Identificação de aluno ou ano letivo inválida.';
        return false;
    }

    $aluno = ch_aluno($idAluno);
    if (!$aluno) {
        $erros[] = 'Aluno não encontrado.';
        return false;
    }

    $ativo          = !empty($dados['ativo']) ? 1 : 0;
    $idProfAee      = (int)($dados['id_professor_aee'] ?? 0);
    $diasHorarios   = mb_substr(strip_tags(trim((string)($dados['dias_horarios'] ?? ''))), 0, 500);
    $salaRecursos   = mb_substr(strip_tags(trim((string)($dados['sala_recursos'] ?? ''))), 0, 150);
    $objetivosPlano = mb_substr(strip_tags(trim((string)($dados['objetivos_plano'] ?? ''))), 0, 1000);

    if ($idProfAee > 0) {
        $okProf = ch_val(
            "SELECT ID FROM TB_USUARIOS WHERE ID = ? AND NIVEL = 3 AND ATIVO = 1 AND CATEGORIA = 'AEE'",
            [$idProfAee]
        );
        if (!$okProf) {
            $erros[] = 'Selecione um(a) professor(a) ativo(a) da categoria AEE.';
            return false;
        }
    } else {
        $idProfAee = null;
    }

    $existente = ch_row(
        'SELECT ID FROM TB_ALUNO_AEE WHERE ID_ALUNO = ? AND ANO_LETIVO = ?',
        [$idAluno, $anoLetivo]
    );

    $conn = ch_db();
    sqlsrv_begin_transaction($conn);

    if ($existente) {
        $ok = ch_exec(
            'UPDATE TB_ALUNO_AEE
                SET ID_PROFESSOR_AEE = ?, ATIVO = ?, DIAS_HORARIOS = ?,
                    SALA_RECURSOS = ?, OBJETIVOS_PLANO = ?, MODIFIED = SYSDATETIME(),
                    ID_USUARIO = ?
              WHERE ID = ?',
            [$idProfAee, $ativo, $diasHorarios, $salaRecursos, $objetivosPlano, ch_uid(), (int)$existente['ID']]
        );
    } else {
        $novoId = ch_insert_id(
            'INSERT INTO TB_ALUNO_AEE
                (ID_ALUNO, ANO_LETIVO, ID_PROFESSOR_AEE, ATIVO, DIAS_HORARIOS, SALA_RECURSOS, OBJETIVOS_PLANO, ID_USUARIO)
             OUTPUT INSERTED.ID
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
            [$idAluno, $anoLetivo, $idProfAee, $ativo, $diasHorarios, $salaRecursos, $objetivosPlano, ch_uid()]
        );
        $ok = $novoId > 0;
    }

    $idAtendimento = $existente ? (int)$existente['ID'] : (int)$novoId;
    if (!$ok) {
        sqlsrv_rollback($conn);
        $erros[] = 'Não foi possível salvar a atribuição do AEE no banco de dados.';
        return false;
    }

    // Sincroniza em TB_ALUNOS se o ano for o ano letivo da turma atual do aluno
    if ((int)$aluno['ANO_LETIVO'] === $anoLetivo) {
        $okSync = ch_exec(
            'UPDATE TB_ALUNOS
                SET PASSA_AEE = ?,
                    ID_PROFESSOR_AEE = ?,
                    AEE_OBSERVACOES = ?
              WHERE ID = ?',
            [$ativo, $idProfAee, $diasHorarios !== '' ? $diasHorarios : null, $idAluno]
        );
        if (!$okSync) {
            sqlsrv_rollback($conn);
            $erros[] = 'Não foi possível sincronizar o aluno atual.';
            return false;
        }
    }

    if (sqlsrv_commit($conn)) {
        ch_auditar(
            'AEE',
            $existente ? 'Atualizar atendimento' : 'Criar atendimento',
            'ALUNO',
            $idAluno,
            [
                'ID_ATENDIMENTO'   => $idAtendimento,
                'ANO_LETIVO'       => $anoLetivo,
                'ATIVO'            => $ativo,
                'ID_PROFESSOR_AEE' => $idProfAee,
            ]
        );
        return true;
    }

    sqlsrv_rollback($conn);
    $erros[] = 'Falha ao confirmar transação.';
    return false;
}
