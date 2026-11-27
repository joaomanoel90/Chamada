/* ============================================================================
   TRAVAMENTO DA GRADE DIÁRIA APÓS SALVAR
   ----------------------------------------------------------------------------
   - Depois de salvar, os selects da grade ficam somente leitura.
   - A gestão usa o botão "Editar grade" para desbloquear e ajustar.
   Idempotente: pode ser executado quantas vezes for necessário.
   ============================================================================ */

IF COL_LENGTH(N'dbo.TB_TURMAS', N'GRADE_BLOQUEADA') IS NULL
BEGIN
    ALTER TABLE dbo.TB_TURMAS
        ADD GRADE_BLOQUEADA bit NOT NULL CONSTRAINT DF_TB_TURMAS_GRADE_BLOQUEADA DEFAULT (0);
    PRINT '  + TB_TURMAS.GRADE_BLOQUEADA criada';

    /* Grades já cadastradas antes da coluna existir começam bloqueadas.
       EXEC dinâmico porque a coluna ainda não existe no momento da compilação
       deste lote. Só roda quando a coluna é criada: reexecutar a migração não
       desfaz um eventual "Editar grade" feito pela gestão. */
    IF OBJECT_ID(N'dbo.TB_GRADE_AULAS', N'U') IS NOT NULL
        EXEC sp_executesql N'UPDATE t SET GRADE_BLOQUEADA = 1 FROM dbo.TB_TURMAS t WHERE EXISTS (SELECT 1 FROM dbo.TB_GRADE_AULAS g WHERE g.ID_TURMA = t.ID)';
    PRINT '  + grades já cadastradas marcadas como salvas';
END
GO
