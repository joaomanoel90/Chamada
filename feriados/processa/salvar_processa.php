<?php
require_once(dirname(__DIR__, 2) . '/config.php');
require_once(DBAPI);
require_once(ABSPATH . 'inc/chamada.php');
ch_db();
ch_require_gestor();

/** Feriado global (ID_UNIDADE nulo) só pode ser gerido pela rede. */
function feriado_pode_unidade($idUnidade)
{
    $idUnidade = (int)$idUnidade;
    return $idUnidade > 0 ? ch_pode_unidade($idUnidade) : ch_rede();
}

function feriado_validar($post, $idAtual = 0)
{
    $d = [
        'DATA'        => ch_data_iso((string)($post['data'] ?? '')),
        'DESCRICAO'   => strip_tags(trim((string)($post['descricao'] ?? ''))),
        'TIPO'        => strtoupper(trim((string)($post['tipo'] ?? 'INTEGRAL'))),
        'HORA_INICIO' => trim((string)($post['hora_inicio'] ?? '')),
        'HORA_FIM'    => trim((string)($post['hora_fim'] ?? '')),
        'ID_UNIDADE'  => (int)($post['id_unidade'] ?? 0),
        'CALENDARIO'  => strtoupper(trim((string)($post['calendario'] ?? 'AMBOS'))),
    ];
    $e = [];
    $horaOk = function ($h) { return preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $h); };

    if (!$d['DATA']) { $e[] = 'Informe uma data válida.'; }
    if ($d['DESCRICAO'] === '' || mb_strlen($d['DESCRICAO']) > 150) { $e[] = 'Informe a descrição (até 150 caracteres).'; }
    if (!in_array($d['TIPO'], ['INTEGRAL', 'PARCIAL'], true)) { $e[] = 'Tipo de feriado inválido.'; }
    if (!in_array($d['CALENDARIO'], ['AMBOS', 'CRECHE', 'FUNDAMENTAL'], true)) {
        $e[] = 'Calendário inválido.';
    }

    if ($d['TIPO'] === 'PARCIAL') {
        if (!$horaOk($d['HORA_INICIO']) || !$horaOk($d['HORA_FIM'])) {
            $e[] = 'No feriado parcial, informe a faixa sem aula (HH:MM).';
        } elseif ($d['HORA_FIM'] <= $d['HORA_INICIO']) {
            $e[] = 'O fim da faixa sem aula precisa ser maior que o início.';
        }
    } else {
        $d['HORA_INICIO'] = null;
        $d['HORA_FIM'] = null;
    }

    if ($d['ID_UNIDADE'] > 0) {
        if (!ch_val('SELECT id FROM TB_UNIDADES WHERE id = ?', [$d['ID_UNIDADE']])) {
            $e[] = 'Unidade inválida.';
        } elseif (!feriado_pode_unidade($d['ID_UNIDADE'])) {
            $e[] = 'Você não tem acesso a esta unidade.';
        }
    } elseif (!feriado_pode_unidade(0)) {
        $e[] = 'Somente a gestão da rede pode criar feriado para todas as unidades.';
    }

    /* AMBOS conflita com qualquer calendário; um específico só conflita com
       ele mesmo ou com AMBOS. A unidade continua fazendo parte da chave: uma
       regra da rede e outra exclusiva de uma unidade podem coexistir. */
    if (!$e) {
        if (ch_feriados_tem_calendario()) {
            $conflito = ch_val(
                "SELECT ID FROM TB_FERIADOS
                  WHERE DATA = ? AND ISNULL(ID_UNIDADE, 0) = ? AND ID <> ?
                    AND (CALENDARIO = ? OR CALENDARIO = 'AMBOS' OR ? = 'AMBOS')",
                [$d['DATA'], $d['ID_UNIDADE'], $idAtual, $d['CALENDARIO'], $d['CALENDARIO']]
            );
        } else {
            $conflito = ch_val(
                'SELECT ID FROM TB_FERIADOS WHERE DATA = ? AND ISNULL(ID_UNIDADE, 0) = ? AND ID <> ?',
                [$d['DATA'], $d['ID_UNIDADE'], $idAtual]
            );
        }
        if ($conflito) {
            $e[] = 'Já existe um feriado nessa data e abrangência para este calendário (ou para ambos).';
        }
    }

    // Não deixa criar feriado por cima de chamada já lançada
    if (!$e) {
        $sql = 'SELECT COUNT(*) FROM TB_CHAMADA c
                INNER JOIN TB_ALUNOS a ON a.ID = c.ID_ALUNO
                INNER JOIN TB_TURMAS t ON t.ID = a.ID_TURMA
                INNER JOIN TB_PERIODOS p ON p.ID = t.ID_PERIODO
                WHERE c.DATA = ?';
        $params = [$d['DATA']];
        if ($d['ID_UNIDADE'] > 0) { $sql .= ' AND t.ID_UNIDADE = ?'; $params[] = $d['ID_UNIDADE']; }

        /* Só conta a chamada das turmas atingidas pelo calendário escolhido. */
        if (ch_feriados_tem_calendario() && $d['CALENDARIO'] !== 'AMBOS') {
            $serieCreche = "('BERCARIO_I','BERCARIO_II','MATERNAL_I','MATERNAL_II')";
            if ($d['CALENDARIO'] === 'CRECHE') {
                $sql .= " AND (t.ETAPA_ENSINO = 'INFANTIL_CRECHE' OR t.SERIE IN {$serieCreche})";
            } else {
                $sql .= " AND (t.ETAPA_ENSINO IS NULL OR t.ETAPA_ENSINO <> 'INFANTIL_CRECHE')"
                      . " AND (t.SERIE IS NULL OR t.SERIE NOT IN {$serieCreche})";
            }
        }
        if ($d['TIPO'] === 'PARCIAL') {
            $sql .= ' AND p.HORA_INICIO < ? AND p.HORA_FIM > ?';
            array_push($params, $d['HORA_FIM'], $d['HORA_INICIO']);
        }
        $existentes = (int)ch_val($sql, $params);
        if ($existentes > 0) {
            $e[] = 'Já existem ' . $existentes . ' registros de chamada nessa data'
                 . ($d['TIPO'] === 'PARCIAL' ? ' e faixa de horário' : '')
                 . '. Apague a chamada antes de cadastrar o feriado.';
        }
    }
    return [$d, $e];
}
