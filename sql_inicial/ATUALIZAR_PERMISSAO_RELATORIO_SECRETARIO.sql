/* Permite que o Diretor autorize leitura de relatórios por Secretário e unidade.
   A ausência de registro equivale a acesso negado. */
USE [db_chamada];
GO

IF OBJECT_ID(N'dbo.TB_USUARIOS', N'U') IS NULL
   OR OBJECT_ID(N'dbo.TB_UNIDADES', N'U') IS NULL
   OR OBJECT_ID(N'dbo.TB_ACESSO', N'U') IS NULL
BEGIN
    ;THROW 50002, 'Execute primeiro a instalação e as atualizações de usuários, unidades e acessos.', 1;
END;
GO

IF OBJECT_ID(N'dbo.TB_SECRETARIO_RELATORIOS', N'U') IS NULL
BEGIN
    CREATE TABLE dbo.TB_SECRETARIO_RELATORIOS (
        ID_USUARIO     int      NOT NULL,
        ID_UNIDADE     int      NOT NULL,
        PERMITIDO      bit      NOT NULL CONSTRAINT DF_SEC_REL_PERMITIDO DEFAULT (0),
        ID_DIRETOR     int      NULL,
        ATUALIZADO_EM  datetime NOT NULL CONSTRAINT DF_SEC_REL_ATUALIZADO DEFAULT (GETDATE()),
        CONSTRAINT PK_SECRETARIO_RELATORIO PRIMARY KEY CLUSTERED (ID_USUARIO, ID_UNIDADE),
        CONSTRAINT FK_SEC_REL_USUARIO FOREIGN KEY (ID_USUARIO) REFERENCES dbo.TB_USUARIOS (ID),
        CONSTRAINT FK_SEC_REL_UNIDADE FOREIGN KEY (ID_UNIDADE) REFERENCES dbo.TB_UNIDADES (id),
        CONSTRAINT FK_SEC_REL_DIRETOR FOREIGN KEY (ID_DIRETOR) REFERENCES dbo.TB_USUARIOS (ID)
    );
    PRINT 'TB_SECRETARIO_RELATORIOS criada.';
END;
GO

IF NOT EXISTS (SELECT 1 FROM sys.indexes
               WHERE object_id = OBJECT_ID('dbo.TB_SECRETARIO_RELATORIOS')
                 AND name = 'IX_SEC_REL_UNIDADE')
    CREATE INDEX IX_SEC_REL_UNIDADE
        ON dbo.TB_SECRETARIO_RELATORIOS (ID_UNIDADE, PERMITIDO);
GO

PRINT 'Autorização de leitura de relatórios pronta. Sem autorização explícita, o acesso permanece negado.';
GO
