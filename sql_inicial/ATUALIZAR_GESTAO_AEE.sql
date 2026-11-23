/* ============================================================================
   ATUALIZAR_GESTAO_AEE.sql
   ----------------------------------------------------------------------------
   Cria a tabela dbo.TB_ALUNO_AEE para gerenciar o Atendimento Educacional
   Especializado (AEE) de forma dinâmica por Ano Letivo, permitindo:
   - Atribuir professor(a) de AEE a cada ano letivo;
   - Definir dias da semana, horário e sala de recursos multifuncionais;
   - Registrar metas / objetivos do plano de atendimento;
   - Preservar o histórico completo dos anos anteriores.

   Idempotente: pode ser executado mais de uma vez sem erro.
   ============================================================================ */
SET NOCOUNT ON;
GO

IF OBJECT_ID(N'dbo.TB_ALUNOS', N'U') IS NULL
    THROW 50101, 'A tabela dbo.TB_ALUNOS não existe; rode primeiro a instalação do sistema.', 1;
GO

IF OBJECT_ID(N'dbo.TB_ALUNO_AEE', N'U') IS NULL
BEGIN
    CREATE TABLE dbo.TB_ALUNO_AEE (
        ID                  int IDENTITY(1,1) NOT NULL,
        ID_ALUNO            int               NOT NULL,
        ANO_LETIVO          int               NOT NULL,
        ID_PROFESSOR_AEE    int               NULL,
        ATIVO               bit               NOT NULL CONSTRAINT DF_ALUNOAEE_ATIVO DEFAULT (1),
        DIAS_HORARIOS       nvarchar(500)     NULL,
        SALA_RECURSOS       nvarchar(150)     NULL,
        OBJETIVOS_PLANO     nvarchar(1000)    NULL,
        CREATED             datetime2(0)      NOT NULL CONSTRAINT DF_ALUNOAEE_CREATED DEFAULT (SYSDATETIME()),
        MODIFIED            datetime2(0)      NULL,
        ID_USUARIO          int               NULL,
        CONSTRAINT PK_TB_ALUNO_AEE PRIMARY KEY CLUSTERED (ID),
        CONSTRAINT FK_ALUNOAEE_ALUNO FOREIGN KEY (ID_ALUNO) REFERENCES dbo.TB_ALUNOS (ID),
        CONSTRAINT FK_ALUNOAEE_PROF FOREIGN KEY (ID_PROFESSOR_AEE) REFERENCES dbo.TB_USUARIOS (ID),
        CONSTRAINT UQ_ALUNOAEE_ALUNO_ANO UNIQUE (ID_ALUNO, ANO_LETIVO)
    );
    CREATE INDEX IX_ALUNOAEE_PROF_ANO ON dbo.TB_ALUNO_AEE (ID_PROFESSOR_AEE, ANO_LETIVO);
    CREATE INDEX IX_ALUNOAEE_ALUNO ON dbo.TB_ALUNO_AEE (ID_ALUNO);
    PRINT '  + TB_ALUNO_AEE criada';
END
ELSE
    PRINT '  = TB_ALUNO_AEE já existe';
GO

/* Migra dados existentes de TB_ALUNOS para TB_ALUNO_AEE (idempotente) */
IF OBJECT_ID(N'dbo.TB_ALUNO_AEE', N'U') IS NOT NULL
BEGIN
    INSERT INTO dbo.TB_ALUNO_AEE (ID_ALUNO, ANO_LETIVO, ID_PROFESSOR_AEE, ATIVO, DIAS_HORARIOS, MODIFIED)
    SELECT a.ID, ISNULL(t.ANO_LETIVO, YEAR(GETDATE())), a.ID_PROFESSOR_AEE, 1, a.AEE_OBSERVACOES, SYSDATETIME()
    FROM dbo.TB_ALUNOS a
    LEFT JOIN dbo.TB_TURMAS t ON t.ID = a.ID_TURMA
    WHERE a.PASSA_AEE = 1
      AND NOT EXISTS (
          SELECT 1 FROM dbo.TB_ALUNO_AEE ae 
          WHERE ae.ID_ALUNO = a.ID 
            AND ae.ANO_LETIVO = ISNULL(t.ANO_LETIVO, YEAR(GETDATE()))
      );
    PRINT '  + Migração de atendimentos existentes concluída';
END
GO

PRINT 'Atualização de Gestão do AEE concluída com sucesso.';
GO
