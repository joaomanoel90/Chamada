/* ============================================================================
   AULAS POR DIA, MODELOS DE TURMA E GRADE FLEXÍVEL
   ----------------------------------------------------------------------------
   - Cada turma define manualmente quantas aulas tem por dia (1 a 6).
   - A chamada e a grade passam a aceitar AULA_SEQ de 1 a 6.
   - Modelos nomeados guardam aulas por dia e matérias para replicar em várias
     turmas da mesma unidade ou de outras.
   Idempotente: pode ser executado quantas vezes for necessário.
   ============================================================================ */

IF COL_LENGTH(N'dbo.TB_TURMAS', N'AULAS_DIA') IS NULL
BEGIN
    ALTER TABLE dbo.TB_TURMAS
        ADD AULAS_DIA int NOT NULL CONSTRAINT DF_TB_TURMAS_AULAS_DIA DEFAULT (2);
    PRINT '  + TB_TURMAS.AULAS_DIA criada';
END
GO

IF OBJECT_ID(N'dbo.CK_TB_TURMAS_AULAS_DIA', N'C') IS NULL
BEGIN
    ALTER TABLE dbo.TB_TURMAS
        ADD CONSTRAINT CK_TB_TURMAS_AULAS_DIA CHECK (AULAS_DIA BETWEEN 1 AND 6);
    PRINT '  + CK_TB_TURMAS_AULAS_DIA criado';
END
GO

/* Antes a chamada e a grade eram fixas em duas aulas. Agora o limite é o da
   turma, calculado pelo sistema; o banco aceita até seis posições. */
IF OBJECT_ID(N'dbo.CK_CHAMADA_AULA', N'C') IS NOT NULL
    ALTER TABLE dbo.TB_CHAMADA DROP CONSTRAINT CK_CHAMADA_AULA;
GO
ALTER TABLE dbo.TB_CHAMADA
    ADD CONSTRAINT CK_CHAMADA_AULA CHECK (AULA_SEQ BETWEEN 1 AND 6);
GO

IF OBJECT_ID(N'dbo.CK_GRADE_AULA_SEQ', N'C') IS NOT NULL
    ALTER TABLE dbo.TB_GRADE_AULAS DROP CONSTRAINT CK_GRADE_AULA_SEQ;
GO
ALTER TABLE dbo.TB_GRADE_AULAS
    ADD CONSTRAINT CK_GRADE_AULA_SEQ CHECK (AULA_SEQ BETWEEN 1 AND 6);
GO

/* Modelo = padrão de turma. Guarda aulas por dia e matérias com o total
   semanal. Professor continua sendo definido pela Atribuição. */
IF OBJECT_ID(N'dbo.TB_MODELOS_TURMA', N'U') IS NULL
BEGIN
    CREATE TABLE dbo.TB_MODELOS_TURMA (
        ID         int IDENTITY(1,1) NOT NULL,
        NOME       nvarchar(80)  NOT NULL,
        AULAS_DIA  int           NOT NULL CONSTRAINT DF_MODELO_TURMA_AULAS DEFAULT (2),
        DESCRICAO  nvarchar(300) NULL,
        ATIVO      bit           NOT NULL CONSTRAINT DF_MODELO_TURMA_ATIVO DEFAULT (1),
        CREATED    datetime      NOT NULL CONSTRAINT DF_MODELO_TURMA_CREATED DEFAULT (GETDATE()),
        CONSTRAINT PK_TB_MODELOS_TURMA PRIMARY KEY CLUSTERED (ID),
        CONSTRAINT UQ_MODELO_TURMA_NOME UNIQUE (NOME),
        CONSTRAINT CK_MODELO_AULAS_DIA CHECK (AULAS_DIA BETWEEN 1 AND 6),
        CONSTRAINT CK_MODELO_NOME CHECK (LEN(LTRIM(RTRIM(NOME))) BETWEEN 1 AND 80)
    );
    PRINT '  + TB_MODELOS_TURMA criada';
END
GO

IF OBJECT_ID(N'dbo.TB_MODELO_DISCIPLINAS', N'U') IS NULL
BEGIN
    CREATE TABLE dbo.TB_MODELO_DISCIPLINAS (
        ID         int IDENTITY(1,1) NOT NULL,
        ID_MODELO  int           NOT NULL,
        NOME       nvarchar(80)  NOT NULL,
        AULAS      int           NOT NULL,
        CONSTRAINT PK_TB_MODELO_DISCIPLINAS PRIMARY KEY CLUSTERED (ID),
        CONSTRAINT UQ_MODELO_DISCIPLINA UNIQUE (ID_MODELO, NOME),
        CONSTRAINT FK_MODELO_DISC_MODELO FOREIGN KEY (ID_MODELO) REFERENCES dbo.TB_MODELOS_TURMA (ID) ON DELETE CASCADE,
        CONSTRAINT CK_MODELO_DISC_NOME CHECK (LEN(LTRIM(RTRIM(NOME))) BETWEEN 1 AND 80),
        CONSTRAINT CK_MODELO_DISC_AULAS CHECK (AULAS BETWEEN 1 AND 40)
    );
    PRINT '  + TB_MODELO_DISCIPLINAS criada';
END
GO

IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE object_id = OBJECT_ID('dbo.TB_MODELO_DISCIPLINAS') AND name = 'IX_MODELO_DISC_MODELO')
BEGIN
    CREATE INDEX IX_MODELO_DISC_MODELO ON dbo.TB_MODELO_DISCIPLINAS (ID_MODELO);
    PRINT '  + IX_MODELO_DISC_MODELO criado';
END
GO
