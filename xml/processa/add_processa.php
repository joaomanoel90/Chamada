<?php
require_once('../config.php');
require_once(DBAPI);

$conn = open_database();

function formatarCNPJ($cnpj)
{
    $cnpj = preg_replace('/[^0-9]/', '', $cnpj);
    if (strlen($cnpj) == 14) {
        return substr($cnpj, 0, 2) . '.' .
            substr($cnpj, 2, 3) . '.' .
            substr($cnpj, 5, 3) . '/' .
            substr($cnpj, 8, 4) . '-' .
            substr($cnpj, 12, 2);
    }
    return $cnpj;
}

// Consulta para obter contratos ativos
$sql_contrato = "SELECT * FROM VIEW_CONTRATO_CREDENCIAMENTO WHERE ATIVO = 1";
$stmt_contrato = sqlsrv_query($conn, $sql_contrato);

if ($stmt_contrato === false) {
    die(print_r(sqlsrv_errors(), true));
}

$contratos = [];
while ($row = sqlsrv_fetch_array($stmt_contrato, SQLSRV_FETCH_ASSOC)) {
    $contratos[] = $row;
}

sqlsrv_free_stmt($stmt_contrato);

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $id_contrato = $_POST['id_contrato'];
    $data_emissao = $_POST['data_emissao'];
    $cnpj_posto = $_POST['cnpj_posto'];
    $cnpj_posto_formatado = formatarCNPJ($cnpj_posto);
    $chave = $_POST['chave'];
    $id_setor = $_POST['id_setor'];
    $usuario = $_SESSION['usuarioId'];

    // Verifica se a chave já está cadastrada
    $sql_verifica_chave = "SELECT COUNT(*) AS total FROM TB_XML WHERE CHAVE = ?";
    $params_verifica = array($chave);
    $stmt_verifica = sqlsrv_query($conn, $sql_verifica_chave, $params_verifica);

    if ($stmt_verifica === false) {
        die(print_r(sqlsrv_errors(), true));
    }

    $row_verifica = sqlsrv_fetch_array($stmt_verifica, SQLSRV_FETCH_ASSOC);
    sqlsrv_free_stmt($stmt_verifica);

    if ($row_verifica['total'] > 0) {
        echo "<script>alert('Erro: Chave já cadastrada. Inserção cancelada.');</script>";
        echo "<script>window.location.href = 'index.php';</script>";
        exit;
    }

    // Obtém os produtos do POST
    $produtos_json = $_POST['produtos'];
    $produtos = json_decode($produtos_json, true);

    // Obtém o CNPJ do contrato selecionado e o combustível
    $cnpj_contrato = null;
    $combustivel_contrato = null;
    $id_posto = null;

    foreach ($contratos as $contrato) {
        if ($contrato['ID'] == $id_contrato) {
            $cnpj_contrato = $contrato['CNPJ']; // Obtém o CNPJ do contrato
            $combustivel_contrato = $contrato['COMBUSTIVEL'];
            $id_posto = $contrato['ID_POSTO']; // Obtém o ID_POSTO
            break;
        }
    }

    if (!$id_posto) {
        echo "<script>alert('Erro: ID do posto não encontrado para este contrato. Inserção cancelada.');</script>";
        echo "<script>window.location.href = 'index.php';</script>";
        exit;
    }

    if ($cnpj_contrato && $combustivel_contrato) {
        // Comparar CNPJ do emitente com o CNPJ do contrato
        if ($cnpj_posto_formatado !== formatarCNPJ($cnpj_contrato)) {
            echo "<script>alert('CNPJ do emitente não corresponde ao CNPJ do contrato. Inserção cancelada.');</script>";
            echo "<script>window.location.href = 'index.php';</script>";
            exit;
        }

        $primeiraPalavra = explode(' ', trim($combustivel_contrato))[0];

        // Armazena uma lista de produtos válidos
        $produtosValidos = [];

        // Verifica se a primeira palavra de cada produto está entre os produtos lidos
        foreach ($produtos as $produto) {
            $primeiraPalavraProduto = explode(' ', trim($produto['produto']))[0];

            if ($primeiraPalavraProduto === $primeiraPalavra) {
                $produtosValidos[] = $produto; // Adiciona o produto válido ao array
            }
        }

        if (!empty($produtosValidos)) {
            // Se existem produtos válidos, insere os dados
            foreach ($produtosValidos as $produto) {
                $sql_insert = "INSERT INTO TB_XML (ID_CONTRATO, ID_SETOR, ID_POSTO, COMBUSTIVEL, DATA_EMISSAO, CNPJ_POSTO, CHAVE, VALOR_LITRO, QUANTIDADE, CREATED, MODIFIED, USUARIO) 
                               VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, GETDATE(), GETDATE(), ?)";

                $params = array($id_contrato, $id_setor, $id_posto, $produto['produto'], $data_emissao, $cnpj_posto_formatado, $chave, $produto['valor_litro'], $produto['quantidade'], $usuario);

                $stmt_insert = sqlsrv_query($conn, $sql_insert, $params);

                if ($stmt_insert === false) {
                    die("Erro na inserção: " . print_r(sqlsrv_errors(), true));
                }
            }
            echo "<script>alert('Dados inseridos com sucesso.');</script>";
        } else {
            echo "<script>alert('Nenhum produto válido encontrado para inserção.');</script>";
        }
    } else {
        echo "<script>alert('Contrato não encontrado.');</script>";
    }

    // Redireciona após a inserção
    echo "<script>window.location.href = 'index.php';</script>";
    exit;
}

sqlsrv_close($conn);
?>