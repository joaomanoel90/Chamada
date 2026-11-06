<?php
require_once(dirname(__DIR__, 2) . '/config.php');
require_once(DBAPI);
require_once(ABSPATH . 'inc/chamada.php');
ch_db();
ch_require_admin();          // antes só o acesso.php checava, depois do header

$conn = open_database();

$id = (int)($_GET['id'] ?? 0);

function adjustIdentitySeed($conn) {

    $sql_max_id = 'SELECT MAX(id) AS max_id FROM TB_ACESSO';
    $stmt_max_id = sqlsrv_query($conn, $sql_max_id);

    if ($stmt_max_id === false) {
        die("Erro ao recuperar o maior ID: " . print_r(sqlsrv_errors(), true));
    }

    $row = sqlsrv_fetch_array($stmt_max_id, SQLSRV_FETCH_ASSOC);
    $max_id = $row['max_id'];

    if ($max_id === null) {
        $sql_checkident = "DBCC CHECKIDENT ('TB_ACESSO', RESEED, 1)";
    } else {
        $sql_checkident = "DBCC CHECKIDENT ('TB_ACESSO', RESEED, $max_id)";
    }

    $stmt_checkident = sqlsrv_query($conn, $sql_checkident);

    if ($stmt_checkident === false) {

        $errors = sqlsrv_errors();

        // 🔥 IGNORA WARNING 01000
        foreach ($errors as $error) {
            if ($error['SQLSTATE'] != '01000') {
                die("Erro ao executar DBCC CHECKIDENT: " . print_r($errors, true));
            }
        }
    }
}

if ($id) {

    // 🔹 USUÁRIO
    $sql_usuario = "SELECT * FROM TB_USUARIOS WHERE ID = ?";
    $stmt_usuario = sqlsrv_query($conn, $sql_usuario, array($id));
    $rows_usuario = sqlsrv_has_rows($stmt_usuario);

    // 🔹 UNIDADES
    $sql_unidades = "SELECT id, unidade FROM TB_UNIDADES";
    $stmt_unidades = sqlsrv_query($conn, $sql_unidades);

    // 🔹 ACESSOS
    $sql_acessos = "SELECT ID_ACESSO, NOME_UNIDADE 
                    FROM VIEW_ACESSOS_USUARIOS 
                    WHERE ID_USUARIO = ? AND ATIVO = 1";

    $stmt_acessos = sqlsrv_query($conn, $sql_acessos, array($id));
    $rows_acessos = sqlsrv_has_rows($stmt_acessos);

    // ============================
    // 🔹 ADICIONAR TODAS
    // ============================
    if (isset($_POST['add_all'])) {
        if (!ch_csrf_ok()) { header('Location: acesso.php?id=' . $id); exit; }

        $sql_insert_all = "
            INSERT INTO TB_ACESSO (id_usuario, id_unidade, ativo)
            SELECT ?, u.id, 1
            FROM TB_UNIDADES u
            WHERE NOT EXISTS (
                SELECT 1 FROM TB_ACESSO a
                WHERE a.id_usuario = ?
                AND a.id_unidade = u.id
                AND a.ativo = 1
            )
        ";

        $params = array($id, $id);
        sqlsrv_query($conn, $sql_insert_all, $params);

        // 🔧 AJUSTA IDENTITY (se necessário)
        adjustIdentitySeed($conn);

        header("Location: acesso.php?id=" . $id);
        exit;
    }

    // ============================
    // 🔹 ADICIONAR INDIVIDUAL
    // ============================
    if (!empty($_POST['unidade'])) {
        if (!ch_csrf_ok()) { header('Location: acesso.php?id=' . $id); exit; }

        $id_unidade = strip_tags(trim($_POST['unidade']));

        // Verifica duplicidade
        $sql_check = "SELECT 1 FROM TB_ACESSO 
                      WHERE id_usuario = ? 
                      AND id_unidade = ? 
                      AND ativo = 1";

        $params_check = array($id, $id_unidade);
        $stmt_check = sqlsrv_query($conn, $sql_check, $params_check);

        if (sqlsrv_has_rows($stmt_check)) {
            header("Location: acesso.php?id=$id&erro=duplicado");
            exit;
        }

        // Insere
        $sql_insert = "INSERT INTO TB_ACESSO (id_usuario, id_unidade, ativo)
                       VALUES (?, ?, 1)";

        $params_insert = array($id, $id_unidade);
        sqlsrv_query($conn, $sql_insert, $params_insert);

        // 🔧 AJUSTA IDENTITY (se necessário)
        adjustIdentitySeed($conn);

        header("Location: acesso.php?id=" . $id);
        exit;
    }
}
?>