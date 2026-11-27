/* ============================================================================
   GRADE DIÁRIA POR TURMA
   ----------------------------------------------------------------------------
   Cada turma pode definir, para cada dia da semana (1 = segunda ... 7 = domingo)
   e cada aula do dia (1 = 1ª, 2 = 2ª), qual disciplina e qual professor.

   Diferente de TB_ATRIBUICOES (carga SEMANAL), esta tabela responde "o que tem
   hoje". É usada pela Ficha de Chamada para separar as colunas por disciplina
   e professor. A chamada continua gravando apenas AULA_SEQ; a grade é consulta.

   Idempotente: pode ser executada quantas vezes for necessário.
   ============================================================================ */

IF OBJECT_ID(N'dbo.TB_GRADE_AULAS', N'U') IS NULL
BEGIN
    CREATE TABLE dbo.TB_GRADE_AULAS (
        ID            int IDENTITY(1,1) NOT NULL,
        ID_TURMA      int           NOT NULL,
        DIA_SEMANA    tinyint       NOT NULL,
        AULA_SEQ      tinyint       NOT NULL,
        ID_DISCIPLINA int           NULL,
        ID_PROFESSOR  int           NULL,
        CREATED       datetime      NOT NULL CONSTRAINT DF_GRADE_AULAS_CREATED DEFAULT (GETDATE()),
        CONSTRAINT PK_TB_GRADE_AULAS PRIMARY KEY CLUSTERED (ID),
        CONSTRAINT UQ_GRADE_AULA UNIQUE (ID_TURMA, DIA_SEMANA, AULA_SEQ),
        CONSTRAINT FK_GRADE_TURMA FOREIGN KEY (ID_TURMA) REFERENCES dbo.TB_TURMAS (ID),
        CONSTRAINT FK_GRADE_DISCIPLINA FOREIGN KEY (ID_DISCIPLINA) REFERENCES dbo.TB_TURMA_DISCIPLINAS (ID),
        CONSTRAINT FK_GRADE_PROFESSOR FOREIGN KEY (ID_PROFESSOR) REFERENCES dbo.TB_USUARIOS (ID),
        CONSTRAINT CK_GRADE_DIA_SEMANA CHECK (DIA_SEMANA BETWEEN 1 AND 7),
        CONSTRAINT CK_GRADE_AULA_SEQ CHECK (AULA_SEQ BETWEEN 1 AND 2)
    );
    PRINT '  + TB_GRADE_AULAS criada';
END
ELSE
    PRINT '  = TB_GRADE_AULAS já existe';
GO

IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE object_id = OBJECT_ID('dbo.TB_GRADE_AULAS') AND name = 'UQ_GRADE_AULA')
BEGIN
    CREATE UNIQUE INDEX UQ_GRADE_AULA ON dbo.TB_GRADE_AULAS (ID_TURMA, DIA_SEMANA, AULA_SEQ);
    PRINT '  + UQ_GRADE_AULA criado';
END
GO

IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE object_id = OBJECT_ID('dbo.TB_GRADE_AULAS') AND name = 'IX_GRADE_AULAS_DISCIPLINA')
BEGIN
    CREATE INDEX IX_GRADE_AULAS_DISCIPLINA ON dbo.TB_GRADE_AULAS (ID_DISCIPLINA);
    PRINT '  + IX_GRADE_AULAS_DISCIPLINA criado';
END
GO

IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE object_id = OBJECT_ID('dbo.TB_GRADE_AULAS') AND name = 'IX_GRADE_AULAS_PROFESSOR')
BEGIN
    CREATE INDEX IX_GRADE_AULAS_PROFESSOR ON dbo.TB_GRADE_AULAS (ID_PROFESSOR);
    PRINT '  + IX_GRADE_AULAS_PROFESSOR criado';
END
GO
