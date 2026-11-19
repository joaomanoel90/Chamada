<?php
/* ============================================================================
   BUSCA DE RESPONSÁVEL POR CPF (JSON)
   Usado no formulário de cadastro/edição de aluno: quando o CPF do pai, da mãe
   ou de outro responsável já existe em TB_RESPONSAVEIS, devolve nome, telefone,
   e-mail e os filhos já vinculados no sistema para preenchimento automático.
   ============================================================================ */
require_once(dirname(__DIR__) . '/config.php');
require_once(DBAPI);
require_once(ABSPATH . 'inc/chamada.php');
ch_db();

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store, private');

if (!ch_gestao_alunos()) {
    http_response_code(403);
    echo json_encode(['encontrado' => false, 'erro' => 'Acesso restrito.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$cpf = fam_cpf_limpo($_POST['cpf'] ?? '');
if (strlen($cpf) !== 11 || !fam_cpf_valido($cpf)) {
    echo json_encode(['encontrado' => false, 'valido' => false], JSON_UNESCAPED_UNICODE);
    exit;
}

$resp = fam_buscar_por_cpf($cpf);
if (!$resp) {
    echo json_encode(['encontrado' => false, 'valido' => true], JSON_UNESCAPED_UNICODE);
    exit;
}

$series = ch_series_ensino();
$filhos = [];
foreach ($resp['FILHOS'] ?? [] as $f) {
    $filhos[] = [
        'id'        => (int)$f['ID'],
        'nome'      => (string)$f['NOME'],
        'turma'     => ($series[$f['SERIE'] ?? '']['rotulo'] ?? 'Série') . ' · Sala ' . $f['TURMA'] . ' (' . $f['UNIDADE'] . ')',
        'ativo'     => (int)$f['ATIVO'] === 1,
    ];
}

echo json_encode([
    'encontrado'    => true,
    'valido'        => true,
    'cpf_formatado' => ch_cpf_mask($resp['CPF']),
    'nome'          => (string)$resp['NOME'],
    'telefone'      => (string)($resp['TELEFONE'] ?? ''),
    'email'         => (string)($resp['EMAIL'] ?? ''),
    'filhos'        => $filhos,
], JSON_UNESCAPED_UNICODE);
