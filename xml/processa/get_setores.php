<?php
require_once('../../config.php');
require_once(DBAPI);

$conn = open_database();
$id_contrato = $_GET['id_contrato'] ?? null;

if ($id_contrato) {
    $sql = "SELECT ID, SETOR FROM view_divisao WHERE ID_CONTRATO = ?";
    $params = array($id_contrato);
    $stmt = sqlsrv_query($conn, $sql, $params);

    if ($stmt === false) {
        die(json_encode(['error' => sqlsrv_errors()]));
    }

    $setores = [];
    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $setores[] = $row; // Agora inclui 'ID' e 'SETOR'
    }

    sqlsrv_free_stmt($stmt);
    echo json_encode($setores);
} else {
    echo json_encode([]);
}

sqlsrv_close($conn);
?>