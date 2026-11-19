<?php
/* ============================================================================
   CONSULTA DE FAMÍLIAS / RESPONSÁVEIS E SEUS FILHOS NA REDE
   ----------------------------------------------------------------------------
   Permite pesquisar pelo nome ou CPF do pai, da mãe ou de outro responsável e
   enxergar todos os filhos vinculados a ele(a) no sistema (respeitando o
   alcance de unidades/turmas do usuário logado).
   ============================================================================ */
require_once(dirname(__DIR__, 2) . '/config.php');
require_once(DBAPI);
require_once(ABSPATH . 'inc/chamada.php');
ch_db();

$q          = trim((string)($_GET['q'] ?? ''));
$fUnidade   = (int)($_GET['unidade'] ?? 0);
$fParentesco = strtoupper(trim((string)($_GET['parentesco'] ?? '')));
if (!isset(fam_parentescos()[$fParentesco])) {
    $fParentesco = '';
}
$soIrmaos   = !empty($_GET['irmaos']) ? 1 : 0;
$verCpf     = ch_gestao_alunos();

$unidades = ch_unidades_opcoes();
$series   = ch_series_ensino();

$fAlcance = ch_filtro_turma_ou_aee('t', 'a');
$where    = ' WHERE 1 = 1' . $fAlcance['sql'];
$params   = $fAlcance['params'];

if ($fUnidade > 0) {
    $where .= ' AND t.ID_UNIDADE = ?';
    $params[] = $fUnidade;
}
if ($fParentesco !== '') {
    $where .= ' AND ar.PARENTESCO = ?';
    $params[] = $fParentesco;
}
if ($q !== '') {
    $qDigitos = preg_replace('/\D/', '', $q);
    if ($qDigitos !== '' && $qDigitos === str_replace(['.', '-', ' '], '', $q)) {
        $where .= ' AND (r.CPF LIKE ? OR a.CPF LIKE ? OR a.MATRICULA LIKE ?)';
        $params[] = "%{$qDigitos}%";
        $params[] = "%{$qDigitos}%";
        $params[] = "%{$q}%";
    } elseif ($qDigitos !== '') {
        $where .= ' AND (r.NOME LIKE ? OR r.CPF LIKE ? OR r.TELEFONE LIKE ? OR a.NOME LIKE ?)';
        $params[] = "%{$q}%";
        $params[] = "%{$qDigitos}%";
        $params[] = "%{$q}%";
        $params[] = "%{$q}%";
    } else {
        $where .= ' AND (r.NOME LIKE ? OR r.EMAIL LIKE ? OR a.NOME LIKE ?)';
        $params[] = "%{$q}%";
        $params[] = "%{$q}%";
        $params[] = "%{$q}%";
    }
}

$linhas = ch_rows(
    "SELECT r.ID AS ID_RESPONSAVEL, r.CPF AS RESP_CPF, r.NOME AS RESP_NOME,
            r.TELEFONE AS RESP_TELEFONE, r.EMAIL AS RESP_EMAIL,
            ar.PARENTESCO, ar.DESCRICAO_OUTRO, ar.CONTATO_PRINCIPAL,
            a.ID AS ID_ALUNO, a.NOME AS ALUNO_NOME, a.MATRICULA, a.NASCIMENTO, a.ATIVO,
            t.NOME AS TURMA, t.SERIE, t.ANO_LETIVO, p.NOME AS PERIODO, u.unidade AS UNIDADE
       FROM TB_RESPONSAVEIS r
       INNER JOIN TB_ALUNO_RESPONSAVEIS ar ON ar.ID_RESPONSAVEL = r.ID
       INNER JOIN TB_ALUNOS a   ON a.ID = ar.ID_ALUNO
       INNER JOIN TB_TURMAS t   ON t.ID = a.ID_TURMA
       INNER JOIN TB_PERIODOS p ON p.ID = t.ID_PERIODO
       INNER JOIN TB_UNIDADES u ON u.id = t.ID_UNIDADE"
    . $where .
    " ORDER BY r.NOME, a.ATIVO DESC, a.NASCIMENTO, a.NOME",
    $params
) ?: [];

/* Agrupa por responsável (ID_RESPONSAVEL). */
$responsaveis = [];
foreach ($linhas as $l) {
    $idR = (int)$l['ID_RESPONSAVEL'];
    if (!isset($responsaveis[$idR])) {
        $responsaveis[$idR] = [
            'ID'       => $idR,
            'CPF'      => $l['RESP_CPF'],
            'NOME'     => $l['RESP_NOME'],
            'TELEFONE' => $l['RESP_TELEFONE'],
            'EMAIL'    => $l['RESP_EMAIL'],
            'FILHOS'   => [],
        ];
    }
    $responsaveis[$idR]['FILHOS'][] = $l;
}

if ($soIrmaos) {
    $responsaveis = array_filter($responsaveis, fn($r) => count($r['FILHOS']) >= 2);
}
$responsaveis = array_values($responsaveis);
