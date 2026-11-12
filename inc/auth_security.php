<?php

function ch_auth_security_headers()
{
    if (headers_sent()) {
        return;
    }

    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header("Permissions-Policy: camera=(), microphone=(), geolocation=()");
    header("Content-Security-Policy: object-src 'none'; base-uri 'self'; frame-ancestors 'none'");

    $https = !empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off';
    if (!$https && (int)($_SERVER['SERVER_PORT'] ?? 0) === 443) {
        $https = true;
    }
    if ($https) {
        header('Strict-Transport-Security: max-age=31536000');
    }
}

function ch_auth_session_guard()
{
    if (empty($_SESSION['usuarioId'])) {
        return;
    }

    $agora = time();
    $ultimoAcesso = (int)($_SESSION['ch_ultima_atividade'] ?? 0);
    if ($ultimoAcesso > 0 && $agora - $ultimoAcesso > 1800) {
        $_SESSION = [];
        if (!session_regenerate_id(true)) {
            error_log('Segurança: não foi possível renovar o ID após expiração da sessão.');
        }
        $_SESSION['loginErro'] = 'Sua sessão expirou. Entre novamente.';
        header('Location: ' . BASEURL . 'login.php');
        exit;
    }

    $_SESSION['ch_ultima_atividade'] = $agora;
}

function ch_auth_password_hash($senha)
{
    if (mb_strlen($senha, 'UTF-8') < 12) {
        return false;
    }
    if (PASSWORD_DEFAULT === PASSWORD_BCRYPT && strlen($senha) > 72) {
        return false;
    }

    return password_hash($senha, PASSWORD_DEFAULT);
}

function ch_auth_password_verify($senha, $armazenada, &$precisaRehash)
{
    $precisaRehash = false;
    $armazenada = trim((string)$armazenada);

    if (preg_match('/\A[a-f0-9]{32}\z/i', $armazenada)) {
        if (!hash_equals(strtolower($armazenada), md5($senha))) {
            return false;
        }
        $precisaRehash = true;
        return true;
    }

    if (!password_verify($senha, $armazenada)) {
        return false;
    }

    $precisaRehash = password_needs_rehash($armazenada, PASSWORD_DEFAULT);
    return true;
}

function ch_auth_login_key($cpf)
{
    $ip = (string)($_SERVER['REMOTE_ADDR'] ?? '');
    if (!filter_var($ip, FILTER_VALIDATE_IP)) {
        $ip = 'unknown';
    }

    $identificador = preg_replace('/\D+/', '', (string)$cpf);
    if ($identificador === '') {
        $identificador = trim((string)$cpf);
        $identificador = function_exists('mb_strtolower')
            ? mb_strtolower($identificador, 'UTF-8')
            : strtolower($identificador);
    }

    $dados = $ip . "\0" . substr($identificador, 0, 64);
    $segredo = getenv('CHAMADA_AUTH_PEPPER');
    if (($segredo === false || $segredo === '') && defined('DB_PASSWORD')) {
        $segredo = (string)DB_PASSWORD;
    }

    return $segredo !== false && $segredo !== ''
        ? hash_hmac('sha256', $dados, $segredo)
        : hash('sha256', $dados);
}

function ch_auth_log_sql_error($contexto)
{
    $erros = function_exists('sqlsrv_errors') ? sqlsrv_errors() : null;
    error_log($contexto . ': ' . ($erros ? print_r($erros, true) : 'erro SQL sem detalhes.'));
}

function ch_auth_login($conn, $cpf, $senha, $chave)
{
    if (!sqlsrv_begin_transaction($conn)) {
        ch_auth_log_sql_error('Login: não foi possível iniciar transação');
        return ['status' => 'error'];
    }

    $stmt = sqlsrv_query(
        $conn,
        'SELECT CASE
                    WHEN INICIO_JANELA > DATEADD(MINUTE, -15, SYSUTCDATETIME()) THEN FALHAS
                    ELSE 0
                END AS FALHAS_ATUAIS,
                CASE WHEN BLOQUEADO_ATE > SYSUTCDATETIME() THEN 1 ELSE 0 END AS BLOQUEADO
           FROM dbo.TB_LOGIN_TENTATIVAS WITH (UPDLOCK, HOLDLOCK)
          WHERE CHAVE_HASH = ?',
        [$chave]
    );
    if ($stmt === false) {
        ch_auth_log_sql_error('Login: falha ao consultar o limitador');
        sqlsrv_rollback($conn);
        return ['status' => 'error'];
    }
    $limite = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
    sqlsrv_free_stmt($stmt);
    if ($limite === false) {
        ch_auth_log_sql_error('Login: falha ao ler o limitador');
        sqlsrv_rollback($conn);
        return ['status' => 'error'];
    }

    if ($limite && (int)$limite['BLOQUEADO'] === 1) {
        if (!sqlsrv_commit($conn)) {
            ch_auth_log_sql_error('Login: falha ao confirmar a consulta do limitador');
            sqlsrv_rollback($conn);
            return ['status' => 'error'];
        }
        return ['status' => 'blocked'];
    }

    $credenciaisDentroDoLimite = trim((string)$cpf) !== ''
        && strlen((string)$cpf) <= 64
        && $senha !== ''
        && strlen($senha) <= 1024;
    $usuario = null;
    if ($credenciaisDentroDoLimite) {
        $stmt = sqlsrv_query(
            $conn,
            'SELECT ID, NOME, SENHA, NIVEL, CPF, ATIVO
               FROM dbo.TB_USUARIOS WITH (UPDLOCK, HOLDLOCK)
              WHERE CPF = ?',
            [$cpf]
        );
        if ($stmt === false) {
            ch_auth_log_sql_error('Login: falha ao consultar usuário');
            sqlsrv_rollback($conn);
            return ['status' => 'error'];
        }
        $usuario = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
        sqlsrv_free_stmt($stmt);
        if ($usuario === false) {
            ch_auth_log_sql_error('Login: falha ao ler usuário');
            sqlsrv_rollback($conn);
            return ['status' => 'error'];
        }
    }

    $precisaRehash = false;
    $senhaValida = $usuario
        ? ch_auth_password_verify($senha, $usuario['SENHA'] ?? '', $precisaRehash)
        : false;
    $usuarioAtivo = $usuario && (int)($usuario['ATIVO'] ?? 1) === 1;
    $nivelValido = $usuario && in_array((int)$usuario['NIVEL'], [1, 2, 3, 4, 5], true);

    if ($senhaValida && $usuarioAtivo && $nivelValido) {
        if ($precisaRehash) {
            if (PASSWORD_DEFAULT === PASSWORD_BCRYPT && strlen($senha) > 72) {
                sqlsrv_rollback($conn);
                return ['status' => 'password_reset'];
            }
            $novoHash = password_hash($senha, PASSWORD_DEFAULT);
            if ($novoHash === false) {
                error_log('Login: não foi possível gerar o novo hash da senha.');
                sqlsrv_rollback($conn);
                return ['status' => 'error'];
            }

            $stmt = sqlsrv_query(
                $conn,
                'UPDATE dbo.TB_USUARIOS SET SENHA = ?, MODIFIED = GETDATE() WHERE ID = ? AND SENHA = ?',
                [$novoHash, $usuario['ID'], $usuario['SENHA']]
            );
            if ($stmt === false || sqlsrv_rows_affected($stmt) !== 1) {
                ch_auth_log_sql_error('Login: falha ao atualizar hash de senha');
                if ($stmt !== false) {
                    sqlsrv_free_stmt($stmt);
                }
                sqlsrv_rollback($conn);
                return ['status' => 'error'];
            }
            sqlsrv_free_stmt($stmt);
        }

        $stmt = sqlsrv_query(
            $conn,
            'DELETE FROM dbo.TB_LOGIN_TENTATIVAS WHERE CHAVE_HASH = ?',
            [$chave]
        );
        if ($stmt === false) {
            ch_auth_log_sql_error('Login: falha ao limpar tentativas após autenticação');
            sqlsrv_rollback($conn);
            return ['status' => 'error'];
        }
        sqlsrv_free_stmt($stmt);

        if (!sqlsrv_commit($conn)) {
            ch_auth_log_sql_error('Login: falha ao confirmar autenticação');
            sqlsrv_rollback($conn);
            return ['status' => 'error'];
        }

        unset($usuario['SENHA']);
        return ['status' => 'success', 'usuario' => $usuario];
    }

    $falhasAtuais = $limite ? (int)$limite['FALHAS_ATUAIS'] : 0;
    $proximaFalha = min(10, $falhasAtuais + 1);
    $bloqueiaAgora = $falhasAtuais >= 9;
    if (!$limite) {
        $stmt = sqlsrv_query(
            $conn,
            'INSERT INTO dbo.TB_LOGIN_TENTATIVAS
                 (CHAVE_HASH, FALHAS, INICIO_JANELA, BLOQUEADO_ATE, ATUALIZADO_EM)
             VALUES (?, 1, SYSUTCDATETIME(), NULL, SYSUTCDATETIME())',
            [$chave]
        );
    } elseif ($falhasAtuais === 0) {
        $stmt = sqlsrv_query(
            $conn,
            'UPDATE dbo.TB_LOGIN_TENTATIVAS
                SET FALHAS = 1,
                    INICIO_JANELA = SYSUTCDATETIME(),
                    BLOQUEADO_ATE = NULL,
                    ATUALIZADO_EM = SYSUTCDATETIME()
              WHERE CHAVE_HASH = ?',
            [$chave]
        );
    } else {
        $stmt = sqlsrv_query(
            $conn,
            'UPDATE dbo.TB_LOGIN_TENTATIVAS
                SET FALHAS = ?,
                    BLOQUEADO_ATE = CASE
                        WHEN ? >= 10 THEN DATEADD(MINUTE, 15, SYSUTCDATETIME())
                        ELSE BLOQUEADO_ATE
                    END,
                    ATUALIZADO_EM = SYSUTCDATETIME()
              WHERE CHAVE_HASH = ?',
            [$proximaFalha, $proximaFalha, $chave]
        );
    }
    if ($stmt === false) {
        ch_auth_log_sql_error('Login: falha ao registrar tentativa');
        sqlsrv_rollback($conn);
        return ['status' => 'error'];
    }
    sqlsrv_free_stmt($stmt);

    if (!sqlsrv_commit($conn)) {
        ch_auth_log_sql_error('Login: falha ao confirmar tentativa');
        sqlsrv_rollback($conn);
        return ['status' => 'error'];
    }

    return ['status' => $bloqueiaAgora ? 'blocked' : 'invalid'];
}

function ch_auth_login_cleanup($conn)
{
    $stmt = sqlsrv_query(
        $conn,
        'DELETE TOP (100) FROM dbo.TB_LOGIN_TENTATIVAS
          WHERE ATUALIZADO_EM < DATEADD(DAY, -1, SYSUTCDATETIME())'
    );
    if ($stmt === false) {
        ch_auth_log_sql_error('Login: falha ao remover tentativas expiradas');
        return;
    }
    sqlsrv_free_stmt($stmt);
}
