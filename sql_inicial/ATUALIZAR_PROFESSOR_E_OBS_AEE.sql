/* ============================================================================
   ATUALIZAR_PROFESSOR_E_OBS_AEE.sql
   ----------------------------------------------------------------------------
   1) Inclui a categoria 'AEE' (Professor de AEE — Educação Especial) na CHECK
      CK_USUARIOS_CATEGORIA de TB_USUARIOS.
   2) Adiciona a coluna RESTRITA_AEE (0 = compartilhada com a turma;
      1 = restrita ao professor de AEE) em TB_OBSERVACOES e
      TB_HISTORICO_OBSERVACOES.

   Script idempotente: pode ser executado mais de uma vez sem erro.
   ============================================================================ */
SET NOCOUNT ON;
GO

/* 1. Categoria 'AEE' em TB_USUARIOS.CATEGORIA */
IF EXISTS (
    SELECT 1 FROM sys.check_constraints
    WHERE name = 'CK_USUARIOS_CATEGORIA'
      AND parent_object_id = OBJECT_ID('dbo.TB_USUARIOS')
      AND definition NOT LIKE '%''AEE''%'
)
BEGIN
    ALTER TABLE dbo.TB_USUARIOS DROP CONSTRAINT CK_USUARIOS_CATEGORIA;
    PRINT '  - CK_USUARIOS_CATEGORIA antiga removida';
END
GO

IF NOT EXISTS (
    SELECT 1 FROM sys.check_constraints
    WHERE name = 'CK_USUARIOS_CATEGORIA'
      AND parent_object_id = OBJECT_ID('dbo.TB_USUARIOS')
)
BEGIN
    ALTER TABLE dbo.TB_USUARIOS
        ADD CONSTRAINT CK_USUARIOS_CATEGORIA
        CHECK (CATEGORIA IS NULL OR CATEGORIA IN ('PDI_CRECHE', 'PDI', 'PEB_I', 'PEB_II', 'AEE'));
    PRINT '  + CK_USUARIOS_CATEGORIA atualizada com AEE';
END
ELSE
    PRINT '  = CK_USUARIOS_CATEGORIA já contempla AEE';
GO

/* 2. Coluna RESTRITA_AEE nas observações e no histórico */
IF COL_LENGTH('dbo.TB_OBSERVACOES', 'RESTRITA_AEE') IS NULL
BEGIN
    ALTER TABLE dbo.TB_OBSERVACOES ADD RESTRITA_AEE bit NOT NULL
        CONSTRAINT DF_OBS_RESTRITA_AEE DEFAULT (0);
    PRINT '  + TB_OBSERVACOES.RESTRITA_AEE adicionada';
END
ELSE
    PRINT '  = TB_OBSERVACOES.RESTRITA_AEE já existe';
GO

IF NOT EXISTS (
    SELECT 1 FROM sys.check_constraints
    WHERE name = 'CK_OBS_RESTRITA_AEE'
      AND parent_object_id = OBJECT_ID('dbo.TB_OBSERVACOES')
)
BEGIN
    ALTER TABLE dbo.TB_OBSERVACOES
        ADD CONSTRAINT CK_OBS_RESTRITA_AEE CHECK (RESTRITA_AEE IN (0, 1));
    PRINT '  + CK_OBS_RESTRITA_AEE criada';
END
GO

IF COL_LENGTH('dbo.TB_HISTORICO_OBSERVACOES', 'RESTRITA_AEE') IS NULL
BEGIN
    ALTER TABLE dbo.TB_HISTORICO_OBSERVACOES ADD RESTRITA_AEE bit NOT NULL
        CONSTRAINT DF_HISTOBS_RESTRITA_AEE DEFAULT (0);
    PRINT '  + TB_HISTORICO_OBSERVACOES.RESTRITA_AEE adicionada';
END
ELSE
    PRINT '  = TB_HISTORICO_OBSERVACOES.RESTRITA_AEE já existe';
GO

PRINT 'Atualização de categoria AEE e observações AEE concluída.';
GO
