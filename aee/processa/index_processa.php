<?php
require_once(dirname(__DIR__, 2) . '/config.php');
require_once(DBAPI);
require_once(ABSPATH . 'inc/chamada.php');
ch_db();

if (!ch_gestao_alunos() && !ch_professor()) {
    ch_flash('Acesso restrito à equipe escolar.', 'danger');
    header('Location: ' . BASEURL . 'index.php');
    exit;
}

$anos = aee_anos_disponiveis();
$anoAtual = (int)date('Y');
$fAno = (int)($_GET['ano'] ?? ($anos[0] ?? $anoAtual));
if (!in_array($fAno, $anos, true)) {
    $fAno = $anos[0] ?? $anoAtual;
}

$fUnidade = (int)($_GET['unidade_id'] ?? 0);
$fProf    = (int)($_GET['professor_id'] ?? 0);
$fStatus  = trim((string)($_GET['status'] ?? ''));
$fBusca   = trim((string)($_GET['busca'] ?? ''));

$unidadesOpcoes = ch_unidades_opcoes();
$professoresAeeOpcoes = ch_professores_aee_opcoes();

$alunosAee = aee_painel_listar($fAno, [
    'unidade_id'   => $fUnidade,
    'professor_id' => $fProf,
    'status'       => $fStatus,
    'busca'        => $fBusca,
]);

// Contadores do painel
$totalAlunosAee = count($alunosAee);
$qtdAtivos = 0;
$qtdSemProfessor = 0;
foreach ($alunosAee as $al) {
    if (!empty($al['AEE_ATIVO']) && !empty($al['ID_PROFESSOR_AEE'])) {
        $qtdAtivos++;
    } elseif (empty($al['ID_PROFESSOR_AEE'])) {
        $qtdSemProfessor++;
    }
}
