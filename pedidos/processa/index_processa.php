<?php
require_once('../config.php');
require_once(DBAPI);


$conn = open_database();

$status_disponiveis = ['Aberto', 'Em Análise', 'Em Separação', 'Finalizado', 'Cancelado'];
$status_filtro = isset($_GET['status']) && in_array($_GET['status'], $status_disponiveis, true)
    ? $_GET['status']
    : '';

$sql_indice = "
    SELECT V.*
    FROM VIEW_PEDIDOS_UNIDADES V
    WHERE EXISTS (
        SELECT 1 
        FROM TB_ACESSO A
        WHERE 
            A.id_usuario = ?
            AND A.id_unidade = V.id_unidade
            AND A.ativo = 1
    )";
$params = [$_SESSION['usuarioId']];

if ($status_filtro !== '') {
    $sql_indice .= " AND V.Status = ?";
    $params[] = $status_filtro;
}

$sql_indice .= ";";
$stmt_indice = sqlsrv_query($conn, $sql_indice, $params);
$rows_indice = sqlsrv_has_rows($stmt_indice);

?>