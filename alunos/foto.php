<?php
require_once(dirname(__DIR__) . '/config.php');
require_once(DBAPI);
require_once(ABSPATH . 'inc/chamada.php');
ch_db();

$aluno = ch_exige_aluno((int)($_GET['id'] ?? 0));
$stmt = ch_q('SELECT FOTO_DADOS, FOTO_MIME FROM TB_ALUNOS WHERE ID = ?', [(int)$aluno['ID']]);
if ($stmt === false) {
    http_response_code(500);
    exit;
}
$foto = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
if (!$foto || !in_array($foto['FOTO_MIME'], ['image/jpeg'], true)) {
    sqlsrv_free_stmt($stmt);
    http_response_code(404);
    exit;
}

$dados = $foto['FOTO_DADOS'];
if (is_resource($dados)) {
    $dados = stream_get_contents($dados);
    if (is_resource($foto['FOTO_DADOS'])) {
        fclose($foto['FOTO_DADOS']);
    }
}
sqlsrv_free_stmt($stmt);
if (!is_string($dados) || $dados === '') {
    error_log('Chamada: foto inválida no banco para o aluno #' . (int)$aluno['ID'] . '.');
    http_response_code(404);
    exit;
}

header('Content-Type: image/jpeg');
header('Content-Length: ' . strlen($dados));
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, no-store');
echo $dados;
