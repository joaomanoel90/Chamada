<?php
require_once(dirname(__DIR__, 2) . '/config.php');
require_once(DBAPI);
require_once(ABSPATH . 'inc/chamada.php');
ch_db();
/* $turmaFixa > 0 = edição: a turma NÃO vem do formulário. Trocar o aluno de
   turma/escola é a transferência (transferencias/), que tem duas etapas, data
   de início e histórico. Um <select> aqui permitia mover o aluno sem registrar
   nada — e a chamada de datas passadas mudava de turma junto. */
function aluno_validar($post, $idAtual = 0, $turmaFixa = 0)
{
    $d = [
        'ID_TURMA'       => $turmaFixa > 0 ? (int)$turmaFixa : (int)($post['id_turma'] ?? 0),
        'MATRICULA'      => strip_tags(trim($post['matricula'] ?? '')),
        'NOME'           => strip_tags(trim($post['nome'] ?? '')),
        'NASCIMENTO'     => ch_data_iso($post['nascimento'] ?? ''),
        'DATA_MATRICULA' => ch_data_iso($post['data_matricula'] ?? '', ch_hoje()),
        'RESPONSAVEL'    => strip_tags(trim($post['responsavel'] ?? '')),
        'TELEFONE'       => strip_tags(trim($post['telefone'] ?? '')),
        'EMAIL'          => trim($post['email'] ?? ''),
    ];
    $e = [];

    $turma = $d['ID_TURMA'] ? ch_turma($d['ID_TURMA']) : null;
    if (!$turma || (int)$turma['ATIVO'] !== 1) {
        $e[] = 'Selecione uma turma ativa.';
    } elseif (!ch_gestor() && !ch_pode_unidade($turma['ID_UNIDADE'])) {
        $e[] = 'Você não tem acesso a essa unidade.';
    }

    if ($d['MATRICULA'] === '' || mb_strlen($d['MATRICULA']) > 30) { $e[] = 'Informe a matrícula (até 30 caracteres).'; }
    if ($d['NOME'] === '' || mb_strlen($d['NOME']) > 150)          { $e[] = 'Informe o nome do aluno (até 150 caracteres).'; }
    if ($d['RESPONSAVEL'] === '' || mb_strlen($d['RESPONSAVEL']) > 150) { $e[] = 'Informe o nome do responsável.'; }
    if ($d['TELEFONE'] === '' || mb_strlen($d['TELEFONE']) > 30)   { $e[] = 'Informe um telefone de contato.'; }

    if (!$d['NASCIMENTO']) {
        $e[] = 'Informe a data de nascimento.';
    } elseif ($d['NASCIMENTO'] > ch_hoje()) {
        $e[] = 'A data de nascimento não pode ser futura.';
    }
    if (!$d['DATA_MATRICULA']) {
        $e[] = 'Informe a data de matrícula.';
    } elseif ($d['NASCIMENTO'] && $d['DATA_MATRICULA'] < $d['NASCIMENTO']) {
        $e[] = 'A matrícula não pode ser anterior ao nascimento.';
    }
    if ($d['EMAIL'] !== '' && !filter_var($d['EMAIL'], FILTER_VALIDATE_EMAIL)) {
        $e[] = 'E-mail inválido.';
    }
    if (!$e && ch_val('SELECT ID FROM TB_ALUNOS WHERE MATRICULA = ? AND ID <> ?', [$d['MATRICULA'], $idAtual])) {
        $e[] = 'Já existe um aluno com essa matrícula.';
    }
    /* Lotação só interessa quando o aluno está ENTRANDO na turma. Na edição
       ele já está lá, e conferir de novo impediria corrigir o nome de um aluno
       numa turma que já estava cheia. */
    if (!$e && $turma && $turmaFixa === 0) {
        $ocupadas = (int)ch_val('SELECT COUNT(*) FROM TB_ALUNOS WHERE ID_TURMA = ? AND ATIVO = 1 AND ID <> ?',
                                [$d['ID_TURMA'], $idAtual]);
        if ($ocupadas >= (int)$turma['CAPACIDADE']) {
            $e[] = 'A turma ' . $turma['NOME'] . ' já atingiu a capacidade de ' . $turma['CAPACIDADE'] . ' alunos.';
        }
    }
    return [$d, $e];
}

function aluno_turmas_disponiveis()
{
    $f = ch_filtro_turma('t');
    return ch_rows(
        'SELECT t.ID, t.NOME, t.ANO_LETIVO, t.CAPACIDADE, u.unidade AS UNIDADE,
                (SELECT COUNT(*) FROM TB_ALUNOS a WHERE a.ID_TURMA = t.ID AND a.ATIVO = 1) AS OCUPADAS
         FROM TB_TURMAS t INNER JOIN TB_UNIDADES u ON u.id = t.ID_UNIDADE
         WHERE t.ATIVO = 1' . $f['sql'] . ' ORDER BY u.unidade, t.NOME',
        $f['params']
    );
}
