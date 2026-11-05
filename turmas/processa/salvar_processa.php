<?php
require_once(dirname(__DIR__, 2) . '/config.php');
require_once(DBAPI);
require_once(ABSPATH . 'inc/chamada.php');
ch_db();
ch_require_gestor();

/** Valida os dados do formulário de turma. Devolve [dados, erros]. */
function turma_validar($post, $idAtual = 0)
{
    /* Equipe: vários professores. O regente é um deles.
       ID_PROFESSOR continua gravado como legado, sempre igual ao regente. */
    $professores = array_values(array_unique(array_filter(
        array_map('intval', (array)($post['professores'] ?? [])),
        function ($v) { return $v > 0; }
    )));
    $regente = (int)($post['regente'] ?? 0);

    /* Se a equipe foi preenchida e nenhum regente veio, assume o primeiro. */
    if ($professores && !in_array($regente, $professores, true)) {
        $regente = $professores[0];
    }
    if (!$professores) {
        $regente = 0;
    }

    $d = [
        'ID_UNIDADE'   => (int)($post['id_unidade'] ?? 0),
        'NOME'         => strip_tags(trim($post['nome'] ?? '')),
        'ANO_LETIVO'   => (int)($post['ano_letivo'] ?? 0),
        'ID_PERIODO'   => (int)($post['id_periodo'] ?? 0),
        'ID_PROFESSOR' => $regente,
        'CAPACIDADE'   => (int)($post['capacidade'] ?? 0),
        'PROFESSORES'  => $professores,
        'REGENTE'      => $regente,
    ];
    $e = [];

    if (!ch_val('SELECT id FROM TB_UNIDADES WHERE id = ?', [$d['ID_UNIDADE']])) {
        $e[] = 'Selecione uma unidade válida.';
    } elseif (!ch_pode_unidade($d['ID_UNIDADE'])) {
        $e[] = 'Você não tem acesso a essa unidade.';
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

    /* Todos os professores precisam existir e estar ativos. */
    if ($professores) {
        $ph = implode(',', array_fill(0, count($professores), '?'));
        $validos = ch_rows(
            "SELECT ID FROM TB_USUARIOS WHERE ID IN ({$ph}) AND ISNULL(ATIVO, 1) = 1",
            $professores
        );
        if (count($validos) !== count($professores)) {
            $e[] = 'Há professor(a) inválido ou inativo na equipe.';
        }
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

/** Grava a equipe da turma. Deve ser chamado DENTRO de uma transação.
 *  Quem sai da equipe é desativado (não apagado), preservando o histórico. */
function turma_equipe_salvar($idTurma, $professores, $regente)
{
    $idTurma = (int)$idTurma;
    $professores = array_values(array_unique(array_map('intval', (array)$professores)));

    /* Desativa quem não está mais na equipe. */
    $atuais = ch_rows('SELECT ID_USUARIO FROM TB_TURMA_PROFESSORES WHERE ID_TURMA = ? AND ATIVO = 1', [$idTurma]);
    foreach ($atuais as $a) {
        if (!in_array((int)$a['ID_USUARIO'], $professores, true)) {
            if (!ch_exec('UPDATE TB_TURMA_PROFESSORES SET ATIVO = 0 WHERE ID_TURMA = ? AND ID_USUARIO = ?',
                         [$idTurma, (int)$a['ID_USUARIO']])) {
                return false;
            }
        }
    }

    /* Primeiro zera o regente atual: o índice único filtrado só permite um
       regente ativo por turma, então não dá para promover o novo antes. */
    if ($professores) {
        if (!ch_exec("UPDATE TB_TURMA_PROFESSORES SET PAPEL = 'AUXILIAR' WHERE ID_TURMA = ? AND PAPEL = 'REGENTE'",
                     [$idTurma])) {
            return false;
        }
    }

    foreach ($professores as $idUsuario) {
        $papel = ($idUsuario === (int)$regente) ? 'REGENTE' : 'AUXILIAR';
        $existe = ch_val('SELECT ID FROM TB_TURMA_PROFESSORES WHERE ID_TURMA = ? AND ID_USUARIO = ?',
                         [$idTurma, $idUsuario]);
        if ($existe) {
            $ok = ch_exec('UPDATE TB_TURMA_PROFESSORES SET PAPEL = ?, ATIVO = 1 WHERE ID = ?',
                          [$papel, (int)$existe]);
        } else {
            $ok = ch_exec('INSERT INTO TB_TURMA_PROFESSORES (ID_TURMA, ID_USUARIO, PAPEL, ATIVO) VALUES (?, ?, ?, 1)',
                          [$idTurma, $idUsuario, $papel]);
        }
        if (!$ok) return false;
    }
    return true;
}

/** Equipe atual, para preencher o formulário de edição. */
function turma_equipe_atual($idTurma)
{
    $eq = ch_rows('SELECT ID_USUARIO, PAPEL FROM TB_TURMA_PROFESSORES WHERE ID_TURMA = ? AND ATIVO = 1',
                  [(int)$idTurma]);
    $professores = [];
    $regente = 0;
    foreach ($eq as $m) {
        $professores[] = (int)$m['ID_USUARIO'];
        if ($m['PAPEL'] === 'REGENTE') $regente = (int)$m['ID_USUARIO'];
    }
    return ['PROFESSORES' => $professores, 'REGENTE' => $regente];
}

/** Opções dos selects do formulário. */
function turma_opcoes()
{
    return [
        'unidades' => ch_unidades_opcoes(),
        'periodos' => ch_rows('SELECT ID, NOME, HORA_INICIO, HORA_FIM FROM TB_PERIODOS WHERE ATIVO = 1 ORDER BY HORA_INICIO'),
        'professores' => ch_rows('SELECT ID, NOME, NIVEL FROM TB_USUARIOS WHERE ISNULL(ATIVO, 1) = 1 ORDER BY NOME'),
    ];
}
