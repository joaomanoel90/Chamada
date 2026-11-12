/*
   Atualiza o armazenamento de senhas e instala o limitador de login.
   Execute no SQL Server antes de publicar o novo código PHP.
   Pode ser executado novamente sem apagar dados existentes.
*/
SET NOCOUNT ON;
SET XACT_ABORT ON;
GO

USE [db_chamada];
GO

IF OBJECT_ID(N'dbo.TB_USUARIOS', N'U') IS NULL
    THROW 51000, 'A tabela dbo.TB_USUARIOS não existe. Execute a instalação do banco primeiro.', 1;
IF NOT EXISTS (
    SELECT 1
      FROM sys.columns
     WHERE object_id = OBJECT_ID(N'dbo.TB_USUARIOS')
       AND name = N'SENHA'
)
    THROW 51001, 'A coluna dbo.TB_USUARIOS.SENHA não existe.', 1;
GO

IF EXISTS (SELECT 1 FROM dbo.TB_USUARIOS WHERE SENHA IS NULL)
    THROW 51002, 'Há senhas nulas em TB_USUARIOS; corrija esses registros antes de migrar.', 1;
GO

IF EXISTS (
    SELECT 1
      FROM sys.columns
     WHERE object_id = OBJECT_ID(N'dbo.TB_USUARIOS')
       AND name = N'SENHA'
       AND (user_type_id <> TYPE_ID(N'nvarchar') OR max_length <> 510)
)
BEGIN
    ALTER TABLE dbo.TB_USUARIOS ALTER COLUMN SENHA nvarchar(255) NOT NULL;
    PRINT 'Coluna TB_USUARIOS.SENHA ampliada para nvarchar(255).';
END
ELSE
    PRINT 'Coluna TB_USUARIOS.SENHA já aceita hashes de até 255 caracteres.';
GO

IF OBJECT_ID(N'dbo.TB_LOGIN_TENTATIVAS', N'U') IS NULL
BEGIN
    CREATE TABLE dbo.TB_LOGIN_TENTATIVAS (
        CHAVE_HASH     char(64)    NOT NULL,
        FALHAS         tinyint     NOT NULL,
        INICIO_JANELA  datetime2(3) NOT NULL,
        BLOQUEADO_ATE  datetime2(3) NULL,
        ATUALIZADO_EM  datetime2(3) NOT NULL,
        CONSTRAINT PK_TB_LOGIN_TENTATIVAS
            PRIMARY KEY CLUSTERED (CHAVE_HASH),
        CONSTRAINT CK_TB_LOGIN_TENTATIVAS_FALHAS
            CHECK (FALHAS BETWEEN 1 AND 10)
    );
    PRINT 'Tabela TB_LOGIN_TENTATIVAS criada.';
END
ELSE
    PRINT 'Tabela TB_LOGIN_TENTATIVAS já existe.';
GO

IF NOT EXISTS (
    SELECT 1
      FROM sys.indexes
     WHERE object_id = OBJECT_ID(N'dbo.TB_LOGIN_TENTATIVAS')
       AND name = N'IX_TB_LOGIN_TENTATIVAS_ATUALIZADO'
)
    CREATE INDEX IX_TB_LOGIN_TENTATIVAS_ATUALIZADO
        ON dbo.TB_LOGIN_TENTATIVAS (ATUALIZADO_EM);
GO

PRINT 'Atualização de segurança do login concluída.';
