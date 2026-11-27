<?php
/**
 * ============================================================================
 * MODELOS DE TURMA
 * ----------------------------------------------------------------------------
 * Padrão nomeado com aulas por dia e matérias (total semanal). Serve para
 * criar várias turmas iguais na mesma unidade ou em outras, sem copiar código.
 * O professor continua sendo definido na Atribuição da matéria.
 * ============================================================================
 */

/** Instalações antigas podem não ter as tabelas ainda. */
function mod_tem_tabela()
{
    static $tem = null;
    if ($tem === null) {
        $tem = (bool)ch_val(
            "SELECT 1 FROM sys.tables WHERE object_id = OBJECT_ID(N'dbo.TB_MODELOS_TURMA')"
        );
    }
    return $tem;
}

/** Modelos ativos para os selects. */
function mod_opcoes($soAtivos = true)
{
    if (!mod_tem_tabela()) return [];
    $sql = 'SELECT ID, NOME, AULAS_DIA, DESCRICAO, ATIVO FROM TB_MODELOS_TURMA';
    if ($soAtivos) $sql .= ' WHERE ATIVO = 1';
    $sql .= ' ORDER BY NOME';
    return ch_rows($sql) ?: [];
}

/** Modelo completo, com as matérias. */
function mod_dados($id)
{
    $id = (int)$id;
    if ($id <= 0 || !mod_tem_tabela()) return null;
    $modelo = ch_row('SELECT * FROM TB_MODELOS_TURMA WHERE ID = ?', [$id]);
    if (!$modelo) return null;
    $modelo['DISCIPLINAS'] = ch_rows(
        'SELECT ID, NOME, AULAS FROM TB_MODELO_DISCIPLINAS WHERE ID_MODELO = ? ORDER BY NOME',
        [$id]
    ) ?: [];
    return $modelo;
}

/** Valida o formulário do modelo. Devolve [dados, erros]. */
function mod_validar($post, $idAtual = 0)
{
    $d = [
        'ID'        => (int)$idAtual,
        'NOME'      => strip_tags(trim((string)($post['nome'] ?? ''))),
        'AULAS_DIA' => (int)($post['aulas_dia'] ?? 2),
        'DESCRICAO' => strip_tags(trim((string)($post['descricao'] ?? ''))),
        'ATIVO'     => !empty($post['ativo']) ? 1 : 0,
        'DISCIPLINAS' => eq_disciplinas_ler_post($post),
    ];
    $erros = [];

    if ($d['NOME'] === '' || mb_strlen($d['NOME']) > 80) {
        $erros[] = 'Informe o nome do modelo (até 80 caracteres).';
    } elseif (mod_tem_tabela()) {
        $params = [$d['NOME']];
        $sql = 'SELECT ID FROM TB_MODELOS_TURMA WHERE NOME = ?';
        if ($d['ID'] > 0) { $sql .= ' AND ID <> ?'; $params[] = $d['ID']; }
        if (ch_val($sql, $params)) {
            $erros[] = 'Já existe um modelo com esse nome.';
        }
    }
    if ($d['AULAS_DIA'] < 1 || $d['AULAS_DIA'] > ch_aula_max()) {
        $erros[] = 'Informe de 1 a ' . ch_aula_max() . ' aulas por dia.';
    }
    if (mb_strlen($d['DESCRICAO']) > 300) {
        $erros[] = 'A descrição deve ter até 300 caracteres.';
    }
    foreach (eq_disciplinas_validar($d['DISCIPLINAS'], 0) as $msg) {
        $erros[] = $msg;
    }
    return [$d, $erros];
}

/** Grava modelo e matérias. Devolve ID ou 0. Deve rodar dentro de transação. */
function mod_salvar($d)
{
    $id = (int)($d['ID'] ?? 0);
    if ($id > 0) {
        $ok = ch_exec(
            'UPDATE TB_MODELOS_TURMA SET NOME = ?, AULAS_DIA = ?, DESCRICAO = ?, ATIVO = ? WHERE ID = ?',
            [$d['NOME'], $d['AULAS_DIA'], $d['DESCRICAO'] ?: null, $d['ATIVO'], $id]
        );
        if (!$ok) return 0;
        if (!ch_exec('DELETE FROM TB_MODELO_DISCIPLINAS WHERE ID_MODELO = ?', [$id])) return 0;
    } else {
        $id = ch_insert_id(
            'INSERT INTO TB_MODELOS_TURMA (NOME, AULAS_DIA, DESCRICAO, ATIVO)
             OUTPUT INSERTED.ID VALUES (?, ?, ?, ?)',
            [$d['NOME'], $d['AULAS_DIA'], $d['DESCRICAO'] ?: null, $d['ATIVO']]
        );
        if ($id <= 0) return 0;
    }

    foreach ($d['DISCIPLINAS'] as $disc) {
        $ok = ch_exec(
            'INSERT INTO TB_MODELO_DISCIPLINAS (ID_MODELO, NOME, AULAS) VALUES (?, ?, ?)',
            [$id, $disc['NOME'], $disc['AULAS']]
        );
        if (!$ok) return 0;
    }
    return $id;
}

/**
 * Aplica o modelo às turmas escolhidas. Não remove matérias que já existam:
 * apenas atualiza as que batem pelo nome e acrescenta as que faltam.
 * Deve rodar dentro de transação.
 */
function mod_aplicar($idModelo, $turmasIds)
{
    $modelo = mod_dados($idModelo);
    if (!$modelo) return ['aplicadas' => 0, 'avisos' => ['Modelo não encontrado.']];

    $aplicadas = 0;
    $avisos = [];
    foreach (array_unique(array_map('intval', (array)$turmasIds)) as $idTurma) {
        if ($idTurma <= 0 || !ch_pode_turma($idTurma)) continue;
        $turma = ch_turma($idTurma);
        if (!$turma) continue;

        $ok = ch_exec('UPDATE TB_TURMAS SET AULAS_DIA = ? WHERE ID = ?', [$modelo['AULAS_DIA'], $idTurma]);
        if (!$ok) return ['aplicadas' => $aplicadas, 'avisos' => ['Falha ao atualizar a turma ' . $turma['NOME'] . '.']];

        foreach ($modelo['DISCIPLINAS'] as $disc) {
            $idDisc = (int)ch_val(
                'SELECT ID FROM TB_TURMA_DISCIPLINAS WHERE ID_TURMA = ? AND NOME = ?',
                [$idTurma, $disc['NOME']]
            );
            if ($idDisc > 0) {
                $atribuidas = eq_disciplina_atribuidas($idDisc);
                if ((int)$disc['AULAS'] < $atribuidas) {
                    $avisos[] = $turma['NOME'] . ': "' . $disc['NOME'] . '" manteve '
                        . $atribuidas . ' aula(s), pois já há atribuições.';
                    continue;
                }
                $ok = ch_exec('UPDATE TB_TURMA_DISCIPLINAS SET AULAS = ? WHERE ID = ?', [$disc['AULAS'], $idDisc]);
            } else {
                $ok = ch_exec(
                    'INSERT INTO TB_TURMA_DISCIPLINAS (ID_TURMA, NOME, AULAS) VALUES (?, ?, ?)',
                    [$idTurma, $disc['NOME'], $disc['AULAS']]
                );
            }
            if (!$ok) return ['aplicadas' => $aplicadas, 'avisos' => ['Falha ao salvar matéria na turma ' . $turma['NOME'] . '.']];
        }

        /* Se a turma passou a ter menos aulas por dia, posições acima do novo
           limite saem da grade para não aparecerem como aulas válidas. */
        $removidas = 0;
        if (ch_grade_tem_tabela()) {
            $stmt = ch_q(
                'DELETE FROM TB_GRADE_AULAS WHERE ID_TURMA = ? AND AULA_SEQ > ?',
                [$idTurma, $modelo['AULAS_DIA']]
            );
            if ($stmt === false) {
                return ['aplicadas' => $aplicadas, 'avisos' => ['Falha ao ajustar a grade da turma ' . $turma['NOME'] . '.']];
            }
            $removidas = (int)sqlsrv_rows_affected($stmt);
            sqlsrv_free_stmt($stmt);
        }
        if ($removidas > 0) {
            $avisos[] = $turma['NOME'] . ': ' . $removidas
                . ' posição(ões) acima de ' . $modelo['AULAS_DIA'] . ' aula(s)/dia removida(s) da grade.';
        }

        ch_auditar('MODELO_TURMA', 'APLICAR', 'TURMA', $idTurma, [
            'modelo' => $modelo['NOME'], 'aulas_dia' => (int)$modelo['AULAS_DIA'],
        ]);
        $aplicadas++;
    }
    return ['aplicadas' => $aplicadas, 'avisos' => $avisos];
}
