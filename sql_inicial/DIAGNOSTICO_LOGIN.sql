/* ============================================================================
   DIAGNÓSTICO DE LOGIN — erro 18456 "Falha de logon do usuário"
   ----------------------------------------------------------------------------
   SOMENTE LEITURA. Não altera nada no servidor.

   COMO USAR:
     1. Abra o SSMS conectado como ADMINISTRADOR
        (Autenticação do Windows com sua conta de admin, ou o login 'sa').
     2. Execute este script (F5).
     3. Leia os resultados na ordem em que aparecem.

   O erro 18456 mostrado ao PHP é genérico de propósito (não revela o motivo).
   O motivo real fica no ERRORLOG do SQL Server, como "State: N".
   A seção 1 abaixo busca isso.
   ============================================================================ */

SET NOCOUNT ON;
GO

/* ============================================================================
   1. O MOTIVO REAL — lê o ERRORLOG do SQL Server
   ============================================================================ */
PRINT '================================================================';
PRINT '1. OCORRÊNCIAS DE 18456 NO LOG DO SERVIDOR (procure por "State:")';
PRINT '================================================================';

IF OBJECT_ID('master.dbo.xp_readerrorlog') IS NOT NULL
BEGIN
    BEGIN TRY
        EXEC master.dbo.xp_readerrorlog 0, 1, N'18456';
    END TRY
    BEGIN CATCH
        PRINT 'Não foi possível ler o ERRORLOG (permissão). Veja a seção 7.';
    END CATCH
END
GO

/* ----------------------------------------------------------------------------
   Tabela de interpretação do State:
     State 2  -> login não existe (nome errado)
     State 5  -> login não existe  <-- MAIS COMUM quando só criaram o USER
     State 7  -> login desabilitado E senha errada
     State 8  -> senha incorreta  <-- MAIS COMUM quando DB_PASSWORD está vazio
     State 11 -> login válido, mas sem permissão de acesso ao servidor
     State 12 -> idem, para login do Windows
     State 18 -> a senha precisa ser trocada (MUST_CHANGE)
     State 38 -> o banco padrão/informado não existe ou está inacessível
     State 40 -> não conseguiu abrir o banco informado
     State 58 -> SQL Server está em modo "Somente Autenticação do Windows"
                 <-- MUITO COMUM em instalação padrão
   ---------------------------------------------------------------------------- */

/* ============================================================================
   2. MODO DE AUTENTICAÇÃO DO SERVIDOR
      Se IsIntegratedSecurityOnly = 1, NENHUM login SQL funciona.
   ============================================================================ */
PRINT '';
PRINT '================================================================';
PRINT '2. MODO DE AUTENTICAÇÃO';
PRINT '================================================================';

SELECT
    CAST(SERVERPROPERTY('IsIntegratedSecurityOnly') AS int) AS SomenteWindows,
    CASE CAST(SERVERPROPERTY('IsIntegratedSecurityOnly') AS int)
        WHEN 1 THEN 'PROBLEMA: somente Windows. Login SQL nao funciona. Mude para Modo Misto.'
        ELSE 'OK: Modo Misto (SQL e Windows). Login SQL e aceito.'
    END AS Diagnostico,
    CAST(SERVERPROPERTY('ServerName')     AS sysname) AS Servidor,
    CAST(SERVERPROPERTY('ProductVersion') AS nvarchar(50)) AS Versao,
    CAST(SERVERPROPERTY('Edition')        AS nvarchar(100)) AS Edicao;
GO

/* ============================================================================
   3. O LOGIN EXISTE NO SERVIDOR?
      USER de banco e LOGIN de servidor são coisas DIFERENTES.
      CREATE USER ... FOR LOGIN exige que o LOGIN já exista.
   ============================================================================ */
PRINT '';
PRINT '================================================================';
PRINT '3. LOGIN NO NIVEL DO SERVIDOR';
PRINT '================================================================';

IF NOT EXISTS (SELECT 1 FROM sys.server_principals WHERE name = N'user_chamada')
BEGIN
    PRINT 'PROBLEMA: o LOGIN [user_chamada] NAO EXISTE no servidor.';
    PRINT '          Execute CRIAR_LOGIN_APLICACAO.sql como administrador.';
END
ELSE
BEGIN
    SELECT
        p.name                  AS LoginName,
        p.type_desc             AS Tipo,
        p.is_disabled           AS Desabilitado,
        p.default_database_name AS BancoPadrao,
        p.create_date           AS Criado,
        p.modify_date           AS Alterado,
        CAST(LOGINPROPERTY(p.name, 'IsLocked')     AS int) AS Bloqueado,
        CAST(LOGINPROPERTY(p.name, 'IsExpired')    AS int) AS Expirado,
        CAST(LOGINPROPERTY(p.name, 'IsMustChange') AS int) AS PrecisaTrocarSenha,
        l.is_policy_checked     AS PoliticaSenha,
        l.is_expiration_checked AS ExpiracaoSenha
    FROM sys.server_principals p
    LEFT JOIN sys.sql_logins l ON l.principal_id = p.principal_id
    WHERE p.name = N'user_chamada';

    PRINT 'Se Desabilitado=1, Bloqueado=1, Expirado=1 ou PrecisaTrocarSenha=1,';
    PRINT 'o login nao consegue autenticar. O script de correção resolve.';
END
GO

/* ============================================================================
   4. O USUÁRIO EXISTE NO BANCO E ESTÁ VINCULADO AO LOGIN?
      Um USER criado sem LOGIN, ou com SID diferente, fica "órfão".
   ============================================================================ */
PRINT '';
PRINT '================================================================';
PRINT '4. USUARIO NO BANCO db_chamada';
PRINT '================================================================';

IF DB_ID(N'db_chamada') IS NULL
BEGIN
    PRINT 'PROBLEMA: o banco db_chamada nao existe. Rode INSTALACAO_COMPLETA.sql.';
END
ELSE
BEGIN
    EXEC (N'USE [db_chamada];
        SELECT
            dp.name        AS UsuarioNoBanco,
            dp.type_desc   AS Tipo,
            dp.sid         AS SidDoUsuario,
            sp.name        AS LoginVinculado,
            CASE WHEN sp.name IS NULL AND dp.type = ''S''
                 THEN ''PROBLEMA: usuario ORFAO (sem login correspondente)''
                 ELSE ''OK'' END AS Situacao
        FROM sys.database_principals dp
        LEFT JOIN sys.server_principals sp ON sp.sid = dp.sid
        WHERE dp.name = N''user_chamada'';');

    PRINT 'Se a consulta acima não retornou linha, o USER não existe no banco.';
END
GO

/* ============================================================================
   5. PERMISSÕES DO USUÁRIO NO BANCO
   ============================================================================ */
PRINT '';
PRINT '================================================================';
PRINT '5. PAPEIS (ROLES) DO USUARIO EM db_chamada';
PRINT '================================================================';

IF DB_ID(N'db_chamada') IS NOT NULL
BEGIN
    EXEC (N'USE [db_chamada];
        SELECT
            r.name AS Papel,
            m.name AS Membro
        FROM sys.database_role_members rm
        INNER JOIN sys.database_principals r ON r.principal_id = rm.role_principal_id
        INNER JOIN sys.database_principals m ON m.principal_id = rm.member_principal_id
        WHERE m.name = N''user_chamada'';');

    PRINT 'Esperado: db_datareader e db_datawriter.';
END
GO

/* ============================================================================
   6. RESUMO — Todos os logins SQL do servidor (para conferir o nome)
   ============================================================================ */
PRINT '';
PRINT '================================================================';
PRINT '6. LOGINS SQL EXISTENTES NO SERVIDOR';
PRINT '================================================================';

SELECT
    name AS Login,
    is_disabled AS Desabilitado,
    default_database_name AS BancoPadrao
FROM sys.sql_logins
ORDER BY name;
GO

/* ============================================================================
   7. SE NÃO CONSEGUIU LER O ERRORLOG
   ----------------------------------------------------------------------------
   Peça ao administrador do servidor para abrir o log:

     SSMS -> Management -> SQL Server Logs -> Current
     (ou o arquivo ERRORLOG em
      C:\Program Files\Microsoft SQL Server\MSSQLxx.<INSTANCIA>\MSSQL\Log\)

   Procure por "Error: 18456" e veja o "State:" na linha.

   Interpretação rápida:
     State 5  -> o LOGIN não existe      -> rode CRIAR_LOGIN_APLICACAO.sql
     State 8  -> senha errada            -> confira DB_PASSWORD no config.php
     State 58 -> modo somente Windows    -> mude para Modo Misto (abaixo)
     State 38 -> banco inacessível       -> confira DB_NAME e o mapeamento
     State 18 -> precisa trocar a senha  -> desative MUST_CHANGE

   ----------------------------------------------------------------------------
   COMO MUDAR PARA MODO MISTO (SQL Server e Windows):
     SSMS -> botão direito no servidor -> Properties -> Security
          -> Server authentication -> "SQL Server and Windows Authentication mode"
          -> OK
     ** É PRECISO REINICIAR O SERVIÇO DO SQL SERVER **
          SQL Server Configuration Manager -> SQL Server Services
          -> botão direito em SQL Server (INSTANCIA) -> Restart
   ============================================================================ */
PRINT '';
PRINT '================================================================';
PRINT 'Diagnóstico concluído. Leia as seções 1 a 5 acima.';
PRINT '================================================================';
GO
