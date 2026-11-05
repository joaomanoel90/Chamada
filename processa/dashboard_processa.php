<?php
require_once('config.php');
require_once(DBAPI);

$conn = open_database();

$abertos = 0;
$finalizados = 0;
$separacao = 0;
$total = 0;
$analise = 0;
$cancelados = 0;

$labelsMes = [];
$dadosMes = [];

$id_usuario = intval($_SESSION['usuarioId']);

// ======================================
// FILTRO POR ACESSO ÀS UNIDADES
// ======================================

$where = "
WHERE P.ID_Unidade IN (
    SELECT A.ID_Unidade
    FROM TB_ACESSO A
    WHERE A.ID_Usuario = ?
    AND A.Ativo = 1
)
";

$params = [$id_usuario];

// ======================================
// CARDS
// ======================================

$sql = "
SELECT
    COUNT(*) AS Total,

    SUM(CASE 
        WHEN P.Status = 'Em Análise'
        THEN 1 ELSE 0
    END) AS Analise,

    SUM(CASE 
        WHEN P.Status = 'Em Separação'
        THEN 1 ELSE 0
    END) AS Separacao,

    SUM(CASE 
        WHEN P.Status = 'Aberto'
        THEN 1 ELSE 0
    END) AS Aberto,

    SUM(CASE 
        WHEN P.Status = 'Finalizado'
        THEN 1 ELSE 0
    END) AS Finalizados,

    SUM(CASE 
        WHEN P.Status = 'Cancelado'
        THEN 1 ELSE 0
    END) AS Cancelados

FROM TB_PEDIDOS P
$where
";

$stmt = sqlsrv_query($conn, $sql, $params);

if ($stmt && ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC))) {

    $total = intval($row['Total']);
    $analise = intval($row['Analise']);
    $separacao = intval($row['Separacao']);
    $abertos = intval($row['Aberto']);
    $finalizados = intval($row['Finalizados']);
    $cancelados = intval($row['Cancelados']);
}

// ======================================
// GRÁFICO MENSAL
// ======================================

$sql_mes = "
SELECT 
    MONTH(P.DataCadastro) AS Mes,
    COUNT(*) AS Total

FROM TB_PEDIDOS P
$where

GROUP BY MONTH(P.DataCadastro)
ORDER BY Mes
";

$stmt_mes = sqlsrv_query($conn, $sql_mes, $params);

$meses = [
    1 => 'Jan',
    2 => 'Fev',
    3 => 'Mar',
    4 => 'Abr',
    5 => 'Mai',
    6 => 'Jun',
    7 => 'Jul',
    8 => 'Ago',
    9 => 'Set',
    10 => 'Out',
    11 => 'Nov',
    12 => 'Dez'
];

if ($stmt_mes) {

    while ($m = sqlsrv_fetch_array($stmt_mes, SQLSRV_FETCH_ASSOC)) {

        $labelsMes[] = $meses[intval($m['Mes'])];
        $dadosMes[] = intval($m['Total']);
    }
}


// ======================================
// GRÁFICO UNIDADES
// ======================================

$labelsUnidades = [];
$dadosUnidades = [];

if (
    $_SESSION['usuarioNiveisAcessoId'] == 1 ||
    $_SESSION['usuarioNiveisAcessoId'] == 2
) {

    $sql_unidades = "
    SELECT 
        U.Unidade,
        COUNT(P.ID) AS Total

    FROM TB_PEDIDOS P

    INNER JOIN TB_UNIDADES U
        ON U.ID = P.ID_Unidade

    WHERE P.ID_Unidade IN (
        SELECT A.ID_Unidade
        FROM TB_ACESSO A
        WHERE A.ID_Usuario = ?
        AND A.Ativo = 1
    )

    GROUP BY U.Unidade
    ORDER BY U.Unidade
    ";

    $stmt_unidades = sqlsrv_query($conn, $sql_unidades, [$id_usuario]);

    if ($stmt_unidades) {

        while ($u = sqlsrv_fetch_array($stmt_unidades, SQLSRV_FETCH_ASSOC)) {

            $labelsUnidades[] = $u['Unidade'];
            $dadosUnidades[] = intval($u['Total']);
        }
    }
}
