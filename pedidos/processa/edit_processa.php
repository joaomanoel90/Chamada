<?php
require_once('../config.php');
require_once(DBAPI);
require_once('../inc/functions.php'); 

$conn = open_database();
$erro = ''; // Variável para armazenar a mensagem de erro
$pedido = null; // Inicializa variável para uso no GET ou fallback no POST

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = strip_tags(trim($_POST['id'] ?? ''));
    $objetivo = strip_tags(trim($_POST['objetivo'] ?? ''));
    $unidade = strip_tags(trim($_POST['unidade'] ?? ''));

    if (!$id || !$objetivo || !$unidade) {
        $erro = "Todos os campos são obrigatórios.";
    } else {
        sqlsrv_begin_transaction($conn);

        try {
            // Verifica se o registro existe
            $sql_check = "SELECT * FROM TB_PEDIDOS WHERE ID = ?";
            $stmt_check = sqlsrv_query($conn, $sql_check, [$id]);

            if ($stmt_check === false) {
                throw new Exception("Erro ao verificar o pedido existente.");
            }

            $pedido_existente = sqlsrv_fetch_array($stmt_check, SQLSRV_FETCH_ASSOC);

            if (!$pedido_existente) {
                throw new Exception("Pedido não encontrado.");
            }

            // 🔐 VERIFICA ACESSO
            if (!usuario_tem_acesso_unidade($conn, $_SESSION['usuarioId'], $pedido_existente['ID_Unidade'])) {
                throw new Exception("Você não tem permissão para editar este pedido.");
            }

            $almoxarifado = strip_tags(trim($_POST['almoxarifado'] ?? ''));

            // Verifica se o status permite edição
            if (strtolower($pedido_existente['Status']) !== 'aberto') {
                throw new Exception("Este pedido não pode ser editado. Status atual: " . $pedido_existente['Status']);
            }

            // Se mudou o almoxarifado, exclui todos os itens do pedido (cesta)
            if ($pedido_existente['ID_Almoxarifado'] != $almoxarifado) {
                $sql_delete_items = "DELETE FROM TB_ITENS_PEDIDO WHERE ID_Pedido = ?";
                sqlsrv_query($conn, $sql_delete_items, [$id]);
            }

            // Atualiza os dados
            $sql_update = "UPDATE TB_PEDIDOS SET Objetivo = ?, ID_Unidade = ?, ID_Almoxarifado = ? WHERE ID = ?";
            $params_update = [$objetivo, $unidade, $almoxarifado, $id];
            $stmt_update = sqlsrv_query($conn, $sql_update, $params_update);

            if ($stmt_update === false) {
                throw new Exception("Erro ao atualizar o pedido.");
            }

            sqlsrv_commit($conn);
            header("Location: index.php?msg=sucesso");
            exit;

        } catch (Exception $e) {
            sqlsrv_rollback($conn);
            error_log($e->getMessage());
            $erro = "Erro: " . $e->getMessage();

            // Recarrega os dados do pedido após erro
            $sql = "SELECT * FROM TB_PEDIDOS WHERE ID = ?";
            $stmt_select = sqlsrv_query($conn, $sql, [$id]);

            if ($stmt_select && ($dados = sqlsrv_fetch_array($stmt_select, SQLSRV_FETCH_ASSOC))) {
                $pedido = $dados;
            } else {
                $pedido = null;
            }
        }
    }


} elseif ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['id'])) {
    $id = $_GET['id'];

    $sql = "SELECT * FROM TB_PEDIDOS WHERE ID = ?";
    $stmt_select = sqlsrv_query($conn, $sql, [$id]);

    if ($stmt_select === false) {
        $erro = "Erro na consulta: " . print_r(sqlsrv_errors(), true);
    } else {
        $pedido = sqlsrv_fetch_array($stmt_select, SQLSRV_FETCH_ASSOC);

        if (!$pedido) {
            $erro = "Pedido não encontrado.";
        } 
        // 🔐 Verifica se o usuário tem acesso à unidade
        elseif (!usuario_tem_acesso_unidade($conn, $_SESSION['usuarioId'], $pedido['ID_Unidade'])) {
            $erro = "Você não tem permissão para editar este pedido.";
            $pedido = null;
        } 
        elseif (strtolower($pedido['Status']) !== 'aberto') {
            $erro = "Este pedido não pode ser editado pois está com status: " . $pedido['Status'];
        }
    }

    // Buscar Acessos do Usuário
    $sql_acessos = "SELECT ID_UNIDADE, NOME_UNIDADE FROM VIEW_ACESSOS_USUARIOS WHERE ID_USUARIO = ? AND ATIVO = 1";
    $stmt_acessos = sqlsrv_query($conn, $sql_acessos, array($_SESSION['usuarioId']));
    $rows_acessos = sqlsrv_has_rows($stmt_acessos);

    // Buscar Almoxarifados do Usuário
    $sql_almox = "SELECT a.* FROM TB_ALMOXARIFADOS a 
                  INNER JOIN TB_USUARIO_ALMOXARIFADO ua ON a.ID = ua.ID_Almoxarifado 
                  WHERE a.Ativo = 1 AND ua.ID_Usuario = ? AND ua.Ativo = 1";
    $stmt_almox = sqlsrv_query($conn, $sql_almox, array($_SESSION['usuarioId']));
}
//var_dump(usuario_tem_acesso_unidade($conn, $_SESSION['usuarioId'], $pedido['ID_Unidade']));
//exit;

?>
