<?php
require_once(dirname(__DIR__, 2) . '/config.php');
require_once(DBAPI);
require_once(ABSPATH . 'inc/chamada.php');
ch_db();

ch_require_gestao_alunos();

$erros = [];
$anos = aee_anos_disponiveis();
$anoAtual = (int)date('Y');

$idAluno = (int)($_POST['id_aluno'] ?? $_GET['aluno_id'] ?? 0);
$aluno = $idAluno > 0 ? ch_aluno($idAluno) : null;

$anoLetivo = (int)($_POST['ano_letivo'] ?? $_GET['ano'] ?? ($aluno['ANO_LETIVO'] ?? $anoAtual));
if (!in_array($anoLetivo, $anos, true)) {
    $anoLetivo = $anos[0] ?? $anoAtual;
}

// Se o aluno não foi informado previamente, carrega lista de alunos ativos no alcance para seleção
$alunosDisponiveis = [];
if (!$aluno) {
    $fTurma = ch_filtro_turma('t');
    $alunosDisponiveis = ch_rows(
        'SELECT a.ID, a.NOME, a.MATRICULA, t.NOME AS TURMA, t.SERIE, u.unidade AS UNIDADE, t.ANO_LETIVO
         FROM TB_ALUNOS a
         INNER JOIN TB_TURMAS t ON t.ID = a.ID_TURMA
         INNER JOIN TB_UNIDADES u ON u.id = t.ID_UNIDADE
         WHERE a.ATIVO = 1 ' . $fTurma['sql'] . '
         ORDER BY u.unidade, t.SERIE, a.NOME',
        $fTurma['params']
    );
}

$atendimento = $idAluno > 0 ? aee_atendimento_aluno($idAluno, $anoLetivo) : null;
$professoresAee = ch_professores_aee_opcoes((int)($atendimento['ID_PROFESSOR_AEE'] ?? $aluno['ID_PROFESSOR_AEE'] ?? 0));

$d = [
    'id_aluno'          => $idAluno,
    'ano_letivo'        => $anoLetivo,
    'ativo'             => $atendimento ? (int)$atendimento['ATIVO'] : (!empty($aluno['PASSA_AEE']) ? 1 : 1),
    'id_professor_aee'  => (int)($atendimento['ID_PROFESSOR_AEE'] ?? $aluno['ID_PROFESSOR_AEE'] ?? 0),
    'dias_horarios'     => (string)($atendimento['DIAS_HORARIOS'] ?? $aluno['AEE_OBSERVACOES'] ?? ''),
    'sala_recursos'     => (string)($atendimento['SALA_RECURSOS'] ?? ''),
    'objetivos_plano'   => (string)($atendimento['OBJETIVOS_PLANO'] ?? ''),
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!ch_csrf_ok()) {
        header('Location: ' . ch_url('aee/atribuir.php?aluno_id=' . $idAluno . '&ano=' . $anoLetivo));
        exit;
    }

    $idAluno = (int)($_POST['id_aluno'] ?? 0);
    $anoLetivo = (int)($_POST['ano_letivo'] ?? 0);

    $d['id_aluno']         = $idAluno;
    $d['ano_letivo']       = $anoLetivo;
    $d['ativo']            = !empty($_POST['ativo']) ? 1 : 0;
    $d['id_professor_aee'] = (int)($_POST['id_professor_aee'] ?? 0);
    $d['dias_horarios']    = trim((string)($_POST['dias_horarios'] ?? ''));
    $d['sala_recursos']    = trim((string)($_POST['sala_recursos'] ?? ''));
    $d['objetivos_plano']  = trim((string)($_POST['objetivos_plano'] ?? ''));

    if ($idAluno <= 0) {
        $erros[] = 'Selecione o aluno para o atendimento de AEE.';
    }
    if ($anoLetivo <= 0) {
        $erros[] = 'Informe o ano letivo de referência.';
    }

    if (!$erros) {
        if (aee_salvar_atendimento($idAluno, $anoLetivo, $d, $erros)) {
            ch_flash('Atendimento de AEE salvo com sucesso para o ano letivo ' . $anoLetivo . '.');
            $origem = $_POST['origem'] ?? '';
            if ($origem === 'ficha') {
                header('Location: ' . ch_url('alunos/view.php?id=' . $idAluno));
            } else {
                header('Location: ' . ch_url('aee/index.php?ano=' . $anoLetivo));
            }
            exit;
        }
    }
}
