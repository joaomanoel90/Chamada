<?php
require_once(dirname(__DIR__, 2) . '/config.php');
require_once(DBAPI);
require_once(ABSPATH . 'inc/chamada.php');
ch_db();
$filtro = ch_filtro_turma('t');
$turmas = ch_rows(
    'SELECT t.ID, t.NOME, t.SERIE, t.ANO_LETIVO, u.unidade AS UNIDADE, p.NOME AS PERIODO
     FROM TB_TURMAS t
     INNER JOIN TB_UNIDADES u ON u.id = t.ID_UNIDADE
     INNER JOIN TB_PERIODOS p ON p.ID = t.ID_PERIODO
     WHERE t.ATIVO = 1' . $filtro['sql'] . ' ORDER BY u.unidade, t.SERIE, t.NOME, p.NOME',
    $filtro['params']
);

$turmaId = (int)($_GET['turma_id'] ?? ($turmas ? (int)$turmas[0]['ID'] : 0));
$data    = ch_data_iso($_GET['data'] ?? '', ch_hoje());
$aula    = ch_aula_normalizar($_GET['aula'] ?? 1);

$turma = null;
$bloqueio = null;
$alunos = [];
$jaLancada = false;
$resumo = ['P' => 0, 'F' => 0, 'J' => 0, 'A' => 0];
$pendentes = [];
$confirmados = [];
$porAula = [1 => 0, 2 => 0];
$equipe = [];
$podeRetificar = false;
$podeConfirmar = false;

if ($turmaId > 0) {
    if (!ch_pode_turma($turmaId)) {
        ch_flash('Você não tem acesso a essa turma.', 'danger');
        header('Location: index.php?aula=' . $aula);
        exit;
    }
    $turma = ch_turma($turmaId);
    $equipe = ch_turma_professores($turmaId);
    $bloqueio = ch_bloqueio_chamada($data, $turma);
    $podeRetificar = ch_pode_retificar($turmaId);
    $podeConfirmar = ch_pode_lancar_chamada($turmaId);

    /* A lista é de quem pertencia à turma NAQUELA data, não de quem pertence
       hoje. Sem isto, um aluno transferido apareceria na turma nova em datas
       anteriores à transferência e sumiria do histórico da turma antiga. */
    $naData = tr_filtro_na_data($turmaId, $data, 'a');

    /* CONFIRMADA_EM vira texto no banco: o driver sqlsrv devolveria DateTime.
       A aula escolhida filtra o LEFT JOIN, então a 1ª e a 2ª aula do mesmo dia
       aparecem como registros independentes. */
    $colAula = ch_aula_col('c');
    $joinAula = ch_chamada_tem_aula() ? ' AND c.AULA_SEQ = ?' : '';
    $paramsAlunos = [$data];
    if (ch_chamada_tem_aula()) $paramsAlunos[] = $aula;

    $alunos = ch_rows(
        'SELECT a.ID, a.MATRICULA, a.NOME,
                a.ALERGIAS, a.RESTRICOES_ALIMENTARES, a.MEDICACAO,
                ' . $colAula . ' AS AULA_SEQ,
                c.ID AS ID_CHAMADA, c.STATUS, c.JUSTIFICATIVA,
                CONVERT(varchar(16), c.CONFIRMADA_EM, 120) AS CONFIRMADA_EM,
                uc.NOME AS CONFIRMADA_POR_NOME,
                CONVERT(varchar(10), a.NASCIMENTO, 23) AS NASCIMENTO,
                (SELECT COUNT(*) FROM TB_CHAMADA_RETIFICACOES r WHERE r.ID_CHAMADA = c.ID) AS RETIFICACOES
         FROM TB_ALUNOS a
         LEFT JOIN TB_CHAMADA c ON c.ID_ALUNO = a.ID AND c.DATA = ?' . $joinAula . '
         LEFT JOIN TB_USUARIOS uc ON uc.ID = c.CONFIRMADA_POR
         WHERE a.ATIVO = 1' . $naData['sql'] . '
         ORDER BY a.NOME',
        array_merge($paramsAlunos, $naData['params'])
    );

    /* Placar das duas aulas no mesmo dia, para o professor ver o que já fez. */
    if (ch_chamada_tem_aula()) {
        foreach (ch_rows(
            'SELECT c.AULA_SEQ AS AULA_SEQ, COUNT(*) AS QTDE
               FROM TB_CHAMADA c
              INNER JOIN TB_ALUNOS a ON a.ID = c.ID_ALUNO
              WHERE c.DATA = ?' . $naData['sql'] . '
              GROUP BY c.AULA_SEQ',
            array_merge([$data], $naData['params'])
        ) as $linha) {
            $porAula[ch_aula_normalizar($linha['AULA_SEQ'])] = (int)$linha['QTDE'];
        }
    } else {
        $porAula[1] = (int)ch_val(
            'SELECT COUNT(*) FROM TB_CHAMADA c
              INNER JOIN TB_ALUNOS a ON a.ID = c.ID_ALUNO
              WHERE c.DATA = ?' . $naData['sql'],
            array_merge([$data], $naData['params'])
        );
    }

    foreach ($alunos as $a) {
        if (!empty($a['STATUS'])) {
            $jaLancada = true;
            $resumo[$a['STATUS']] = ($resumo[$a['STATUS']] ?? 0) + 1;
            $confirmados[] = $a;
        } else {
            $pendentes[] = $a;
        }
    }
}

$observacoesDoDia = [];
if ($turmaId > 0) {
    /* O contador também respeita o bloqueio: mostrar "3 observações" já
       revelaria que existe registro que ele não pode ler. */
    $fObsDia = eq_obs_filtro('o', 'a');
    $fObsPermissaoDia = ch_filtro_observacao_aluno('a');
    $fObsAeeDia = ch_obs_restrita_aee_filtro('o', 'a');
    $observacoesDoDia = ch_rows(
        'SELECT o.ID_ALUNO, COUNT(*) AS QTDE
         FROM TB_OBSERVACOES o
         INNER JOIN TB_ALUNOS a ON a.ID = o.ID_ALUNO
         WHERE o.DATA = ?' . $naData['sql'] . $fObsDia['sql'] . $fObsPermissaoDia['sql'] . $fObsAeeDia['sql'] . '
         GROUP BY o.ID_ALUNO',
        array_merge([$data], $naData['params'], $fObsDia['params'], $fObsPermissaoDia['params'], $fObsAeeDia['params'])
    );
}
$obsPorAluno = [];
foreach ($observacoesDoDia as $o) { $obsPorAluno[(int)$o['ID_ALUNO']] = (int)$o['QTDE']; }

/* Quem tem alerta de saúde nesta turma. Vai num aviso no alto da tela, além
   do aviso no cartão de cada aluno: a professora vê a lista antes de começar. */
$comSaude = [];
foreach ($alunos as $a) {
    if (eq_tem_saude($a)) $comSaude[] = $a;
}

/* Substituição valendo na data da chamada, para a tela dizer quem está cobrindo
   quem — e não parecer que a equipe mudou sozinha. */
$substitutos = $turmaId > 0 ? eq_substitutos_em($turmaId, $data) : [];
