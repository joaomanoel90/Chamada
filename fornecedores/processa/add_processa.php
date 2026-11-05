<?php
require_once('../config.php');
require_once(DBAPI);

$conn = open_database();

function adjustIdentitySeed($conn) {
    $sql_max_id = 'SELECT MAX(id) AS max_id FROM TB_FORNECEDORES';
    $stmt_max_id = sqlsrv_query($conn, $sql_max_id);

    if ($stmt_max_id === false) {
        // Log técnico para admin/dev
        error_log("Erro ao recuperar o maior ID: " . print_r(sqlsrv_errors(), true));
        return false; // Não interrompe o usuário
    }

    $row = sqlsrv_fetch_array($stmt_max_id, SQLSRV_FETCH_ASSOC);
    $max_id = $row['max_id'] ?? null;

    $sql_checkident = $max_id === null 
        ? "DBCC CHECKIDENT ('TB_FORNECEDORES', RESEED, 0)" 
        : "DBCC CHECKIDENT ('TB_FORNECEDORES', RESEED, $max_id)";

    $stmt_checkident = sqlsrv_query($conn, $sql_checkident);

    if ($stmt_checkident === false) {
        error_log("Erro ao executar DBCC CHECKIDENT: " . print_r(sqlsrv_errors(), true));
        return false;
    }

    return true;
}

// Função simples de validação de CNPJ
function validarCNPJ($cnpj) {
    $cnpj = preg_replace('/[^0-9]/', '', $cnpj);
    return strlen($cnpj) === 14;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fornecedor = strip_tags(trim($_POST['fornecedor'] ?? ''));
    $cnpj = strip_tags(trim($_POST['cnpj'] ?? ''));

    $erros = [];

    // Validações
    if (empty($fornecedor)) $erros[] = "O campo fornecedor é obrigatório.";
    if (empty($cnpj)) $erros[] = "O campo CNPJ é obrigatório.";
    elseif (!validarCNPJ($cnpj)) $erros[] = "CNPJ inválido. Insira 14 dígitos.";

    if (!empty($erros)) {
        // Mostra erros amigáveis
        foreach ($erros as $erro) {
            echo "<p style='color:red;'>$erro</p>";
        }
        exit();
    }

    sqlsrv_begin_transaction($conn);

    try {
        $sql = 'INSERT INTO TB_FORNECEDORES (Fornecedor, CNPJ) VALUES (?, ?)';
        $params = [$fornecedor, $cnpj];

        $stmt = sqlsrv_query($conn, $sql, $params);

        if ($stmt === false) {
            throw new Exception("Não foi possível salvar o fornecedor no momento. Tente novamente mais tarde.");
        }

        sqlsrv_commit($conn);
        header("Location: index.php?success=1");
        exit();
    } catch (Exception $e) {
        sqlsrv_rollback($conn);
        adjustIdentitySeed($conn); // corrige o IDENTITY se necessário
        echo "<p style='color:red;'>Erro ao salvar fornecedor: " . htmlspecialchars($e->getMessage()) . "</p>";
    }
}
?>