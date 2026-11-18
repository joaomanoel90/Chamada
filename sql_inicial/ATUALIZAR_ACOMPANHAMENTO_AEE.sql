/* ============================================================================
   ATUALIZAR_ACOMPANHAMENTO_AEE.sql
   ----------------------------------------------------------------------------
   Adiciona na ficha interna do aluno os campos de Educação Especial / condição
   específica (ex.: TEA), Atendimento Educacional Especializado (AEE) — com
   vínculo opcional a um professor do sistema — e outros acompanhamentos.

   Script idempotente: pode ser executado mais de uma vez sem erro.
   ============================================================================ */
SET NOCOUNT ON;
GO

IF COL_LENGTH('dbo.TB_ALUNOS', 'CONDICAO_ESPECIFICA') IS NULL
BEGIN
    ALTER TABLE dbo.TB_ALUNOS ADD CONDICAO_ESPECIFICA nvarchar(1000) NULL;
    PRINT '  + TB_ALUNOS.CONDICAO_ESPECIFICA adicionada';
END
ELSE
    PRINT '  = TB_ALUNOS.CONDICAO_ESPECIFICA já existe';
GO

IF COL_LENGTH('dbo.TB_ALUNOS', 'OUTROS_ACOMPANHAMENTOS') IS NULL
BEGIN
    ALTER TABLE dbo.TB_ALUNOS ADD OUTROS_ACOMPANHAMENTOS nvarchar(1000) NULL;
    PRINT '  + TB_ALUNOS.OUTROS_ACOMPANHAMENTOS adicionada';
END
ELSE
    PRINT '  = TB_ALUNOS.OUTROS_ACOMPANHAMENTOS já existe';
GO

IF COL_LENGTH('dbo.TB_ALUNOS', 'PASSA_AEE') IS NULL
BEGIN
    ALTER TABLE dbo.TB_ALUNOS ADD PASSA_AEE bit NOT NULL
        CONSTRAINT DF_ALUNOS_PASSA_AEE DEFAULT (0);
    PRINT '  + TB_ALUNOS.PASSA_AEE adicionada';
END
ELSE
    PRINT '  = TB_ALUNOS.PASSA_AEE já existe';
GO

IF COL_LENGTH('dbo.TB_ALUNOS', 'ID_PROFESSOR_AEE') IS NULL
BEGIN
    ALTER TABLE dbo.TB_ALUNOS ADD ID_PROFESSOR_AEE int NULL;
    PRINT '  + TB_ALUNOS.ID_PROFESSOR_AEE adicionada';
END
ELSE
    PRINT '  = TB_ALUNOS.ID_PROFESSOR_AEE já existe';
GO

IF COL_LENGTH('dbo.TB_ALUNOS', 'AEE_OBSERVACOES') IS NULL
BEGIN
    ALTER TABLE dbo.TB_ALUNOS ADD AEE_OBSERVACOES nvarchar(500) NULL;
    PRINT '  + TB_ALUNOS.AEE_OBSERVACOES adicionada';
END
ELSE
    PRINT '  = TB_ALUNOS.AEE_OBSERVACOES já existe';
GO

IF NOT EXISTS (SELECT 1 FROM sys.foreign_keys
               WHERE name = 'FK_ALUNOS_PROF_AEE'
                 AND parent_object_id = OBJECT_ID('dbo.TB_ALUNOS'))
BEGIN
    ALTER TABLE dbo.TB_ALUNOS
        ADD CONSTRAINT FK_ALUNOS_PROF_AEE
        FOREIGN KEY (ID_PROFESSOR_AEE) REFERENCES dbo.TB_USUARIOS (ID);
    PRINT '  + FK_ALUNOS_PROF_AEE criada';
END
GO

IF NOT EXISTS (SELECT 1 FROM sys.check_constraints
               WHERE name = 'CK_ALUNOS_PROF_AEE'
                 AND parent_object_id = OBJECT_ID('dbo.TB_ALUNOS'))
BEGIN
    ALTER TABLE dbo.TB_ALUNOS
        ADD CONSTRAINT CK_ALUNOS_PROF_AEE
        CHECK (ID_PROFESSOR_AEE IS NULL OR PASSA_AEE = 1);
    PRINT '  + CK_ALUNOS_PROF_AEE criada';
END
GO

IF NOT EXISTS (SELECT 1 FROM sys.indexes
               WHERE name = 'IX_ALUNOS_PROF_AEE'
                 AND object_id = OBJECT_ID('dbo.TB_ALUNOS'))
BEGIN
    CREATE INDEX IX_ALUNOS_PROF_AEE ON dbo.TB_ALUNOS (ID_PROFESSOR_AEE, ATIVO);
    PRINT '  + IX_ALUNOS_PROF_AEE criado';
END
GO

PRINT 'Atualização de Educação Especial / AEE / acompanhamentos concluída.';
GO
