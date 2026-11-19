<?php
/* ============================================================================
   APOIO DO MÓDULO DE SUBSTITUIÇÕES
   ----------------------------------------------------------------------------
   Gerencia os registros PAPEL = 'SUBSTITUTO' de TB_TURMA_PROFESSORES em tela
   própria, incluindo substituição por período (Manhã + Tarde = 2 substitutos)
   quando um professor que atua o dia todo (Integral, ex.: Apoio de Creche)
   falta em turma integral.
   ============================================================================ */
if (!defined('ABSPATH')) {
    http_response_code(404);
    exit;
}

/** Turmas ativas que o gestor pode administrar, com seus professores titulares. */
function subst_opcoes_form($idTurmaForcada = 0)
{
    $filtro = ch_filtro_turma('t');
    $sql = 'SELECT t.ID, t.NOME, t.SERIE, t.ANO_LETIVO, t.ID_UNIDADE,
                   u.unidade AS UNIDADE, p.NOME AS PERIODO, p.TURNO AS TURNO_TURMA
              FROM TB_TURMAS t
              INNER JOIN TB_UNIDADES u ON u.id = t.ID_UNIDADE
              INNER JOIN TB_PERIODOS p ON p.ID = t.ID_PERIODO
             WHERE (t.ATIVO = 1' . ($idTurmaForcada > 0 ? ' OR t.ID = ' . (int)$idTurmaForcada : '') . ')'
           . $filtro['sql']
           . ' ORDER BY t.ANO_LETIVO DESC, u.unidade, t.SERIE, t.NOME, p.NOME';
    $turmas = ch_rows($sql, $filtro['params']) ?: [];

    $titularesPorTurma = [];
    if ($turmas) {
        $ids = array_map(fn($t) => (int)$t['ID'], $turmas);
        $ph = implode(',', array_fill(0, count($ids), '?'));
        $rows = ch_rows(
            "SELECT tp.ID_TURMA, u.ID AS ID_USUARIO, u.NOME, tp.TURNO, tp.PAPEL
               FROM TB_TURMA_PROFESSORES tp
               INNER JOIN TB_USUARIOS u ON u.ID = tp.ID_USUARIO
              WHERE tp.ID_TURMA IN ({$ph}) AND tp.ATIVO = 1 AND tp.PAPEL <> 'SUBSTITUTO'
                AND u.NIVEL = 3 AND ISNULL(u.ATIVO, 1) = 1
             UNION
             SELECT d.ID_TURMA, u.ID AS ID_USUARIO, u.NOME,
                    CASE WHEN u.TURNO IN ('MANHA','TARDE','INTEGRAL') THEN u.TURNO ELSE 'INTEGRAL' END AS TURNO,
                    'AUXILIAR' AS PAPEL
               FROM TB_ATRIBUICOES a
               INNER JOIN TB_TURMA_DISCIPLINAS d ON d.ID = a.ID_DISCIPLINA
               INNER JOIN TB_USUARIOS u ON u.ID = a.ID_PROFESSOR
              WHERE d.ID_TURMA IN ({$ph}) AND u.NIVEL = 3 AND ISNULL(u.ATIVO, 1) = 1
              ORDER BY NOME",
            array_merge($ids, $ids)
        ) ?: [];
        foreach ($rows as $r) {
            $tid = (int)$r['ID_TURMA'];
            $uid = (int)$r['ID_USUARIO'];
            if (!isset($titularesPorTurma[$tid][$uid]) || $r['PAPEL'] === 'REGENTE') {
                $titularesPorTurma[$tid][$uid] = [
                    'ID'          => $uid,
                    'NOME'        => $r['NOME'],
                    'TURNO'       => $r['TURNO'],
                    'TURNO_ROT'   => eq_turno_rotulo($r['TURNO']),
                    'PAPEL_ROT'   => $r['PAPEL'] === 'REGENTE' ? 'Regente' : 'Apoio / Matéria',
                ];
            }
        }
        foreach ($titularesPorTurma as $tid => $mapa) {
            $titularesPorTurma[$tid] = array_values($mapa);
        }
    }

    $professores = ch_rows(
        "SELECT ID, NOME, TURNO, CATEGORIA FROM TB_USUARIOS
          WHERE NIVEL = 3 AND ISNULL(ATIVO, 1) = 1 AND ISNULL(CATEGORIA, '') <> 'AEE'
          ORDER BY NOME"
    ) ?: [];

    return [
        'turmas'            => $turmas,
        'titularesPorTurma' => $titularesPorTurma,
        'professores'       => $professores,
    ];
}

/** Valida os dados vindos do POST de cadastro/edição de substituição. */
function subst_validar($post, $op, $idAtual = 0)
{
    $turnoPost = strtoupper(trim((string)($post['turno'] ?? '')));
    $d = [
        'ID_TURMA'        => (int)($post['id_turma'] ?? 0),
        'ID_SUBSTITUIDO'  => (int)($post['id_substituido'] ?? 0),
        'ID_USUARIO'      => (int)($post['id_usuario'] ?? 0),
        'TURNO'           => isset(eq_turnos()[$turnoPost]) ? $turnoPost : 'INTEGRAL',
        'DATA_INICIO'     => trim((string)($post['data_inicio'] ?? '')),
        'DATA_FIM'        => trim((string)($post['data_fim'] ?? '')),
        'MOTIVO'          => mb_substr(trim(strip_tags((string)($post['motivo'] ?? ''))), 0, 200, 'UTF-8'),
        'VER_OBSERVACOES' => !empty($post['ver_observacoes']) ? 1 : 0,
    ];
    $erros = [];

    $turmaInfo = null;
    foreach ($op['turmas'] as $t) {
        if ((int)$t['ID'] === $d['ID_TURMA']) {
            $turmaInfo = $t;
            break;
        }
    }
    if (!$turmaInfo || !ch_pode_unidade((int)$turmaInfo['ID_UNIDADE'])) {
        $erros[] = 'Selecione uma turma válida.';
    }

    $titulares = $op['titularesPorTurma'][$d['ID_TURMA']] ?? [];
    $cobertoInfo = null;
    foreach ($titulares as $tit) {
        if ((int)$tit['ID'] === $d['ID_SUBSTITUIDO']) {
            $cobertoInfo = $tit;
            break;
        }
    }
    if ($turmaInfo && !$cobertoInfo) {
        $erros[] = 'Selecione um professor titular atribuído a esta turma para ser substituído.';
    } elseif ($cobertoInfo) {
        $turnoCoberto = isset(eq_turnos()[$cobertoInfo['TURNO']]) ? $cobertoInfo['TURNO'] : 'INTEGRAL';
        /* Se o titular atua só Manhã ou só Tarde, a substituição cobre exatamente aquele turno.
           Se atua o dia todo (Integral, ex.: Apoio de Creche), a substituição pode cobrir
           Manhã, Tarde (2 substitutos) ou Integral (1 substituto). */
        if ($turnoCoberto !== 'INTEGRAL') {
            $d['TURNO'] = $turnoCoberto;
        } elseif (!isset(eq_turnos()[$turnoPost])) {
            $d['TURNO'] = 'INTEGRAL';
        }
    }

    $substOk = false;
    foreach ($op['professores'] as $pr) {
        if ((int)$pr['ID'] === $d['ID_USUARIO']) {
            $substOk = true;
            break;
        }
    }
    if (!$substOk) {
        $erros[] = 'Selecione um professor substituto ativo.';
    }

    if ($d['ID_USUARIO'] > 0 && $d['ID_USUARIO'] === $d['ID_SUBSTITUIDO']) {
        $erros[] = 'Um professor não pode substituir a si mesmo.';
    }

    $iniIso = ch_data_iso($d['DATA_INICIO'], '');
    $fimIso = ch_data_iso($d['DATA_FIM'], '');
    if ($iniIso === '' || $fimIso === '') {
        $erros[] = 'Informe as datas inicial (De) e final (Até) da substituição.';
    } elseif ($fimIso < $iniIso) {
        $erros[] = 'A data final (Até) não pode ser anterior à data inicial (De).';
    } else {
        $d['DATA_INICIO'] = $iniIso;
        $d['DATA_FIM']    = $fimIso;
    }

    if (!$erros) {
        /* 1) Verifica se o substituto escolhido já está ativo nesta turma. */
        $conflito = ch_row(
            'SELECT ID, PAPEL, ATIVO FROM TB_TURMA_PROFESSORES
              WHERE ID_TURMA = ? AND ID_USUARIO = ? AND ID <> ?',
            [$d['ID_TURMA'], $d['ID_USUARIO'], (int)$idAtual]
        );
        if ($conflito && (int)$conflito['ATIVO'] === 1) {
            if ($conflito['PAPEL'] === 'SUBSTITUTO') {
                $erros[] = 'Este substituto já possui uma substituição ativa nesta turma. Para cobrir o outro período nas mesmas datas, escolha um segundo professor substituto (ou mude o período da substituição existente para Integral).';
            } else {
                $erros[] = 'Este professor já faz parte da equipe titular desta turma.';
            }
        }

        /* 2) Verifica se já há outro substituto cobrindo o MESMO professor ausente
              no MESMO período/turno em datas sobrepostas (Manhã + Tarde é permitido!). */
        $outrasCob = ch_rows(
            "SELECT tp.ID, tp.TURNO, u.NOME AS SUBSTITUTO
               FROM TB_TURMA_PROFESSORES tp
               INNER JOIN TB_USUARIOS u ON u.ID = tp.ID_USUARIO
              WHERE tp.ID_TURMA = ? AND tp.PAPEL = 'SUBSTITUTO' AND tp.ATIVO = 1
                AND tp.ID_SUBSTITUIDO = ? AND tp.ID <> ?
                AND tp.DATA_INICIO <= ? AND tp.DATA_FIM >= ?",
            [$d['ID_TURMA'], $d['ID_SUBSTITUIDO'], (int)$idAtual, $d['DATA_FIM'], $d['DATA_INICIO']]
        ) ?: [];
        foreach ($outrasCob as $oc) {
            if (eq_turno_sobrepoe($d['TURNO'], $oc['TURNO'])) {
                $erros[] = 'O período ' . eq_turno_rotulo($d['TURNO']) . ' deste professor já está coberto por '
                         . $oc['SUBSTITUTO'] . ' nessas datas. Para dividir a substituição em 2 substitutos, cadastre um na Manhã e outro na Tarde.';
                break;
            }
        }
    }

    return [$d, $erros];
}

/** Grava (cria ou atualiza) uma substituição respeitando a chave única (ID_TURMA, ID_USUARIO). */
function subst_salvar($d, $idAtual = 0)
{
    $idAtual = (int)$idAtual;
    $motivo = $d['MOTIVO'] !== '' ? $d['MOTIVO'] : null;
    $verObs = (int)$d['VER_OBSERVACOES'] === 1 ? 1 : 0;

    if ($idAtual > 0) {
        $ant = ch_row(
            'SELECT ID_TURMA, ID_USUARIO, VER_OBSERVACOES, OBS_LIBERADA_POR,
                    CONVERT(varchar(19), OBS_LIBERADA_EM, 120) AS OBS_LIBERADA_EM
               FROM TB_TURMA_PROFESSORES WHERE ID = ? AND PAPEL = \'SUBSTITUTO\'',
            [$idAtual]
        );
        if (!$ant) return false;

        if ((int)$ant['ID_TURMA'] !== (int)$d['ID_TURMA'] || (int)$ant['ID_USUARIO'] !== (int)$d['ID_USUARIO']) {
            ch_exec(
                'DELETE FROM TB_TURMA_PROFESSORES WHERE ID_TURMA = ? AND ID_USUARIO = ? AND ATIVO = 0 AND ID <> ?',
                [$d['ID_TURMA'], $d['ID_USUARIO'], $idAtual]
            );
        }

        if ($verObs === 1) {
            if ((int)$ant['VER_OBSERVACOES'] === 1 && !empty($ant['OBS_LIBERADA_POR']) && !empty($ant['OBS_LIBERADA_EM'])) {
                return ch_exec(
                    "UPDATE TB_TURMA_PROFESSORES
                        SET ID_TURMA = ?, ID_USUARIO = ?, PAPEL = 'SUBSTITUTO', TURNO = ?,
                            ID_SUBSTITUIDO = ?, DATA_INICIO = ?, DATA_FIM = ?, MOTIVO = ?,
                            VER_OBSERVACOES = 1, VINCULO_DIRETO = 0, ATIVO = 1
                      WHERE ID = ?",
                    [$d['ID_TURMA'], $d['ID_USUARIO'], $d['TURNO'],
                     $d['ID_SUBSTITUIDO'], $d['DATA_INICIO'], $d['DATA_FIM'], $motivo, $idAtual]
                );
            }
            return ch_exec(
                "UPDATE TB_TURMA_PROFESSORES
                    SET ID_TURMA = ?, ID_USUARIO = ?, PAPEL = 'SUBSTITUTO', TURNO = ?,
                        ID_SUBSTITUIDO = ?, DATA_INICIO = ?, DATA_FIM = ?, MOTIVO = ?,
                        VER_OBSERVACOES = 1, OBS_LIBERADA_POR = ?, OBS_LIBERADA_EM = GETDATE(),
                        VINCULO_DIRETO = 0, ATIVO = 1
                  WHERE ID = ?",
                [$d['ID_TURMA'], $d['ID_USUARIO'], $d['TURNO'],
                 $d['ID_SUBSTITUIDO'], $d['DATA_INICIO'], $d['DATA_FIM'], $motivo, ch_uid(), $idAtual]
            );
        }

        return ch_exec(
            "UPDATE TB_TURMA_PROFESSORES
                SET ID_TURMA = ?, ID_USUARIO = ?, PAPEL = 'SUBSTITUTO', TURNO = ?,
                    ID_SUBSTITUIDO = ?, DATA_INICIO = ?, DATA_FIM = ?, MOTIVO = ?,
                    VER_OBSERVACOES = 0, OBS_LIBERADA_POR = NULL, OBS_LIBERADA_EM = NULL,
                    VINCULO_DIRETO = 0, ATIVO = 1
              WHERE ID = ?",
            [$d['ID_TURMA'], $d['ID_USUARIO'], $d['TURNO'],
             $d['ID_SUBSTITUIDO'], $d['DATA_INICIO'], $d['DATA_FIM'], $motivo, $idAtual]
        );
    }

    $inativa = ch_row(
        'SELECT ID FROM TB_TURMA_PROFESSORES WHERE ID_TURMA = ? AND ID_USUARIO = ? AND ATIVO = 0',
        [$d['ID_TURMA'], $d['ID_USUARIO']]
    );
    if ($inativa) {
        if ($verObs === 1) {
            return ch_exec(
                "UPDATE TB_TURMA_PROFESSORES
                    SET PAPEL = 'SUBSTITUTO', TURNO = ?, ID_SUBSTITUIDO = ?,
                        DATA_INICIO = ?, DATA_FIM = ?, MOTIVO = ?,
                        VER_OBSERVACOES = 1, OBS_LIBERADA_POR = ?, OBS_LIBERADA_EM = GETDATE(),
                        VINCULO_DIRETO = 0, ATIVO = 1
                  WHERE ID = ?",
                [$d['TURNO'], $d['ID_SUBSTITUIDO'], $d['DATA_INICIO'], $d['DATA_FIM'],
                 $motivo, ch_uid(), (int)$inativa['ID']]
            );
        }
        return ch_exec(
            "UPDATE TB_TURMA_PROFESSORES
                SET PAPEL = 'SUBSTITUTO', TURNO = ?, ID_SUBSTITUIDO = ?,
                    DATA_INICIO = ?, DATA_FIM = ?, MOTIVO = ?,
                    VER_OBSERVACOES = 0, OBS_LIBERADA_POR = NULL, OBS_LIBERADA_EM = NULL,
                    VINCULO_DIRETO = 0, ATIVO = 1
              WHERE ID = ?",
            [$d['TURNO'], $d['ID_SUBSTITUIDO'], $d['DATA_INICIO'], $d['DATA_FIM'],
             $motivo, (int)$inativa['ID']]
        );
    }

    if ($verObs === 1) {
        return ch_exec(
            "INSERT INTO TB_TURMA_PROFESSORES
                (ID_TURMA, ID_USUARIO, PAPEL, TURNO, ID_SUBSTITUIDO, DATA_INICIO, DATA_FIM, MOTIVO,
                 VER_OBSERVACOES, OBS_LIBERADA_POR, OBS_LIBERADA_EM, VINCULO_DIRETO, ATIVO)
             VALUES (?, ?, 'SUBSTITUTO', ?, ?, ?, ?, ?, 1, ?, GETDATE(), 0, 1)",
            [$d['ID_TURMA'], $d['ID_USUARIO'], $d['TURNO'], $d['ID_SUBSTITUIDO'],
             $d['DATA_INICIO'], $d['DATA_FIM'], $motivo, ch_uid()]
        );
    }
    return ch_exec(
        "INSERT INTO TB_TURMA_PROFESSORES
            (ID_TURMA, ID_USUARIO, PAPEL, TURNO, ID_SUBSTITUIDO, DATA_INICIO, DATA_FIM, MOTIVO,
             VER_OBSERVACOES, OBS_LIBERADA_POR, OBS_LIBERADA_EM, VINCULO_DIRETO, ATIVO)
         VALUES (?, ?, 'SUBSTITUTO', ?, ?, ?, ?, ?, 0, NULL, NULL, 0, 1)",
        [$d['ID_TURMA'], $d['ID_USUARIO'], $d['TURNO'], $d['ID_SUBSTITUIDO'],
         $d['DATA_INICIO'], $d['DATA_FIM'], $motivo]
    );
}
