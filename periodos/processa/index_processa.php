<?php
require_once(dirname(__DIR__, 2) . '/config.php');
require_once(DBAPI);
require_once(ABSPATH . 'inc/chamada.php');
ch_db();
ch_require_gestor();

$periodos = ch_rows(
    'SELECT p.ID, p.NOME, p.HORA_INICIO, p.HORA_FIM, p.ATIVO,
            (SELECT COUNT(*) FROM TB_TURMAS t WHERE t.ID_PERIODO = p.ID AND t.ATIVO = 1) AS TURMAS
     FROM TB_PERIODOS p
     ORDER BY p.HORA_INICIO, p.NOME'
);
