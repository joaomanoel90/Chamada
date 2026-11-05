<?php
require_once('../config.php');
require_once(DBAPI);

header('Content-Type: application/json');

if (!isset($_SESSION['usuarioId'])) {
    echo json_encode(['error' => 'Not logged in']);
    exit;
}

$conn = open_database();
$id_usuario = intval($_SESSION['usuarioId']);
$nivel_acesso = intval($_SESSION['usuarioNiveisAcessoId']);

$response = [
    'novos_finalizados' => [],
    'novos_analise' => []
];

// 1. Check for orders created by this user that just got Finalizado
// We can use a simple trick: store the highest ID we've already notified the user about in their session,
// or rely on the frontend sending the latest IDs it knows about.
// Better: the frontend can send `last_check` timestamp, and we query orders that changed since then.

$last_check = isset($_GET['last_check']) ? $_GET['last_check'] : null;

if ($last_check && strpos($last_check, 'T') === false) {
    $last_check = null; // force reset to avoid locale looping bug
}

if (!$last_check) {
    // First load, just return current server time so frontend can start polling
    $sql_time = "SELECT GETDATE() as server_time";
    $stmt = sqlsrv_query($conn, $sql_time);
    $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
    echo json_encode(['server_time' => $row['server_time']->format('Y-m-d\TH:i:s')]);
    exit;
}

// Convert last_check to SQL format safely
$params = [$last_check];

// --- 1. Pedidos do usuário que foram finalizados ---
$sql_fin = "
    SELECT ID 
    FROM TB_PEDIDOS 
    WHERE ID_Usuario = ? 
    AND Status = 'Finalizado' 
    AND DataFinalizado > CAST(? AS DATETIME)
";
$stmt_fin = sqlsrv_query($conn, $sql_fin, [$id_usuario, $last_check]);
if ($stmt_fin) {
    while ($row = sqlsrv_fetch_array($stmt_fin, SQLSRV_FETCH_ASSOC)) {
        $response['novos_finalizados'][] = $row['ID'];
    }
}

// --- 2. Novos pedidos para análise (se o usuário for analista) ---
if ($nivel_acesso == 1 || $nivel_acesso == 2) {
    $sql_ana = "
        SELECT p.ID 
        FROM TB_PEDIDOS p
        INNER JOIN TB_ALMOXARIFADO_RESPONSAVEL ar ON p.ID_Almoxarifado = ar.ID_Almoxarifado
        WHERE ar.ID_Usuario = ? 
        AND ar.Ativo = 1
        AND p.Status = 'Em Análise'
        AND p.DataEnvio > CAST(? AS DATETIME)
    ";
    $stmt_ana = sqlsrv_query($conn, $sql_ana, [$id_usuario, $last_check]);
    if ($stmt_ana) {
        while ($row = sqlsrv_fetch_array($stmt_ana, SQLSRV_FETCH_ASSOC)) {
            $response['novos_analise'][] = $row['ID'];
        }
    }
}

// Get current server time for the next check
$sql_time = "SELECT GETDATE() as server_time";
$stmt = sqlsrv_query($conn, $sql_time);
$row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
$response['server_time'] = $row['server_time']->format('Y-m-d\TH:i:s');

echo json_encode($response);
