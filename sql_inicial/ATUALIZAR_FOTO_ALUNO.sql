/* Adiciona o armazenamento comprimido da foto diretamente no banco. */
USE [db_chamada];
GO

IF COL_LENGTH('dbo.TB_ALUNOS', 'FOTO_DADOS') IS NULL
BEGIN
    ALTER TABLE dbo.TB_ALUNOS ADD FOTO_DADOS varbinary(max) NULL;
    PRINT 'TB_ALUNOS.FOTO_DADOS adicionada.';
END
ELSE
    PRINT 'TB_ALUNOS.FOTO_DADOS já existe.';
GO

IF COL_LENGTH('dbo.TB_ALUNOS', 'FOTO_MIME') IS NULL
BEGIN
    ALTER TABLE dbo.TB_ALUNOS ADD FOTO_MIME nvarchar(30) NULL;
    PRINT 'TB_ALUNOS.FOTO_MIME adicionada.';
END
ELSE
    PRINT 'TB_ALUNOS.FOTO_MIME já existe.';
GO
