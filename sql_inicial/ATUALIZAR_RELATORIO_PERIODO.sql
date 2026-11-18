/* ============================================================================
   ATUALIZAÇÃO — RELATÓRIO BIMESTRAL / SEMESTRAL
   ----------------------------------------------------------------------------
   O relatório de desenvolvimento deixa de ser só anual e passa a ser por
   período: bimestral (6 períodos de 2 meses) ou semestral (2 períodos de
   6 meses), à escolha na tela. O parecer passa a ser salvo por período.

   A tabela TB_RELATORIOS_ANUAIS ganha a coluna PERIODO ('B1'..'B6' ou
   'S1'/'S2'). Os pareceres antigos (anuais) ficam com PERIODO NULL e são
   preservados — só não aparecem mais na tela, que agora é por período.

   Idempotente: pode rodar mais de uma vez sem erro.
   ============================================================================ */

SET NOCOUNT ON;
GO

/* 1. Coluna PERIODO. ------------------------------------------------------- */
IF COL_LENGTH('dbo.TB_RELATORIOS_ANUAIS', 'PERIODO') IS NULL
BEGIN
    ALTER TABLE dbo.TB_RELATORIOS_ANUAIS ADD PERIODO varchar(10) NULL;
    PRINT '  + TB_RELATORIOS_ANUAIS.PERIODO criada';
END
ELSE
    PRINT '  = TB_RELATORIOS_ANUAIS.PERIODO já existe';
GO

/* 2. Unicidade passa a ser (aluno, ano, período). ------------------------- */
IF EXISTS (SELECT 1 FROM sys.key_constraints
           WHERE name = 'UQ_REL_ALUNO_ANO'
             AND parent_object_id = OBJECT_ID('dbo.TB_RELATORIOS_ANUAIS'))
BEGIN
    ALTER TABLE dbo.TB_RELATORIOS_ANUAIS DROP CONSTRAINT UQ_REL_ALUNO_ANO;
    PRINT '  - UQ_REL_ALUNO_ANO antiga removida';
END
GO

IF NOT EXISTS (SELECT 1 FROM sys.key_constraints
               WHERE name = 'UQ_REL_ALUNO_ANO_PERIODO'
                 AND parent_object_id = OBJECT_ID('dbo.TB_RELATORIOS_ANUAIS'))
BEGIN
    ALTER TABLE dbo.TB_RELATORIOS_ANUAIS
        ADD CONSTRAINT UQ_REL_ALUNO_ANO_PERIODO UNIQUE (ID_ALUNO, ANO, PERIODO);
    PRINT '  + UQ_REL_ALUNO_ANO_PERIODO criada';
END
GO

IF NOT EXISTS (SELECT 1 FROM sys.check_constraints WHERE name = 'CK_REL_PERIODO')
BEGIN
    ALTER TABLE dbo.TB_RELATORIOS_ANUAIS
        ADD CONSTRAINT CK_REL_PERIODO CHECK (PERIODO IS NULL OR PERIODO IN ('B1','B2','B3','B4','B5','B6','S1','S2'));
    PRINT '  + CK_REL_PERIODO criada';
END
GO

PRINT 'Atualização do relatório por período concluída.';
GO
