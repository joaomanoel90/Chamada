<?php
require_once(dirname(__DIR__, 2) . '/config.php');
require_once(DBAPI);
require_once(ABSPATH . 'inc/chamada.php');
ch_db();
require_once(ABSPATH . 'inc/chamada_categorias.php');

function observacao_validar($post)
{
    $d = [
        'ID_ALUNO'  => (int)($post['aluno_id'] ?? 0),
        'DATA'      => ch_data_iso($post['data'] ?? '', ch_hoje()),
        'CATEGORIA' => trim($post['categoria'] ?? ''),
        'TEXTO'     => trim(strip_tags($post['texto'] ?? '')),
    ];
    $e = [];

    /* Escrita exige a turma ATUAL: a escola de origem, depois da
       transferência, fica só com a consulta ao histórico. */
    if (!ch_pode_aluno_atual($d['ID_ALUNO'])) { $e[] = 'Selecione um aluno da sua unidade.'; }
    if (!$d['DATA']) { $e[] = 'Informe uma data válida.'; }
    elseif ($d['DATA'] > ch_hoje()) { $e[] = 'A data da observação não pode ser futura.'; }
    if (!in_array($d['CATEGORIA'], ch_categorias(), true)) { $e[] = 'Selecione uma categoria válida.'; }
    if ($d['TEXTO'] === '') { $e[] = 'Escreva a observação.'; }
    elseif (mb_strlen($d['TEXTO']) > 5000) { $e[] = 'A observação deve ter no máximo 5000 caracteres.'; }

    return [$d, $e];
}

/** Alunos que o usuário pode observar. */
function observacao_alunos()
{
    $f = ch_filtro_turma('t');
    return ch_rows(
        'SELECT a.ID, a.NOME, a.MATRICULA, t.NOME AS TURMA, u.unidade AS UNIDADE
         FROM TB_ALUNOS a
         INNER JOIN TB_TURMAS t ON t.ID = a.ID_TURMA
         INNER JOIN TB_UNIDADES u ON u.id = t.ID_UNIDADE
         WHERE a.ATIVO = 1' . $f['sql'] . ' ORDER BY u.unidade, t.NOME, a.NOME',
        $f['params']
    );
}
