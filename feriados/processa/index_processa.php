<?php
require_once(dirname(__DIR__, 2) . '/config.php');
require_once(DBAPI);
require_once(ABSPATH . 'inc/chamada.php');
ch_db();
$fAno = (int)($_GET['ano'] ?? (int)date('Y'));
$feriados = ch_rows(
    'SELECT f.ID, CONVERT(varchar(10), f.DATA, 23) AS DATA, f.DESCRICAO, f.TIPO,
            f.HORA_INICIO, f.HORA_FIM, f.ID_UNIDADE, u.unidade AS UNIDADE
     FROM TB_FERIADOS f LEFT JOIN TB_UNIDADES u ON u.id = f.ID_UNIDADE
     WHERE YEAR(f.DATA) = ? ORDER BY f.DATA',
    [$fAno]
);
$unidades = ch_unidades_opcoes();
