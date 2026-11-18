<?php
/* ============================================================================
   APOIO DO MÓDULO DE SUBSTITUIÇÕES
   ----------------------------------------------------------------------------
   Gerencia os registros PAPEL = 'SUBSTITUTO' de TB_TURMA_PROFESSORES em tela
   própria, respeitando todas as constraints do banco:
     - UQ_TURMAPROF_TURMA_USUARIO (ID_TURMA, ID_USUARIO)
     - CK_TURMAPROF_SUBSTITUTO (ID_SUBSTITUIDO, DATA_INICIO, DATA_FIM >= DATA_INICIO)
     - CK_TURMAPROF_NAO_SI_MESMO (ID_SUBSTITUIDO <> ID_USUARIO)
     - CK_TURMAPROF_OBSLIB_JUNTAS (OBS_LIBERADA_POR e OBS_LIBERADA_EM juntas)
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

    /* Professores titulares/atribuídos de cada turma que podem ser cobertos:
       quem está ativo em TB_TURMA_PROFESSORES com PAPEL <> 'SUBSTITUTO' OU tem
       aulas atribuídas em TB_ATRIBUICOES na turma. */
    $titularesPorTurma = [];
    if ($turmas) {
        $ids = array_map(fn($t) => (int)$t['ID'], $turmas);
        $ph = implode(',', array_fill(0, count($ids), '?'));
        $rows = ch_rows(
            "SELECT tp.ID_TURMA, u.ID AS ID_USUARIO, u.NOME, tp.TURNO
               FROM TB_TURMA_PROFESSORES tp
               INNER JOIN TB_USUARIOS u ON u.ID = tp.ID_USUARIO
              WHERE tp.ID_TURMA IN ({$ph}) AND tp.ATIVO = 1 AND tp.PAPEL <> 'SUBSTITUTO'
                AND u.NIVEL = 3 AND ISNULL(u.ATIVO, 1) = 1
             UNION
             SELECT d.ID_TURMA, u.ID AS ID_USUARIO, u.NOME,
                    CASE WHEN u.TURNO IN ('MANHA','TARDE','INTEGRAL') THEN u.TURNO ELSE 'INTEGRAL' END AS TURNO
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
            if (!isset($titularesPorTurma[$tid][$uid])) {
                $titularesPorTurma[$tid][$uid] = [
                    'ID'    => $uid,
                    'NOME'  => $r['NOME'],
                    'TURNO' => $r['TURNO'],
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
    $d = [
        'ID_TURMA'        => (int)($post['id_turma'] ?? 0),
        'ID_SUBSTITUIDO'  => (int)($post['id_substituido'] ?? 0),
        'ID_USUARIO'      => (int)($post['id_usuario'] ?? 0),
        'DATA_INICIO'     => trim((string)($post['data_inicio'] ?? '')),
        'DATA_FIM'        => trim((string)($post['data_fim'] ?? '')),
        'MOTIVO'          => mb_substr(trim(strip_tags((string)($post['motivo'] ?? ''))), 0, 200, 'UTF-8'),
        'VER_OBSERVACOES' => !empty($post['ver_observacoes']) ? 1 : 0,
        'TURNO'           => 'INTEGRAL',
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
        $d['TURNO'] = isset(eq_turnos()[$cobertoInfo['TURNO']]) ? $cobertoInfo['TURNO'] : 'INTEGRAL';
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
        /* Verifica se o professor escolhido já está ativo na turma (como titular
           ou em outra substituição ativa diferente desta). */
        $conflito = ch_row(
            'SELECT ID, PAPEL, ATIVO FROM TB_TURMA_PROFESSORES
              WHERE ID_TURMA = ? AND ID_USUARIO = ? AND ID <> ?',
            [$d['ID_TURMA'], $d['ID_USUARIO'], (int)$idAtual]
        );
        if ($conflito && (int)$conflito['ATIVO'] === 1) {
            if ($conflito['PAPEL'] === 'SUBSTITUTO') {
                $erros[] = 'Este professor já possui uma substituição ativa nesta turma. Edite ou encerre a substituição existente.';
            } else {
                $erros[] = 'Este professor já faz parte da equipe titular desta turma.';
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

        /* Se trocou turma ou professor e já existia linha INATIVA no par novo,
           remove a linha inativa antiga para não esbarrar em UQ_TURMAPROF_TURMA_USUARIO. */
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
                            VER_OBSERVACOES = 1, ATIVO = 1
                      WHERE ID = ?",
                    [$d['ID_TURMA'], $d['ID_USUARIO'], $d['TURNO'],
                     $d['ID_SUBSTITUIDO'], $d['DATA_INICIO'], $d['DATA_FIM'], $motivo, $idAtual]
                );
            }
            return ch_exec(
                "UPDATE TB_TURMA_PROFESSORES
                    SET ID_TURMA = ?, ID_USUARIO = ?, PAPEL = 'SUBSTITUTO', TURNO = ?,
                        ID_SUBSTITUIDO = ?, DATA_INICIO = ?, DATA_FIM = ?, MOTIVO = ?,
                        VER_OBSERVACOES = 1, OBS_LIBERADA_POR = ?, OBS_LIBERADA_EM = GETDATE(), ATIVO = 1
                  WHERE ID = ?",
                [$d['ID_TURMA'], $d['ID_USUARIO'], $d['TURNO'],
                 $d['ID_SUBSTITUIDO'], $d['DATA_INICIO'], $d['DATA_FIM'], $motivo, ch_uid(), $idAtual]
            );
        }

        return ch_exec(
            "UPDATE TB_TURMA_PROFESSORES
                SET ID_TURMA = ?, ID_USUARIO = ?, PAPEL = 'SUBSTITUTO', TURNO = ?,
                    ID_SUBSTITUIDO = ?, DATA_INICIO = ?, DATA_FIM = ?, MOTIVO = ?,
                    VER_OBSERVACOES = 0, OBS_LIBERADA_POR = NULL, OBS_LIBERADA_EM = NULL, ATIVO = 1
              WHERE ID = ?",
            [$d['ID_TURMA'], $d['ID_USUARIO'], $d['TURNO'],
             $d['ID_SUBSTITUIDO'], $d['DATA_INICIO'], $d['DATA_FIM'], $motivo, $idAtual]
        );
    }

    /* Criação: se já houver registro INATIVO para (ID_TURMA, ID_USUARIO), reaproveita a linha. */
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
                        VER_OBSERVACOES = 1, OBS_LIBERADA_POR = ?, OBS_LIBERADA_EM = GETDATE(), ATIVO = 1
                  WHERE ID = ?",
                [$d['TURNO'], $d['ID_SUBSTITUIDO'], $d['DATA_INICIO'], $d['DATA_FIM'],
                 $motivo, ch_uid(), (int)$inativa['ID']]
            );
        }
        return ch_exec(
            "UPDATE TB_TURMA_PROFESSORES
                SET PAPEL = 'SUBSTITUTO', TURNO = ?, ID_SUBSTITUIDO = ?,
                    DATA_INICIO = ?, DATA_FIM = ?, MOTIVO = ?,
                    VER_OBSERVACOES = 0, OBS_LIBERADA_POR = NULL, OBS_LIBERADA_EM = NULL, ATIVO = 1
              WHERE ID = ?",
            [$d['TURNO'], $d['ID_SUBSTITUIDO'], $d['DATA_INICIO'], $d['DATA_FIM'],
             $motivo, (int)$inativa['ID']]
        );
    }

    if ($verObs === 1) {
        return ch_exec(
            "INSERT INTO TB_TURMA_PROFESSORES
                (ID_TURMA, ID_USUARIO, PAPEL, TURNO, ID_SUBSTITUIDO, DATA_INICIO, DATA_FIM, MOTIVO,
                 VER_OBSERVACOES, OBS_LIBERADA_POR, OBS_LIBERADA_EM, ATIVO)
             VALUES (?, ?, 'SUBSTITUTO', ?, ?, ?, ?, ?, 1, ?, GETDATE(), 1)",
            [$d['ID_TURMA'], $d['ID_USUARIO'], $d['TURNO'], $d['ID_SUBSTITUIDO'],
             $d['DATA_INICIO'], $d['DATA_FIM'], $motivo, ch_uid()]
        );
    }
    return ch_exec(
        "INSERT INTO TB_TURMA_PROFESSORES
            (ID_TURMA, ID_USUARIO, PAPEL, TURNO, ID_SUBSTITUIDO, DATA_INICIO, DATA_FIM, MOTIVO,
             VER_OBSERVACOES, OBS_LIBERADA_POR, OBS_LIBERADA_EM, ATIVO)
         VALUES (?, ?, 'SUBSTITUTO', ?, ?, ?, ?, ?, 0, NULL, NULL, 1)",
        [$d['ID_TURMA'], $d['ID_USUARIO'], $d['TURNO'], $d['ID_SUBSTITUIDO'],
         $d['DATA_INICIO'], $d['DATA_FIM'], $motivo]
    );
}
