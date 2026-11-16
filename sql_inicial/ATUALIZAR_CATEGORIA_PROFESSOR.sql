/* ============================================================================
   ATUALIZAÇÃO — CATEGORIA DO CONCURSO E MATÉRIAS DO PROFESSOR
   ----------------------------------------------------------------------------
   Problema: no começo do ano o professor ainda não tem turma nem equipe
   vinculada a ele, então o painel de atribuição não tinha como saber em
   quais turmas ele pode ser atribuído.

   Solução: o cadastro do professor passa a guardar
     1. CATEGORIA (TB_USUARIOS.CATEGORIA): 'PEB_I' ou 'PEB_II' — diz em quais
        etapas ele pode ensinar (PEB I = Infantil + Anos Iniciais;
        PEB II = Anos Finais). NULL = sem restrição (todas as etapas).
     2. MATÉRIAS (TB_PROFESSOR_MATERIAS): quais matérias ele pode lecionar.
        Sem linhas = sem restrição (pode lecionar qualquer matéria).

   Com isso o painel mostra, para o professor escolhido, as turmas da equipe
   dele MAIS as turmas compatíveis com a categoria — mesmo antes de existir
   equipe. Ao atribuir uma matéria, o professor entra na equipe da turma
   automaticamente (senão ele não consegue lançar no diário).

   Idempotente: pode rodar mais de uma vez sem erro.
   ============================================================================ */

SET NOCOUNT ON;
GO

/* 1. TB_USUARIOS.CATEGORIA ------------------------------------------------ */
IF COL_LENGTH('dbo.TB_USUARIOS', 'CATEGORIA') IS NULL
BEGIN
    ALTER TABLE dbo.TB_USUARIOS ADD CATEGORIA nvarchar(20) NULL;
    PRINT '  + TB_USUARIOS.CATEGORIA criada';
END
ELSE
    PRINT '  = TB_USUARIOS.CATEGORIA já existe';
GO

IF NOT EXISTS (SELECT 1 FROM sys.check_constraints WHERE name = 'CK_USUARIOS_CATEGORIA')
BEGIN
    ALTER TABLE dbo.TB_USUARIOS
        ADD CONSTRAINT CK_USUARIOS_CATEGORIA CHECK (CATEGORIA IS NULL OR CATEGORIA IN ('PEB_I', 'PEB_II'));
    PRINT '  + CK_USUARIOS_CATEGORIA criada';
END
ELSE
    PRINT '  = CK_USUARIOS_CATEGORIA já existe';
GO

/* 2. TB_PROFESSOR_MATERIAS ------------------------------------------------ */
IF OBJECT_ID(N'dbo.TB_PROFESSOR_MATERIAS', N'U') IS NULL
BEGIN
    CREATE TABLE dbo.TB_PROFESSOR_MATERIAS (
        ID         int IDENTITY(1,1) NOT NULL,
        ID_USUARIO int           NOT NULL,
        NOME       nvarchar(80)  NOT NULL,
        CREATED    datetime      NOT NULL CONSTRAINT DF_PROFMATERIA_CREATED DEFAULT (GETDATE()),
        CONSTRAINT PK_TB_PROFESSOR_MATERIAS PRIMARY KEY CLUSTERED (ID),
        CONSTRAINT UQ_PROFMATERIA UNIQUE (ID_USUARIO, NOME),
        CONSTRAINT FK_PROFMATERIA_USUARIO FOREIGN KEY (ID_USUARIO) REFERENCES dbo.TB_USUARIOS (ID),
        CONSTRAINT CK_PROFMATERIA_NOME CHECK (LEN(LTRIM(RTRIM(NOME))) BETWEEN 1 AND 80)
    );
    PRINT '  + TB_PROFESSOR_MATERIAS criada';
END
ELSE
    PRINT '  = TB_PROFESSOR_MATERIAS já existe';
GO

IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE object_id = OBJECT_ID('dbo.TB_PROFESSOR_MATERIAS') AND name = 'UQ_PROFMATERIA')
BEGIN
    CREATE UNIQUE INDEX UQ_PROFMATERIA ON dbo.TB_PROFESSOR_MATERIAS (ID_USUARIO, NOME);
    PRINT '  + UQ_PROFMATERIA criada';
END
GO

IF NOT EXISTS (SELECT 1 FROM sys.foreign_keys
               WHERE parent_object_id = OBJECT_ID('dbo.TB_PROFESSOR_MATERIAS')
                 AND name = 'FK_PROFMATERIA_USUARIO')
BEGIN
    ALTER TABLE dbo.TB_PROFESSOR_MATERIAS ADD CONSTRAINT FK_PROFMATERIA_USUARIO
        FOREIGN KEY (ID_USUARIO) REFERENCES dbo.TB_USUARIOS (ID);
    PRINT '  + FK_PROFMATERIA_USUARIO criada';
END
GO

PRINT 'Atualização de categoria do professor concluída.';
GO
