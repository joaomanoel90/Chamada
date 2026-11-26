<?php
/* ============================================================================
   SUSPENSÃO DE ALUNOS — regras de período, faltas e histórico
   ----------------------------------------------------------------------------
   Uma suspensão é uma ação da gestão escolar, fora da edição do cadastro.
   Cada registro guarda início, fim, motivo e quem determinou. As faltas em
   TB_CHAMADA ficam ligadas por ID_SUSPENSAO. Cada liberação parcial informa
   quais datas/aulas foram tiradas, se virou presença nos dias passados, o
   motivo e o usuário; quando não sobra nenhuma falta ligada, a suspensão é
   marcada como encerrada.

   Este arquivo presume config.php, DBAPI e inc/chamada.php já carregados.
   As funções de gravação DEVEM ser chamadas dentro de uma transação aberta.
   ============================================================================ */

/** Tabela de suspensões instalada? */
function susp_tem_tabela()
{
    static $ok = null;
    if ($ok === null) {
        $ok = (bool)ch_val(
            "SELECT 1 FROM sys.objects
              WHERE object_id = OBJECT_ID(N'dbo.TB_SUSPENSOES_ALUNOS') AND type = N'U'"
        );
    }
    return $ok;
}

/** Coluna que liga a falta à suspensão instalada? */
function susp_tem_coluna_chamada()
{
    static $ok = null;
    if ($ok === null) {
        $ok = (bool)ch_val(
            "SELECT 1 FROM sys.columns
              WHERE object_id = OBJECT_ID(N'dbo.TB_CHAMADA') AND name = N'ID_SUSPENSAO'"
        );
    }
    return $ok;
}

/** Tabela de liberações parciais instalada? */
function susp_tem_tabela_liberacoes()
{
    static $ok = null;
    if ($ok === null) {
        $ok = (bool)ch_val(
            "SELECT 1 FROM sys.objects
              WHERE object_id = OBJECT_ID(N'dbo.TB_SUSPENSOES_ALUNOS_LIBERACOES') AND type = N'U'"
        );
    }
    return $ok;
}

function susp_pronto()
{
    return susp_tem_tabela() && susp_tem_tabela_liberacoes() && susp_tem_coluna_chamada();
}

/** Histórico completo de suspensões do aluno (mais recente primeiro). */
function susp_historico($idAluno)
{
    if (!susp_tem_tabela() || !susp_tem_coluna_chamada()) return [];

    $colLiberacoes = susp_tem_tabela_liberacoes()
        ? ', (SELECT COUNT(*) FROM dbo.TB_SUSPENSOES_ALUNOS_LIBERACOES l
               WHERE l.ID_SUSPENSAO = s.ID) AS LIBERACOES'
        : ', 0 AS LIBERACOES';

    return ch_rows(
        'SELECT s.ID, s.DATA_INICIO, s.DATA_FIM, s.MOTIVO, s.SITUACAO,
                CONVERT(varchar(10), s.DATA_INICIO, 23) AS DATA_INICIO_TXT,
                CONVERT(varchar(10), s.DATA_FIM, 23) AS DATA_FIM_TXT,
                CONVERT(varchar(19), s.CRIADO_EM, 120) AS CRIADO_EM_TXT,
                CONVERT(varchar(19), s.ENCERRADA_EM, 120) AS ENCERRADA_EM_TXT,
                s.MOTIVO_ENCERRAMENTO,
                u.NOME AS USUARIO_NOME,
                uf.NOME AS USUARIO_ENCERRAMENTO_NOME,
                (SELECT COUNT(*) FROM dbo.TB_CHAMADA c WHERE c.ID_SUSPENSAO = s.ID) AS FALTAS_GERADAS'
        . $colLiberacoes . '
         FROM dbo.TB_SUSPENSOES_ALUNOS s
         INNER JOIN dbo.TB_USUARIOS u ON u.ID = s.ID_USUARIO
         LEFT JOIN dbo.TB_USUARIOS uf ON uf.ID = s.ID_USUARIO_ENCERRAMENTO
         WHERE s.ID_ALUNO = ?
         ORDER BY s.DATA_INICIO DESC, s.ID DESC',
        [(int)$idAluno]
    );
}

/** Suspensão em vigor na data de hoje, se houver. */
function susp_em_vigor($idAluno)
{
    if (!susp_tem_tabela() || !susp_tem_coluna_chamada()) return null;

    return ch_row(
        'SELECT s.ID, s.DATA_INICIO, s.DATA_FIM, s.MOTIVO,
                CONVERT(varchar(10), s.DATA_INICIO, 23) AS DATA_INICIO_TXT,
                CONVERT(varchar(10), s.DATA_FIM, 23) AS DATA_FIM_TXT,
                CONVERT(varchar(19), s.CRIADO_EM, 120) AS CRIADO_EM_TXT,
                u.NOME AS USUARIO_NOME,
                (SELECT COUNT(*) FROM dbo.TB_CHAMADA c WHERE c.ID_SUSPENSAO = s.ID) AS FALTAS_GERADAS
         FROM dbo.TB_SUSPENSOES_ALUNOS s
         INNER JOIN dbo.TB_USUARIOS u ON u.ID = s.ID_USUARIO
         WHERE s.ID_ALUNO = ?
           AND EXISTS (SELECT 1 FROM dbo.TB_CHAMADA c
                        WHERE c.ID_SUSPENSAO = s.ID AND c.STATUS = N\'F\'
                          AND c.DATA = ?)
         ORDER BY s.DATA_INICIO DESC, s.ID DESC',
        [(int)$idAluno, ch_hoje()]
    );
}

/** Suspensão que ainda cobre parte do intervalo pedido. Só contam as faltas
    ligadas que permanecem em TB_CHAMADA, então liberações parciais reduzem o
    alcance do conflito. Devolve a linha conflitante ou null. */
function susp_conflito($idAluno, $inicio, $fim, $ignorarId = 0)
{
    if (!susp_tem_tabela() || !susp_tem_coluna_chamada()) return null;

    return ch_row(
        'SELECT TOP 1 s.ID,
                CONVERT(varchar(10), s.DATA_INICIO, 23) AS DATA_INICIO_TXT,
                CONVERT(varchar(10), s.DATA_FIM, 23) AS DATA_FIM_TXT,
                s.SITUACAO
         FROM dbo.TB_SUSPENSOES_ALUNOS s
         WHERE s.ID_ALUNO = ? AND s.ID <> ?
           AND s.DATA_INICIO <= ?
           AND EXISTS (SELECT 1 FROM dbo.TB_CHAMADA c
                       WHERE c.ID_SUSPENSAO = s.ID AND c.STATUS = N\'F\')
          AND (SELECT MAX(c.DATA) FROM dbo.TB_CHAMADA c
                WHERE c.ID_SUSPENSAO = s.ID AND c.STATUS = N\'F\') >= ?
         ORDER BY s.DATA_INICIO DESC',
        [(int)$idAluno, (int)$ignorarId, $fim, $inicio]
    );
}

/** Texto curto que aparece na justificativa da chamada gerada. */
function susp_texto_chamada($motivo)
{
    return mb_substr('Suspensão: ' . trim((string)$motivo), 0, 500);
}

/** Aulas ainda ligadas à suspensão, disponíveis para liberação. */
function susp_itens($idSuspensao)
{
    if (!susp_pronto()) return [];

    $colAula = ch_chamada_tem_aula() ? ', c.AULA_SEQ' : '';
    $linhas = ch_rows(
        'SELECT CONVERT(varchar(10), c.DATA, 23) AS DATA_TXT' . $colAula . '
           FROM dbo.TB_CHAMADA c
          WHERE c.ID_SUSPENSAO = ?
          ORDER BY c.DATA' . (ch_chamada_tem_aula() ? ', c.AULA_SEQ' : ''),
        [(int)$idSuspensao]
    );
    $itens = [];
    foreach ($linhas as $linha) {
        $data = (string)$linha['DATA_TXT'];
        $aula = ch_chamada_tem_aula() ? (int)$linha['AULA_SEQ'] : 1;
        $itens[] = [
            'CHAVE' => $data . ':' . $aula,
            'DATA_TXT' => $data,
            'AULA' => $aula,
            'PASSOU' => $data <= ch_hoje(),
        ];
    }
    return $itens;
}

/** Histórico de liberações parciais de uma suspensão. */
function susp_liberacoes($idSuspensao)
{
    if (!susp_tem_tabela_liberacoes()) return [];

    return ch_rows(
        'SELECT CONVERT(varchar(10), l.DATA_LIBERADA, 23) AS DATA_TXT,
                l.AULA_SEQ, l.PRESENCA_LANCADA, l.MOTIVO,
                CONVERT(varchar(19), l.CRIADO_EM, 120) AS CRIADO_EM_TXT,
                u.NOME AS USUARIO_NOME
           FROM dbo.TB_SUSPENSOES_ALUNOS_LIBERACOES l
          INNER JOIN dbo.TB_USUARIOS u ON u.ID = l.ID_USUARIO
          WHERE l.ID_SUSPENSAO = ?
          ORDER BY l.DATA_LIBERADA, l.AULA_SEQ, l.ID',
        [(int)$idSuspensao]
    );
}

/**
 * Registra a suspensão e gera as faltas em todas as aulas letivas do período.
 * Deve ser chamado dentro de transação. Não inicia nem confirma transação.
 *
 * Devolve: ok, erro, id, criadas, retificadas, jaFaltas, semAula.
 */
function susp_aplicar($idAluno, $inicio, $fim, $motivo)
{
    $retorno = [
        'ok' => false, 'erro' => '', 'id' => 0,
        'criadas' => 0, 'retificadas' => 0, 'jaFaltas' => 0, 'semAula' => 0,
    ];

    if (!susp_pronto()) {
        $retorno['erro'] = 'Banco não atualizado. Execute sql_inicial/ATUALIZAR_SUSPENSAO_ALUNOS.sql.';
        return $retorno;
    }

    $inicio  = (string)$inicio;
    $fim     = (string)$fim;
    $motivo  = trim((string)$motivo);
    $dias = (int)floor((strtotime($fim) - strtotime($inicio)) / 86400);
    if ($dias < 0 || $dias > 366) {
        $retorno['erro'] = 'Período inválido: o fim deve ser depois do início e o intervalo não pode passar de 366 dias.';
        return $retorno;
    }

    $idSuspensao = ch_insert_id(
        'INSERT INTO dbo.TB_SUSPENSOES_ALUNOS
            (ID_ALUNO, DATA_INICIO, DATA_FIM, MOTIVO, ID_USUARIO)
         OUTPUT INSERTED.ID
         VALUES (?, ?, ?, ?, ?)',
        [(int)$idAluno, $inicio, $fim, $motivo, ch_uid()]
    );
    if (!$idSuspensao) {
        $retorno['erro'] = 'Falha ao registrar a suspensão.';
        return $retorno;
    }
    $retorno['id'] = $idSuspensao;

    $justificativa = susp_texto_chamada($motivo);
    $motivoRetificacao = mb_substr('Suspensão aplicada: ' . $motivo, 0, 500);
    $temAula = ch_chamada_tem_aula();
    $aulas = $temAula ? [1, 2] : [1];
    $tsInicio = strtotime($inicio);
    $tsFim = strtotime($fim);

    for ($ts = $tsInicio; $ts <= $tsFim; $ts = strtotime('+1 day', $ts)) {
        $dataIso = date('Y-m-d', $ts);

        /* A turma pode mudar no meio do período; cada data usa o vínculo
           vigente nela, como a chamada normal já faz. */
        $idTurma = tr_turma_na_data($idAluno, $dataIso);
        $turma = $idTurma ? ch_turma($idTurma) : null;

        /* $ignorarFuturo e $ignorarEquipe: a suspensão é uma ação da gestão e
           cobre datas futuras. Os demais filtros (calendário, feriado) valem. */
        if (!$turma || ch_bloqueio_chamada($dataIso, $turma, true, true) !== null) {
            $retorno['semAula']++;
            continue;
        }

        foreach ($aulas as $aula) {
            $paramsAtual = [(int)$idAluno, $dataIso];
            $whereAula = '';
            if ($temAula) {
                $whereAula = ' AND c.AULA_SEQ = ?';
                $paramsAtual[] = $aula;
            }
            $atual = ch_row(
                'SELECT c.ID, c.STATUS, c.JUSTIFICATIVA
                   FROM dbo.TB_CHAMADA AS c WITH (UPDLOCK, HOLDLOCK)
                  WHERE c.ID_ALUNO = ? AND c.DATA = ?' . $whereAula,
                $paramsAtual
            );

            /* Falta que já existia (do professor ou de outra origem) fica como
               estava: não é preciso retificar e não será removida depois. */
            if ($atual && $atual['STATUS'] === 'F') {
                $retorno['jaFaltas']++;
                continue;
            }

            if ($atual) {
                $okRet = ch_exec(
                    'INSERT INTO dbo.TB_CHAMADA_RETIFICACOES
                        (ID_CHAMADA, STATUS_ANTERIOR, STATUS_NOVO, JUSTIFICATIVA_ANTERIOR,
                         JUSTIFICATIVA_NOVA, MOTIVO, ID_USUARIO)
                     VALUES (?, ?, N\'F\', ?, ?, ?, ?)',
                    [(int)$atual['ID'], $atual['STATUS'], $atual['JUSTIFICATIVA'],
                     $justificativa, $motivoRetificacao, ch_uid()]
                );
                if (!$okRet) {
                    $retorno['erro'] = 'Falha ao registrar a retificação da chamada.';
                    return $retorno;
                }
                $okUpd = ch_exec(
                    'UPDATE dbo.TB_CHAMADA
                        SET STATUS = N\'F\', JUSTIFICATIVA = ?, ID_USUARIO = ?,
                            ID_SUSPENSAO = ?, MODIFIED = GETDATE()
                      WHERE ID = ?',
                    [$justificativa, ch_uid(), $idSuspensao, (int)$atual['ID']]
                );
                if (!$okUpd) {
                    $retorno['erro'] = 'Falha ao atualizar a chamada para falta.';
                    return $retorno;
                }
                $retorno['retificadas']++;
                continue;
            }

            $colAula = $temAula ? ', AULA_SEQ' : '';
            $marcaAula = $temAula ? ', ?' : '';
            $params = [(int)$idAluno, $dataIso];
            if ($temAula) $params[] = $aula;
            array_push($params, 'F', $justificativa, ch_uid(), ch_uid(), $idSuspensao);
            $okIns = ch_exec(
                'INSERT INTO dbo.TB_CHAMADA
                    (ID_ALUNO, DATA' . $colAula . ', STATUS, JUSTIFICATIVA,
                     ID_USUARIO, CONFIRMADA_EM, CONFIRMADA_POR, ID_SUSPENSAO)
                 VALUES (?, ?' . $marcaAula . ', ?, ?, ?, GETDATE(), ?, ?)',
                $params
            );
            if (!$okIns) {
                $retorno['erro'] = 'Falha ao gravar falta da suspensão em ' . ch_data_br($dataIso) . '.';
                return $retorno;
            }
            $retorno['criadas']++;
        }
    }

    $retorno['ok'] = true;
    return $retorno;
}

/**
 * Libera datas/aulas específicas de uma suspensão. Nos dias que já passaram,
 * a falta pode virar presença (com retificação) ou ser removida, ficando a
 * chamada pendente. Cada item liberado vai para o histórico. Quando não sobra
 * nenhuma falta ligada, a suspensão é marcada como encerrada.
 * Deve ser chamado dentro de transação.
 *
 * $itens: array de chaves "YYYY-MM-DD:AULA" (1 ou 2).
 * $presenca: 1 para lançar presença nos dias já passados; 0 para só remover.
 */
function susp_liberar($idSuspensao, $itens, $presenca, $motivo)
{
    $retorno = [
        'ok' => false, 'erro' => '', 'id' => 0, 'aluno_id' => 0,
        'aluno_nome' => '', 'removidas' => 0, 'presencas' => 0,
        'encerrada' => false, 'restantes' => 0,
    ];

    if (!susp_pronto()) {
        $retorno['erro'] = 'Banco não atualizado. Execute sql_inicial/ATUALIZAR_SUSPENSAO_ALUNOS.sql.';
        return $retorno;
    }

    $motivo = trim((string)$motivo);
    if ($motivo === '' || mb_strlen($motivo) > 500) {
        $retorno['erro'] = 'Informe o motivo da liberação (máximo de 500 caracteres).';
        return $retorno;
    }
    if (!is_array($itens) || !$itens) {
        $retorno['erro'] = 'Selecione pelo menos uma data/aula para liberar.';
        return $retorno;
    }
    $itens = array_values(array_unique(array_map('strval', $itens)));
    if (count($itens) > 800) {
        $retorno['erro'] = 'Selecione no máximo 800 aulas por liberação.';
        return $retorno;
    }
    $temAula = ch_chamada_tem_aula();
    $selecionados = [];
    foreach ($itens as $chave) {
        if (!preg_match('/^(\d{4}-\d{2}-\d{2}):([12])$/', $chave, $m)) {
            $retorno['erro'] = 'Data/aula inválida. Recarregue a página e tente novamente.';
            return $retorno;
        }
        $data = $m[1];
        $partes = explode('-', $data);
        if (!checkdate((int)$partes[1], (int)$partes[2], (int)$partes[0])
            || (!$temAula && (int)$m[2] !== 1)) {
            $retorno['erro'] = 'Data/aula inválida. Recarregue a página e tente novamente.';
            return $retorno;
        }
        $selecionados[] = [$data, (int)$m[2]];
    }

    $row = ch_row(
        'SELECT s.ID, s.ID_ALUNO, s.SITUACAO, a.NOME AS NOME
           FROM dbo.TB_SUSPENSOES_ALUNOS AS s WITH (UPDLOCK, HOLDLOCK)
          INNER JOIN dbo.TB_ALUNOS a ON a.ID = s.ID_ALUNO
          WHERE s.ID = ?',
        [(int)$idSuspensao]
    );
    if (!$row) {
        $retorno['erro'] = 'Suspensão não encontrada.';
        return $retorno;
    }
    $retorno['id'] = (int)$row['ID'];
    $retorno['aluno_id'] = (int)$row['ID_ALUNO'];
    $retorno['aluno_nome'] = (string)$row['NOME'];

    /* Suspensões encerradas pelo fluxo antigo podem ter deixado faltas ligadas
       no passado. Elas também podem ser liberadas para limpar a chamada. */
    if ((int)ch_val(
        'SELECT COUNT(*) FROM dbo.TB_CHAMADA WHERE ID_SUSPENSAO = ?',
        [(int)$idSuspensao]
    ) === 0) {
        $retorno['erro'] = 'Não há mais aulas ligadas a esta suspensão.';
        return $retorno;
    }

    $motivoRetificacao = mb_substr('Liberação de suspensão: ' . $motivo, 0, 500);
    $hoje = ch_hoje();
    foreach ($selecionados as $item) {
        list($data, $aula) = $item;
        $params = [(int)$idSuspensao, $data];
        $whereAula = '';
        if ($temAula) {
            $whereAula = ' AND c.AULA_SEQ = ?';
            $params[] = $aula;
        }
        $falta = ch_row(
            'SELECT c.ID, c.STATUS, c.JUSTIFICATIVA
               FROM dbo.TB_CHAMADA AS c WITH (UPDLOCK, HOLDLOCK)
              WHERE c.ID_SUSPENSAO = ? AND c.DATA = ?' . $whereAula,
            $params
        );
        if (!$falta) {
            $retorno['erro'] = 'A aula de ' . ch_data_br($data) . ' já não está ligada a esta suspensão. Recarregue a página.';
            return $retorno;
        }

        $comPresenca = $presenca && $data <= $hoje;
        if ($comPresenca) {
            $okRet = ch_exec(
                'INSERT INTO dbo.TB_CHAMADA_RETIFICACOES
                    (ID_CHAMADA, STATUS_ANTERIOR, STATUS_NOVO, JUSTIFICATIVA_ANTERIOR,
                     JUSTIFICATIVA_NOVA, MOTIVO, ID_USUARIO)
                 VALUES (?, ?, N\'P\', ?, NULL, ?, ?)',
                [(int)$falta['ID'], $falta['STATUS'], $falta['JUSTIFICATIVA'],
                 $motivoRetificacao, ch_uid()]
            );
            if (!$okRet) {
                $retorno['erro'] = 'Falha ao registrar a retificação da liberação.';
                return $retorno;
            }
            $okUpd = ch_exec(
                'UPDATE dbo.TB_CHAMADA
                    SET STATUS = N\'P\', JUSTIFICATIVA = NULL, ID_USUARIO = ?,
                        ID_SUSPENSAO = NULL, MODIFIED = GETDATE()
                  WHERE ID = ? AND ID_SUSPENSAO = ?',
                [ch_uid(), (int)$falta['ID'], (int)$idSuspensao]
            );
            $aindaLigada = (int)ch_val(
                'SELECT COUNT(*) FROM dbo.TB_CHAMADA
                  WHERE ID = ? AND ID_SUSPENSAO = ?',
                [(int)$falta['ID'], (int)$idSuspensao]
            );
            if (!$okUpd || $aindaLigada !== 0) {
                $retorno['erro'] = 'Falha ao lançar a presença da liberação.';
                return $retorno;
            }
            $retorno['presencas']++;
        } else {
            /* Quando a suspensão sobrescreveu um lançamento do professor, ela
               mesma criou uma retificação apontando para esta linha. O FK exige
               remover essas retificações antes de apagar a falta. */
            $okLimpa = ch_exec(
                'DELETE FROM dbo.TB_CHAMADA_RETIFICACOES WHERE ID_CHAMADA = ?',
                [(int)$falta['ID']]
            );
            if (!$okLimpa) {
                $retorno['erro'] = 'Falha ao limpar as retificações da falta liberada.';
                return $retorno;
            }
            $okDel = ch_exec(
                'DELETE FROM dbo.TB_CHAMADA
                  WHERE ID = ? AND ID_SUSPENSAO = ?',
                [(int)$falta['ID'], (int)$idSuspensao]
            );
            $aindaLigada = (int)ch_val(
                'SELECT COUNT(*) FROM dbo.TB_CHAMADA
                  WHERE ID = ? AND ID_SUSPENSAO = ?',
                [(int)$falta['ID'], (int)$idSuspensao]
            );
            if (!$okDel || $aindaLigada !== 0) {
                $retorno['erro'] = 'Falha ao retirar a falta da liberação.';
                return $retorno;
            }
            $retorno['removidas']++;
        }

        $okHist = ch_exec(
            'INSERT INTO dbo.TB_SUSPENSOES_ALUNOS_LIBERACOES
                (ID_SUSPENSAO, DATA_LIBERADA, AULA_SEQ, PRESENCA_LANCADA, MOTIVO, ID_USUARIO)
             VALUES (?, ?, ?, ?, ?, ?)',
            [(int)$idSuspensao, $data, $aula, $comPresenca ? 1 : 0, $motivo, ch_uid()]
        );
        if (!$okHist) {
            $retorno['erro'] = 'Falha ao registrar o histórico da liberação.';
            return $retorno;
        }
    }

    $restantes = (int)ch_val(
        'SELECT COUNT(*) FROM dbo.TB_CHAMADA WHERE ID_SUSPENSAO = ?',
        [(int)$idSuspensao]
    );
    $retorno['restantes'] = $restantes;
    if ($restantes === 0) {
        $retorno['encerrada'] = true;
        if ($row['SITUACAO'] === 'ATIVA') {
            $okFim = ch_exec(
                'UPDATE dbo.TB_SUSPENSOES_ALUNOS
                    SET SITUACAO = N\'ENCERRADA\', ENCERRADA_EM = SYSDATETIME(),
                        ID_USUARIO_ENCERRAMENTO = ?, MOTIVO_ENCERRAMENTO = ?
                  WHERE ID = ? AND SITUACAO = N\'ATIVA\'',
                [ch_uid(), $motivo, (int)$idSuspensao]
            );
            $situacao = (string)ch_val(
                'SELECT SITUACAO FROM dbo.TB_SUSPENSOES_ALUNOS WHERE ID = ?',
                [(int)$idSuspensao]
            );
            if (!$okFim || $situacao !== 'ENCERRADA') {
                $retorno['erro'] = 'Não foi possível encerrar a suspensão. Tente novamente.';
                return $retorno;
            }
        }
    }

    $retorno['ok'] = true;
    return $retorno;
}
