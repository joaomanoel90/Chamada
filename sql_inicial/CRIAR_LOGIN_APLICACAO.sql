/* ============================================================================
   CRIAR / CORRIGIR O LOGIN DA APLICAÇÃO — resolve o erro 18456
   ----------------------------------------------------------------------------
   EXECUTE COMO ADMINISTRADOR no SSMS
   (Autenticação do Windows com conta admin, ou o login 'sa').

   Este script é IDEMPOTENTE e não apaga dados. Ele:
     - avisa se o servidor está em modo "Somente Windows"
     - cria o LOGIN no nível do SERVIDOR, se não existir
     - reabilita / desbloqueia o login, se estiver travado
     - cria o USER no banco db_chamada e corrige usuário órfão
     - concede db_datareader + db_datawriter + EXECUTE

   ----------------------------------------------------------------------------
   >>> EDITE A SENHA ABAIXO ANTES DE EXECUTAR <<<
   Depois, coloque EXATAMENTE a mesma senha em config.php:
        define('DB_PASSWORD', 'a senha que você definiu');
   ----------------------------------------------------------------------------

   CONCEITO IMPORTANTE:
     LOGIN  = nível do SERVIDOR   (sys.sql_logins)      -> autentica
     USER   = nível do BANCO      (sys.database_principals) -> autoriza
     São objetos diferentes. "CREATE USER ... FOR LOGIN" exige que o LOGIN
     já exista. Criar só o USER produz exatamente o erro 18456 State 5.
   ============================================================================ */

SET NOCOUNT ON;
SET XACT_ABORT ON;
GO

USE [master];
GO

/* ============================================================================
   1. O SERVIDOR ACEITA LOGIN SQL?
   ============================================================================ */
IF CAST(SERVERPROPERTY('IsIntegratedSecurityOnly') AS int) = 1
BEGIN
    PRINT '################################################################';
    PRINT '# ATENÇÃO: o servidor está em SOMENTE AUTENTICAÇÃO DO WINDOWS.  #';
    PRINT '# Nenhum login SQL vai funcionar até mudar para Modo Misto.     #';
    PRINT '#                                                               #';
    PRINT '# SSMS -> botão direito no servidor -> Properties -> Security    #';
    PRINT '#      -> "SQL Server and Windows Authentication mode" -> OK     #';
    PRINT '#                                                               #';
    PRINT '# DEPOIS REINICIE O SERVIÇO DO SQL SERVER:                      #';
    PRINT '# SQL Server Configuration Manager -> SQL Server Services        #';
    PRINT '#      -> SQL Server (INSTANCIA) -> Restart                      #';
    PRINT '#                                                               #';
    PRINT '# O script vai continuar e criar o login, mas ele só funciona    #';
    PRINT '# depois do Modo Misto + restart.                               #';
    PRINT '################################################################';
END
ELSE
    PRINT 'Modo Misto ativo: o servidor aceita login SQL.';
GO

/* ============================================================================
   2. CRIA OU CORRIGE O LOGIN NO SERVIDOR
   ============================================================================ */
USE [master];
GO

DECLARE @login  sysname       = N'user_chamada';
DECLARE @senha  nvarchar(128) = N'Chamada@2026#Angatuba';   -- <<< EDITE AQUI
DECLARE @banco  sysname       = N'db_chamada';

/* Se o login já existe e você quer TROCAR a senha dele, mude para 1.
   Deixe 0 para preservar a senha atual de um login existente. */
DECLARE @redefinirSenha bit = 0;

/* Conta de serviço: desligar a política evita bloqueio/expiração e o
   erro 18456 State 18 no futuro. É um trade-off de segurança — a senha
   passa a não expirar. Deixe 1 para contas de aplicação. */
DECLARE @desligarPolitica bit = 1;

DECLARE @sql nvarchar(max);
DECLARE @senhaEscapada nvarchar(256) = REPLACE(@senha, '''', '''''');

IF NOT EXISTS (SELECT 1 FROM sys.server_principals WHERE name = @login)
BEGIN
    SET @sql = N'CREATE LOGIN ' + QUOTENAME(@login)
             + N' WITH PASSWORD = N''' + @senhaEscapada + N''''
             + N', DEFAULT_DATABASE = ' + QUOTENAME(@banco)
             + N', CHECK_EXPIRATION = ' + CASE WHEN @desligarPolitica = 1 THEN N'OFF' ELSE N'ON' END
             + N', CHECK_POLICY = '     + CASE WHEN @desligarPolitica = 1 THEN N'OFF' ELSE N'ON' END
             + N';';
    EXEC (@sql);
    PRINT '  + LOGIN [' + @login + '] criado no servidor.';
END
ELSE
BEGIN
    PRINT '  = LOGIN [' + @login + '] já existia.';

    IF @redefinirSenha = 1
    BEGIN
        SET @sql = N'ALTER LOGIN ' + QUOTENAME(@login)
                 + N' WITH PASSWORD = N''' + @senhaEscapada + N''' UNLOCK;';
        EXEC (@sql);
        PRINT '    + senha redefinida e bloqueio removido.';
    END

    -- Reabilita se estiver desabilitado
    IF EXISTS (SELECT 1 FROM sys.server_principals WHERE name = @login AND is_disabled = 1)
    BEGIN
        SET @sql = N'ALTER LOGIN ' + QUOTENAME(@login) + N' ENABLE;';
        EXEC (@sql);
        PRINT '    + login reabilitado (estava desabilitado).';
    END

    -- Ajusta o banco padrão
    SET @sql = N'ALTER LOGIN ' + QUOTENAME(@login)
             + N' WITH DEFAULT_DATABASE = ' + QUOTENAME(@banco) + N';';
    EXEC (@sql);
    PRINT '    + banco padrão ajustado para ' + @banco + '.';

    -- Política de senha
    IF @desligarPolitica = 1
    BEGIN
        SET @sql = N'ALTER LOGIN ' + QUOTENAME(@login) + N' WITH CHECK_POLICY = OFF, CHECK_EXPIRATION = OFF;';
        EXEC (@sql);
        PRINT '    + política/expiração de senha desligadas (conta de serviço).';
    END
END
GO

/* ============================================================================
   3. CRIA O USER NO BANCO E CORRIGE USUÁRIO ÓRFÃO
   ============================================================================ */
IF DB_ID(N'db_chamada') IS NULL
BEGIN
    PRINT 'ERRO: o banco db_chamada não existe. Rode INSTALACAO_COMPLETA.sql primeiro.';
END
GO

USE [db_chamada];
GO

DECLARE @login sysname = N'user_chamada';
DECLARE @sql nvarchar(max);

IF NOT EXISTS (SELECT 1 FROM sys.database_principals WHERE name = @login)
BEGIN
    SET @sql = N'CREATE USER ' + QUOTENAME(@login) + N' FOR LOGIN ' + QUOTENAME(@login) + N';';
    EXEC (@sql);
    PRINT '  + USER [' + @login + '] criado em db_chamada.';
END
ELSE
BEGIN
    PRINT '  = USER [' + @login + '] já existia em db_chamada.';

    -- Usuário órfão: o SID do USER não bate com o SID do LOGIN.
    IF EXISTS (
        SELECT 1
        FROM sys.database_principals dp
        LEFT JOIN sys.server_principals sp ON sp.sid = dp.sid
        WHERE dp.name = @login AND dp.type = 'S' AND sp.name IS NULL
    )
    BEGIN
        SET @sql = N'ALTER USER ' + QUOTENAME(@login) + N' WITH LOGIN = ' + QUOTENAME(@login) + N';';
        EXEC (@sql);
        PRINT '    + usuário órfão revinculado ao LOGIN do servidor.';
    END
END
GO

/* ============================================================================
   4. PERMISSÕES MÍNIMAS
   ============================================================================ */
USE [db_chamada];
GO

ALTER ROLE db_datareader ADD MEMBER [user_chamada];
ALTER ROLE db_datawriter ADD MEMBER [user_chamada];
GRANT EXECUTE TO [user_chamada];
PRINT '  + permissões: db_datareader, db_datawriter, EXECUTE';
GO

/* ============================================================================
   5. VERIFICAÇÃO
   ============================================================================ */
PRINT '';
PRINT '================================================================';
PRINT 'VERIFICAÇÃO FINAL';
PRINT '================================================================';

SELECT
    sp.name                   AS LoginNoServidor,
    sp.is_disabled            AS Desabilitado,
    sp.default_database_name  AS BancoPadrao,
    CAST(LOGINPROPERTY(sp.name, 'IsLocked')     AS int) AS Bloqueado,
    CAST(LOGINPROPERTY(sp.name, 'IsExpired')    AS int) AS Expirado,
    CAST(LOGINPROPERTY(sp.name, 'IsMustChange') AS int) AS PrecisaTrocarSenha,
    CAST(SERVERPROPERTY('IsIntegratedSecurityOnly') AS int) AS SomenteWindows
FROM sys.server_principals sp
WHERE sp.name = N'user_chamada';

USE [db_chamada];
SELECT
    dp.name  AS UsuarioNoBanco,
    sp.name  AS LoginVinculado,
    CASE WHEN sp.name IS NULL THEN 'AINDA ÓRFÃO' ELSE 'OK' END AS Vinculo
FROM sys.database_principals dp
LEFT JOIN sys.server_principals sp ON sp.sid = dp.sid
WHERE dp.name = N'user_chamada';

SELECT r.name AS Papel
FROM sys.database_role_members rm
INNER JOIN sys.database_principals r ON r.principal_id = rm.role_principal_id
INNER JOIN sys.database_principals m ON m.principal_id = rm.member_principal_id
WHERE m.name = N'user_chamada';
GO

/* ============================================================================
   6. TESTE RÁPIDO
   ----------------------------------------------------------------------------
   No SSMS: File -> New -> Database Engine Query
     Authentication: SQL Server Authentication
     Login: user_chamada
     Password: a senha que você definiu no bloco 2

   Se conectar, rode:  SELECT COUNT(*) FROM db_chamada.dbo.TB_UNIDADES;
   Deve retornar 20 (ou o total de unidades cadastradas).

   ----------------------------------------------------------------------------
   7. AJUSTE O config.php DA APLICAÇÃO
   ----------------------------------------------------------------------------
        define('DB_NAME',     'db_chamada');
        define('DB_USER',     'user_chamada');
        define('DB_PASSWORD', 'Chamada@2026#Angatuba');  // a MESMA senha do bloco 2
        define('DB_HOST',     '127.0.0.1');
        define('DB_PORT',     '1433');

   ATENÇÃO ao DB_HOST:
     - Instância padrão:      127.0.0.1  (ou o nome do servidor)
     - Instância nomeada:     SERVIDOR\SQLEXPRESS
       Nesse caso deixe DB_PORT vazio ('') e use o nome da instância,
       ou descubra a porta da instância e informe só o host + porta.

   Depois de alterar o config.php, recarregue a página de login.
   ============================================================================ */
