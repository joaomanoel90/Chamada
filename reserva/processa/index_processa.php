<?php
require_once('../config.php');
require_once(DBAPI);

if (!isset($_SESSION['usuarioId'])) {
    header("Location: " . BASEURL . "login.php");
    exit;
}

$conn = open_database();

if (!$conn) {
    die("Erro de conexao com banco.");
}

$produtos = [];

$sql = "
SELECT

    P.ID,
    P.Descricao AS Produto,

    ISNULL(E.SaldoAtual, 0) AS EstoqueAtual,

    ISNULL(R.Reservado, 0) AS Reservado,

    ISNULL(E.SaldoAtual, 0) - ISNULL(R.Reservado, 0) AS Disponivel

FROM TB_PRODUTOS P

LEFT JOIN (SELECT ID_Produto, SUM(EstoqueAtual) AS SaldoAtual FROM VIEW_ESTOQUE_ALMOX EA INNER JOIN TB_ALMOXARIFADO_RESPONSAVEL AR ON AR.ID_Almoxarifado = EA.ID_Almoxarifado WHERE AR.ID_Usuario = ? AND AR.Ativo = 1 GROUP BY ID_Produto) E
    ON E.ID_Produto = P.ID

LEFT JOIN (

    SELECT

        IP.ID_Produto,

        SUM(
            ISNULL(IP.Quantidade,0) - ISNULL(IP.Liberado,0)
        ) AS Reservado

    FROM TB_ITENS_PEDIDO IP

    INNER JOIN TB_PEDIDOS PED
        ON PED.ID = IP.ID_Pedido

    WHERE
        IP.Ativo = 1 AND PED.Ativo = 1 AND PED.Status IN ('Aberto', 'Em Separação', 'Em separaçao', 'Em Análise', 'Em separacao') AND EXISTS (SELECT 1 FROM TB_ALMOXARIFADO_RESPONSAVEL AR WHERE AR.ID_Usuario = ? AND AR.ID_Almoxarifado = PED.ID_Almoxarifado AND AR.Ativo = 1)

    GROUP BY IP.ID_Produto

) R ON R.ID_Produto = P.ID

WHERE
    P.Ativo = 1 AND EXISTS (SELECT 1 FROM TB_PRODUTO_ALMOXARIFADO PA INNER JOIN TB_ALMOXARIFADO_RESPONSAVEL AR ON AR.ID_Almoxarifado = PA.ID_Almoxarifado WHERE PA.ID_Produto = P.ID AND AR.ID_Usuario = ? AND AR.Ativo = 1 AND PA.Ativo = 1)

ORDER BY

    CASE 
        WHEN ISNULL(R.Reservado, 0) > 0 THEN 0
        ELSE 1
    END,

    Produto ASC
";

$stmt = sqlsrv_query($conn, $sql, [$_SESSION['usuarioId'], $_SESSION['usuarioId'], $_SESSION['usuarioId']]);

if ($stmt === false) {
    die(print_r(sqlsrv_errors(), true));
}

while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {

    $row['EstoqueAtual'] = floatval($row['EstoqueAtual']);
    $row['Reservado'] = floatval($row['Reservado']);
    $row['Disponivel'] = floatval($row['Disponivel']);

    $produtos[] = $row;
}

$totalProdutos = count($produtos);
$totalFalta = 0;
$saldoTotal = 0;

foreach ($produtos as $p) {

    if ($p['Disponivel'] < 0) {
        $totalFalta++;
    }

    $saldoTotal += $p['Disponivel'];
}

$sqlPedidos = "
SELECT COUNT(*) AS Total
FROM TB_PEDIDOS
WHERE Ativo = 1 AND Status IN ('Aberto', 'Em Separação', 'Em separaçao', 'Em Análise', 'Em separacao') AND EXISTS (SELECT 1 FROM TB_ALMOXARIFADO_RESPONSAVEL AR WHERE AR.ID_Usuario = ? AND AR.ID_Almoxarifado = TB_PEDIDOS.ID_Almoxarifado AND AR.Ativo = 1)
";

$stmtPedidos = sqlsrv_query($conn, $sqlPedidos, [$_SESSION['usuarioId']]);

$pedidosAbertos = 0;
if ($stmtPedidos && $r = sqlsrv_fetch_array($stmtPedidos, SQLSRV_FETCH_ASSOC)) {
    $pedidosAbertos = $r['Total'];
}
