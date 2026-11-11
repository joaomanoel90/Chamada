<?php
if (!defined('ABSPATH')) {
    http_response_code(404);
    exit;
}

function aluno_foto_src($temFoto, $idAluno)
{
    if (!$temFoto || (int)$idAluno < 1) {
        return null;
    }
    return BASEURL . 'alunos/foto.php?id=' . (int)$idAluno;
}

function aluno_foto_comprimir_upload(&$erros)
{
    $arquivo = $_FILES['foto'] ?? null;
    if (!$arquivo || !isset($arquivo['error']) || (int)$arquivo['error'] === UPLOAD_ERR_NO_FILE) {
        return null;
    }

    if (in_array((int)$arquivo['error'], [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true)) {
        $erros[] = 'A foto excede o limite de tamanho aceito pelo servidor.';
        return null;
    }

    if ((int)$arquivo['error'] !== UPLOAD_ERR_OK || empty($arquivo['tmp_name']) || !is_uploaded_file($arquivo['tmp_name'])) {
        $erros[] = 'Não foi possível receber a foto. Tente enviar o arquivo novamente.';
        return null;
    }

    if ((int)$arquivo['size'] < 1 || (int)$arquivo['size'] > 5 * 1024 * 1024) {
        $erros[] = 'A foto deve ter no máximo 5 MB.';
        return null;
    }

    $bytes = file_get_contents($arquivo['tmp_name']);
    if ($bytes === false) {
        $erros[] = 'Não foi possível ler a foto enviada.';
        return null;
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    return aluno_foto_comprimir_dados($bytes, $finfo->buffer($bytes), $erros);
}

function aluno_foto_comprimir_dados($bytes, $mime, &$erros)
{
    if (!in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true)) {
        $erros[] = 'Envie uma imagem JPEG, PNG ou WebP válida, com até 20 megapixels.';
        return null;
    }

    if (!function_exists('imagecreatefromstring') || !function_exists('imagejpeg')) {
        error_log('Chamada: a extensão GD é necessária para comprimir fotos de alunos.');
        $erros[] = 'A compressão de fotos não está disponível. Ative a extensão GD no PHP.';
        return null;
    }

    $imagem = @getimagesizefromstring($bytes);
    if (!$imagem || $imagem['mime'] !== $mime || $imagem[0] < 1 || $imagem[1] < 1
        || $imagem[0] * $imagem[1] > 20000000) {
        $erros[] = 'Envie uma imagem JPEG, PNG ou WebP válida, com até 20 megapixels.';
        return null;
    }

    $origem = @imagecreatefromstring($bytes);
    if (!$origem) {
        $erros[] = 'Não foi possível decodificar a foto enviada.';
        return null;
    }

    $escala = min(1, 640 / imagesx($origem), 800 / imagesy($origem));
    $largura = max(1, (int)round(imagesx($origem) * $escala));
    $altura = max(1, (int)round(imagesy($origem) * $escala));
    $destino = imagecreatetruecolor($largura, $altura);
    if (!$destino) {
        $erros[] = 'Não foi possível preparar a foto para compressão.';
        return null;
    }

    $branco = imagecolorallocate($destino, 255, 255, 255);
    imagefill($destino, 0, 0, $branco);
    imagecopyresampled($destino, $origem, 0, 0, 0, 0, $largura, $altura, imagesx($origem), imagesy($origem));

    $dados = false;
    for ($qualidade = 82; $qualidade >= 54; $qualidade -= 7) {
        ob_start();
        $gerada = imagejpeg($destino, null, $qualidade);
        $comprimida = ob_get_clean();
        if ($gerada && is_string($comprimida)) {
            $dados = $comprimida;
        }
        if ($dados !== false && strlen($dados) <= 512 * 1024) {
            break;
        }
    }

    if ($dados === false || strlen($dados) > 512 * 1024) {
        $erros[] = 'Não foi possível comprimir a foto para o tamanho permitido.';
        return null;
    }

    return ['dados' => $dados, 'mime' => 'image/jpeg'];
}

function aluno_foto_parametro($dados)
{
    return [
        $dados,
        SQLSRV_PARAM_IN,
        SQLSRV_PHPTYPE_STRING(SQLSRV_ENC_BINARY),
        SQLSRV_SQLTYPE_VARBINARY('max'),
    ];
}
