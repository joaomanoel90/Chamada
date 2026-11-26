/* Log de auditoria de operações sensíveis — atualização idempotente.
   SQL Server 2016 ou mais novo. Executar no banco db_chamada.

   Depois de rodar este script, as alterações de permissões, AEE, feriados,
   unidades e usuários passam a registrar automaticamente quem executou e quando.
   ID_USUARIO tem FK para TB_USUARIOS: usuário é desativado (ATIVO = 0), nunca
   removido. Registros antigos sem usuário correspondente ficam com ID_USUARIO
   nulo para que a constraint possa ser criada sem perder o histórico. */
USE [db_chamada];
GO

IF OBJECT_ID(N'dbo.TB_AUDITORIA', N'U') IS NULL
BEGIN
    CREATE TABLE dbo.TB_AUDITORIA (
        ID            bigint IDENTITY(1,1) NOT NULL,
        CRIADO_EM     datetime       NOT NULL CONSTRAINT DF_AUDITORIA_CRIADO DEFAULT (GETDATE()),
        ID_USUARIO    int            NULL,
        USUARIO_NOME  nvarchar(150)  NULL,
        MODULO        varchar(40)    NOT NULL,
        ACAO          varchar(40)    NOT NULL,
        REGISTRO_TIPO varchar(40)    NOT NULL,
        REGISTRO_ID   varchar(60)    NULL,
        IP            varchar(45)    NULL,
        DETALHES      nvarchar(2000) NULL,
        CONSTRAINT PK_TB_AUDITORIA PRIMARY KEY CLUSTERED (ID),
        CONSTRAINT FK_AUDITORIA_USUARIO FOREIGN KEY (ID_USUARIO) REFERENCES dbo.TB_USUARIOS (ID)
    );
    PRINT '  + TB_AUDITORIA criada';
END
ELSE
    PRINT '  = TB_AUDITORIA já existe';
GO

/* Instalações que já tinham a tabela sem a FK recebem a constraint agora.
   A limpeza abaixo só desfaz o vínculo de registros cujo usuário não existe
   mais; o texto USUARIO_NOME é preservado para não apagar o histórico. */
IF OBJECT_ID(N'dbo.TB_AUDITORIA', N'U') IS NOT NULL
   AND NOT EXISTS (SELECT 1 FROM sys.foreign_keys WHERE name = 'FK_AUDITORIA_USUARIO')
BEGIN
    UPDATE a
       SET ID_USUARIO = NULL
      FROM dbo.TB_AUDITORIA a
     WHERE a.ID_USUARIO IS NOT NULL
       AND NOT EXISTS (SELECT 1 FROM dbo.TB_USUARIOS u WHERE u.ID = a.ID_USUARIO);

    ALTER TABLE dbo.TB_AUDITORIA
        ADD CONSTRAINT FK_AUDITORIA_USUARIO FOREIGN KEY (ID_USUARIO) REFERENCES dbo.TB_USUARIOS (ID);
    PRINT '  + FK_AUDITORIA_USUARIO criada';
END
GO

IF NOT EXISTS (
    SELECT 1 FROM sys.indexes
    WHERE name = 'IX_AUDITORIA_DATA' AND object_id = OBJECT_ID(N'dbo.TB_AUDITORIA')
)
    CREATE INDEX IX_AUDITORIA_DATA ON dbo.TB_AUDITORIA (CRIADO_EM DESC, ID DESC);
GO

IF NOT EXISTS (
    SELECT 1 FROM sys.indexes
    WHERE name = 'IX_AUDITORIA_MODULO' AND object_id = OBJECT_ID(N'dbo.TB_AUDITORIA')
)
    CREATE INDEX IX_AUDITORIA_MODULO ON dbo.TB_AUDITORIA (MODULO, REGISTRO_TIPO, REGISTRO_ID, ID DESC);
GO
