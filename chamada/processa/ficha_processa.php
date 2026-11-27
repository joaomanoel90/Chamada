<?php
/* ch-somente-leitura
   Ficha de chamada da turma: alunos × datas letivas do mês, com as duas aulas.
   Nenhuma gravação; a edição continua em chamada/index.php e salvar.php. */
require_once(dirname(__DIR__, 2) . '/config.php');
require_once(DBAPI);
require_once(ABSPATH . 'inc/chamada.php');
ch_db();

/* Turmas ao alcance do usuário (rede, unidades vinculadas ou equipe da turma). */
$filtro = ch_filtro_turma('t');
$turmas = ch_rows(
    'SELECT t.ID, t.NOME, t.SERIE, t.ANO_LETIVO, u.unidade AS UNIDADE, p.NOME AS PERIODO
       FROM TB_TURMAS t
      INNER JOIN TB_UNIDADES u ON u.id = t.ID_UNIDADE
      INNER JOIN TB_PERIODOS p ON p.ID = t.ID_PERIODO
      WHERE t.ATIVO = 1' . $filtro['sql'] . ' ORDER BY u.unidade, t.SERIE, t.NOME, p.NOME',
    $filtro['params']
);

$turmaId = (int)($_GET['turma_id'] ?? 0);
$turma = null;
if ($turmaId > 0) {
    if (!ch_pode_turma($turmaId)) {
        ch_flash('Você não tem acesso a essa turma.', 'danger');
        header('Location: ficha.php');
        exit;
    }
    $turma = ch_turma($turmaId) ?: null;
}

$ano = (int)($_GET['ano'] ?? ($turma ? (int)$turma['ANO_LETIVO'] : (int)date('Y')));
$mes = (int)($_GET['mes'] ?? (int)date('n'));
if ($ano < 2000 || $ano > 2100) $ano = (int)date('Y');
if ($mes < 1 || $mes > 12) $mes = (int)date('n');

$fichaDatas = [];
$fichaAlunos = [];
$fichaMarcas = [];
$fichaTotais = [];
$fichaTotaisPorData = [];
$fichaInicio = sprintf('%04d-%02d-01', $ano, $mes);
$fichaFim = date('Y-m-t', strtotime($fichaInicio));

if ($turma) {
    $diasSemana = ['Dom', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb'];

    /* A ficha é leitura histórica: turma inativa e equipe atual não bloqueiam
       a consulta, e datas futuras aparecem como colunas ainda sem lançamento. */
    $turmaRegras = $turma;
    $turmaRegras['ATIVO'] = 1;
    for ($dia = 1; $dia <= (int)date('t', strtotime($fichaInicio)); $dia++) {
        $iso = sprintf('%04d-%02d-%02d', $ano, $mes, $dia);
        if (ch_bloqueio_chamada($iso, $turmaRegras, true, true) !== null) continue;
        $w = (int)date('w', strtotime($iso));
        $fichaDatas[$iso] = [
            'DIA' => $dia,
            'ROTULO' => $diasSemana[$w] . ' ' . $dia,
        ];
    }

    /* TB_CHAMADA não guarda ID_TURMA; usá-la como fallback traria alunos de
       outras turmas com lançamento no mesmo mês. O vínculo na data vem só da
       VW_ALUNO_VINCULO. */
    $condAlunos = " AND EXISTS (SELECT 1 FROM VW_ALUNO_VINCULO v
                                  WHERE v.ID_ALUNO = a.ID AND v.ID_TURMA = ?
                                    AND (v.DATA_INICIO IS NULL OR v.DATA_INICIO <= ?)
                                    AND (v.DATA_FIM IS NULL OR v.DATA_FIM >= ?))";
    $paramsAlunos = [$turmaId, $fichaFim, $fichaInicio];

    $fichaAlunos = ch_rows(
        'SELECT a.ID, a.MATRICULA, a.NOME
           FROM TB_ALUNOS a
          WHERE a.ATIVO = 1' . $condAlunos . ' ORDER BY a.NOME',
        $paramsAlunos
    );

    foreach ($fichaAlunos as $aluno) {
        $fichaTotais[(int)$aluno['ID']] = ['P' => 0, 'F' => 0, 'J' => 0, 'A' => 0, 'LANCADOS' => 0];
    }

    $linhas = ch_rows(
        'SELECT c.ID_ALUNO, CONVERT(varchar(10), c.DATA, 23) AS DATA,
                ' . ch_aula_col('c') . ' AS AULA_SEQ, c.STATUS
           FROM TB_CHAMADA c
          INNER JOIN TB_ALUNOS a ON a.ID = c.ID_ALUNO
          WHERE c.DATA >= ? AND c.DATA <= ?' . $condAlunos,
        array_merge([$fichaInicio, $fichaFim], $paramsAlunos)
    );

    foreach ($linhas as $linha) {
        $idAluno = (int)$linha['ID_ALUNO'];
        $iso = (string)$linha['DATA'];
        $aula = ch_aula_normalizar($linha['AULA_SEQ'], ch_turma_aulas_dia($turmaId));
        $status = (string)$linha['STATUS'];
        if (!isset($fichaTotais[$idAluno])) continue;

        /* Data com lançamento entra na grade mesmo que hoje esteja bloqueada. */
        if (!isset($fichaDatas[$iso])) {
            $w = (int)date('w', strtotime($iso));
            $fichaDatas[$iso] = [
                'DIA' => (int)date('d', strtotime($iso)),
                'ROTULO' => $diasSemana[$w] . ' ' . date('d', strtotime($iso)),
            ];
        }
        $fichaMarcas[$idAluno][$iso][$aula] = $status;
        if (!isset($fichaTotaisPorData[$iso][$status])) $fichaTotaisPorData[$iso][$status] = 0;
        $fichaTotaisPorData[$iso][$status]++;
    }
    ksort($fichaDatas);

    /* Grade diária: disciplina e professor de cada aula. Substitutos valendo
       na data aparecem no lugar do professor titular da grade. */
    $grade = ch_grade_da_turma($turmaId);
    foreach ($fichaDatas as $iso => &$info) {
        $diaSemana = (int)date('N', strtotime($iso));
        $substitutos = [];
        foreach (eq_substitutos_em($turmaId, $iso) as $substituto) {
            if (!empty($substituto['ID_SUBSTITUIDO'])) {
                $substitutos[(int)$substituto['ID_SUBSTITUIDO']] = (string)$substituto['NOME'];
            }
        }
        $info['GRADE'] = [];
        foreach (array_keys(ch_aula_lista_turma($turmaId)) as $aula) {
            $slot = $grade[$diaSemana][$aula] ?? null;
            if ($slot && isset($substitutos[$slot['ID_PROFESSOR']])) {
                $slot['PROFESSOR_SUBSTITUTO'] = $slot['PROFESSOR'];
                $slot['PROFESSOR'] = $substitutos[$slot['ID_PROFESSOR']];
            }
            $info['GRADE'][$aula] = $slot;
            $info['GRADE_LINHAS'][$aula] = [
                'DISCIPLINA' => ch_grade_curto($slot['DISCIPLINA'] ?? ''),
                'PROFESSOR'  => ch_grade_curto($slot['PROFESSOR'] ?? '')
                                . (!empty($slot['PROFESSOR_SUBSTITUTO']) ? '*' : ''),
                'TITULO'     => ch_grade_titulo($slot, $aula),
            ];
        }
    }
    unset($info);

    foreach ($fichaMarcas as $idAluno => $porData) {
        if (!isset($fichaTotais[$idAluno])) continue;
        foreach ($porData as $porAula) {
            foreach ($porAula as $status) {
                if (!isset($fichaTotais[$idAluno][$status])) continue;
                $fichaTotais[$idAluno][$status]++;
                $fichaTotais[$idAluno]['LANCADOS']++;
            }
        }
    }
}
