/* ============================================================================
   ATUALIZAR_CRECHE_FAMILIA_CRACHA.sql
   ----------------------------------------------------------------------------
   1) Inclui a categoria 'APOIO_CRECHE' (Professor de Apoio — Creche) na CHECK
      CK_USUARIOS_CATEGORIA de TB_USUARIOS.
   2) Adiciona VINCULO_DIRETO em TB_TURMA_PROFESSORES para distinguir regentes
      e apoios atribuídos sem matéria (Creche por período, Apoio dia todo e
      Pré I/II PEB I) dos professores que entram na equipe apenas por aulas
      de matérias específicas.
   3) Cria TB_RESPONSAVEIS (cadastro único por CPF) e TB_ALUNO_RESPONSAVEIS
      (vínculo Mãe / Pai / Outro responsável com os alunos), permitindo
      reaproveitar responsáveis pelo CPF e consultar todos os filhos no sistema.

   Script idempotente: pode ser executado mais de uma vez sem erro.
   ============================================================================ */
SET NOCOUNT ON;
GO

/* 1. Categoria 'APOIO_CRECHE' em TB_USUARIOS.CATEGORIA */
IF EXISTS (
    SELECT 1 FROM sys.check_constraints
    WHERE name = 'CK_USUARIOS_CATEGORIA'
      AND parent_object_id = OBJECT_ID('dbo.TB_USUARIOS')
      AND definition NOT LIKE '%''APOIO_CRECHE''%'
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
    ALTER TABLE dbo.TB_USUARIOS ADD CONSTRAINT CK_USUARIOS_CATEGORIA
        CHECK (CATEGORIA IS NULL OR CATEGORIA IN ('PDI_CRECHE', 'APOIO_CRECHE', 'PDI', 'PEB_I', 'PEB_II', 'AEE'));
    PRINT '  + CK_USUARIOS_CATEGORIA atualizada (incluindo APOIO_CRECHE e AEE)';
END
ELSE
    PRINT '  = CK_USUARIOS_CATEGORIA já contempla APOIO_CRECHE';
GO

/* 2. TB_TURMA_PROFESSORES.VINCULO_DIRETO (atribuição sem matéria: Creche / Apoio / Pré) */
IF COL_LENGTH('dbo.TB_TURMA_PROFESSORES', 'VINCULO_DIRETO') IS NULL
BEGIN
    ALTER TABLE dbo.TB_TURMA_PROFESSORES ADD VINCULO_DIRETO bit NOT NULL
        CONSTRAINT DF_TURMAPROF_VINCULO_DIRETO DEFAULT (0);
    PRINT '  + TB_TURMA_PROFESSORES.VINCULO_DIRETO adicionada';

    /* Quem já estava ativo como REGENTE ou AUXILIAR sem aulas em TB_ATRIBUICOES
       passa a ser marcado como vínculo direto para não perder o acesso. */
    EXEC(N'
        UPDATE tp
           SET tp.VINCULO_DIRETO = 1
          FROM dbo.TB_TURMA_PROFESSORES tp
         WHERE tp.ATIVO = 1
           AND tp.PAPEL IN (''REGENTE'', ''AUXILIAR'')
           AND NOT EXISTS (
               SELECT 1
                 FROM dbo.TB_ATRIBUICOES a
                 INNER JOIN dbo.TB_TURMA_DISCIPLINAS d ON d.ID = a.ID_DISCIPLINA
                WHERE d.ID_TURMA = tp.ID_TURMA
                  AND a.ID_PROFESSOR = tp.ID_USUARIO
           );
    ');
END
ELSE
    PRINT '  = TB_TURMA_PROFESSORES.VINCULO_DIRETO já existe';
GO

IF NOT EXISTS (
    SELECT 1 FROM sys.check_constraints
    WHERE name = 'CK_TURMAPROF_VINCULO_DIRETO'
      AND parent_object_id = OBJECT_ID('dbo.TB_TURMA_PROFESSORES')
)
BEGIN
    ALTER TABLE dbo.TB_TURMA_PROFESSORES ADD CONSTRAINT CK_TURMAPROF_VINCULO_DIRETO
        CHECK (VINCULO_DIRETO IN (0, 1) AND (PAPEL <> 'SUBSTITUTO' OR VINCULO_DIRETO = 0));
    PRINT '  + CK_TURMAPROF_VINCULO_DIRETO criada';
END
ELSE
    PRINT '  = CK_TURMAPROF_VINCULO_DIRETO já existe';
GO

/* 3. TB_RESPONSAVEIS — cadastro único de responsáveis por CPF */
IF OBJECT_ID('dbo.TB_RESPONSAVEIS', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.TB_RESPONSAVEIS (
        ID       int IDENTITY(1,1) NOT NULL,
        CPF      char(11)          NOT NULL,
        NOME     nvarchar(150)     NOT NULL,
        TELEFONE nvarchar(30)      NOT NULL,
        EMAIL    nvarchar(150)     NULL,
        CREATED  datetime2(0)      NOT NULL CONSTRAINT DF_RESPONSAVEIS_CREATED DEFAULT (SYSDATETIME()),
        MODIFIED datetime2(0)      NULL,
        CONSTRAINT PK_TB_RESPONSAVEIS PRIMARY KEY CLUSTERED (ID),
        CONSTRAINT UQ_RESPONSAVEIS_CPF UNIQUE (CPF),
        CONSTRAINT CK_RESPONSAVEIS_CPF CHECK (LEN(CPF) = 11 AND CPF NOT LIKE '%[^0-9]%')
    );
    CREATE INDEX IX_RESPONSAVEIS_NOME ON dbo.TB_RESPONSAVEIS (NOME);
    PRINT '  + TB_RESPONSAVEIS criada';
END
ELSE
    PRINT '  = TB_RESPONSAVEIS já existe';
GO

/* 4. TB_ALUNO_RESPONSAVEIS — vínculo aluno <-> pai/mãe/outro responsável */
IF OBJECT_ID('dbo.TB_ALUNO_RESPONSAVEIS', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.TB_ALUNO_RESPONSAVEIS (
        ID                int IDENTITY(1,1) NOT NULL,
        ID_ALUNO          int               NOT NULL,
        ID_RESPONSAVEL    int               NOT NULL,
        PARENTESCO        varchar(15)       NOT NULL,
        DESCRICAO_OUTRO   nvarchar(60)      NULL,
        CONTATO_PRINCIPAL bit               NOT NULL CONSTRAINT DF_ALUNORESP_PRINCIPAL DEFAULT (0),
        CREATED           datetime2(0)      NOT NULL CONSTRAINT DF_ALUNORESP_CREATED DEFAULT (SYSDATETIME()),
        CONSTRAINT PK_TB_ALUNO_RESPONSAVEIS PRIMARY KEY CLUSTERED (ID),
        CONSTRAINT FK_ALUNORESP_ALUNO FOREIGN KEY (ID_ALUNO) REFERENCES dbo.TB_ALUNOS (ID),
        CONSTRAINT FK_ALUNORESP_RESP  FOREIGN KEY (ID_RESPONSAVEL) REFERENCES dbo.TB_RESPONSAVEIS (ID),
        CONSTRAINT UQ_ALUNORESP_ALUNO_RESP UNIQUE (ID_ALUNO, ID_RESPONSAVEL),
        CONSTRAINT UQ_ALUNORESP_ALUNO_TIPO UNIQUE (ID_ALUNO, PARENTESCO),
        CONSTRAINT CK_ALUNORESP_PARENTESCO CHECK (PARENTESCO IN ('MAE', 'PAI', 'OUTRO'))
    );
    CREATE INDEX IX_ALUNORESP_RESP  ON dbo.TB_ALUNO_RESPONSAVEIS (ID_RESPONSAVEL);
    CREATE INDEX IX_ALUNORESP_ALUNO ON dbo.TB_ALUNO_RESPONSAVEIS (ID_ALUNO);
    PRINT '  + TB_ALUNO_RESPONSAVEIS criada';
END
ELSE
    PRINT '  = TB_ALUNO_RESPONSAVEIS já existe';
GO
