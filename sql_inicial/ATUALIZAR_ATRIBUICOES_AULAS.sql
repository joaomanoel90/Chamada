/* ============================================================================
   ATUALIZAÇÃO — ATRIBUIÇÕES POR AULAS, 4 CATEGORIAS E LISTA DE MATÉRIAS
   ----------------------------------------------------------------------------
   1. Categorias do concurso passam a ser 4 (antes eram 2):
        PDI_CRECHE (Creche), PDI (Pré-escola), PEB_I (Anos Iniciais),
        PEB_II (Anos Finais). Os valores antigos (PEB_I/PEB_II) continuam
        válidos; a CHECK é recriada com os 4.

   2. Nova tabela TB_ATRIBUICOES: divide as aulas de uma matéria entre
      professores. A turma define o TOTAL de aulas da matéria (ex.: 10 de
      Matemática); cada professor pega uma parte (ex.: 4 + 6). O painel
      mostra quantas aulas ainda faltam ser atribuídas, e cada professor
      tem teto de 36 h/a por semana (validado no sistema).
      Os dados antigos (TB_TURMA_DISCIPLINAS.ID_PROFESSOR) são migrados:
      o professor responsável passa a ter uma atribuição com todas as
      aulas da matéria.

   Idempotente: pode rodar mais de uma vez sem erro.
   ============================================================================ */

SET NOCOUNT ON;
GO

/* 1. Categorias: recria a CHECK com os 4 valores. -------------------------- */
IF EXISTS (SELECT 1 FROM sys.check_constraints WHERE name = 'CK_USUARIOS_CATEGORIA')
BEGIN
    ALTER TABLE dbo.TB_USUARIOS DROP CONSTRAINT CK_USUARIOS_CATEGORIA;
    PRINT '  - CK_USUARIOS_CATEGORIA antiga removida';
END
GO

IF NOT EXISTS (SELECT 1 FROM sys.check_constraints WHERE name = 'CK_USUARIOS_CATEGORIA')
BEGIN
    ALTER TABLE dbo.TB_USUARIOS
        ADD CONSTRAINT CK_USUARIOS_CATEGORIA CHECK (CATEGORIA IS NULL OR CATEGORIA IN ('PDI_CRECHE', 'PDI', 'PEB_I', 'PEB_II'));
    PRINT '  + CK_USUARIOS_CATEGORIA criada (4 categorias)';
END
GO

/* 2. TB_ATRIBUICOES -------------------------------------------------------- */
IF OBJECT_ID(N'dbo.TB_ATRIBUICOES', N'U') IS NULL
BEGIN
    CREATE TABLE dbo.TB_ATRIBUICOES (
        ID            int IDENTITY(1,1) NOT NULL,
        ID_DISCIPLINA int           NOT NULL,
        ID_PROFESSOR  int           NOT NULL,
        AULAS         int           NOT NULL,
        CREATED       datetime      NOT NULL CONSTRAINT DF_ATRIBUICOES_CREATED DEFAULT (GETDATE()),
        CONSTRAINT PK_TB_ATRIBUICOES PRIMARY KEY CLUSTERED (ID),
        CONSTRAINT UQ_ATRIBUICAO UNIQUE (ID_DISCIPLINA, ID_PROFESSOR),
        CONSTRAINT FK_ATRIBUICAO_DISCIPLINA FOREIGN KEY (ID_DISCIPLINA) REFERENCES dbo.TB_TURMA_DISCIPLINAS (ID),
        CONSTRAINT FK_ATRIBUICAO_PROFESSOR  FOREIGN KEY (ID_PROFESSOR)  REFERENCES dbo.TB_USUARIOS (ID),
        CONSTRAINT CK_ATRIBUICAO_AULAS CHECK (AULAS BETWEEN 1 AND 40)
    );
    PRINT '  + TB_ATRIBUICOES criada';
END
ELSE
    PRINT '  = TB_ATRIBUICOES já existe';
GO

IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE object_id = OBJECT_ID('dbo.TB_ATRIBUICOES') AND name = 'UQ_ATRIBUICAO')
BEGIN
    CREATE UNIQUE INDEX UQ_ATRIBUICAO ON dbo.TB_ATRIBUICOES (ID_DISCIPLINA, ID_PROFESSOR);
    PRINT '  + UQ_ATRIBUICAO criada';
END
GO

IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE object_id = OBJECT_ID('dbo.TB_ATRIBUICOES') AND name = 'IX_ATRIBUICAO_PROFESSOR')
BEGIN
    CREATE INDEX IX_ATRIBUICAO_PROFESSOR ON dbo.TB_ATRIBUICOES (ID_PROFESSOR);
    PRINT '  + IX_ATRIBUICAO_PROFESSOR criado';
END
GO

/* 3. Migra os professores responsáveis antigos para atribuições. ----------- */
INSERT INTO dbo.TB_ATRIBUICOES (ID_DISCIPLINA, ID_PROFESSOR, AULAS)
SELECT d.ID, d.ID_PROFESSOR, ISNULL(d.AULAS, 1)
FROM dbo.TB_TURMA_DISCIPLINAS d
WHERE d.ID_PROFESSOR IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM dbo.TB_ATRIBUICOES a WHERE a.ID_DISCIPLINA = d.ID);
IF @@ROWCOUNT > 0 PRINT '  + ' + CAST(@@ROWCOUNT AS varchar(10)) + ' atribuição(ões) migrada(s) do professor responsável';
GO

PRINT 'Atualização de atribuições por aulas concluída.';
GO
