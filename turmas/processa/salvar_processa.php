<?php
require_once(dirname(__DIR__, 2) . '/config.php');
require_once(DBAPI);
require_once(ABSPATH . 'inc/chamada.php');
ch_db();
ch_require_gestor();

/** Valida os dados do formulário de turma. Devolve [dados, erros]. */
function turma_validar($post, $idAtual = 0)
{
    $d = [
        'ID_UNIDADE'   => (int)($post['id_unidade'] ?? 0),
        'NOME'         => strip_tags(trim($post['nome'] ?? '')),
        'ANO_LETIVO'   => (int)($post['ano_letivo'] ?? 0),
        'ID_PERIODO'   => (int)($post['id_periodo'] ?? 0),
        'ID_PROFESSOR' => (int)($post['id_professor'] ?? 0),
        'CAPACIDADE'   => (int)($post['capacidade'] ?? 0),
    ];
    $e = [];

    if (!ch_val('SELECT id FROM TB_UNIDADES WHERE id = ?', [$d['ID_UNIDADE']])) {
        $e[] = 'Selecione uma unidade válida.';
    }
    if ($d['NOME'] === '' || mb_strlen($d['NOME']) > 80) {
        $e[] = 'Informe o nome da turma (até 80 caracteres).';
    }
    if ($d['ANO_LETIVO'] < 2000 || $d['ANO_LETIVO'] > 2100) {
        $e[] = 'Ano letivo inválido.';
    }
    if (!ch_val('SELECT ID FROM TB_PERIODOS WHERE ID = ? AND ATIVO = 1', [$d['ID_PERIODO']])) {
        $e[] = 'Selecione um período ativo.';
    }
    if ($d['ID_PROFESSOR'] > 0 &&
        !ch_val('SELECT ID FROM TB_USUARIOS WHERE ID = ? AND ISNULL(ATIVO, 1) = 1', [$d['ID_PROFESSOR']])) {
        $e[] = 'Professor(a) inválido ou inativo.';
    }
    if ($d['CAPACIDADE'] < 1 || $d['CAPACIDADE'] > 500) {
        $e[] = 'A capacidade deve ficar entre 1 e 500.';
    }
    if (!$e && ch_val('SELECT ID FROM TB_TURMAS WHERE ID_UNIDADE = ? AND NOME = ? AND ANO_LETIVO = ? AND ID <> ?',
                      [$d['ID_UNIDADE'], $d['NOME'], $d['ANO_LETIVO'], $idAtual])) {
        $e[] = 'Já existe uma turma com esse nome nessa unidade e ano letivo.';
    }
    if (!$e && $idAtual > 0) {
        $matriculados = (int)ch_val('SELECT COUNT(*) FROM TB_ALUNOS WHERE ID_TURMA = ? AND ATIVO = 1', [$idAtual]);
        if ($d['CAPACIDADE'] < $matriculados) {
            $e[] = 'A capacidade não pode ser menor que os ' . $matriculados . ' alunos já matriculados.';
        }
    }
    return [$d, $e];
}

/** Opções dos selects do formulário. */
function turma_opcoes()
{
    return [
        'unidades' => ch_rows('SELECT id, unidade FROM TB_UNIDADES ORDER BY unidade'),
        'periodos' => ch_rows('SELECT ID, NOME, HORA_INICIO, HORA_FIM FROM TB_PERIODOS WHERE ATIVO = 1 ORDER BY HORA_INICIO'),
        'professores' => ch_rows('SELECT ID, NOME FROM TB_USUARIOS WHERE ISNULL(ATIVO, 1) = 1 ORDER BY NOME'),
    ];
}
