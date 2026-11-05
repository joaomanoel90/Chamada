<?php

// Função para verificar se um usuário tem acesso a uma unidade
function usuario_tem_acesso_unidade($conn, $usuarioId, $idUnidade) {
    $sql = "SELECT 1 
            FROM VIEW_ACESSOS_USUARIOS 
            WHERE ID_USUARIO = ? AND ID_UNIDADE = ? AND ATIVO = 1";

    $params = [$usuarioId, $idUnidade];
    $stmt = sqlsrv_query($conn, $sql, $params);

    // Verifica se houve erro na query
    if ($stmt === false) {
        error_log("Erro ao verificar acesso à unidade: " . print_r(sqlsrv_errors(), true));
        return false;
    }

    return sqlsrv_has_rows($stmt);
}
