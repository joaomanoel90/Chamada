<?php
require_once('../config.php');
require_once(DBAPI);

$conn = open_database();
$erro = ''; // Variável para armazenar a mensagem de erro
$produto = null; // Inicializa variável para uso no GET ou fallback no POST

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = strip_tags(trim($_POST['id'] ?? ''));
    $descricao = strip_tags(trim($_POST['descricao'] ?? ''));
    $categoria = strip_tags(trim($_POST['categoria'] ?? ''));
    $unidade = strip_tags(trim($_POST['unidade'] ?? ''));

    if (!$id || !$descricao || !$categoria || !$unidade) {
        $erro = "Todos os campos são obrigatórios.";
    } else {
        sqlsrv_begin_transaction($conn);

        try {
            // Verifica se o registro existe
            $sql_check = "SELECT * FROM TB_PRODUTOS WHERE ID = ?";
            $stmt_check = sqlsrv_query($conn, $sql_check, [$id]);

            if ($stmt_check === false) {
                throw new Exception("Erro ao verificar o produto existente.");
            }

            $produto_existente = sqlsrv_fetch_array($stmt_check, SQLSRV_FETCH_ASSOC);

            if (!$produto_existente) {
                throw new Exception("Produto não encontrado.");
            }

           
            // Atualiza os dados
            $sql_update = "UPDATE TB_PRODUTOS SET Descricao = ?, Unidade = ?, Categoria = ? WHERE ID = ?";
            $params_update = [$descricao, $unidade, $categoria, $id];
            $stmt_update = sqlsrv_query($conn, $sql_update, $params_update);

            if ($stmt_update === false) {
                throw new Exception("Erro ao atualizar o Produto.");
            }

            // Atualiza Almoxarifados
            $almoxarifados = $_POST['almoxarifados'] ?? array();
            
            // Remove antigos logicamente APENAS dos almoxarifados que o usuário tem permissão
            $sql_del_almox = "UPDATE pa SET pa.Ativo = 0 
                              FROM TB_PRODUTO_ALMOXARIFADO pa
                              INNER JOIN TB_ALMOXARIFADO_RESPONSAVEL ar ON pa.ID_Almoxarifado = ar.ID_Almoxarifado
                              WHERE pa.ID_Produto = ? AND ar.ID_Usuario = ? AND ar.Ativo = 1";
            sqlsrv_query($conn, $sql_del_almox, array($id, $_SESSION['usuarioId']));

            // Insere ou reativa novos
            if (!empty($almoxarifados)) {
                foreach ($almoxarifados as $id_almox) {
                    $sql_check = "SELECT ID FROM TB_PRODUTO_ALMOXARIFADO WHERE ID_Produto = ? AND ID_Almoxarifado = ?";
                    $stmt_check = sqlsrv_query($conn, $sql_check, array($id, $id_almox));
                    
                    if (sqlsrv_has_rows($stmt_check)) {
                        $row = sqlsrv_fetch_array($stmt_check, SQLSRV_FETCH_ASSOC);
                        $sql_upd = "UPDATE TB_PRODUTO_ALMOXARIFADO SET Ativo = 1 WHERE ID = ?";
                        sqlsrv_query($conn, $sql_upd, array($row['ID']));
                    } else {
                        $sql_almox = "INSERT INTO TB_PRODUTO_ALMOXARIFADO (ID_Produto, ID_Almoxarifado, Ativo) VALUES (?, ?, 1)";
                        sqlsrv_query($conn, $sql_almox, array($id, $id_almox));
                    }
                }
            }

            sqlsrv_commit($conn);
            header("Location: index.php?msg=sucesso");
            exit;

        } catch (Exception $e) {
            sqlsrv_rollback($conn);
            error_log($e->getMessage());
            $erro = "Erro: " . $e->getMessage();

            // Recarrega os dados do efetivo após erro
            $sql = "SELECT * FROM TB_PRODUTOS WHERE ID = ?";
            $stmt_select = sqlsrv_query($conn, $sql, [$id]);

            if ($stmt_select && ($dados = sqlsrv_fetch_array($stmt_select, SQLSRV_FETCH_ASSOC))) {
                $produto = $dados;
            } else {
                $produto = null;
            }
        }
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['id'])) {
    $id = $_GET['id'];

    $sql = "SELECT * FROM TB_PRODUTOS WHERE ID = ?";
    $stmt_select = sqlsrv_query($conn, $sql, [$id]);

    if ($stmt_select === false) {
        $erro = "Erro na consulta: " . print_r(sqlsrv_errors(), true);
    } else {
        $produto = sqlsrv_fetch_array($stmt_select, SQLSRV_FETCH_ASSOC);

        if (!$produto) {
            $erro = "Produto não encontrado.";
        }
    }
}
?>

