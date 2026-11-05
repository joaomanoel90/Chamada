/* ============================================================================
   MÓDULO CHAMADA DE ALUNOS — SupriADM / Prefeitura de Angatuba
   Banco: db_chamada (SQL Server 2019+)

   REAPROVEITA as tabelas existentes:
     TB_UNIDADES  -> creches / escolas
     TB_USUARIOS  -> professores e gestão
     TB_ACESSO    -> vínculo usuário <-> unidade

   Execute este script DEPOIS de TB_UNIDADES.sql, TB_USUARIOS.sql e TB_ACESSO.sql.
   Script idempotente: pode ser executado mais de uma vez sem perder dados.
   ============================================================================ */

SET NOCOUNT ON;
GO

/* ---------------------------------------------------------------- PERÍODOS */
IF OBJECT_ID(N'dbo.TB_PERIODOS', N'U') IS NULL
BEGIN
    CREATE TABLE dbo.TB_PERIODOS (
        ID          int IDENTITY(1,1) NOT NULL,
        NOME        nvarchar(60)  NOT NULL,
        HORA_INICIO nvarchar(5)   NOT NULL,   -- 'HH:MM'
        HORA_FIM    nvarchar(5)   NOT NULL,   -- 'HH:MM'
        ATIVO       bit           NOT NULL CONSTRAINT DF_PERIODOS_ATIVO DEFAULT (1),
        CREATED     datetime      NOT NULL CONSTRAINT DF_PERIODOS_CREATED DEFAULT (GETDATE()),
        CONSTRAINT PK_TB_PERIODOS PRIMARY KEY CLUSTERED (ID),
        CONSTRAINT UQ_PERIODOS_NOME UNIQUE (NOME),
        CONSTRAINT CK_PERIODOS_HORA CHECK (HORA_FIM > HORA_INICIO)
    );
END
GO

/* ------------------------------------------------------------------ TURMAS */
IF OBJECT_ID(N'dbo.TB_TURMAS', N'U') IS NULL
BEGIN
    CREATE TABLE dbo.TB_TURMAS (
        ID           int IDENTITY(1,1) NOT NULL,
        ID_UNIDADE   int          NOT NULL,
        NOME         nvarchar(80) NOT NULL,
        ANO_LETIVO   int          NOT NULL,
        ID_PERIODO   int          NOT NULL,
        ID_PROFESSOR int          NULL,
        CAPACIDADE   int          NOT NULL CONSTRAINT DF_TURMAS_CAP DEFAULT (20),
        ATIVO        bit          NOT NULL CONSTRAINT DF_TURMAS_ATIVO DEFAULT (1),
        CREATED      datetime     NOT NULL CONSTRAINT DF_TURMAS_CREATED DEFAULT (GETDATE()),
        CONSTRAINT PK_TB_TURMAS PRIMARY KEY CLUSTERED (ID),
        CONSTRAINT UQ_TURMAS UNIQUE (ID_UNIDADE, NOME, ANO_LETIVO),
        CONSTRAINT FK_TURMAS_UNIDADE   FOREIGN KEY (ID_UNIDADE)   REFERENCES dbo.TB_UNIDADES (id),
        CONSTRAINT FK_TURMAS_PERIODO   FOREIGN KEY (ID_PERIODO)   REFERENCES dbo.TB_PERIODOS (ID),
        CONSTRAINT FK_TURMAS_PROFESSOR FOREIGN KEY (ID_PROFESSOR) REFERENCES dbo.TB_USUARIOS (ID),
        CONSTRAINT CK_TURMAS_ANO CHECK (ANO_LETIVO BETWEEN 2000 AND 2100),
        CONSTRAINT CK_TURMAS_CAP CHECK (CAPACIDADE BETWEEN 1 AND 500)
    );
    CREATE INDEX IX_TURMAS_PROFESSOR ON dbo.TB_TURMAS (ID_PROFESSOR);
    CREATE INDEX IX_TURMAS_UNIDADE   ON dbo.TB_TURMAS (ID_UNIDADE, ANO_LETIVO);
END
GO

/* ------------------------------------------------------------------ ALUNOS */
IF OBJECT_ID(N'dbo.TB_ALUNOS', N'U') IS NULL
BEGIN
    CREATE TABLE dbo.TB_ALUNOS (
        ID             int IDENTITY(1,1) NOT NULL,
        ID_TURMA       int           NOT NULL,
        MATRICULA      nvarchar(30)  NOT NULL,
        NOME           nvarchar(150) NOT NULL,
        NASCIMENTO     date          NOT NULL,
        DATA_MATRICULA date          NOT NULL,
        RESPONSAVEL    nvarchar(150) NOT NULL,
        TELEFONE       nvarchar(30)  NOT NULL,
        EMAIL          nvarchar(150) NULL,
        ATIVO          bit           NOT NULL CONSTRAINT DF_ALUNOS_ATIVO DEFAULT (1),
        CREATED        datetime      NOT NULL CONSTRAINT DF_ALUNOS_CREATED DEFAULT (GETDATE()),
        CONSTRAINT PK_TB_ALUNOS PRIMARY KEY CLUSTERED (ID),
        CONSTRAINT UQ_ALUNOS_MATRICULA UNIQUE (MATRICULA),
        CONSTRAINT FK_ALUNOS_TURMA FOREIGN KEY (ID_TURMA) REFERENCES dbo.TB_TURMAS (ID),
        CONSTRAINT CK_ALUNOS_NASC CHECK (NASCIMENTO >= '1900-01-01')
    );
    CREATE INDEX IX_ALUNOS_TURMA ON dbo.TB_ALUNOS (ID_TURMA, ATIVO);
    CREATE INDEX IX_ALUNOS_NOME  ON dbo.TB_ALUNOS (NOME);
END
GO

/* ---------------------------------------------------------------- FERIADOS */
IF OBJECT_ID(N'dbo.TB_FERIADOS', N'U') IS NULL
BEGIN
    CREATE TABLE dbo.TB_FERIADOS (
        ID          int IDENTITY(1,1) NOT NULL,
        DATA        date          NOT NULL,
        DESCRICAO   nvarchar(150) NOT NULL,
        TIPO        nvarchar(10)  NOT NULL,   -- INTEGRAL | PARCIAL
        HORA_INICIO nvarchar(5)   NULL,       -- faixa SEM AULA (só PARCIAL)
        HORA_FIM    nvarchar(5)   NULL,
        ID_UNIDADE  int           NULL,       -- NULL = todas as unidades
        CREATED     datetime      NOT NULL CONSTRAINT DF_FERIADOS_CREATED DEFAULT (GETDATE()),
        CONSTRAINT PK_TB_FERIADOS PRIMARY KEY CLUSTERED (ID),
        CONSTRAINT UQ_FERIADOS_DATA UNIQUE (DATA, ID_UNIDADE),
        CONSTRAINT FK_FERIADOS_UNIDADE FOREIGN KEY (ID_UNIDADE) REFERENCES dbo.TB_UNIDADES (id),
        CONSTRAINT CK_FERIADOS_TIPO CHECK (TIPO IN ('INTEGRAL', 'PARCIAL')),
        CONSTRAINT CK_FERIADOS_HORA CHECK (
            TIPO = 'INTEGRAL'
            OR (HORA_INICIO IS NOT NULL AND HORA_FIM IS NOT NULL AND HORA_FIM > HORA_INICIO)
        )
    );
END
GO

/* ----------------------------------------------------------------- CHAMADA */
IF OBJECT_ID(N'dbo.TB_CHAMADA', N'U') IS NULL
BEGIN
    CREATE TABLE dbo.TB_CHAMADA (
        ID            int IDENTITY(1,1) NOT NULL,
        ID_ALUNO      int           NOT NULL,
        DATA          date          NOT NULL,
        STATUS        nvarchar(1)   NOT NULL,   -- P | F | J | A
        JUSTIFICATIVA nvarchar(500) NULL,
        ID_USUARIO    int           NOT NULL,
        CREATED       datetime      NOT NULL CONSTRAINT DF_CHAMADA_CREATED DEFAULT (GETDATE()),
        MODIFIED      datetime      NULL,
        CONSTRAINT PK_TB_CHAMADA PRIMARY KEY CLUSTERED (ID),
        CONSTRAINT UQ_CHAMADA_ALUNO_DATA UNIQUE (ID_ALUNO, DATA),
        CONSTRAINT FK_CHAMADA_ALUNO   FOREIGN KEY (ID_ALUNO)   REFERENCES dbo.TB_ALUNOS (ID),
        CONSTRAINT FK_CHAMADA_USUARIO FOREIGN KEY (ID_USUARIO) REFERENCES dbo.TB_USUARIOS (ID),
        CONSTRAINT CK_CHAMADA_STATUS CHECK (STATUS IN ('P', 'F', 'J', 'A')),
        CONSTRAINT CK_CHAMADA_JUST CHECK (STATUS <> 'J' OR JUSTIFICATIVA IS NOT NULL)
    );
    CREATE INDEX IX_CHAMADA_DATA ON dbo.TB_CHAMADA (DATA);
END
GO

/* ------------------------------------------------------------- OBSERVAÇÕES */
IF OBJECT_ID(N'dbo.TB_OBSERVACOES', N'U') IS NULL
BEGIN
    CREATE TABLE dbo.TB_OBSERVACOES (
        ID         int IDENTITY(1,1) NOT NULL,
        ID_ALUNO   int           NOT NULL,
        DATA       date          NOT NULL,
        CATEGORIA  nvarchar(40)  NOT NULL,
        TEXTO      nvarchar(max) NOT NULL,
        ID_USUARIO int           NOT NULL,
        CREATED    datetime      NOT NULL CONSTRAINT DF_OBS_CREATED DEFAULT (GETDATE()),
        MODIFIED   datetime      NULL,
        CONSTRAINT PK_TB_OBSERVACOES PRIMARY KEY CLUSTERED (ID),
        CONSTRAINT FK_OBS_ALUNO   FOREIGN KEY (ID_ALUNO)   REFERENCES dbo.TB_ALUNOS (ID),
        CONSTRAINT FK_OBS_USUARIO FOREIGN KEY (ID_USUARIO) REFERENCES dbo.TB_USUARIOS (ID)
    );
    CREATE INDEX IX_OBS_ALUNO_DATA ON dbo.TB_OBSERVACOES (ID_ALUNO, DATA);
END
GO

/* -------------------------------------------------------- RELATÓRIO ANUAL */
IF OBJECT_ID(N'dbo.TB_RELATORIOS_ANUAIS', N'U') IS NULL
BEGIN
    CREATE TABLE dbo.TB_RELATORIOS_ANUAIS (
        ID              int IDENTITY(1,1) NOT NULL,
        ID_ALUNO        int           NOT NULL,
        ANO             int           NOT NULL,
        PARECER         nvarchar(max) NULL,
        ENCAMINHAMENTOS nvarchar(max) NULL,
        ID_USUARIO      int           NOT NULL,
        CREATED         datetime      NOT NULL CONSTRAINT DF_REL_CREATED DEFAULT (GETDATE()),
        MODIFIED        datetime      NULL,
        CONSTRAINT PK_TB_RELATORIOS_ANUAIS PRIMARY KEY CLUSTERED (ID),
        CONSTRAINT UQ_REL_ALUNO_ANO UNIQUE (ID_ALUNO, ANO),
        CONSTRAINT FK_REL_ALUNO   FOREIGN KEY (ID_ALUNO)   REFERENCES dbo.TB_ALUNOS (ID),
        CONSTRAINT FK_REL_USUARIO FOREIGN KEY (ID_USUARIO) REFERENCES dbo.TB_USUARIOS (ID)
    );
END
GO

/* --------------------------------------------------- PERÍODOS PADRÃO (seed) */
IF NOT EXISTS (SELECT 1 FROM dbo.TB_PERIODOS)
BEGIN
    INSERT INTO dbo.TB_PERIODOS (NOME, HORA_INICIO, HORA_FIM) VALUES
        (N'Manhã',   '07:00', '12:00'),
        (N'Tarde',   '13:00', '18:00'),
        (N'Integral','07:00', '18:00');
END
GO

PRINT 'Módulo Chamada de Alunos instalado/atualizado com sucesso.';
GO
