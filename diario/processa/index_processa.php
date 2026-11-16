<?php
require_once(dirname(__DIR__, 2) . '/config.php');
require_once(DBAPI);
require_once(ABSPATH . 'inc/chamada.php');
ch_db();

function diario_nota_normalizar($valor)
{
    if (!is_string($valor) && !is_int($valor) && !is_float($valor)) {
        return false;
    }
    $valor = trim((string)$valor);
    if ($valor === '') {
        return null;
    }
    $valor = str_replace(',', '.', $valor);
    if (!preg_match('/^(?:[0-9]+(?:\.[0-9]{1,2})?|\.[0-9]{1,2})$/D', $valor)
        || (float)$valor < 0 || (float)$valor > 10) {
        return false;
    }
    return number_format((float)$valor, 2, '.', '');
}

$filtro = ch_filtro_turma('t');
$turmas = ch_rows(
    'SELECT t.ID, t.NOME, t.SERIE, t.ANO_LETIVO, t.ETAPA_ENSINO, t.NOTAS_ATIVAS,
            u.unidade AS UNIDADE, p.NOME AS PERIODO
     FROM TB_TURMAS t
     INNER JOIN TB_UNIDADES u ON u.id = t.ID_UNIDADE
     INNER JOIN TB_PERIODOS p ON p.ID = t.ID_PERIODO
     WHERE t.ATIVO = 1 AND t.NOTAS_ATIVAS = 1
       AND (t.ETAPA_ENSINO IS NULL OR t.ETAPA_ENSINO <> \'INFANTIL_CRECHE\')' . $filtro['sql'] . '
     ORDER BY u.unidade, t.SERIE, t.NOME, p.NOME',
    $filtro['params']
);

$parametros = $_SERVER['REQUEST_METHOD'] === 'POST' ? $_POST : $_GET;
$turmaId = filter_var($parametros['turma_id'] ?? ($turmas ? (int)$turmas[0]['ID'] : 0), FILTER_VALIDATE_INT);
$turmaId = $turmaId ?: 0;
$bimestreInformado = filter_var($parametros['bimestre'] ?? 1, FILTER_VALIDATE_INT);
$bimestreValido = $bimestreInformado !== false && $bimestreInformado >= 1 && $bimestreInformado <= 4;
$bimestre = $bimestreValido ? $bimestreInformado : 1;

$turma = null;
$podeLancar = false;
$disciplinas = [];
$professoresDisciplina = [];
$disciplinaId = filter_var($parametros['disciplina_id'] ?? 0, FILTER_VALIDATE_INT);
$disciplinaId = $disciplinaId ?: 0;
$alunos = [];
$notaPorAluno = [];
$notasPost = [];
$erros = [];

if ($turmaId > 0) {
    if (!ch_pode_turma($turmaId)) {
        ch_flash('Você não tem acesso a essa turma.', 'danger');
        header('Location: ' . ch_url('diario/index.php'));
        exit;
    }

    $turma = ch_turma($turmaId);
    if (!$turma || (int)$turma['NOTAS_ATIVAS'] !== 1
        || $turma['ETAPA_ENSINO'] === 'INFANTIL_CRECHE') {
        ch_flash('O lançamento de notas não está habilitado para essa turma.', 'warning');
        header('Location: ' . ch_url('diario/index.php'));
        exit;
    }
    $membros = ch_rows(
        "SELECT DISTINCT u.ID, u.NOME
         FROM TB_TURMA_PROFESSORES tp
         INNER JOIN TB_USUARIOS u ON u.ID = tp.ID_USUARIO
         WHERE tp.ID_TURMA = ? AND tp.ATIVO = 1 AND ISNULL(u.ATIVO, 1) = 1
           AND u.NIVEL = 3 AND tp.PAPEL <> 'SUBSTITUTO'
         ORDER BY u.NOME",
        [$turmaId]
    );
    foreach ($membros ?: [] as $membro) {
        $professoresDisciplina[(int)$membro['ID']] = $membro['NOME'];
    }
    /* Matérias que cada membro pode lecionar (do cadastro dele). Só é
       possível atribuir a ele uma matéria que esteja no cadastro. */
    $materiasPorProfessor = [];
    if ($professoresDisciplina) {
        $idsP = array_keys($professoresDisciplina);
        $phP = implode(',', array_fill(0, count($idsP), '?'));
        foreach (ch_rows(
            "SELECT ID_USUARIO, NOME FROM TB_PROFESSOR_MATERIAS WHERE ID_USUARIO IN ({$phP}) ORDER BY NOME",
            $idsP
        ) ?: [] as $mp) {
            $materiasPorProfessor[(int)$mp['ID_USUARIO']][mb_strtolower((string)$mp['NOME'], 'UTF-8')] = (string)$mp['NOME'];
        }
    }
    $disciplinas = ch_rows(
        'SELECT d.ID, d.NOME, d.ID_PROFESSOR, d.AULAS
         FROM TB_TURMA_DISCIPLINAS d
         WHERE d.ID_TURMA = ? ORDER BY d.NOME',
        [$turmaId]
    );
    /* Professores com aulas em cada disciplina (as aulas são divididas). */
    $atribuicoesPorDisc = [];
    if ($disciplinas) {
        $idsD = array_map(function ($d) { return (int)$d['ID']; }, $disciplinas);
        $phD = implode(',', array_fill(0, count($idsD), '?'));
        foreach (ch_rows(
            "SELECT a.ID_DISCIPLINA, a.ID_PROFESSOR, a.AULAS, u.NOME
             FROM TB_ATRIBUICOES a
             INNER JOIN TB_USUARIOS u ON u.ID = a.ID_PROFESSOR
             WHERE a.ID_DISCIPLINA IN ({$phD}) ORDER BY u.NOME",
            $idsD
        ) ?: [] as $a) {
            $atribuicoesPorDisc[(int)$a['ID_DISCIPLINA']][] = $a;
        }
    }
    foreach ($disciplinas as $i => $d) {
        $nomes = [];
        $minha = false;
        foreach ($atribuicoesPorDisc[(int)$d['ID']] ?? [] as $a) {
            $nomes[] = $a['NOME'] . ' (' . (int)$a['AULAS'] . ')';
            if ((int)$a['ID_PROFESSOR'] === ch_uid()) { $minha = true; }
        }
        $disciplinas[$i]['PROFESSOR'] = implode(', ', $nomes);
        $disciplinas[$i]['TEM_AULA_MINHA'] = $minha;
    }
    if ($disciplinaId < 1 || !array_filter($disciplinas, function ($d) use ($disciplinaId) {
        return (int)$d['ID'] === $disciplinaId;
    })) {
        $salvandoNotas = $_SERVER['REQUEST_METHOD'] === 'POST'
            && ($_POST['acao'] ?? '') === 'salvar_notas';
        if (!$salvandoNotas) {
            $disciplinaId = $disciplinas ? (int)$disciplinas[0]['ID'] : 0;
        }
    }

    $disciplinaSelecionada = null;
    foreach ($disciplinas as $disciplina) {
        if ((int)$disciplina['ID'] === $disciplinaId) {
            $disciplinaSelecionada = $disciplina;
            break;
        }
    }
    $podeLancar = ch_professor() && $disciplinaSelecionada
        && !empty($disciplinaSelecionada['TEM_AULA_MINHA']);

    if ($disciplinaId > 0) {
        $alunos = ch_rows(
            'SELECT a.ID, a.NOME, a.MATRICULA, a.ID_TURMA, a.ATIVO
             FROM TB_ALUNOS a
             WHERE (a.ID_TURMA = ? AND a.ATIVO = 1)
                OR EXISTS (
                    SELECT 1 FROM TB_NOTAS n
                    INNER JOIN TB_TURMA_DISCIPLINAS d ON d.ID = n.ID_DISCIPLINA
                    WHERE n.ID_ALUNO = a.ID AND d.ID_TURMA = ?
                )
             ORDER BY a.NOME',
            [$turmaId, $turmaId]
        );
        $notas = ch_rows(
            'SELECT ID_ALUNO, NOTA FROM TB_NOTAS
             WHERE ID_DISCIPLINA = ? AND BIMESTRE = ?',
            [$disciplinaId, $bimestre]
        );
        foreach ($notas as $nota) {
            $notaPorAluno[(int)$nota['ID_ALUNO']] = number_format((float)$nota['NOTA'], 2, '.', '');
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!ch_csrf_ok()) {
        ch_flash('A sessão expirou. Tente novamente.', 'danger');
        header('Location: ' . ch_url('diario/index.php'));
        exit;
    }

    $acao = is_string($_POST['acao'] ?? null) ? $_POST['acao'] : '';
    /* As matérias da turma são definidas na área de Turmas; aqui só se
       atribui professor às matérias já cadastradas. */
    if ($acao === 'atribuir_professor' && !ch_gestor()) {
        $erros[] = 'Somente a gestão pode atribuir professor à disciplina.';
    } elseif ($acao === 'atribuir_professor') {
        $professorId = filter_var($_POST['id_professor'] ?? 0, FILTER_VALIDATE_INT);
        $disciplinaValida = $disciplinaId > 0
            && (bool)ch_val('SELECT ID FROM TB_TURMA_DISCIPLINAS WHERE ID = ? AND ID_TURMA = ?', [$disciplinaId, $turmaId]);
        if (!$disciplinaValida) {
            $erros[] = 'Selecione uma disciplina válida.';
        } elseif (!$professorId || !isset($professoresDisciplina[$professorId])) {
            $erros[] = 'Selecione um professor ativo da equipe da turma para esta disciplina.';
        } elseif (!eq_professor_pode_materia(
            $materiasPorProfessor[$professorId] ?? [],
            $disciplinaSelecionada['NOME'] ?? ''
        )) {
            $erros[] = 'O professor ' . $professoresDisciplina[$professorId]
                     . ' não tem "' . ($disciplinaSelecionada['NOME'] ?? '') . '" no cadastro dele. Só é possível atribuir a matéria que ele pode lecionar.';
        } else {
            /* Adiciona o professor às aulas da disciplina (1 aula, se ele
               ainda não tem atribuição; o ajuste fino é no painel). */
            sqlsrv_begin_transaction(ch_db());
            $jaTem = (int)ch_val(
                'SELECT COUNT(*) FROM TB_ATRIBUICOES WHERE ID_DISCIPLINA = ? AND ID_PROFESSOR = ?',
                [$disciplinaId, $professorId]
            );
            $ok = $jaTem > 0
               || ch_exec('INSERT INTO TB_ATRIBUICOES (ID_DISCIPLINA, ID_PROFESSOR, AULAS) VALUES (?, ?, 1)',
                          [$disciplinaId, $professorId]);
            if ($ok) {
                $ok = eq_disciplina_sincronizar_responsavel($disciplinaId);
            }
            if ($ok) {
                $ok = (bool)sqlsrv_commit(ch_db());
            } else {
                sqlsrv_rollback(ch_db());
            }
            if (!$ok) {
                $erros[] = 'Não foi possível atribuir o professor.';
            } else {
                ch_flash('Professor atribuído à disciplina.');
                header('Location: ' . ch_url('diario/index.php?turma_id=' . $turmaId
                    . '&disciplina_id=' . $disciplinaId . '&bimestre=' . $bimestre));
                exit;
            }
        }
    } elseif ($acao === 'salvar_notas' && !$podeLancar) {
        $erros[] = 'Somente o professor com aulas atribuídas nesta disciplina pode lançar notas.';
    } elseif ($acao === 'salvar_notas') {
        if (!$bimestreValido) {
            $erros[] = 'Selecione um bimestre válido.';
        }
        $disciplinaValida = $disciplinaId > 0
            && (bool)ch_val(
                'SELECT ID FROM TB_TURMA_DISCIPLINAS d
                 WHERE d.ID = ? AND d.ID_TURMA = ?
                   AND EXISTS (SELECT 1 FROM TB_ATRIBUICOES a
                               WHERE a.ID_DISCIPLINA = d.ID AND a.ID_PROFESSOR = ?)',
                [$disciplinaId, $turmaId, ch_uid()]
            );
        if (!$disciplinaValida) {
            $erros[] = 'Selecione uma disciplina válida.';
        }

        $idsAlunos = [];
        foreach ($alunos as $aluno) {
            if ((int)$aluno['ID_TURMA'] === $turmaId && (int)$aluno['ATIVO'] === 1) {
                $idsAlunos[(int)$aluno['ID']] = true;
            }
        }
        $notasPost = (array)($_POST['notas'] ?? []);
        $valores = [];
        foreach ($notasPost as $idAluno => $valor) {
            $idAluno = filter_var($idAluno, FILTER_VALIDATE_INT);
            if (!$idAluno || !isset($idsAlunos[$idAluno])) {
                $erros[] = 'A lista de alunos enviada não corresponde à turma selecionada.';
                break;
            }
            $notaNormalizada = diario_nota_normalizar($valor);
            if ($notaNormalizada === false) {
                $erros[] = 'As notas devem ser números de 0 a 10, com até duas casas decimais.';
                break;
            }
            if ($notaNormalizada === null) {
                $valores[$idAluno] = null;
                continue;
            }
            $valores[$idAluno] = $notaNormalizada;
        }

        if (!$erros) {
            if (!sqlsrv_begin_transaction(ch_db())) {
                $erros[] = 'Não foi possível iniciar o salvamento das notas.';
            } else {
                $ok = true;
                foreach ($valores as $idAluno => $nota) {
                    if ($nota === null) {
                        $ok = ch_exec(
                            'DELETE FROM TB_NOTAS WHERE ID_ALUNO = ? AND ID_DISCIPLINA = ? AND BIMESTRE = ?',
                            [$idAluno, $disciplinaId, $bimestre]
                        );
                    } else {
                        $ok = ch_exec(
                            'UPDATE TB_NOTAS
                                SET NOTA = ?, ID_USUARIO = ?, ATUALIZADA_EM = GETDATE()
                              WHERE ID_ALUNO = ? AND ID_DISCIPLINA = ? AND BIMESTRE = ?;
                             IF @@ROWCOUNT = 0
                                INSERT INTO TB_NOTAS (ID_ALUNO, ID_DISCIPLINA, BIMESTRE, NOTA, ID_USUARIO)
                                VALUES (?, ?, ?, ?, ?);',
                            [$nota, ch_uid(), $idAluno, $disciplinaId, $bimestre,
                             $idAluno, $disciplinaId, $bimestre, $nota, ch_uid()]
                        );
                    }
                    if (!$ok) {
                        break;
                    }
                }

                if ($ok && sqlsrv_commit(ch_db())) {
                    ch_flash('Notas salvas.');
                    header('Location: ' . ch_url('diario/index.php?turma_id=' . $turmaId
                        . '&disciplina_id=' . $disciplinaId . '&bimestre=' . $bimestre));
                    exit;
                }
                sqlsrv_rollback(ch_db());
                $erros[] = 'Não foi possível salvar as notas. Nenhuma alteração foi mantida.';
            }
        }
    } else {
        $erros[] = 'Ação inválida.';
    }
}
