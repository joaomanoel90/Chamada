<?php
require_once(dirname(__DIR__, 2) . '/config.php');
require_once(DBAPI);
require_once(ABSPATH . 'inc/chamada.php');
ch_db();
require_once(ABSPATH . 'inc/chamada_categorias.php');

function observacao_validar($post)
{
    $idAluno = (int)($post['aluno_id'] ?? 0);
    $d = [
        'ID_ALUNO'     => $idAluno,
        'DATA'         => ch_data_iso($post['data'] ?? '', ch_hoje()),
        'CATEGORIA'    => trim((string)($post['categoria'] ?? '')),
        'TEXTO'        => trim(strip_tags((string)($post['texto'] ?? ''))),
        'RESTRITA_AEE' => (!empty($post['restrita_aee']) && ch_pode_marcar_obs_restrita_aee($idAluno)) ? 1 : 0,
    ];
    $e = [];

    /* Escrita exige a turma ATUAL: a escola de origem, depois da
       transferência, fica só com a consulta ao histórico. */
    if (!ch_pode_escrever_observacao_aluno($d['ID_ALUNO'], $d['DATA'])) {
        $e[] = ch_secretario_escolar()
            ? 'O Diretor precisa autorizar o registro de observações nesta unidade.'
            : 'Selecione um aluno da sua unidade.';
    }
    if (!$d['DATA']) { $e[] = 'Informe uma data válida.'; }
    elseif ($d['DATA'] > ch_hoje()) { $e[] = 'A data da observação não pode ser futura.'; }
    if (!in_array($d['CATEGORIA'], ch_categorias(), true)) { $e[] = 'Selecione uma categoria válida.'; }
    if ($d['TEXTO'] === '') { $e[] = 'Escreva a observação.'; }
    elseif (mb_strlen($d['TEXTO']) > 5000) { $e[] = 'A observação deve ter no máximo 5000 caracteres.'; }

    return [$d, $e];
}

/**
 * Observação automática de restrição familiar. Avisa que é preciso gerar
 * outro crachá sem os dados do contato restrito. Chamada pela tela própria
 * de restrição (alunos/restricao.php), dentro da transação da ação. Só grava
 * se o usuário tiver autorização para escrever observações no aluno; a falha
 * aqui não aborta a restrição já salva.
 */
function observacao_inserir_restricao_familiar($idAluno, $texto)
{
    $idAluno = (int)$idAluno;
    $texto = trim(strip_tags((string)$texto));
    if ($idAluno <= 0 || $texto === '' || mb_strlen($texto) > 5000) return false;
    if (!ch_pode_escrever_observacao_aluno($idAluno, ch_hoje())) return false;

    return ch_exec(
        'INSERT INTO TB_OBSERVACOES (ID_ALUNO, DATA, CATEGORIA, TEXTO, ID_USUARIO, RESTRITA_AEE)
         VALUES (?, ?, ?, ?, ?, 0)',
        [$idAluno, ch_hoje(), 'Contato com a família', $texto, ch_uid()]
    );
}

/** Alunos que o usuário pode observar. */
function observacao_alunos()
{
    $f = ch_filtro_turma_ou_aee('t', 'a');
    if (ch_secretario_escolar()) {
        $ids = ch_unidades_observacoes_ids();
        if (!$ids) {
            $f['sql'] .= ' AND 1 = 0';
        } else {
            $f['sql'] .= ' AND t.ID_UNIDADE IN (' . implode(',', array_fill(0, count($ids), '?')) . ')';
            $f['params'] = array_merge($f['params'], $ids);
        }
    }
    return ch_rows(
        'SELECT a.ID, a.NOME, a.MATRICULA, t.NOME AS TURMA, u.unidade AS UNIDADE
         FROM TB_ALUNOS a
         INNER JOIN TB_TURMAS t ON t.ID = a.ID_TURMA
         INNER JOIN TB_UNIDADES u ON u.id = t.ID_UNIDADE
         WHERE a.ATIVO = 1' . $f['sql'] . ' ORDER BY u.unidade, t.NOME, a.NOME',
        $f['params']
    );
}
