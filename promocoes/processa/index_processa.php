<?php
require_once(dirname(__DIR__, 2) . '/config.php');
require_once(DBAPI);
require_once(ABSPATH . 'inc/chamada.php');
ch_db();
ch_require_gestor();

$filtro = ch_filtro_turma('t');
$filtroDestinos = ch_filtro_turma('td');
$turmas = ch_rows(
    'SELECT t.ID, t.NOME, t.SERIE, t.ANO_LETIVO, u.unidade AS UNIDADE,
            (SELECT COUNT(*) FROM TB_ALUNOS a WHERE a.ID_TURMA = t.ID AND a.ATIVO = 1) AS ALUNOS,
            (SELECT COUNT(*) FROM TB_TURMAS td
             WHERE td.ANO_LETIVO = t.ANO_LETIVO + 1
               AND td.ATIVO = 1 AND td.SERIE IS NOT NULL' . $filtroDestinos['sql'] . ') AS DESTINOS
     FROM TB_TURMAS t
     INNER JOIN TB_UNIDADES u ON u.id = t.ID_UNIDADE
     WHERE t.ATIVO = 1 AND t.ANO_LETIVO < 2100' . $filtro['sql'] . '
     ORDER BY t.ANO_LETIVO DESC, u.unidade, t.NOME',
    array_merge($filtroDestinos['params'], $filtro['params'])
);
