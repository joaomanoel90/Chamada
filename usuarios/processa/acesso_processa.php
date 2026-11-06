<?php
require_once(dirname(__DIR__, 2) . '/config.php');
require_once(DBAPI);
require_once(ABSPATH . 'inc/chamada.php');
ch_db();
ch_require_admin();          // antes só o acesso.php checava, depois do header

$conn = open_database();

$id = (int)($_GET['id'] ?? 0);

/* REMOVIDO: adjustIdentitySeed(), que rodava SELECT MAX(id) + DBCC CHECKIDENT
   RESEED depois de cada acesso gravado. Dois problemas: (1) DBCC CHECKIDENT
   exige permissão de ALTER na tabela, e o usuário da aplicação tem só
   db_datareader/db_datawriter — a chamada falhava e o código dava die(),
   quebrando a liberação de unidades; (2) resemear IDENTITY numa tabela em uso
   pode gerar chave duplicada com dois usuários gravando ao mesmo tempo.
   Coluna IDENTITY já se incrementa sozinha: não há nada para ajustar. */

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

        header("Location: acesso.php?id=" . $id);
        exit;
    }
}
?>