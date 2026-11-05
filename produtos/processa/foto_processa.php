<?php
require_once('../config.php');
require_once(DBAPI);

$conn = open_database();
$erro = '';
$sucesso = '';
$produto = null;
$foto_atual_src = '';

function coluna_foto_produto_existe($conn)
{
    $sql = "SELECT COL_LENGTH('TB_PRODUTOS', 'FotoBase64') AS tamanho";
    $stmt = sqlsrv_query($conn, $sql);

    if (!$stmt) {
        return false;
    }

    $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
    return !empty($row['tamanho']);
}

function foto_para_src($foto)
{
    $foto = trim((string) $foto);

    if ($foto === '') {
        return '';
    }

    if (stripos($foto, 'data:image/') === 0) {
        return $foto;
    }

    return 'data:image/jpeg;base64,' . $foto;
}

function carregar_produto_foto($conn, $id, $tem_coluna_foto)
{
    $campo_foto = $tem_coluna_foto ? ', FotoBase64' : '';
    $sql = "SELECT ID, Descricao, Unidade, Categoria" . $campo_foto . " FROM TB_PRODUTOS WHERE ID = ?";
    $stmt = sqlsrv_query($conn, $sql, [$id]);

    if (!$stmt) {
        return null;
    }

    return sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
}

$id = isset($_GET['id']) ? intval($_GET['id']) : intval($_POST['id'] ?? 0);
$tem_coluna_foto = coluna_foto_produto_existe($conn);

if ($id <= 0) {
    $erro = "Produto nao informado.";
} else {
    $produto = carregar_produto_foto($conn, $id, $tem_coluna_foto);

    if (!$produto) {
        $erro = "Produto nao encontrado.";
    }
}

if ($produto && !$tem_coluna_foto) {
    $erro = "A coluna FotoBase64 ainda nao existe em TB_PRODUTOS. Execute o script sql_inicial/Outras Tabelas/ALTER_TB_PRODUTOS_FOTOBASE64.sql antes de salvar fotos.";
}

if ($produto && $tem_coluna_foto && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $acao = $_POST['acao'] ?? 'salvar';

    if ($acao === 'remover') {
        $stmt = sqlsrv_query($conn, "UPDATE TB_PRODUTOS SET FotoBase64 = NULL WHERE ID = ?", [$id]);

        if ($stmt) {
            $sucesso = "Foto removida com sucesso.";
            $produto = carregar_produto_foto($conn, $id, $tem_coluna_foto);
        } else {
            $erro = "Erro ao remover a foto.";
        }
    } else {
        if (empty($_FILES['foto']) || $_FILES['foto']['error'] !== UPLOAD_ERR_OK) {
            $erro = "Selecione uma imagem valida para salvar.";
        } elseif ($_FILES['foto']['size'] > 2 * 1024 * 1024) {
            $erro = "A imagem deve ter no maximo 2 MB.";
        } else {
            $tmp_name = $_FILES['foto']['tmp_name'];
            $info_imagem = @getimagesize($tmp_name);
            $mime = $info_imagem['mime'] ?? '';
            $mimes_permitidos = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];

            if (!in_array($mime, $mimes_permitidos, true)) {
                $erro = "Formato de imagem nao permitido.";
            } else {
                $conteudo = file_get_contents($tmp_name);
                $foto_base64 = 'data:' . $mime . ';base64,' . base64_encode($conteudo);

                $params = [
                    [$foto_base64, SQLSRV_PARAM_IN, SQLSRV_PHPTYPE_STRING('UTF-8'), SQLSRV_SQLTYPE_VARCHAR('max')],
                    $id
                ];
                $stmt = sqlsrv_query($conn, "UPDATE TB_PRODUTOS SET FotoBase64 = ? WHERE ID = ?", $params);

                if ($stmt) {
                    $sucesso = "Foto salva com sucesso.";
                    $produto = carregar_produto_foto($conn, $id, $tem_coluna_foto);
                } else {
                    $erro = "Erro ao salvar a foto.";
                }
            }
        }
    }
}

if ($produto && $tem_coluna_foto) {
    $foto_atual_src = foto_para_src($produto['FotoBase64'] ?? '');
}
?>
