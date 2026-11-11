/* Adiciona CPF obrigatório nos formulários e unicidade no banco.
   Registros antigos permanecem com CPF NULL até a atualização cadastral. */
USE [db_chamada];
GO

IF COL_LENGTH('dbo.TB_ALUNOS', 'CPF') IS NULL
BEGIN
    ALTER TABLE dbo.TB_ALUNOS ADD CPF nvarchar(11) NULL;
    PRINT 'TB_ALUNOS.CPF adicionada; preencha os alunos existentes ao editar.';
END
ELSE
    PRINT 'TB_ALUNOS.CPF já existe.';
GO

IF NOT EXISTS (SELECT 1 FROM sys.check_constraints WHERE name = 'CK_ALUNOS_CPF')
BEGIN
    ALTER TABLE dbo.TB_ALUNOS ADD CONSTRAINT CK_ALUNOS_CPF
        CHECK (CPF IS NULL OR (LEN(CPF) = 11 AND CPF COLLATE Latin1_General_BIN2 NOT LIKE '%[^0-9]%'));
    PRINT 'CK_ALUNOS_CPF criada.';
END
GO

IF EXISTS (
    SELECT CPF FROM dbo.TB_ALUNOS
    WHERE CPF IS NOT NULL
    GROUP BY CPF HAVING COUNT(*) > 1
)
    THROW 50001, 'Há CPFs duplicados em TB_ALUNOS. Corrija-os antes de criar o índice único.', 1;
GO

IF NOT EXISTS (SELECT 1 FROM sys.indexes
               WHERE object_id = OBJECT_ID('dbo.TB_ALUNOS') AND name = 'UX_ALUNOS_CPF')
BEGIN
    CREATE UNIQUE INDEX UX_ALUNOS_CPF ON dbo.TB_ALUNOS (CPF) WHERE CPF IS NOT NULL;
    PRINT 'UX_ALUNOS_CPF criado.';
END
ELSE
    PRINT 'UX_ALUNOS_CPF já existe.';
GO
