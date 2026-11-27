<?php
/* Grade diária da turma: dia da semana × aula → disciplina e professor. */
require_once(dirname(__DIR__, 2) . '/config.php');
require_once(DBAPI);
require_once(ABSPATH . 'inc/chamada.php');
ch_db();
ch_require_gestor();

$filtro = ch_filtro_turma('t');
$turmas = ch_rows(
    'SELECT t.ID, t.NOME, t.SERIE, t.ANO_LETIVO, u.unidade AS UNIDADE, p.NOME AS PERIODO
       FROM TB_TURMAS t
      INNER JOIN TB_UNIDADES u ON u.id = t.ID_UNIDADE
      INNER JOIN TB_PERIODOS p ON p.ID = t.ID_PERIODO
      WHERE t.ATIVO = 1' . $filtro['sql'] . ' ORDER BY u.unidade, t.SERIE, t.NOME, p.NOME',
    $filtro['params']
) ?: [];

$turmaId = (int)($_POST['turma_id'] ?? $_GET['turma_id'] ?? 0);
if ($turmaId > 0 && !ch_pode_turma($turmaId)) {
    ch_flash('Você não tem acesso a essa turma.', 'danger');
    header('Location: index.php');
    exit;
}

$turma = $turmaId > 0 ? ch_turma($turmaId) : null;
$gradeBloqueada = $turma ? ch_turma_grade_bloqueada($turmaId) : false;

/* "Editar grade" desbloqueia uma grade já salva para uma nova rodada de ajustes. */
if ($turma && ($_POST['acao'] ?? '') === 'editar') {
    if (!ch_csrf_ok()) {
        ch_flash('Não foi possível desbloquear a grade. Tente novamente.', 'danger');
        header('Location: index.php?turma_id=' . $turmaId);
        exit;
    }
    $desbloqueou = ch_exec('UPDATE TB_TURMAS SET GRADE_BLOQUEADA = 0 WHERE ID = ?', [$turmaId]);
    if ($desbloqueou) {
        ch_auditar('GRADE', 'DESBLOQUEAR', 'TURMA', $turmaId, ['turma' => $turma['NOME']]);
        ch_flash('Grade desbloqueada para edição. Salve novamente ao concluir.', 'success');
    } else {
        ch_flash('Não foi possível desbloquear a grade. Tente novamente.', 'danger');
    }
    header('Location: index.php?turma_id=' . $turmaId);
    exit;
}

$disciplinas = [];
$professores = [];
$grade = [];

if ($turma) {
    $disciplinas = ch_rows(
        'SELECT ID, NOME FROM TB_TURMA_DISCIPLINAS WHERE ID_TURMA = ? ORDER BY NOME',
        [$turmaId]
    ) ?: [];

    $sqlProfessores = 'SELECT DISTINCT u.ID, u.NOME
           FROM TB_USUARIOS u
          WHERE u.NIVEL = 3 AND ISNULL(u.ATIVO, 1) = 1
            AND (EXISTS (SELECT 1 FROM TB_ATRIBUICOES a
                          INNER JOIN TB_TURMA_DISCIPLINAS d ON d.ID = a.ID_DISCIPLINA
                          WHERE d.ID_TURMA = ? AND a.ID_PROFESSOR = u.ID)
              OR EXISTS (SELECT 1 FROM TB_TURMA_PROFESSORES tp
                          WHERE tp.ID_TURMA = ? AND tp.ID_USUARIO = u.ID AND tp.ATIVO = 1)';
    $paramsProfessores = [$turmaId, $turmaId];
    if (ch_grade_tem_tabela()) {
        $sqlProfessores .= ' OR EXISTS (SELECT 1 FROM TB_GRADE_AULAS ga
                          WHERE ga.ID_TURMA = ? AND ga.ID_PROFESSOR = u.ID)';
        $paramsProfessores[] = $turmaId;
    }
    $sqlProfessores .= ') ORDER BY u.NOME';
    $professores = ch_rows($sqlProfessores, $paramsProfessores) ?: [];

    /* Professor padrão por disciplina: quem tem mais aulas atribuídas naquela
       matéria da turma (mesmo critério do responsável legado). */
    $atribuicoesPadrao = ch_rows(
        'SELECT d.ID AS ID_DISCIPLINA, a.ID_PROFESSOR, u.NOME AS PROFESSOR, a.AULAS
           FROM TB_TURMA_DISCIPLINAS d
          INNER JOIN TB_ATRIBUICOES a ON a.ID_DISCIPLINA = d.ID
          INNER JOIN TB_USUARIOS u ON u.ID = a.ID_PROFESSOR
          WHERE d.ID_TURMA = ? AND ISNULL(u.ATIVO, 1) = 1
          ORDER BY d.ID, a.AULAS DESC, a.ID_PROFESSOR',
        [$turmaId]
    ) ?: [];
    $professorPadrao = [];
    $limitesDisciplina = [];
    foreach ($atribuicoesPadrao as $atr) {
        $idDisc = (int)$atr['ID_DISCIPLINA'];
        if (!isset($professorPadrao[$idDisc])) {
            $professorPadrao[$idDisc] = [
                'ID' => (int)$atr['ID_PROFESSOR'],
                'NOME' => (string)$atr['PROFESSOR'],
            ];
        }
        /* Carga semanal total da matéria na turma: se dois professores dividem
           a disciplina, as aulas dos dois somam ao limite da grade. */
        $limitesDisciplina[$idDisc] = ($limitesDisciplina[$idDisc] ?? 0) + max(0, (int)$atr['AULAS']);
    }

    /* Só aparecem na grade as matérias que realmente têm professor atribuído. */
    $disciplinasAtribuidas = [];
    foreach ($disciplinas as $d) {
        if (($limitesDisciplina[(int)$d['ID']] ?? 0) > 0) $disciplinasAtribuidas[] = $d;
    }
    $disciplinas = $disciplinasAtribuidas;

    $grade = ch_grade_da_turma($turmaId);

    if (isset($_POST['grade']) && ch_csrf_ok()) {
        if ($gradeBloqueada) {
            ch_flash('A grade está salva. Clique em Editar grade para alterá-la.', 'danger');
            header('Location: index.php?turma_id=' . $turmaId);
            exit;
        }
        if (!ch_grade_tem_tabela()) {
            ch_flash('A tabela da grade diária ainda não foi criada. Rode sql_inicial/ATUALIZAR_GRADE_DIARIA.sql.', 'danger');
            header('Location: index.php?turma_id=' . $turmaId);
            exit;
        }

        $idsDisciplina = [];
        foreach ($disciplinas as $d) $idsDisciplina[(int)$d['ID']] = true;
        $idsProfessor = [];
        foreach ($professores as $p) $idsProfessor[(int)$p['ID']] = true;

        $enviados = $_POST['grade'];
        $alteracoes = 0;
        $falhou = false;
        $erroAmigavel = '';
        $anoLetivo = (int)($turma['ANO_LETIVO'] ?? 0);
        $aulasDia = ch_turma_aulas_dia($turmaId);

        /* Valida conflito antes de tocar no banco: um professor não pode ocupar
           o mesmo dia/aula em duas turmas do mesmo ano letivo. */
        foreach (ch_grade_dias() as $dia => $rotuloDia) {
            foreach (ch_aula_lista($aulasDia) as $aula => $rotuloAula) {
                $linha = $enviados[$dia][$aula] ?? [];
                $disc = (int)($linha['disciplina'] ?? 0);
                $prof = (int)($linha['professor'] ?? 0);
                if ($disc <= 0) continue;
                if ($prof > 0 && !isset($idsProfessor[$prof])) $prof = 0;
                if ($prof <= 0) continue;
                $outro = ch_grade_conflito_professor($turmaId, $dia, $aula, $prof, $anoLetivo);
                if ($outro) {
                    $erroAmigavel = 'O professor já está na ' . $rotuloAula . ' de '
                        . $rotuloDia . ' em ' . $outro['LOCAL'] . '.';
                    break 2;
                }
            }
        }

        /* Limite semanal por matéria, conforme as aulas atribuídas na turma. */
        if ($erroAmigavel === '') {
            $contagemDisciplina = [];
            foreach (ch_grade_dias() as $dia => $rotuloDia) {
                foreach (ch_aula_lista($aulasDia) as $aula => $rotuloAula) {
                    $disc = (int)($enviados[$dia][$aula]['disciplina'] ?? 0);
                    if ($disc > 0) $contagemDisciplina[$disc] = ($contagemDisciplina[$disc] ?? 0) + 1;
                }
            }
            $nomesDisciplina = [];
            foreach ($disciplinas as $d) $nomesDisciplina[(int)$d['ID']] = (string)$d['NOME'];
            foreach ($contagemDisciplina as $disc => $qtd) {
                $limite = (int)($limitesDisciplina[$disc] ?? 0);
                if ($limite > 0 && $qtd > $limite) {
                    $erroAmigavel = ($nomesDisciplina[$disc] ?? 'A matéria')
                        . ' foi usada em ' . $qtd . ' aula(s), mas a turma tem apenas '
                        . $limite . ' aula(s) atribuída(s).';
                    break;
                }
            }
        }

        if ($erroAmigavel !== '') {
            $grade = $enviados;
            ch_flash($erroAmigavel . ' Nada foi salvo.', 'danger');
            header('Location: index.php?turma_id=' . $turmaId);
            exit;
        }

        sqlsrv_begin_transaction(ch_db());

        foreach (ch_grade_dias() as $dia => $rotuloDia) {
            foreach (ch_aula_lista($aulasDia) as $aula => $rotuloAula) {
                $linha = $enviados[$dia][$aula] ?? [];
                $disc = (int)($linha['disciplina'] ?? 0);
                $prof = (int)($linha['professor'] ?? 0);
                if ($disc > 0 && !isset($idsDisciplina[$disc])) $disc = 0;
                if ($prof > 0 && !isset($idsProfessor[$prof])) $prof = 0;
                $atual = $grade[$dia][$aula] ?? null;

                if ($disc === 0) {
                    if ($atual !== null) {
                        $ok = ch_exec('DELETE FROM TB_GRADE_AULAS WHERE ID_TURMA = ? AND DIA_SEMANA = ? AND AULA_SEQ = ?',
                            [$turmaId, $dia, $aula]);
                        if (!$ok) { $falhou = true; break 2; }
                        $alteracoes++;
                    }
                    continue;
                }
                if ($atual !== null
                    && (int)$atual['ID_DISCIPLINA'] === $disc
                    && (int)$atual['ID_PROFESSOR'] === $prof) {
                    continue;
                }
                if ($atual !== null) {
                    $ok = ch_exec('UPDATE TB_GRADE_AULAS SET ID_DISCIPLINA = ?, ID_PROFESSOR = ?
                                    WHERE ID_TURMA = ? AND DIA_SEMANA = ? AND AULA_SEQ = ?',
                        [$disc, $prof > 0 ? $prof : null, $turmaId, $dia, $aula]);
                } else {
                    $ok = ch_exec('INSERT INTO TB_GRADE_AULAS
                                    (ID_TURMA, DIA_SEMANA, AULA_SEQ, ID_DISCIPLINA, ID_PROFESSOR)
                                 VALUES (?, ?, ?, ?, ?)',
                        [$turmaId, $dia, $aula, $disc, $prof > 0 ? $prof : null]);
                }
                if (!$ok) { $falhou = true; break 2; }
                $alteracoes++;
            }
        }

        if (!$falhou) {
            $okBloqueio = ch_exec('UPDATE TB_TURMAS SET GRADE_BLOQUEADA = 1 WHERE ID = ?', [$turmaId]);
            if (!$okBloqueio) $falhou = true;
        }

        if ($falhou) {
            sqlsrv_rollback(ch_db());
            ch_flash('Não foi possível salvar a grade diária. Tente novamente.', 'danger');
        } else {
            if ($alteracoes > 0) {
                ch_auditar('GRADE', 'SALVAR', 'TURMA', $turmaId,
                    ['aulas_alteradas' => $alteracoes, 'turma' => $turma['NOME']]);
            }
            sqlsrv_commit(ch_db());
            ch_flash($alteracoes > 0
                ? 'Grade diária salva (' . $alteracoes . ' aula(s) alterada(s)).'
                : 'Grade diária salva. Nenhuma alteração.', 'success');
        }
        header('Location: index.php?turma_id=' . $turmaId);
        exit;
    }
}
