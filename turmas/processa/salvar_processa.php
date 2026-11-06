<?php
require_once(dirname(__DIR__, 2) . '/config.php');
require_once(DBAPI);
require_once(ABSPATH . 'inc/chamada.php');
ch_db();
ch_require_gestor();

/** Valida os dados do formulário de turma. Devolve [dados, erros]. */
function turma_validar($post, $idAtual = 0)
{
    /* Equipe por turno: cada linha diz quem é, o papel e o turno que cobre.
       O substituto ainda traz prazo e a quem está cobrindo.
       ID_PROFESSOR continua gravado como legado: é o responsável que cobre o
       turno da própria turma (ver eq_regente_legado). */
    $equipe = eq_ler_post($post);

    $d = [
        'ID_UNIDADE'   => (int)($post['id_unidade'] ?? 0),
        'NOME'         => strip_tags(trim($post['nome'] ?? '')),
        'ANO_LETIVO'   => (int)($post['ano_letivo'] ?? 0),
        'ID_PERIODO'   => (int)($post['id_periodo'] ?? 0),
        'ID_PROFESSOR' => 0,
        'CAPACIDADE'   => (int)($post['capacidade'] ?? 0),
        'EQUIPE'       => $equipe,
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
    $periodo = ch_row('SELECT ID, TURNO FROM TB_PERIODOS WHERE ID = ? AND ATIVO = 1', [$d['ID_PERIODO']]);
    if (!$periodo) {
        $e[] = 'Selecione um período ativo.';
    }

    /* Quem está na equipe, com que papel, em que turno e por quanto tempo. */
    foreach (eq_validar_equipe($equipe) as $msg) {
        $e[] = $msg;
    }
    $d['ID_PROFESSOR'] = eq_regente_legado($equipe, $periodo ? $periodo['TURNO'] : 'INTEGRAL');

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

/**
 * Grava a equipe da turma. Deve ser chamado DENTRO de uma transação.
 * Quem sai da equipe é desativado (não apagado), preservando o histórico.
 *
 * A ordem importa: o índice único filtrado permite um responsável por
 * (turma, turno). Antes de promover os novos responsáveis é preciso rebaixar
 * os atuais, senão a troca de duas pessoas de turno bateria no índice no meio
 * do caminho. Por isso o passo 2 rebaixa todos de uma vez.
 */
function turma_equipe_salvar($idTurma, $membros)
{
    $idTurma = (int)$idTurma;
    $membros = array_values((array)$membros);
    $ids = array_map(function ($m) { return (int)$m['ID_USUARIO']; }, $membros);

    /* 1. Desativa quem não está mais na equipe. */
    $atuais = ch_rows('SELECT ID_USUARIO FROM TB_TURMA_PROFESSORES WHERE ID_TURMA = ? AND ATIVO = 1', [$idTurma]);
    foreach ($atuais ?: [] as $a) {
        if (!in_array((int)$a['ID_USUARIO'], $ids, true)) {
            if (!ch_exec('UPDATE TB_TURMA_PROFESSORES SET ATIVO = 0 WHERE ID_TURMA = ? AND ID_USUARIO = ?',
                         [$idTurma, (int)$a['ID_USUARIO']])) {
                return false;
            }
        }
    }

    /* 2. Libera os turnos: nenhum responsável ativo até o passo 3.
       Só REGENTE é rebaixado — mexer no papel de um SUBSTITUTO esbarraria na
       CHECK que exige prazo para quem é substituto. */
    if (!ch_exec("UPDATE TB_TURMA_PROFESSORES SET PAPEL = 'AUXILIAR'
                  WHERE ID_TURMA = ? AND PAPEL = 'REGENTE' AND ATIVO = 1", [$idTurma])) {
        return false;
    }

    /* 3. Grava cada um com papel, turno e, quando for o caso, o prazo. */
    foreach ($membros as $m) {
        $papel = $m['PAPEL'];
        $ehSub = $papel === 'SUBSTITUTO';
        $subst = $ehSub ? (int)$m['ID_SUBSTITUIDO'] : null;
        $ini   = $ehSub ? $m['DATA_INICIO'] : null;
        $fim   = $ehSub ? $m['DATA_FIM'] : null;
        $mot   = $ehSub ? $m['MOTIVO'] : null;

        $existe = ch_val('SELECT ID FROM TB_TURMA_PROFESSORES WHERE ID_TURMA = ? AND ID_USUARIO = ?',
                         [$idTurma, (int)$m['ID_USUARIO']]);
        if ($existe) {
            /* Os campos de substituição vão na MESMA sentença: deixar para
               depois violaria a CHECK, que exige prazo em substituto e proíbe
               prazo em quem não é. */
            $ok = ch_exec(
                'UPDATE TB_TURMA_PROFESSORES
                    SET PAPEL = ?, TURNO = ?, ID_SUBSTITUIDO = ?, DATA_INICIO = ?, DATA_FIM = ?,
                        MOTIVO = ?, ATIVO = 1
                  WHERE ID = ?',
                [$papel, $m['TURNO'], $subst, $ini, $fim, $mot, (int)$existe]
            );
        } else {
            $ok = ch_exec(
                'INSERT INTO TB_TURMA_PROFESSORES
                    (ID_TURMA, ID_USUARIO, PAPEL, TURNO, ID_SUBSTITUIDO, DATA_INICIO, DATA_FIM, MOTIVO, ATIVO)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1)',
                [$idTurma, (int)$m['ID_USUARIO'], $papel, $m['TURNO'], $subst, $ini, $fim, $mot]
            );
        }
        if (!$ok) return false;
    }
    return true;
}

/** Equipe atual, para preencher o formulário de edição. */
function turma_equipe_atual($idTurma)
{
    $equipe = [];
    foreach (eq_equipe($idTurma) as $m) {
        $equipe[] = [
            'ID_USUARIO'     => (int)$m['ID_USUARIO'],
            'PAPEL'          => $m['PAPEL'],
            'TURNO'          => $m['TURNO'],
            'ID_SUBSTITUIDO' => (int)($m['ID_SUBSTITUIDO'] ?? 0),
            'DATA_INICIO'    => (string)($m['DATA_INICIO'] ?? ''),
            'DATA_FIM'       => (string)($m['DATA_FIM'] ?? ''),
            'MOTIVO'         => (string)($m['MOTIVO'] ?? ''),
        ];
    }
    return ['EQUIPE' => $equipe];
}

/** Opções dos selects do formulário. */
function turma_opcoes()
{
    return [
        'unidades' => ch_unidades_opcoes(),
        'periodos' => ch_rows('SELECT ID, NOME, HORA_INICIO, HORA_FIM, TURNO FROM TB_PERIODOS WHERE ATIVO = 1 ORDER BY HORA_INICIO'),
        'professores' => eq_professores_opcoes(),
    ];
}