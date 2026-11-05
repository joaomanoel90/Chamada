<?php
require_once('../config.php');
require_once(DBAPI);

if (!isset($_SESSION['usuarioId'])) {
    header("Location: " . BASEURL . "login.php");
    exit;
}

$conn = open_database();
if (!$conn) {
    die("Erro de conexao com o banco de dados.");
}

$mes_filtro = $_GET['mes'] ?? '';
if ($mes_filtro !== '' && !preg_match('/^\d{4}-\d{2}$/', $mes_filtro)) {
    $mes_filtro = '';
}

$id_unidade = intval($_GET['id_unidade'] ?? 0);

$inicio_mes = null;
$fim_mes = null;
$periodo_label = 'Todos';
$ano_filtro = null;
$mes_numero_filtro = null;

if ($mes_filtro !== '') {
    $inicio_mes = DateTime::createFromFormat('Y-m-d H:i:s', $mes_filtro . '-01 00:00:00');
    if ($inicio_mes) {
        $fim_mes = clone $inicio_mes;
        $fim_mes->modify('+1 month');

        $periodo_label = $inicio_mes->format('m/Y');
        $ano_filtro = intval($inicio_mes->format('Y'));
        $mes_numero_filtro = intval($inicio_mes->format('m'));
    } else {
        $mes_filtro = '';
    }
}

$unidades = [];
$sql_unidades = "
    SELECT U.ID, U.unidade
    FROM TB_UNIDADES U
    WHERE EXISTS (
        SELECT 1
        FROM TB_ACESSO A
        WHERE A.id_usuario = ?
          AND A.id_unidade = U.ID
          AND A.ativo = 1
    )
    ORDER BY U.unidade
";
$stmt_unidades = sqlsrv_query($conn, $sql_unidades, [$_SESSION['usuarioId']]);
if ($stmt_unidades === false) {
    die("Erro ao buscar unidades: " . print_r(sqlsrv_errors(), true));
}

while ($row = sqlsrv_fetch_array($stmt_unidades, SQLSRV_FETCH_ASSOC)) {
    $unidades[] = $row;
}

$meses_disponiveis = [];
$sql_meses = "
    SELECT DISTINCT
        YEAR(DataReferencia) AS Ano,
        MONTH(DataReferencia) AS Mes
    FROM (
        SELECT COALESCE(MOV.UltimaSaida, PED.DataFinalizado, PED.DataCadastro) AS DataReferencia
        FROM TB_ITENS_PEDIDO IP
        INNER JOIN TB_PEDIDOS PED ON PED.ID = IP.ID_Pedido
        LEFT JOIN (
            SELECT
                ID_Pedido,
                ID_Produto,
                MAX(DataMovimento) AS UltimaSaida
            FROM TB_MOVIMENTO
            WHERE TipoMovimento = 'S'
            GROUP BY ID_Pedido, ID_Produto
        ) MOV ON MOV.ID_Pedido = IP.ID_Pedido
            AND MOV.ID_Produto = IP.ID_Produto
        WHERE IP.Ativo = 1
          AND IP.Liberado > 0
          AND EXISTS (
              SELECT 1
              FROM TB_ACESSO A
              WHERE A.id_usuario = ?
                AND A.id_unidade = PED.ID_Unidade
                AND A.ativo = 1
          )
          AND EXISTS (
              SELECT 1 FROM TB_ALMOXARIFADO_RESPONSAVEL AR
              WHERE AR.ID_Usuario = ?
                AND AR.ID_Almoxarifado = PED.ID_Almoxarifado
                AND AR.Ativo = 1
          )
    ) D
    WHERE DataReferencia IS NOT NULL
    ORDER BY Ano DESC, Mes DESC
";

$stmt_meses = sqlsrv_query($conn, $sql_meses, [$_SESSION['usuarioId'], $_SESSION['usuarioId']]);
if ($stmt_meses === false) {
    die("Erro ao buscar meses disponiveis: " . print_r(sqlsrv_errors(), true));
}

while ($row = sqlsrv_fetch_array($stmt_meses, SQLSRV_FETCH_ASSOC)) {
    $valor_mes = sprintf('%04d-%02d', $row['Ano'], $row['Mes']);
    $meses_disponiveis[] = [
        'valor' => $valor_mes,
        'label' => sprintf('%02d/%04d', $row['Mes'], $row['Ano'])
    ];
}

$consumos = [];
$params = [];

$sql_consumo = "
    SELECT
        U.ID AS ID_Unidade,
        U.unidade AS Unidade,
        P.ID AS ID_Produto,
        P.Descricao AS Produto,
        SUM(IP.Liberado) AS QuantidadeTotal,
        COUNT(DISTINCT PED.ID) AS TotalPedidos,
        MAX(COALESCE(MOV.UltimaSaida, PED.DataFinalizado, PED.DataCadastro)) AS UltimaSaida
    FROM TB_ITENS_PEDIDO IP
    INNER JOIN TB_PRODUTOS P ON P.ID = IP.ID_Produto
    INNER JOIN TB_PEDIDOS PED ON PED.ID = IP.ID_Pedido
    INNER JOIN TB_UNIDADES U ON U.ID = PED.ID_Unidade
    LEFT JOIN (
        SELECT
            ID_Pedido,
            ID_Produto,
            MAX(DataMovimento) AS UltimaSaida
        FROM TB_MOVIMENTO
        WHERE TipoMovimento = 'S'
        GROUP BY ID_Pedido, ID_Produto
    ) MOV ON MOV.ID_Pedido = IP.ID_Pedido
        AND MOV.ID_Produto = IP.ID_Produto
    WHERE IP.Ativo = 1
      AND IP.Liberado > 0
";

if ($mes_filtro !== '') {
    $sql_consumo .= "
      AND YEAR(COALESCE(MOV.UltimaSaida, PED.DataFinalizado, PED.DataCadastro)) = ?
      AND MONTH(COALESCE(MOV.UltimaSaida, PED.DataFinalizado, PED.DataCadastro)) = ?
    ";
    $params[] = $ano_filtro;
    $params[] = $mes_numero_filtro;
}

$sql_consumo .= "
      AND EXISTS (
          SELECT 1
          FROM TB_ACESSO A
          WHERE A.id_usuario = ?
            AND A.id_unidade = PED.ID_Unidade
            AND A.ativo = 1
      )
      AND EXISTS (
          SELECT 1 FROM TB_ALMOXARIFADO_RESPONSAVEL AR
          WHERE AR.ID_Usuario = ?
            AND AR.ID_Almoxarifado = PED.ID_Almoxarifado
            AND AR.Ativo = 1
      )
";

$params[] = $_SESSION['usuarioId'];
$params[] = $_SESSION['usuarioId'];

if ($id_unidade > 0) {
    $sql_consumo .= " AND PED.ID_Unidade = ?";
    $params[] = $id_unidade;
}

$sql_consumo .= "
    GROUP BY U.ID, U.unidade, P.ID, P.Descricao
    ORDER BY P.Descricao, U.unidade
";

$stmt_consumo = sqlsrv_query($conn, $sql_consumo, $params);
if ($stmt_consumo === false) {
    die("Erro ao buscar consumo: " . print_r(sqlsrv_errors(), true));
}

$total_geral = 0;
$produtos_unicos = [];
$unidades_unicas = [];
$produtos_consumo = [];

while ($row = sqlsrv_fetch_array($stmt_consumo, SQLSRV_FETCH_ASSOC)) {
    $row['QuantidadeTotal'] = floatval($row['QuantidadeTotal']);
    $row['TotalPedidos'] = intval($row['TotalPedidos']);

    $total_geral += $row['QuantidadeTotal'];
    $produtos_unicos[$row['ID_Produto']] = true;
    $unidades_unicas[$row['ID_Unidade']] = true;

    $consumos[] = $row;

    $id_produto = intval($row['ID_Produto']);
    if (!isset($produtos_consumo[$id_produto])) {
        $produtos_consumo[$id_produto] = [
            'ID_Produto' => $id_produto,
            'Produto' => $row['Produto'],
            'QuantidadeTotal' => 0,
            'TotalPedidos' => 0,
            'TotalUnidades' => 0,
            'UltimaSaida' => null,
            'unidades' => []
        ];
    }
    
    $produtos_consumo[$id_produto]['QuantidadeTotal'] += $row['QuantidadeTotal'];
    $produtos_consumo[$id_produto]['TotalPedidos'] += $row['TotalPedidos'];
    $produtos_consumo[$id_produto]['unidades'][] = $row;
    
    if ($produtos_consumo[$id_produto]['UltimaSaida'] === null || 
        ($row['UltimaSaida'] !== null && $row['UltimaSaida'] > $produtos_consumo[$id_produto]['UltimaSaida'])) {
        $produtos_consumo[$id_produto]['UltimaSaida'] = $row['UltimaSaida'];
    }
}

foreach ($produtos_consumo as &$pc) {
    $pc['TotalUnidades'] = count($pc['unidades']);
}
unset($pc);
usort($produtos_consumo, function($a, $b) {
    return strcmp($a['Produto'], $b['Produto']);
});

$total_produtos_distintos = count($produtos_unicos);
$total_unidades_distintas = count($unidades_unicas);

$total_produtos = count($produtos_unicos);
$total_unidades = count($unidades_unicas);
?>