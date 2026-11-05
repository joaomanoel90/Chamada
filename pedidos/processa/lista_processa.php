<?php
require_once('../config.php');
require_once(DBAPI);
require_once('../inc/functions.php');


$conn = open_database();
if (!$conn) {
    die("Erro de conexão com o banco de dados.");
}

function adjustIdentitySeed($conn)
{
    $sql_max_id = 'SELECT MAX(id) AS max_id FROM TB_ITENS_PEDIDO';
    $stmt_max_id = sqlsrv_query($conn, $sql_max_id);

    if ($stmt_max_id === false) {
        die("Erro ao recuperar o maior ID: " . print_r(sqlsrv_errors(), true));
    }

    $max_id = sqlsrv_fetch_array($stmt_max_id, SQLSRV_FETCH_ASSOC)['max_id'];

    $sql_checkident = $max_id === null ? "DBCC CHECKIDENT ('TB_ITENS_PEDIDO', RESEED, 1)" : "DBCC CHECKIDENT ('TB_ITENS_PEDIDO', RESEED, $max_id)";

    $stmt_checkident = sqlsrv_query($conn, $sql_checkident);

    if ($stmt_checkident === false) {
        die("Erro ao executar DBCC CHECKIDENT: " . print_r(sqlsrv_errors(), true));
    }
}

// ----------------------------
// Produtos serão carregados depois de obter o pedido
// ----------------------------
$produtos = [];

// ----------------------------
// Inicializa variáveis
// ----------------------------
$pedido = null;
$erro = '';
$sucesso = false;
$itens_pedido = []; // para carregar os itens já inseridos

// ----------------------------
// Processamento POST – inserção
// ----------------------------

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id_pedido = isset($_POST['id_pedido']) ? intval($_POST['id_pedido']) : 0;
    $id_produto = isset($_POST['id_produto']) ? intval($_POST['id_produto']) : 0;
    $quantidade = isset($_POST['quantidade']) ? floatval($_POST['quantidade']) : 0;
    $ativo = 1;

    if ($id_pedido > 0 && $id_produto > 0 && $quantidade > 0) {
        // Verifica se o usuário tem acesso à unidade do pedido
        $sql_unidade = "SELECT ID_Unidade FROM VIEW_PEDIDOS_UNIDADES WHERE ID_Pedido = ?";
        $stmt_unidade = sqlsrv_query($conn, $sql_unidade, [$id_pedido]);

        if ($stmt_unidade && ($row_unidade = sqlsrv_fetch_array($stmt_unidade, SQLSRV_FETCH_ASSOC))) {
            $id_unidade = $row_unidade['ID_Unidade'];

            // 🔐 Verifica se o usuário tem acesso à unidade
            if (!usuario_tem_acesso_unidade($conn, $_SESSION['usuarioId'], $id_unidade)) {
                $erro = "Você não tem permissão para adicionar itens a este pedido.";
            } else {
                // Verifica se já existe o item
                $sql_verifica = "SELECT COUNT(*) AS total FROM TB_ITENS_PEDIDO WHERE ID_Pedido = ? AND ID_Produto = ? AND ATIVO = 1";
                $params_verifica = [$id_pedido, $id_produto];
                $stmt_verifica = sqlsrv_query($conn, $sql_verifica, $params_verifica);

                if ($stmt_verifica && ($row = sqlsrv_fetch_array($stmt_verifica, SQLSRV_FETCH_ASSOC))) {
                    if ($row['total'] > 0) {
                        $erro = "Este produto já foi adicionado ao pedido.";
                    } else {
                        try {
                            sqlsrv_begin_transaction($conn);

                            $sql = "INSERT INTO TB_ITENS_PEDIDO (ID_Pedido, ID_Produto, Quantidade, Ativo) VALUES (?, ?, ?, ?)";
                            $params = [$id_pedido, $id_produto, $quantidade, $ativo];

                            $stmt = sqlsrv_query($conn, $sql, $params);

                            if (!$stmt) {
                                throw new Exception("Erro ao inserir entrada: " . print_r(sqlsrv_errors(), true));
                            }

                            sqlsrv_commit($conn);  // Confirma a transação
                            $sucesso = true;
                            header("Location: lista.php?id=$id_pedido");
                            exit;  // Impede múltiplos redirects

                        } catch (Exception $e) {
                            sqlsrv_rollback($conn);  // Desfaz a transação
                            adjustIdentitySeed($conn);  // Ajusta o valor do IDENTITY caso ocorra erro
                            $erro = "Erro ao registrar entrada: " . $e->getMessage();
                        }
                    }
                } else {
                    $erro = "Erro ao verificar existência do produto no pedido.";
                }
            }
        } else {
            $erro = "Unidade do pedido não encontrada.";
        }
    } else {
        $erro = "Pedido, produto ou quantidade inválida.";
    }
}

// ----------------------------
// Processamento GET e também após POST para recarregar dados
// ----------------------------

if (isset($_GET['id']) || (isset($id_pedido) && $id_pedido > 0)) {
    $id_pedido = isset($_GET['id']) ? intval($_GET['id']) : $id_pedido;

    // Busca pedido
    $sql = "SELECT * FROM VIEW_PEDIDOS_UNIDADES WHERE ID_Pedido = ?";
    $stmt = sqlsrv_query($conn, $sql, [$id_pedido]);

    if ($stmt && ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC))) {
        // Verifica se o usuário tem acesso à unidade
        $id_unidade = $row['ID_Unidade'];

        // 🔐 Verifica se o usuário tem acesso à unidade do pedido
        if (!usuario_tem_acesso_unidade($conn, $_SESSION['usuarioId'], $id_unidade)) {
            $erro = "Você não tem permissão para abrir ou editar este pedido.";
        } elseif (strtolower($row['Status']) !== 'aberto') {
            $erro = "Este pedido não pode ser editado pois está com status: " . $row['Status'];
        } else {
            $pedido = $row;
            $sql_coluna_foto = "SELECT COL_LENGTH('TB_PRODUTOS', 'FotoBase64') AS tamanho";
            $stmt_coluna_foto = sqlsrv_query($conn, $sql_coluna_foto);
            $row_coluna_foto = $stmt_coluna_foto ? sqlsrv_fetch_array($stmt_coluna_foto, SQLSRV_FETCH_ASSOC) : null;
            $campo_foto_produto = !empty($row_coluna_foto['tamanho']) ? ", p.FotoBase64" : ", CAST(NULL AS VARCHAR(MAX)) AS FotoBase64";

            // Load products for this Almoxarifado
            $sql_prod = "SELECT p.ID as ID_Produto, p.Descricao, p.Unidade, p.Categoria $campo_foto_produto
                         FROM TB_PRODUTOS p 
                         INNER JOIN TB_PRODUTO_ALMOXARIFADO pa ON p.ID = pa.ID_Produto 
                         WHERE pa.ID_Almoxarifado = ? AND p.Ativo = 1 AND pa.Ativo = 1
                         ORDER BY p.Descricao";
            $stmt_prod = sqlsrv_query($conn, $sql_prod, [$pedido['ID_Almoxarifado']]);
            if ($stmt_prod) {
                while ($row_prod = sqlsrv_fetch_array($stmt_prod, SQLSRV_FETCH_ASSOC)) {
                    $produtos[] = $row_prod;
                }
            }
        }
    } else {
        $erro = "Pedido não encontrado.";
    }

    // Busca itens já inseridos para esse pedido
    $sql_itens = "SELECT * FROM VIEW_ITENS_PEDIDO_PRODUTO WHERE ID_Pedido = ? AND Ativo = 1";
    $stmt_itens = sqlsrv_query($conn, $sql_itens, [$id_pedido]);

    if ($stmt_itens) {
        while ($row_item = sqlsrv_fetch_array($stmt_itens, SQLSRV_FETCH_ASSOC)) {
            $itens_pedido[] = $row_item;
        }
    }
} else {
    $erro = "ID do pedido não informado.";
}
