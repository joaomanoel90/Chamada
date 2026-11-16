/* ============================================================================
   ATUALIZAR_DISCIPLINAS_AULAS.sql
   ----------------------------------------------------------------------------
   Adiciona a coluna AULAS em TB_TURMA_DISCIPLINAS: quantas aulas semanais
   a matéria tem na turma (ex.: Matemática 4 aulas por semana).

   Quem já tem o sistema instalado roda este arquivo no SQL Server.
   Quem instala do zero já recebe a coluna em INSTALACAO_COMPLETA.sql.

   É seguro rodar mais de uma vez: as alterações só acontecem se a coluna
   e a CHECK ainda não existirem.
   ============================================================================ */

USE db_chamada;
GO

IF COL_LENGTH('dbo.TB_TURMA_DISCIPLINAS', 'AULAS') IS NULL
BEGIN
    ALTER TABLE dbo.TB_TURMA_DISCIPLINAS ADD AULAS int NULL;
    PRINT '  + TB_TURMA_DISCIPLINAS.AULAS criada';
END
ELSE
    PRINT '  = TB_TURMA_DISCIPLINAS.AULAS já existe';
GO

IF NOT EXISTS (SELECT 1 FROM sys.check_constraints WHERE name = 'CK_TURMA_DISC_AULAS')
BEGIN
    ALTER TABLE dbo.TB_TURMA_DISCIPLINAS
        ADD CONSTRAINT CK_TURMA_DISC_AULAS CHECK (AULAS IS NULL OR (AULAS BETWEEN 1 AND 40));
    PRINT '  + CK_TURMA_DISC_AULAS criada';
END
ELSE
    PRINT '  = CK_TURMA_DISC_AULAS já existe';
GO

PRINT 'Atualização de disciplinas (AULAS) concluída.';
