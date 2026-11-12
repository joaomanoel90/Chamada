/* ============================================================================
   CHAMADA DE ALUNOS — PREFEITURA MUNICIPAL DE ANGATUBA
   SCRIPT COMPLETO DE INSTALAÇÃO DO BANCO DE DADOS
   ----------------------------------------------------------------------------
   SGBD     : Microsoft SQL Server 2019 ou superior (inclui Azure SQL)
   Banco    : db_chamada
   Driver   : sqlsrv (PHP 8.x)

   Este é o ÚNICO script necessário. Cria banco, tabelas, chaves, índices,
   view e os dados iniciais.

   É IDEMPOTENTE: pode ser executado novamente sem apagar dados existentes.
   Nenhuma tabela é dropada. Nenhum registro existente é sobrescrito.

   ----------------------------------------------------------------------------
   COMO EXECUTAR
   ----------------------------------------------------------------------------
   1. Abra o SQL Server Management Studio (SSMS) ou o Azure Data Studio.
   2. Conecte-se com um login que tenha permissão de criar banco e tabelas.
   3. Abra este arquivo e execute (F5).
      - Se o banco ainda não existe, ele é criado.
      - Se já existe, apenas os objetos que faltam são criados.
   4. Ao final, o script imprime um relatório de verificação.
   UPGRADE: se você já tinha instalado uma versão anterior, basta executar este
   mesmo arquivo de novo. Ele acrescenta o que falta (equipe da turma, histórico
   de retificações, colunas de confirmação) e migra o professor único de cada
   turma para REGENTE, sem apagar nada.
   5. Ajuste config.php com host, usuário e senha do banco.

   ----------------------------------------------------------------------------
   CORREÇÕES EM RELAÇÃO AOS SCRIPTS ANTIGOS
   ----------------------------------------------------------------------------
   - VIEW_ACESSOS_USUARIOS: o arquivo antigo continha apenas um SELECT, sem
     CREATE VIEW. A view nunca era criada, e por isso o gerenciamento antigo
     de acesso por unidade falhava. Aqui ela é criada de fato.
   - TB_USUARIOS.EMAIL: a coluna era usada em 4 comandos SQL de usuarios/ mas
     não existia no schema. Aqui ela é criada (e adicionada em bases antigas).
   - TB_USUARIOS.ATIVO: o registro inicial tinha ATIVO = NULL. Aqui é 1, e os
     registros antigos com NULL são normalizados.

   ----------------------------------------------------------------------------
   SEGURANÇA — LEIA
   ----------------------------------------------------------------------------
   As senhas novas usam password_hash() / password_verify(). Contas antigas em
   MD5 são atualizadas automaticamente após um login válido. O usuário
   administrador inicial é criado com uma senha temporária:

        Trocar@2026

   TROQUE ESSA SENHA NO PRIMEIRO ACESSO, em "Alterar Senha".
   Para instalações existentes, execute ATUALIZAR_SEGURANCA_LOGIN.sql antes
   de publicar o novo código PHP.
   ============================================================================ */

SET NOCOUNT ON;
SET XACT_ABORT ON;
GO

/* ============================================================================
   1. BANCO DE DADOS
   ============================================================================ */
IF DB_ID(N'db_chamada') IS NULL
BEGIN
    PRINT 'Criando o banco db_chamada...';
    EXEC (N'CREATE DATABASE [db_chamada] COLLATE Latin1_General_CI_AS');
END
ELSE
    PRINT 'Banco db_chamada já existe. Prosseguindo.';
GO

USE [db_chamada];
GO

PRINT '----------------------------------------------------------------';
PRINT 'Instalando o schema de Chamada de Alunos em db_chamada';
PRINT '----------------------------------------------------------------';
GO

/* ============================================================================
   2. TB_UNIDADES — creches e escolas
   ============================================================================ */
IF OBJECT_ID(N'dbo.TB_UNIDADES', N'U') IS NULL
BEGIN
    CREATE TABLE dbo.TB_UNIDADES (
        id            int IDENTITY(1,1) NOT NULL,
        unidade       nvarchar(255) NOT NULL,
        data_cadastro datetime      NOT NULL CONSTRAINT DF_UNIDADES_DATA DEFAULT (GETDATE()),
        CONSTRAINT PK_TB_UNIDADES PRIMARY KEY CLUSTERED (id)
    );
    PRINT '  + TB_UNIDADES criada';
END
ELSE
    PRINT '  = TB_UNIDADES já existe';
GO

/* ============================================================================
   3. TB_USUARIOS — gestão e professores
      NIVEL: 1 = administrador | 2 = diretor | 3 = professor
              4 = Secretaria de Educação | 5 = Secretário Escolar
   ============================================================================ */
IF OBJECT_ID(N'dbo.TB_USUARIOS', N'U') IS NULL
BEGIN
    CREATE TABLE dbo.TB_USUARIOS (
        ID       int IDENTITY(1,1) NOT NULL,
        NOME     nvarchar(255) NOT NULL,
        SENHA    nvarchar(255) NOT NULL,
        NIVEL    int           NOT NULL CONSTRAINT DF_USUARIOS_NIVEL DEFAULT (3),
        CPF      nvarchar(14)  NOT NULL,
        EMAIL    nvarchar(150) NULL,
        CREATED  datetime      NOT NULL CONSTRAINT DF_USUARIOS_CREATED DEFAULT (GETDATE()),
        MODIFIED datetime      NULL,
        ATIVO    bit           NOT NULL CONSTRAINT DF_USUARIOS_ATIVO DEFAULT (1),
        USUARIO  int           NULL,
        CONSTRAINT PK_TB_USUARIOS PRIMARY KEY CLUSTERED (ID),
        CONSTRAINT UQ_USUARIOS_CPF UNIQUE (CPF),
        CONSTRAINT CK_USUARIOS_NIVEL CHECK (NIVEL IN (1, 2, 3, 4, 5))
    );
    PRINT '  + TB_USUARIOS criada';
END
ELSE
BEGIN
    PRINT '  = TB_USUARIOS já existe';

    -- Bases antigas não tinham EMAIL, mas o código usa.
    IF COL_LENGTH('dbo.TB_USUARIOS', 'EMAIL') IS NULL
    BEGIN
        ALTER TABLE dbo.TB_USUARIOS ADD EMAIL nvarchar(150) NULL;
        PRINT '    + coluna EMAIL adicionada';
    END

    -- O seed antigo deixava ATIVO como NULL.
    UPDATE dbo.TB_USUARIOS SET ATIVO = 1 WHERE ATIVO IS NULL;
    IF @@ROWCOUNT > 0 PRINT '    + registros com ATIVO nulo normalizados para 1';

    -- CPF era nvarchar(11), mas o formato com pontos e traço tem 14 caracteres.
    IF EXISTS (SELECT 1 FROM sys.columns
               WHERE object_id = OBJECT_ID('dbo.TB_USUARIOS')
                 AND name = 'CPF' AND max_length < 28)
    BEGIN
        ALTER TABLE dbo.TB_USUARIOS ALTER COLUMN CPF nvarchar(14) NOT NULL;
        PRINT '    + coluna CPF ampliada para 14 caracteres';
    END
END
GO

IF OBJECT_ID(N'dbo.TB_USUARIOS', N'U') IS NULL
    THROW 51010, 'A tabela dbo.TB_USUARIOS não existe.', 1;
IF NOT EXISTS (
    SELECT 1 FROM sys.columns
     WHERE object_id = OBJECT_ID(N'dbo.TB_USUARIOS') AND name = N'SENHA'
)
    THROW 51011, 'A coluna dbo.TB_USUARIOS.SENHA não existe.', 1;
GO

IF EXISTS (SELECT 1 FROM dbo.TB_USUARIOS WHERE SENHA IS NULL)
    THROW 51012, 'Há senhas nulas em TB_USUARIOS; corrija esses registros antes de atualizar.', 1;
GO

IF EXISTS (
    SELECT 1 FROM sys.columns
     WHERE object_id = OBJECT_ID(N'dbo.TB_USUARIOS')
       AND name = N'SENHA'
       AND (user_type_id <> TYPE_ID(N'nvarchar') OR max_length <> 510)
)
BEGIN
    ALTER TABLE dbo.TB_USUARIOS ALTER COLUMN SENHA nvarchar(255) NOT NULL;
    PRINT '    + coluna SENHA ampliada para nvarchar(255)';
END
GO

IF OBJECT_ID(N'dbo.TB_LOGIN_TENTATIVAS', N'U') IS NULL
BEGIN
    CREATE TABLE dbo.TB_LOGIN_TENTATIVAS (
        CHAVE_HASH     char(64)     NOT NULL,
        FALHAS         tinyint      NOT NULL,
        INICIO_JANELA  datetime2(3) NOT NULL,
        BLOQUEADO_ATE  datetime2(3) NULL,
        ATUALIZADO_EM  datetime2(3) NOT NULL,
        CONSTRAINT PK_TB_LOGIN_TENTATIVAS
            PRIMARY KEY CLUSTERED (CHAVE_HASH),
        CONSTRAINT CK_TB_LOGIN_TENTATIVAS_FALHAS
            CHECK (FALHAS BETWEEN 1 AND 10)
    );
    PRINT '  + TB_LOGIN_TENTATIVAS criada';
END
ELSE
    PRINT '  = TB_LOGIN_TENTATIVAS já existe';
GO

IF NOT EXISTS (
    SELECT 1 FROM sys.indexes
     WHERE object_id = OBJECT_ID(N'dbo.TB_LOGIN_TENTATIVAS')
       AND name = N'IX_TB_LOGIN_TENTATIVAS_ATUALIZADO'
)
    CREATE INDEX IX_TB_LOGIN_TENTATIVAS_ATUALIZADO
        ON dbo.TB_LOGIN_TENTATIVAS (ATUALIZADO_EM);
GO

/* Atualiza bases preexistentes quando a instalação completa for reaplicada. */
IF EXISTS (SELECT 1 FROM sys.check_constraints
           WHERE parent_object_id = OBJECT_ID('dbo.TB_USUARIOS')
             AND name = 'CK_USUARIOS_NIVEL'
             AND (definition NOT LIKE '%4%' OR definition NOT LIKE '%5%'))
    ALTER TABLE dbo.TB_USUARIOS DROP CONSTRAINT CK_USUARIOS_NIVEL;
GO
IF OBJECT_ID(N'dbo.TB_USUARIOS', N'U') IS NOT NULL
   AND NOT EXISTS (SELECT 1 FROM sys.check_constraints
                   WHERE parent_object_id = OBJECT_ID('dbo.TB_USUARIOS')
                     AND name = 'CK_USUARIOS_NIVEL')
    ALTER TABLE dbo.TB_USUARIOS ADD CONSTRAINT CK_USUARIOS_NIVEL
        CHECK (NIVEL IN (1, 2, 3, 4, 5));
GO

/* ============================================================================
   4. TB_ACESSO — vínculo usuário <-> unidade
   ============================================================================ */
IF OBJECT_ID(N'dbo.TB_ACESSO', N'U') IS NULL
BEGIN
    CREATE TABLE dbo.TB_ACESSO (
        id            int IDENTITY(1,1) NOT NULL,
        id_usuario    int      NOT NULL,
        id_unidade    int      NOT NULL,
        ativo         bit      NOT NULL CONSTRAINT DF_ACESSO_ATIVO DEFAULT (1),
        data_cadastro datetime NOT NULL CONSTRAINT DF_ACESSO_DATA DEFAULT (GETDATE()),
        desativado    datetime NULL,
        CONSTRAINT PK_TB_ACESSO PRIMARY KEY CLUSTERED (id),
        CONSTRAINT UQ_ACESSO_USUARIO_UNIDADE UNIQUE (id_usuario, id_unidade),
        CONSTRAINT FK_ACESSO_USUARIO FOREIGN KEY (id_usuario) REFERENCES dbo.TB_USUARIOS (ID),
        CONSTRAINT FK_ACESSO_UNIDADE FOREIGN KEY (id_unidade) REFERENCES dbo.TB_UNIDADES (id)
    );
    CREATE INDEX IX_ACESSO_USUARIO ON dbo.TB_ACESSO (id_usuario, ativo);
    PRINT '  + TB_ACESSO criada';
END
ELSE
    PRINT '  = TB_ACESSO já existe';
GO

/* Autorização individual, concedida pelo Diretor, para Secretário ler relatórios. */
IF OBJECT_ID(N'dbo.TB_SECRETARIO_RELATORIOS', N'U') IS NULL
BEGIN
    CREATE TABLE dbo.TB_SECRETARIO_RELATORIOS (
        ID_USUARIO     int      NOT NULL,
        ID_UNIDADE     int      NOT NULL,
        PERMITIDO      bit      NOT NULL CONSTRAINT DF_SEC_REL_PERMITIDO DEFAULT (0),
        ID_DIRETOR     int      NULL,
        ATUALIZADO_EM  datetime NOT NULL CONSTRAINT DF_SEC_REL_ATUALIZADO DEFAULT (GETDATE()),
        CONSTRAINT PK_SECRETARIO_RELATORIO PRIMARY KEY CLUSTERED (ID_USUARIO, ID_UNIDADE),
        CONSTRAINT FK_SEC_REL_USUARIO FOREIGN KEY (ID_USUARIO) REFERENCES dbo.TB_USUARIOS (ID),
        CONSTRAINT FK_SEC_REL_UNIDADE FOREIGN KEY (ID_UNIDADE) REFERENCES dbo.TB_UNIDADES (id),
        CONSTRAINT FK_SEC_REL_DIRETOR FOREIGN KEY (ID_DIRETOR) REFERENCES dbo.TB_USUARIOS (ID)
    );
    CREATE INDEX IX_SEC_REL_UNIDADE ON dbo.TB_SECRETARIO_RELATORIOS (ID_UNIDADE, PERMITIDO);
    PRINT '  + TB_SECRETARIO_RELATORIOS criada';
END
ELSE
    PRINT '  = TB_SECRETARIO_RELATORIOS já existe';
GO

/* Autorização individual, concedida pelo Diretor, para Secretário registrar observações. */
IF OBJECT_ID(N'dbo.TB_SECRETARIO_OBSERVACOES', N'U') IS NULL
BEGIN
    CREATE TABLE dbo.TB_SECRETARIO_OBSERVACOES (
        ID_USUARIO     int      NOT NULL,
        ID_UNIDADE     int      NOT NULL,
        PERMITIDO      tinyint  NOT NULL CONSTRAINT DF_SEC_OBS_PERMITIDO DEFAULT (0),
        ID_DIRETOR     int      NULL,
        ATUALIZADO_EM  datetime NOT NULL CONSTRAINT DF_SEC_OBS_ATUALIZADO DEFAULT (GETDATE()),
        CONSTRAINT PK_SECRETARIO_OBSERVACAO PRIMARY KEY CLUSTERED (ID_USUARIO, ID_UNIDADE),
        CONSTRAINT FK_SEC_OBS_USUARIO FOREIGN KEY (ID_USUARIO) REFERENCES dbo.TB_USUARIOS (ID),
        CONSTRAINT FK_SEC_OBS_UNIDADE FOREIGN KEY (ID_UNIDADE) REFERENCES dbo.TB_UNIDADES (id),
        CONSTRAINT FK_SEC_OBS_DIRETOR FOREIGN KEY (ID_DIRETOR) REFERENCES dbo.TB_USUARIOS (ID),
        CONSTRAINT CK_SEC_OBS_PERMITIDO CHECK (PERMITIDO IN (0, 1, 2))
    );
    CREATE INDEX IX_SEC_OBS_UNIDADE ON dbo.TB_SECRETARIO_OBSERVACOES (ID_UNIDADE, PERMITIDO);
    PRINT '  + TB_SECRETARIO_OBSERVACOES criada';
END
ELSE
    PRINT '  = TB_SECRETARIO_OBSERVACOES já existe';
GO

/* ============================================================================ 
   5. TB_PERIODOS — Manhã, Tarde, Integral
      Horários como nvarchar(5) 'HH:MM': evita o driver sqlsrv devolver
      objetos DateTime para colunas time, e a comparação lexicográfica
      de 'HH:MM' é equivalente à comparação de horários.
   ============================================================================ */
IF OBJECT_ID(N'dbo.TB_PERIODOS', N'U') IS NULL
BEGIN
    CREATE TABLE dbo.TB_PERIODOS (
        ID          int IDENTITY(1,1) NOT NULL,
        NOME        nvarchar(60) NOT NULL,
        HORA_INICIO nvarchar(5)  NOT NULL,
        HORA_FIM    nvarchar(5)  NOT NULL,
        ATIVO       bit          NOT NULL CONSTRAINT DF_PERIODOS_ATIVO DEFAULT (1),
        CREATED     datetime     NOT NULL CONSTRAINT DF_PERIODOS_CREATED DEFAULT (GETDATE()),
        CONSTRAINT PK_TB_PERIODOS PRIMARY KEY CLUSTERED (ID),
        CONSTRAINT UQ_PERIODOS_NOME UNIQUE (NOME),
        CONSTRAINT CK_PERIODOS_HORA CHECK (HORA_FIM > HORA_INICIO)
    );
    PRINT '  + TB_PERIODOS criada';
END
ELSE
    PRINT '  = TB_PERIODOS já existe';
GO

/* ============================================================================
   6. TB_TURMAS
   ============================================================================ */
IF OBJECT_ID(N'dbo.TB_TURMAS', N'U') IS NULL
BEGIN
    CREATE TABLE dbo.TB_TURMAS (
        ID           int IDENTITY(1,1) NOT NULL,
        ID_UNIDADE   int          NOT NULL,
        NOME         nvarchar(80) NOT NULL,
        ETAPA_ENSINO nvarchar(30) NULL,
        ANO_LETIVO   int          NOT NULL,
        ID_PERIODO   int          NOT NULL,
        ID_PROFESSOR int          NULL,
        CAPACIDADE   int          NOT NULL CONSTRAINT DF_TURMAS_CAP DEFAULT (20),
        NOTAS_ATIVAS bit          NOT NULL CONSTRAINT DF_TURMAS_NOTAS_ATIVAS DEFAULT (0),
        ATIVO        bit          NOT NULL CONSTRAINT DF_TURMAS_ATIVO DEFAULT (1),
        CREATED      datetime     NOT NULL CONSTRAINT DF_TURMAS_CREATED DEFAULT (GETDATE()),
        CONSTRAINT PK_TB_TURMAS PRIMARY KEY CLUSTERED (ID),
        CONSTRAINT UQ_TURMAS UNIQUE (ID_UNIDADE, NOME, ANO_LETIVO),
        CONSTRAINT FK_TURMAS_UNIDADE   FOREIGN KEY (ID_UNIDADE)   REFERENCES dbo.TB_UNIDADES (id),
        CONSTRAINT FK_TURMAS_PERIODO   FOREIGN KEY (ID_PERIODO)   REFERENCES dbo.TB_PERIODOS (ID),
        CONSTRAINT FK_TURMAS_PROFESSOR FOREIGN KEY (ID_PROFESSOR) REFERENCES dbo.TB_USUARIOS (ID),
        CONSTRAINT CK_TURMAS_ANO CHECK (ANO_LETIVO BETWEEN 2000 AND 2100),
        CONSTRAINT CK_TURMAS_CAP CHECK (CAPACIDADE BETWEEN 1 AND 500),
        CONSTRAINT CK_TURMAS_ETAPA_ENSINO CHECK (ETAPA_ENSINO IS NULL OR ETAPA_ENSINO IN
            ('INFANTIL_CRECHE', 'INFANTIL_PRE_ESCOLA', 'FUNDAMENTAL_1_5', 'FUNDAMENTAL_6_9'))
    );
    CREATE INDEX IX_TURMAS_PROFESSOR ON dbo.TB_TURMAS (ID_PROFESSOR);
    CREATE INDEX IX_TURMAS_UNIDADE   ON dbo.TB_TURMAS (ID_UNIDADE, ANO_LETIVO);
    PRINT '  + TB_TURMAS criada';
END
ELSE
    PRINT '  = TB_TURMAS já existe';
GO

IF COL_LENGTH('dbo.TB_TURMAS', 'NOTAS_ATIVAS') IS NULL
BEGIN
    ALTER TABLE dbo.TB_TURMAS ADD NOTAS_ATIVAS bit NOT NULL
        CONSTRAINT DF_TURMAS_NOTAS_ATIVAS DEFAULT (0);
    PRINT '  + TB_TURMAS.NOTAS_ATIVAS adicionada (notas desativadas por padrão)';
END
GO

IF COL_LENGTH('dbo.TB_TURMAS', 'ETAPA_ENSINO') IS NULL
    ALTER TABLE dbo.TB_TURMAS ADD ETAPA_ENSINO nvarchar(30) NULL;
GO

IF NOT EXISTS (SELECT 1 FROM sys.check_constraints
               WHERE parent_object_id = OBJECT_ID('dbo.TB_TURMAS') AND name = 'CK_TURMAS_ETAPA_ENSINO')
    ALTER TABLE dbo.TB_TURMAS ADD CONSTRAINT CK_TURMAS_ETAPA_ENSINO
        CHECK (ETAPA_ENSINO IS NULL OR ETAPA_ENSINO IN
            ('INFANTIL_CRECHE', 'INFANTIL_PRE_ESCOLA', 'FUNDAMENTAL_1_5', 'FUNDAMENTAL_6_9'));
GO

/* ============================================================================
   7. TB_ALUNOS
   ============================================================================ */
IF OBJECT_ID(N'dbo.TB_ALUNOS', N'U') IS NULL
BEGIN
    CREATE TABLE dbo.TB_ALUNOS (
        ID             int IDENTITY(1,1) NOT NULL,
        ID_TURMA       int           NOT NULL,
        MATRICULA      nvarchar(30)  NOT NULL,
        CPF            nvarchar(11)  NOT NULL,
        NOME           nvarchar(150) NOT NULL,
        NASCIMENTO     date          NOT NULL,
        DATA_MATRICULA date          NOT NULL,
        RESPONSAVEL    nvarchar(150) NOT NULL,
        TELEFONE       nvarchar(30)  NOT NULL,
        EMAIL          nvarchar(150) NULL,
        FOTO_DADOS     varbinary(max) NULL,
        FOTO_MIME      nvarchar(30)  NULL,
        ATIVO          bit           NOT NULL CONSTRAINT DF_ALUNOS_ATIVO DEFAULT (1),
        CREATED        datetime      NOT NULL CONSTRAINT DF_ALUNOS_CREATED DEFAULT (GETDATE()),
        CONSTRAINT PK_TB_ALUNOS PRIMARY KEY CLUSTERED (ID),
        CONSTRAINT UQ_ALUNOS_MATRICULA UNIQUE (MATRICULA),
        CONSTRAINT CK_ALUNOS_CPF CHECK (LEN(CPF) = 11 AND CPF COLLATE Latin1_General_BIN2 NOT LIKE '%[^0-9]%'),
        CONSTRAINT FK_ALUNOS_TURMA FOREIGN KEY (ID_TURMA) REFERENCES dbo.TB_TURMAS (ID),
        CONSTRAINT CK_ALUNOS_NASC CHECK (NASCIMENTO >= '1900-01-01')
    );
    CREATE INDEX IX_ALUNOS_TURMA ON dbo.TB_ALUNOS (ID_TURMA, ATIVO);
    CREATE INDEX IX_ALUNOS_NOME  ON dbo.TB_ALUNOS (NOME);
    PRINT '  + TB_ALUNOS criada';
END
ELSE
    PRINT '  = TB_ALUNOS já existe';
GO

IF COL_LENGTH('dbo.TB_ALUNOS', 'FOTO_DADOS') IS NULL
BEGIN
    ALTER TABLE dbo.TB_ALUNOS ADD FOTO_DADOS varbinary(max) NULL;
    PRINT '  + TB_ALUNOS.FOTO_DADOS adicionada';
END
ELSE
    PRINT '  = TB_ALUNOS.FOTO_DADOS já existe';
GO

IF COL_LENGTH('dbo.TB_ALUNOS', 'FOTO_MIME') IS NULL
BEGIN
    ALTER TABLE dbo.TB_ALUNOS ADD FOTO_MIME nvarchar(30) NULL;
    PRINT '  + TB_ALUNOS.FOTO_MIME adicionada';
END
ELSE
    PRINT '  = TB_ALUNOS.FOTO_MIME já existe';
GO

IF COL_LENGTH('dbo.TB_ALUNOS', 'CPF') IS NULL
BEGIN
    ALTER TABLE dbo.TB_ALUNOS ADD CPF nvarchar(11) NULL;
    PRINT '  + TB_ALUNOS.CPF adicionada (alunos existentes precisam ser atualizados)';
END
ELSE
    PRINT '  = TB_ALUNOS.CPF já existe';
GO

IF NOT EXISTS (SELECT 1 FROM sys.check_constraints WHERE name = 'CK_ALUNOS_CPF')
BEGIN
    ALTER TABLE dbo.TB_ALUNOS ADD CONSTRAINT CK_ALUNOS_CPF
        CHECK (CPF IS NULL OR (LEN(CPF) = 11 AND CPF COLLATE Latin1_General_BIN2 NOT LIKE '%[^0-9]%'));
    PRINT '  + CK_ALUNOS_CPF criada';
END
GO

IF EXISTS (
    SELECT CPF FROM dbo.TB_ALUNOS
    WHERE CPF IS NOT NULL
    GROUP BY CPF HAVING COUNT(*) > 1
)
    THROW 50001, 'Há CPFs duplicados em TB_ALUNOS. Corrija-os antes de criar o índice único.', 1;
GO

IF NOT EXISTS (SELECT 1 FROM sys.indexes
               WHERE object_id = OBJECT_ID('dbo.TB_ALUNOS') AND name = 'UX_ALUNOS_CPF')
BEGIN
    CREATE UNIQUE INDEX UX_ALUNOS_CPF ON dbo.TB_ALUNOS (CPF) WHERE CPF IS NOT NULL;
    PRINT '  + UX_ALUNOS_CPF criado';
END
GO

IF OBJECT_ID(N'dbo.TB_TURMA_DISCIPLINAS', N'U') IS NULL
BEGIN
    CREATE TABLE dbo.TB_TURMA_DISCIPLINAS (
        ID       int IDENTITY(1,1) NOT NULL,
        ID_TURMA int           NOT NULL,
        NOME     nvarchar(80)  NOT NULL,
        ID_PROFESSOR int       NULL,
        CREATED  datetime      NOT NULL CONSTRAINT DF_TURMA_DISC_CREATED DEFAULT (GETDATE()),
        CONSTRAINT PK_TB_TURMA_DISCIPLINAS PRIMARY KEY CLUSTERED (ID),
        CONSTRAINT UQ_TURMA_DISCIPLINA UNIQUE (ID_TURMA, NOME),
        CONSTRAINT FK_TURMA_DISC_TURMA FOREIGN KEY (ID_TURMA) REFERENCES dbo.TB_TURMAS (ID),
        CONSTRAINT FK_TURMA_DISC_PROFESSOR FOREIGN KEY (ID_PROFESSOR) REFERENCES dbo.TB_USUARIOS (ID),
        CONSTRAINT CK_TURMA_DISC_NOME CHECK (LEN(LTRIM(RTRIM(NOME))) BETWEEN 1 AND 80)
    );
    PRINT '  + TB_TURMA_DISCIPLINAS criada';
END
GO

IF COL_LENGTH('dbo.TB_TURMA_DISCIPLINAS', 'ID_PROFESSOR') IS NULL
    ALTER TABLE dbo.TB_TURMA_DISCIPLINAS ADD ID_PROFESSOR int NULL;
GO

IF NOT EXISTS (SELECT 1 FROM sys.foreign_keys
               WHERE parent_object_id = OBJECT_ID('dbo.TB_TURMA_DISCIPLINAS')
                 AND name = 'FK_TURMA_DISC_PROFESSOR')
    ALTER TABLE dbo.TB_TURMA_DISCIPLINAS ADD CONSTRAINT FK_TURMA_DISC_PROFESSOR
        FOREIGN KEY (ID_PROFESSOR) REFERENCES dbo.TB_USUARIOS (ID);
GO

IF NOT EXISTS (SELECT 1 FROM sys.indexes
               WHERE object_id = OBJECT_ID('dbo.TB_TURMA_DISCIPLINAS') AND name = 'IX_TURMA_DISC_PROFESSOR')
    CREATE INDEX IX_TURMA_DISC_PROFESSOR ON dbo.TB_TURMA_DISCIPLINAS (ID_PROFESSOR);
GO

IF NOT EXISTS (SELECT 1 FROM sys.indexes
               WHERE object_id = OBJECT_ID('dbo.TB_TURMA_DISCIPLINAS') AND name = 'UQ_TURMA_DISCIPLINA')
    CREATE UNIQUE INDEX UQ_TURMA_DISCIPLINA ON dbo.TB_TURMA_DISCIPLINAS (ID_TURMA, NOME);
GO

IF OBJECT_ID(N'dbo.TB_NOTAS', N'U') IS NULL
BEGIN
    CREATE TABLE dbo.TB_NOTAS (
        ID           int IDENTITY(1,1) NOT NULL,
        ID_ALUNO     int           NOT NULL,
        ID_DISCIPLINA int          NOT NULL,
        BIMESTRE     tinyint       NOT NULL,
        NOTA         decimal(4,2)  NOT NULL,
        ID_USUARIO   int           NOT NULL,
        ATUALIZADA_EM datetime     NOT NULL CONSTRAINT DF_NOTAS_ATUALIZADA DEFAULT (GETDATE()),
        CONSTRAINT PK_TB_NOTAS PRIMARY KEY CLUSTERED (ID),
        CONSTRAINT UQ_NOTA_ALUNO_DISC_BIM UNIQUE (ID_ALUNO, ID_DISCIPLINA, BIMESTRE),
        CONSTRAINT FK_NOTA_ALUNO FOREIGN KEY (ID_ALUNO) REFERENCES dbo.TB_ALUNOS (ID),
        CONSTRAINT FK_NOTA_DISCIPLINA FOREIGN KEY (ID_DISCIPLINA) REFERENCES dbo.TB_TURMA_DISCIPLINAS (ID),
        CONSTRAINT FK_NOTA_USUARIO FOREIGN KEY (ID_USUARIO) REFERENCES dbo.TB_USUARIOS (ID),
        CONSTRAINT CK_NOTA_BIMESTRE CHECK (BIMESTRE BETWEEN 1 AND 4),
        CONSTRAINT CK_NOTA_VALOR CHECK (NOTA BETWEEN 0 AND 10)
    );
    CREATE INDEX IX_NOTAS_DISC_BIM ON dbo.TB_NOTAS (ID_DISCIPLINA, BIMESTRE);
    PRINT '  + TB_NOTAS criada';
END
GO

IF NOT EXISTS (SELECT 1 FROM sys.indexes
               WHERE object_id = OBJECT_ID('dbo.TB_NOTAS') AND name = 'UQ_NOTA_ALUNO_DISC_BIM')
    CREATE UNIQUE INDEX UQ_NOTA_ALUNO_DISC_BIM ON dbo.TB_NOTAS (ID_ALUNO, ID_DISCIPLINA, BIMESTRE);
GO

IF NOT EXISTS (SELECT 1 FROM sys.indexes
               WHERE object_id = OBJECT_ID('dbo.TB_NOTAS') AND name = 'IX_NOTAS_DISC_BIM')
    CREATE INDEX IX_NOTAS_DISC_BIM ON dbo.TB_NOTAS (ID_DISCIPLINA, BIMESTRE);
GO

/* ============================================================================ 
   8. TB_FERIADOS
      TIPO = INTEGRAL: bloqueia o dia todo.
      TIPO = PARCIAL : HORA_INICIO/HORA_FIM guardam a faixa SEM AULA; bloqueia
                       só as turmas cujo período cruza essa faixa.
      ID_UNIDADE NULL = vale para todas as unidades.
   ============================================================================ */
IF OBJECT_ID(N'dbo.TB_FERIADOS', N'U') IS NULL
BEGIN
    CREATE TABLE dbo.TB_FERIADOS (
        ID          int IDENTITY(1,1) NOT NULL,
        DATA        date          NOT NULL,
        DESCRICAO   nvarchar(150) NOT NULL,
        TIPO        nvarchar(10)  NOT NULL,
        HORA_INICIO nvarchar(5)   NULL,
        HORA_FIM    nvarchar(5)   NULL,
        ID_UNIDADE  int           NULL,
        CREATED     datetime      NOT NULL CONSTRAINT DF_FERIADOS_CREATED DEFAULT (GETDATE()),
        CONSTRAINT PK_TB_FERIADOS PRIMARY KEY CLUSTERED (ID),
        CONSTRAINT FK_FERIADOS_UNIDADE FOREIGN KEY (ID_UNIDADE) REFERENCES dbo.TB_UNIDADES (id),
        CONSTRAINT CK_FERIADOS_TIPO CHECK (TIPO IN ('INTEGRAL', 'PARCIAL')),
        CONSTRAINT CK_FERIADOS_HORA CHECK (
            TIPO = 'INTEGRAL'
            OR (HORA_INICIO IS NOT NULL AND HORA_FIM IS NOT NULL AND HORA_FIM > HORA_INICIO)
        )
    );

    -- Um feriado por data e abrangência. Índice filtrado porque o SQL Server
    -- trata NULL como distinto em UNIQUE comum, o que permitiria duplicar
    -- feriados de abrangência "todas as unidades".
    CREATE UNIQUE INDEX UQ_FERIADOS_DATA_TODAS
        ON dbo.TB_FERIADOS (DATA) WHERE ID_UNIDADE IS NULL;
    CREATE UNIQUE INDEX UQ_FERIADOS_DATA_UNIDADE
        ON dbo.TB_FERIADOS (DATA, ID_UNIDADE) WHERE ID_UNIDADE IS NOT NULL;

    PRINT '  + TB_FERIADOS criada';
END
ELSE
    PRINT '  = TB_FERIADOS já existe';
GO

/* ============================================================================
   9. TB_CHAMADA
      STATUS: P = Presente | A = Atraso | F = Falta | J = Falta justificada
      P e A contam como presença no cálculo de frequência.
      J exige justificativa (garantido por CHECK).
   ============================================================================ */
IF OBJECT_ID(N'dbo.TB_CHAMADA', N'U') IS NULL
BEGIN
    CREATE TABLE dbo.TB_CHAMADA (
        ID            int IDENTITY(1,1) NOT NULL,
        ID_ALUNO      int           NOT NULL,
        DATA          date          NOT NULL,
        STATUS        nvarchar(1)   NOT NULL,
        JUSTIFICATIVA nvarchar(500) NULL,
        ID_USUARIO    int           NOT NULL,
        CONFIRMADA_EM  datetime     NULL,
        CONFIRMADA_POR int          NULL,
        CREATED       datetime      NOT NULL CONSTRAINT DF_CHAMADA_CREATED DEFAULT (GETDATE()),
        MODIFIED      datetime      NULL,
        CONSTRAINT PK_TB_CHAMADA PRIMARY KEY CLUSTERED (ID),
        CONSTRAINT UQ_CHAMADA_ALUNO_DATA UNIQUE (ID_ALUNO, DATA),
        CONSTRAINT FK_CHAMADA_ALUNO   FOREIGN KEY (ID_ALUNO)   REFERENCES dbo.TB_ALUNOS (ID),
        CONSTRAINT FK_CHAMADA_USUARIO FOREIGN KEY (ID_USUARIO) REFERENCES dbo.TB_USUARIOS (ID),
        CONSTRAINT CK_CHAMADA_STATUS CHECK (STATUS IN ('P', 'F', 'J', 'A')),
        CONSTRAINT CK_CHAMADA_JUST CHECK (
            STATUS <> 'J' OR (JUSTIFICATIVA IS NOT NULL AND LTRIM(RTRIM(JUSTIFICATIVA)) <> '')
        )
    );
    CREATE INDEX IX_CHAMADA_DATA ON dbo.TB_CHAMADA (DATA);
    PRINT '  + TB_CHAMADA criada';
END
ELSE
    PRINT '  = TB_CHAMADA já existe';
GO

/* Colunas de confirmação em bases já existentes (upgrade). */
IF COL_LENGTH('dbo.TB_CHAMADA', 'CONFIRMADA_EM') IS NULL
BEGIN
    ALTER TABLE dbo.TB_CHAMADA ADD CONFIRMADA_EM datetime NULL;
    PRINT '  + TB_CHAMADA.CONFIRMADA_EM adicionada';
END
IF COL_LENGTH('dbo.TB_CHAMADA', 'CONFIRMADA_POR') IS NULL
BEGIN
    ALTER TABLE dbo.TB_CHAMADA ADD CONFIRMADA_POR int NULL;
    PRINT '  + TB_CHAMADA.CONFIRMADA_POR adicionada';
END
IF NOT EXISTS (SELECT 1 FROM sys.foreign_keys WHERE name = 'FK_CHAMADA_CONFIRMOU')
BEGIN
    ALTER TABLE dbo.TB_CHAMADA ADD CONSTRAINT FK_CHAMADA_CONFIRMOU
        FOREIGN KEY (CONFIRMADA_POR) REFERENCES dbo.TB_USUARIOS (ID);
    PRINT '  + FK_CHAMADA_CONFIRMOU criada';
END
/* Registros antigos: quem lançou é quem confirmou. */
UPDATE dbo.TB_CHAMADA
   SET CONFIRMADA_EM  = ISNULL(CONFIRMADA_EM, CREATED),
       CONFIRMADA_POR = ISNULL(CONFIRMADA_POR, ID_USUARIO)
 WHERE CONFIRMADA_EM IS NULL OR CONFIRMADA_POR IS NULL;
GO

/* ============================================================================
   10. TB_TURMA_PROFESSORES — equipe da turma (vários professores)
   ----------------------------------------------------------------------------
   Substitui o professor único. TB_TURMAS.ID_PROFESSOR é mantido apenas como
   legado, sincronizado com o REGENTE, para não quebrar consultas antigas.
   ============================================================================ */
PRINT '';
PRINT '10. TB_TURMA_PROFESSORES — equipe da turma';

IF OBJECT_ID('dbo.TB_TURMA_PROFESSORES', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.TB_TURMA_PROFESSORES (
        ID         int IDENTITY(1,1) NOT NULL,
        ID_TURMA   int          NOT NULL,
        ID_USUARIO int          NOT NULL,
        PAPEL      nvarchar(10) NOT NULL CONSTRAINT DF_TURMAPROF_PAPEL   DEFAULT ('AUXILIAR'),
        ATIVO      bit          NOT NULL CONSTRAINT DF_TURMAPROF_ATIVO   DEFAULT (1),
        CREATED    datetime     NOT NULL CONSTRAINT DF_TURMAPROF_CREATED DEFAULT (GETDATE()),
        CONSTRAINT PK_TB_TURMA_PROFESSORES PRIMARY KEY CLUSTERED (ID),
        CONSTRAINT UQ_TURMAPROF_TURMA_USUARIO UNIQUE (ID_TURMA, ID_USUARIO),
        CONSTRAINT FK_TURMAPROF_TURMA   FOREIGN KEY (ID_TURMA)   REFERENCES dbo.TB_TURMAS (ID),
        CONSTRAINT FK_TURMAPROF_USUARIO FOREIGN KEY (ID_USUARIO) REFERENCES dbo.TB_USUARIOS (ID),
        CONSTRAINT CK_TURMAPROF_PAPEL CHECK (PAPEL IN ('REGENTE', 'AUXILIAR'))
    );
    CREATE INDEX IX_TURMAPROF_USUARIO ON dbo.TB_TURMA_PROFESSORES (ID_USUARIO);
    PRINT '  + TB_TURMA_PROFESSORES criada';
END

/* No máximo um regente ativo por turma. Índice filtrado: em SQL Server um
   UNIQUE comum não impediria vários regentes quando a coluna fosse NULL. */
IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'UX_TURMAPROF_REGENTE')
BEGIN
    CREATE UNIQUE INDEX UX_TURMAPROF_REGENTE
        ON dbo.TB_TURMA_PROFESSORES (ID_TURMA)
        WHERE PAPEL = 'REGENTE' AND ATIVO = 1;
    PRINT '  + UX_TURMAPROF_REGENTE criado (um regente por turma)';
END
GO

/* Migração: o professor único de cada turma vira o REGENTE da equipe. */
INSERT INTO dbo.TB_TURMA_PROFESSORES (ID_TURMA, ID_USUARIO, PAPEL, ATIVO)
SELECT t.ID, t.ID_PROFESSOR, 'REGENTE', 1
FROM dbo.TB_TURMAS t
WHERE t.ID_PROFESSOR IS NOT NULL
  AND NOT EXISTS (
      SELECT 1 FROM dbo.TB_TURMA_PROFESSORES tp
      WHERE tp.ID_TURMA = t.ID AND tp.ID_USUARIO = t.ID_PROFESSOR
  );
IF @@ROWCOUNT > 0 PRINT '  + professores únicos migrados para a equipe como REGENTE';
GO

/* ============================================================================
   11. TB_CHAMADA_RETIFICACOES — histórico de correções da chamada
   ----------------------------------------------------------------------------
   Depois de confirmada, a presença do aluno no dia não pode ser confirmada de
   novo: só retificada, com motivo obrigatório. Cada retificação guarda o valor
   anterior, o novo, quem fez e quando.
   ============================================================================ */
PRINT '';
PRINT '11. TB_CHAMADA_RETIFICACOES — histórico de retificações';

IF OBJECT_ID('dbo.TB_CHAMADA_RETIFICACOES', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.TB_CHAMADA_RETIFICACOES (
        ID                     int IDENTITY(1,1) NOT NULL,
        ID_CHAMADA             int           NOT NULL,
        STATUS_ANTERIOR        nvarchar(1)   NOT NULL,
        STATUS_NOVO            nvarchar(1)   NOT NULL,
        JUSTIFICATIVA_ANTERIOR nvarchar(500) NULL,
        JUSTIFICATIVA_NOVA     nvarchar(500) NULL,
        MOTIVO                 nvarchar(500) NOT NULL,
        ID_USUARIO             int           NOT NULL,
        CREATED                datetime      NOT NULL CONSTRAINT DF_RETIF_CREATED DEFAULT (GETDATE()),
        CONSTRAINT PK_TB_CHAMADA_RETIFICACOES PRIMARY KEY CLUSTERED (ID),
        CONSTRAINT FK_RETIF_CHAMADA FOREIGN KEY (ID_CHAMADA) REFERENCES dbo.TB_CHAMADA (ID),
        CONSTRAINT FK_RETIF_USUARIO FOREIGN KEY (ID_USUARIO) REFERENCES dbo.TB_USUARIOS (ID),
        CONSTRAINT CK_RETIF_STATUS CHECK (STATUS_ANTERIOR IN ('P','F','J','A') AND STATUS_NOVO IN ('P','F','J','A')),
        CONSTRAINT CK_RETIF_MOTIVO CHECK (LTRIM(RTRIM(MOTIVO)) <> '')
    );
    CREATE INDEX IX_RETIF_CHAMADA ON dbo.TB_CHAMADA_RETIFICACOES (ID_CHAMADA);
    CREATE INDEX IX_RETIF_CREATED ON dbo.TB_CHAMADA_RETIFICACOES (CREATED);
    PRINT '  + TB_CHAMADA_RETIFICACOES criada';
END
GO

/* ============================================================================
   12. TB_OBSERVACOES — observações do professor por aluno
   ============================================================================ */
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
    PRINT '  + TB_OBSERVACOES criada';
END
ELSE
    PRINT '  = TB_OBSERVACOES já existe';
GO

/* ============================================================================
   13. TB_RELATORIOS_ANUAIS — parecer final por aluno/ano
   ============================================================================ */
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
        CONSTRAINT FK_REL_USUARIO FOREIGN KEY (ID_USUARIO) REFERENCES dbo.TB_USUARIOS (ID),
        CONSTRAINT CK_REL_ANO CHECK (ANO BETWEEN 2000 AND 2100)
    );
    PRINT '  + TB_RELATORIOS_ANUAIS criada';
END
ELSE
    PRINT '  = TB_RELATORIOS_ANUAIS já existe';
GO

IF OBJECT_ID(N'dbo.TB_HISTORICO_OBSERVACOES', N'U') IS NULL
BEGIN
    CREATE TABLE dbo.TB_HISTORICO_OBSERVACOES (
        ID int IDENTITY(1,1) NOT NULL,
        ID_OBSERVACAO int NOT NULL,
        ID_USUARIO_AUTOR int NOT NULL,
        ID_USUARIO_ALTERACAO int NOT NULL,
        ID_ALUNO int NOT NULL,
        DATA date NOT NULL,
        CATEGORIA nvarchar(40) NOT NULL,
        TEXTO nvarchar(max) NOT NULL,
        ALTERADA_EM datetime NOT NULL CONSTRAINT DF_HIST_OBS_DATA DEFAULT (GETDATE()),
        CONSTRAINT PK_TB_HISTORICO_OBSERVACOES PRIMARY KEY CLUSTERED (ID),
        CONSTRAINT FK_HIST_OBS_REGISTRO FOREIGN KEY (ID_OBSERVACAO) REFERENCES dbo.TB_OBSERVACOES (ID),
        CONSTRAINT FK_HIST_OBS_AUTOR FOREIGN KEY (ID_USUARIO_AUTOR) REFERENCES dbo.TB_USUARIOS (ID),
        CONSTRAINT FK_HIST_OBS_ALTERACAO FOREIGN KEY (ID_USUARIO_ALTERACAO) REFERENCES dbo.TB_USUARIOS (ID),
        CONSTRAINT FK_HIST_OBS_ALUNO FOREIGN KEY (ID_ALUNO) REFERENCES dbo.TB_ALUNOS (ID)
    );
    CREATE INDEX IX_HIST_OBS_REGISTRO ON dbo.TB_HISTORICO_OBSERVACOES (ID_OBSERVACAO, ID DESC);
END
GO

IF OBJECT_ID(N'dbo.TB_HISTORICO_RELATORIOS', N'U') IS NULL
BEGIN
    CREATE TABLE dbo.TB_HISTORICO_RELATORIOS (
        ID int IDENTITY(1,1) NOT NULL,
        ID_RELATORIO int NOT NULL,
        ID_USUARIO_AUTOR int NOT NULL,
        ID_USUARIO_ALTERACAO int NOT NULL,
        PARECER nvarchar(max) NULL,
        ENCAMINHAMENTOS nvarchar(max) NULL,
        ALTERADA_EM datetime NOT NULL CONSTRAINT DF_HIST_REL_DATA DEFAULT (GETDATE()),
        CONSTRAINT PK_TB_HISTORICO_RELATORIOS PRIMARY KEY CLUSTERED (ID),
        CONSTRAINT FK_HIST_REL_REGISTRO FOREIGN KEY (ID_RELATORIO) REFERENCES dbo.TB_RELATORIOS_ANUAIS (ID),
        CONSTRAINT FK_HIST_REL_AUTOR FOREIGN KEY (ID_USUARIO_AUTOR) REFERENCES dbo.TB_USUARIOS (ID),
        CONSTRAINT FK_HIST_REL_ALTERACAO FOREIGN KEY (ID_USUARIO_ALTERACAO) REFERENCES dbo.TB_USUARIOS (ID)
    );
    CREATE INDEX IX_HIST_REL_REGISTRO ON dbo.TB_HISTORICO_RELATORIOS (ID_RELATORIO, ID DESC);
END
GO

/* ============================================================================
   14. VIEW_ACESSOS_USUARIOS
       Corrige o script antigo, que trazia só o SELECT e nunca criava a view.
       Usada pelo gerenciamento de acesso por unidade.
   ============================================================================ */
CREATE OR ALTER VIEW dbo.VIEW_ACESSOS_USUARIOS
AS
    SELECT
        A.id            AS ID_ACESSO,
        U.NOME          AS NOME_USUARIO,
        UN.unidade      AS NOME_UNIDADE,
        A.ativo         AS ATIVO,
        A.desativado    AS DESATIVADO,
        A.id_usuario    AS ID_USUARIO,
        A.id_unidade    AS ID_UNIDADE
    FROM dbo.TB_ACESSO A
    INNER JOIN dbo.TB_USUARIOS U  ON U.ID = A.id_usuario
    INNER JOIN dbo.TB_UNIDADES UN ON UN.id = A.id_unidade;
GO
PRINT '  + VIEW_ACESSOS_USUARIOS criada/atualizada';
GO

/* ============================================================================
   15. DADOS INICIAIS — PERÍODOS
   ============================================================================ */
IF NOT EXISTS (SELECT 1 FROM dbo.TB_PERIODOS)
BEGIN
    INSERT INTO dbo.TB_PERIODOS (NOME, HORA_INICIO, HORA_FIM) VALUES
        (N'Manhã',    '07:00', '12:00'),
        (N'Tarde',    '13:00', '18:00'),
        (N'Integral', '07:00', '18:00');
    PRINT '  + períodos padrão inseridos (Manhã, Tarde, Integral)';
END
ELSE
    PRINT '  = períodos já cadastrados';
GO

/* ============================================================================
   16. DADOS INICIAIS — UNIDADES DA REDE MUNICIPAL
       Insere apenas as que ainda não existem, comparando pelo nome.
   ============================================================================ */
DECLARE @unidades TABLE (id int IDENTITY(1,1), nome nvarchar(255));

INSERT INTO @unidades (nome) VALUES
    (N'EMEF "SECRETARIA DE EDUCAÇÃO"'),
    (N'NISC "RECRIANÇA"'),
    (N'EMEIF “PROFA. MARIA INÊZ DOS SANTOS”'),
    (N'EMEIF “PROF. AFFONSO BASILE” - PEBI'),
    (N'EMEIF “PROF. AFFONSO BASILE” - PEBII'),
    (N'EMEIF “MARIA SALETE BASILE SALES”'),
    (N'EMEIF "PROFA.DIVA MORAES CAMARGO PUCCI"'),
    (N'EMEIF "FAZENDA POLENGHI"'),
    (N'EMEIF "BAIRRO SERRA DA BOA VISTA"'),
    (N'EMEIF "BAIRRO BATALHEIRA"'),
    (N'EMEF "PROFA.MARIA ISABEL LOPES DE OLIVEIRA"'),
    (N'EM “PROFESSORA HERMÍNIA ARAÚJO”'),
    (N'CMEI “VÓ CARMELA”'),
    (N'CMEI "VÓ IA"'),
    (N'CMEI "MENINO JESUS"'),
    (N'CMEI "CHICA GORDA"'),
    (N'CEMEIF “VÓ VIRGÍNIA”'),
    (N'CEMEIF “VÓ JOANINHA”'),
    (N'CEMEIF "NHÁ NICA"'),
    (N'EMEF "DR. FORTUNATO DE CAMARGO"');

INSERT INTO dbo.TB_UNIDADES (unidade)
SELECT u.nome
FROM @unidades u
WHERE NOT EXISTS (SELECT 1 FROM dbo.TB_UNIDADES t WHERE t.unidade = u.nome);

PRINT '  + unidades inseridas: ' + CAST(@@ROWCOUNT AS varchar(10));
GO

/* ============================================================================
   17. DADOS INICIAIS — USUÁRIO ADMINISTRADOR
       Criado SOMENTE se a tabela estiver vazia. Nunca sobrescreve usuários.

       CPF   : 398.114.718-94
       Senha : Trocar@2026   <-- TROQUE NO PRIMEIRO ACESSO

       Para usar outro CPF ou nome, edite os valores abaixo antes de executar.
   ============================================================================ */
IF NOT EXISTS (SELECT 1 FROM dbo.TB_USUARIOS)
BEGIN
    INSERT INTO dbo.TB_USUARIOS (NOME, SENHA, NIVEL, CPF, EMAIL, ATIVO, CREATED)
    VALUES (
        N'JOÃO MANOEL MEIRA DA SILVA',
        N'$2y$12$M48e1UNv8.j4oQWNGFjHs.vrGez0YR7syq/N50ShwLTRc1xdnw3ka',
        1,
        N'398.114.718-94',
        NULL,
        1,
        GETDATE()
    );

    DECLARE @novoAdmin int = SCOPE_IDENTITY();

    -- A gestão também precisa de vínculo em TB_ACESSO: as telas filtram por ele.
    INSERT INTO dbo.TB_ACESSO (id_usuario, id_unidade, ativo)
    SELECT @novoAdmin, u.id, 1 FROM dbo.TB_UNIDADES u;

    PRINT '  + administrador criado (CPF 398.114.718-94, senha Trocar@2026)';
    PRINT '    acesso vinculado a ' + CAST(@@ROWCOUNT AS varchar(10)) + ' unidades';
    PRINT '    *** TROQUE A SENHA NO PRIMEIRO ACESSO ***';
END
ELSE
    PRINT '  = já existem usuários; nenhum administrador foi criado';
GO

/* ============================================================================
   18. TRANSFERÊNCIA DE ALUNO ENTRE ESCOLAS
       O diretor da escola de origem indica a escola de destino; a escola
       de destino escolhe a turma e a data de início. Ver o comentário
       completo em ATUALIZAR_TRANSFERENCIAS.sql.
   ============================================================================ */
IF OBJECT_ID(N'dbo.TB_TRANSFERENCIAS', N'U') IS NULL
BEGIN
    CREATE TABLE dbo.TB_TRANSFERENCIAS (
        ID                  int IDENTITY(1,1) NOT NULL,
        ID_ALUNO            int           NOT NULL,
        ID_TURMA_ORIGEM     int           NOT NULL,
        ID_UNIDADE_DESTINO  int           NOT NULL,
        ID_TURMA_DESTINO    int           NULL,
        DATA_INICIO         date          NULL,
        SITUACAO            nvarchar(10)  NOT NULL CONSTRAINT DF_TRANSFER_SITUACAO DEFAULT ('PENDENTE'),
        MOTIVO              nvarchar(500) NOT NULL,
        DOCUMENTO           nvarchar(100) NULL,
        ID_USUARIO_SOLIC    int           NOT NULL,
        SOLICITADA_EM       datetime      NOT NULL CONSTRAINT DF_TRANSFER_SOLIC DEFAULT (GETDATE()),
        OBSERVACAO_DESTINO  nvarchar(500) NULL,
        ID_USUARIO_EFETIVA  int           NULL,
        EFETIVADA_EM        datetime      NULL,
        MOTIVO_CANCELAMENTO nvarchar(500) NULL,
        ID_USUARIO_CANCEL   int           NULL,
        CANCELADA_EM        datetime      NULL,
        CONSTRAINT PK_TB_TRANSFERENCIAS PRIMARY KEY CLUSTERED (ID),
        CONSTRAINT FK_TRANSFER_ALUNO   FOREIGN KEY (ID_ALUNO)           REFERENCES dbo.TB_ALUNOS (ID),
        CONSTRAINT FK_TRANSFER_ORIGEM  FOREIGN KEY (ID_TURMA_ORIGEM)    REFERENCES dbo.TB_TURMAS (ID),
        CONSTRAINT FK_TRANSFER_DESTINO FOREIGN KEY (ID_TURMA_DESTINO)   REFERENCES dbo.TB_TURMAS (ID),
        CONSTRAINT FK_TRANSFER_UNIDADE FOREIGN KEY (ID_UNIDADE_DESTINO) REFERENCES dbo.TB_UNIDADES (id),
        CONSTRAINT FK_TRANSFER_SOLIC   FOREIGN KEY (ID_USUARIO_SOLIC)   REFERENCES dbo.TB_USUARIOS (ID),
        CONSTRAINT FK_TRANSFER_EFETIVA FOREIGN KEY (ID_USUARIO_EFETIVA) REFERENCES dbo.TB_USUARIOS (ID),
        CONSTRAINT FK_TRANSFER_CANCEL  FOREIGN KEY (ID_USUARIO_CANCEL)  REFERENCES dbo.TB_USUARIOS (ID),
        CONSTRAINT CK_TRANSFER_SITUACAO CHECK (SITUACAO IN ('PENDENTE', 'CONCLUIDA', 'CANCELADA')),
        CONSTRAINT CK_TRANSFER_MOTIVO   CHECK (LTRIM(RTRIM(MOTIVO)) <> ''),
        CONSTRAINT CK_TRANSFER_CONCLUIDA CHECK (
            SITUACAO <> 'CONCLUIDA' OR (
                ID_TURMA_DESTINO IS NOT NULL AND DATA_INICIO IS NOT NULL
                AND ID_USUARIO_EFETIVA IS NOT NULL AND EFETIVADA_EM IS NOT NULL
            )
        ),
        CONSTRAINT CK_TRANSFER_PENDENTE CHECK (
            SITUACAO <> 'PENDENTE' OR (
                ID_TURMA_DESTINO IS NULL AND DATA_INICIO IS NULL
                AND ID_USUARIO_EFETIVA IS NULL AND EFETIVADA_EM IS NULL
            )
        ),
        CONSTRAINT CK_TRANSFER_CANCELADA CHECK (
            SITUACAO <> 'CANCELADA' OR (
                MOTIVO_CANCELAMENTO IS NOT NULL AND LTRIM(RTRIM(MOTIVO_CANCELAMENTO)) <> ''
                AND ID_USUARIO_CANCEL IS NOT NULL AND CANCELADA_EM IS NOT NULL
            )
        )
    );
    CREATE INDEX IX_TRANSFER_ALUNO   ON dbo.TB_TRANSFERENCIAS (ID_ALUNO, SITUACAO);
    CREATE INDEX IX_TRANSFER_UNIDADE ON dbo.TB_TRANSFERENCIAS (ID_UNIDADE_DESTINO, SITUACAO);
    CREATE INDEX IX_TRANSFER_ORIGEM  ON dbo.TB_TRANSFERENCIAS (ID_TURMA_ORIGEM);
    PRINT '  + TB_TRANSFERENCIAS criada';
END
ELSE
    PRINT '  = TB_TRANSFERENCIAS já existe';
GO

/* Um aluno não pode ter duas transferências aguardando ao mesmo tempo.
   Índice filtrado: a regra vale só para as PENDENTES, então o histórico de
   transferências concluídas do mesmo aluno não é limitado. */
IF NOT EXISTS (
    SELECT 1 FROM sys.indexes
    WHERE name = 'UX_TRANSFER_PENDENTE'
      AND object_id = OBJECT_ID(N'dbo.TB_TRANSFERENCIAS')
)
BEGIN
    CREATE UNIQUE INDEX UX_TRANSFER_PENDENTE
        ON dbo.TB_TRANSFERENCIAS (ID_ALUNO)
        WHERE SITUACAO = 'PENDENTE';
    PRINT '  + UX_TRANSFER_PENDENTE criado';
END
ELSE
    PRINT '  = UX_TRANSFER_PENDENTE já existe';
GO

IF OBJECT_ID(N'dbo.TB_ALUNO_SAIDAS_EXTERNAS', N'U') IS NULL
BEGIN
    CREATE TABLE dbo.TB_ALUNO_SAIDAS_EXTERNAS (
        ID                  int IDENTITY(1,1) NOT NULL,
        ID_ALUNO            int NOT NULL,
        ID_TURMA_ORIGEM     int NOT NULL,
        DATA_SAIDA          date NOT NULL,
        ESCOLA_DESTINO      nvarchar(150) NOT NULL,
        MUNICIPIO_DESTINO   nvarchar(100) NOT NULL,
        UF_DESTINO          char(2) NOT NULL,
        MOTIVO              nvarchar(500) NOT NULL,
        ID_USUARIO_SAIDA    int NOT NULL,
        REGISTRADA_EM       datetime NOT NULL CONSTRAINT DF_SAIDA_EXT_REGISTRADA DEFAULT (GETDATE()),
        DATA_RETORNO        date NULL,
        ID_TURMA_RETORNO    int NULL,
        ID_USUARIO_RETORNO  int NULL,
        RETORNADA_EM        datetime NULL,
        CONSTRAINT PK_TB_ALUNO_SAIDAS_EXTERNAS PRIMARY KEY CLUSTERED (ID),
        CONSTRAINT FK_SAIDA_EXT_ALUNO FOREIGN KEY (ID_ALUNO) REFERENCES dbo.TB_ALUNOS (ID),
        CONSTRAINT FK_SAIDA_EXT_TURMA FOREIGN KEY (ID_TURMA_ORIGEM) REFERENCES dbo.TB_TURMAS (ID),
        CONSTRAINT FK_SAIDA_EXT_USUARIO FOREIGN KEY (ID_USUARIO_SAIDA) REFERENCES dbo.TB_USUARIOS (ID),
        CONSTRAINT FK_RETORNO_EXT_TURMA FOREIGN KEY (ID_TURMA_RETORNO) REFERENCES dbo.TB_TURMAS (ID),
        CONSTRAINT FK_RETORNO_EXT_USUARIO FOREIGN KEY (ID_USUARIO_RETORNO) REFERENCES dbo.TB_USUARIOS (ID),
        CONSTRAINT CK_SAIDA_EXT_TEXTO CHECK (
            LTRIM(RTRIM(ESCOLA_DESTINO)) <> '' AND LTRIM(RTRIM(MUNICIPIO_DESTINO)) <> ''
            AND LTRIM(RTRIM(MOTIVO)) <> ''
        ),
        CONSTRAINT CK_RETORNO_EXT_COMPLETO CHECK (
            (DATA_RETORNO IS NULL AND ID_TURMA_RETORNO IS NULL
                AND ID_USUARIO_RETORNO IS NULL AND RETORNADA_EM IS NULL)
            OR (DATA_RETORNO IS NOT NULL AND ID_TURMA_RETORNO IS NOT NULL
                AND ID_USUARIO_RETORNO IS NOT NULL AND RETORNADA_EM IS NOT NULL
                AND DATA_RETORNO > DATA_SAIDA)
        )
    );
    CREATE INDEX IX_SAIDA_EXT_ALUNO ON dbo.TB_ALUNO_SAIDAS_EXTERNAS (ID_ALUNO, DATA_SAIDA);
    PRINT '  + TB_ALUNO_SAIDAS_EXTERNAS criada';
END
ELSE
    PRINT '  = TB_ALUNO_SAIDAS_EXTERNAS já existe';
GO

IF NOT EXISTS (
    SELECT 1 FROM sys.indexes
    WHERE name = 'UX_SAIDA_EXT_ABERTA'
      AND object_id = OBJECT_ID(N'dbo.TB_ALUNO_SAIDAS_EXTERNAS')
)
BEGIN
    CREATE UNIQUE INDEX UX_SAIDA_EXT_ABERTA
        ON dbo.TB_ALUNO_SAIDAS_EXTERNAS (ID_ALUNO)
        WHERE DATA_RETORNO IS NULL;
    PRINT '  + UX_SAIDA_EXT_ABERTA criado';
END
ELSE
    PRINT '  = UX_SAIDA_EXT_ABERTA já existe';
GO

IF OBJECT_ID(N'dbo.TB_PROMOCOES', N'U') IS NULL
BEGIN
    CREATE TABLE dbo.TB_PROMOCOES (
        ID              int IDENTITY(1,1) NOT NULL,
        ID_ALUNO        int      NOT NULL,
        ID_TURMA_ORIGEM int      NOT NULL,
        ID_TURMA_DESTINO int     NOT NULL,
        DATA_INICIO     date     NOT NULL,
        ID_USUARIO      int      NOT NULL,
        REGISTRADA_EM   datetime NOT NULL CONSTRAINT DF_PROMOCAO_REGISTRADA DEFAULT (GETDATE()),
        CONSTRAINT PK_TB_PROMOCOES PRIMARY KEY CLUSTERED (ID),
        CONSTRAINT FK_PROMOCAO_ALUNO FOREIGN KEY (ID_ALUNO) REFERENCES dbo.TB_ALUNOS (ID),
        CONSTRAINT FK_PROMOCAO_ORIGEM FOREIGN KEY (ID_TURMA_ORIGEM) REFERENCES dbo.TB_TURMAS (ID),
        CONSTRAINT FK_PROMOCAO_DESTINO FOREIGN KEY (ID_TURMA_DESTINO) REFERENCES dbo.TB_TURMAS (ID),
        CONSTRAINT FK_PROMOCAO_USUARIO FOREIGN KEY (ID_USUARIO) REFERENCES dbo.TB_USUARIOS (ID),
        CONSTRAINT CK_PROMOCAO_TURMAS CHECK (ID_TURMA_ORIGEM <> ID_TURMA_DESTINO)
    );
    CREATE INDEX IX_PROMOCOES_ALUNO_DATA ON dbo.TB_PROMOCOES (ID_ALUNO, DATA_INICIO);
END
GO

IF NOT EXISTS (
    SELECT 1 FROM sys.indexes
    WHERE name = 'UX_PROMOCAO_ALUNO_DATA' AND object_id = OBJECT_ID(N'dbo.TB_PROMOCOES')
)
    CREATE UNIQUE INDEX UX_PROMOCAO_ALUNO_DATA ON dbo.TB_PROMOCOES (ID_ALUNO, DATA_INICIO);
GO

/* ============================================================================
   18b. VW_ALUNO_VINCULO — em que turma o aluno estava, e em que período
      ----------------------------------------------------------------------------
      A tabela TB_ALUNOS guarda só a turma ATUAL. Esta visão reconstrói a linha
      do tempo a partir das transferências concluídas e das saídas/retornos:

        DATA_INICIO NULL = desde o início (primeiro período do aluno)
        DATA_FIM    NULL = até hoje (período em aberto)

      Um aluno sem transferência tem uma única linha, com a turma atual.
      Quem foi transferido uma vez tem duas; duas vezes, três; e assim por diante.

      É desta visão que a chamada do dia decide quem aparece em cada turma, e é
      dela que sai o quadro de escolas/turmas do relatório anual.
   ============================================================================ */
CREATE OR ALTER VIEW dbo.VW_ALUNO_VINCULO
AS
WITH transferencias AS (
    SELECT ID, ID_ALUNO, ID_TURMA_ORIGEM, ID_TURMA_DESTINO, DATA_INICIO
    FROM dbo.TB_TRANSFERENCIAS
    WHERE SITUACAO = 'CONCLUIDA'
),
eventos_origem AS (
    SELECT ID_ALUNO, ID_TURMA_ORIGEM, DATA_INICIO AS DATA_EVENTO, ID AS ORDEM_ID
    FROM transferencias
    UNION ALL
    SELECT ID_ALUNO, ID_TURMA_ORIGEM, DATA_SAIDA, ID
    FROM dbo.TB_ALUNO_SAIDAS_EXTERNAS
    UNION ALL
    SELECT ID_ALUNO, ID_TURMA_ORIGEM, DATA_INICIO, ID
    FROM dbo.TB_PROMOCOES
),
primeira_origem AS (
    SELECT ID_ALUNO, ID_TURMA_ORIGEM,
           ROW_NUMBER() OVER (PARTITION BY ID_ALUNO ORDER BY DATA_EVENTO, ORDEM_ID) AS ORDEM
    FROM eventos_origem
),
limites AS (
    SELECT ID_ALUNO, DATA_INICIO AS DATA_LIMITE FROM transferencias
    UNION ALL
    SELECT ID_ALUNO, DATEADD(DAY, 1, DATA_SAIDA) FROM dbo.TB_ALUNO_SAIDAS_EXTERNAS
    UNION ALL
    SELECT ID_ALUNO, DATA_RETORNO FROM dbo.TB_ALUNO_SAIDAS_EXTERNAS
    WHERE DATA_RETORNO IS NOT NULL
    UNION ALL
    SELECT ID_ALUNO, DATA_INICIO FROM dbo.TB_PROMOCOES
),
inicios AS (
    SELECT ID_ALUNO, ID_TURMA_DESTINO AS ID_TURMA, DATA_INICIO
    FROM transferencias
    UNION ALL
    SELECT ID_ALUNO, ID_TURMA_RETORNO, DATA_RETORNO
    FROM dbo.TB_ALUNO_SAIDAS_EXTERNAS
    WHERE DATA_RETORNO IS NOT NULL
    UNION ALL
    SELECT ID_ALUNO, ID_TURMA_DESTINO, DATA_INICIO FROM dbo.TB_PROMOCOES
),
periodos AS (
    SELECT a.ID AS ID_ALUNO,
           COALESCE(o.ID_TURMA_ORIGEM, a.ID_TURMA) AS ID_TURMA,
           CAST(NULL AS date) AS DATA_INICIO
    FROM dbo.TB_ALUNOS a
    LEFT JOIN primeira_origem o ON o.ID_ALUNO = a.ID AND o.ORDEM = 1
    UNION ALL
    SELECT ID_ALUNO, ID_TURMA, DATA_INICIO FROM inicios
)
SELECT p.ID_ALUNO, p.ID_TURMA, p.DATA_INICIO,
       DATEADD(DAY, -1, (
           SELECT MIN(l.DATA_LIMITE)
           FROM limites l
           WHERE l.ID_ALUNO = p.ID_ALUNO
             AND (p.DATA_INICIO IS NULL OR l.DATA_LIMITE > p.DATA_INICIO)
       )) AS DATA_FIM
FROM periodos p;
GO
PRINT '  + VW_ALUNO_VINCULO criada/atualizada';
GO
GO

/* ============================================================================
   19. EQUIPE POR TURNO, SUBSTITUTOS E SAÚDE DO ALUNO
       As tabelas acima já existem: aqui elas ganham as colunas do turno de
       trabalho, da substituição com prazo e da ficha de saúde. É a mesma
       seção de ATUALIZAR_EQUIPE_SAUDE.sql, que quem já tem o sistema
       instalado roda separadamente. Ver o comentário completo naquele arquivo.

       Em resumo: um responsável por turno (manhã e tarde podem ser pessoas
       diferentes); quem é integral responde pelos dois; substituto entra com
       data de início, data de fim e o professor que está cobrindo.
   ============================================================================ */
/* ============================================================================
   1. TB_PERIODOS.TURNO — classifica o horário de funcionamento
   ============================================================================ */
IF NOT EXISTS (SELECT 1 FROM sys.columns
               WHERE object_id = OBJECT_ID(N'dbo.TB_PERIODOS') AND name = 'TURNO')
BEGIN
    ALTER TABLE dbo.TB_PERIODOS
        ADD TURNO nvarchar(10) NOT NULL CONSTRAINT DF_PERIODOS_TURNO DEFAULT ('INTEGRAL');
    PRINT '  + TB_PERIODOS.TURNO criada';
END
ELSE
    PRINT '  = TB_PERIODOS.TURNO já existe';
GO

IF NOT EXISTS (SELECT 1 FROM sys.check_constraints WHERE name = 'CK_PERIODOS_TURNO')
BEGIN
    ALTER TABLE dbo.TB_PERIODOS
        ADD CONSTRAINT CK_PERIODOS_TURNO CHECK (TURNO IN ('MANHA', 'TARDE', 'INTEGRAL'));
    PRINT '  + CK_PERIODOS_TURNO criada';
END
ELSE
    PRINT '  = CK_PERIODOS_TURNO já existe';
GO

/* Classifica os períodos já cadastrados pelo nome. O que não for claramente
   manhã ou tarde fica INTEGRAL, que é o valor mais permissivo. */
UPDATE dbo.TB_PERIODOS
SET TURNO = CASE
                WHEN NOME LIKE N'%manh%' THEN 'MANHA'
                WHEN NOME LIKE N'%tarde%' THEN 'TARDE'
                ELSE 'INTEGRAL'
            END
WHERE NOME LIKE N'%manh%' OR NOME LIKE N'%tarde%';
PRINT '  + períodos existentes classificados por turno';
GO

/* ============================================================================
   2. TB_USUARIOS.TURNO — o turno de trabalho do professor
      NULL para a gestão (diretor/administrador), que não tem turno.
   ============================================================================ */
IF NOT EXISTS (SELECT 1 FROM sys.columns
               WHERE object_id = OBJECT_ID(N'dbo.TB_USUARIOS') AND name = 'TURNO')
BEGIN
    ALTER TABLE dbo.TB_USUARIOS ADD TURNO nvarchar(10) NULL;
    PRINT '  + TB_USUARIOS.TURNO criada';
END
ELSE
    PRINT '  = TB_USUARIOS.TURNO já existe';
GO

IF NOT EXISTS (SELECT 1 FROM sys.check_constraints WHERE name = 'CK_USUARIOS_TURNO')
BEGIN
    ALTER TABLE dbo.TB_USUARIOS
        ADD CONSTRAINT CK_USUARIOS_TURNO CHECK (TURNO IS NULL OR TURNO IN ('MANHA', 'TARDE', 'INTEGRAL'));
    PRINT '  + CK_USUARIOS_TURNO criada';
END
ELSE
    PRINT '  = CK_USUARIOS_TURNO já existe';
GO

/* ============================================================================
   3. TB_TURMA_PROFESSORES — turno, prazo e quem o substituto cobre
   ============================================================================ */
IF NOT EXISTS (SELECT 1 FROM sys.columns
               WHERE object_id = OBJECT_ID(N'dbo.TB_TURMA_PROFESSORES') AND name = 'TURNO')
BEGIN
    ALTER TABLE dbo.TB_TURMA_PROFESSORES
        ADD TURNO nvarchar(10) NOT NULL CONSTRAINT DF_TURMAPROF_TURNO DEFAULT ('INTEGRAL');
    PRINT '  + TB_TURMA_PROFESSORES.TURNO criada';
END
ELSE
    PRINT '  = TB_TURMA_PROFESSORES.TURNO já existe';
GO

IF NOT EXISTS (SELECT 1 FROM sys.columns
               WHERE object_id = OBJECT_ID(N'dbo.TB_TURMA_PROFESSORES') AND name = 'DATA_INICIO')
BEGIN
    ALTER TABLE dbo.TB_TURMA_PROFESSORES ADD DATA_INICIO date NULL;
    PRINT '  + TB_TURMA_PROFESSORES.DATA_INICIO criada';
END
ELSE
    PRINT '  = TB_TURMA_PROFESSORES.DATA_INICIO já existe';
GO

IF NOT EXISTS (SELECT 1 FROM sys.columns
               WHERE object_id = OBJECT_ID(N'dbo.TB_TURMA_PROFESSORES') AND name = 'DATA_FIM')
BEGIN
    ALTER TABLE dbo.TB_TURMA_PROFESSORES ADD DATA_FIM date NULL;
    PRINT '  + TB_TURMA_PROFESSORES.DATA_FIM criada';
END
ELSE
    PRINT '  = TB_TURMA_PROFESSORES.DATA_FIM já existe';
GO

IF NOT EXISTS (SELECT 1 FROM sys.columns
               WHERE object_id = OBJECT_ID(N'dbo.TB_TURMA_PROFESSORES') AND name = 'ID_SUBSTITUIDO')
BEGIN
    ALTER TABLE dbo.TB_TURMA_PROFESSORES ADD ID_SUBSTITUIDO int NULL;
    PRINT '  + TB_TURMA_PROFESSORES.ID_SUBSTITUIDO criada';
END
ELSE
    PRINT '  = TB_TURMA_PROFESSORES.ID_SUBSTITUIDO já existe';
GO

IF NOT EXISTS (SELECT 1 FROM sys.columns
               WHERE object_id = OBJECT_ID(N'dbo.TB_TURMA_PROFESSORES') AND name = 'MOTIVO')
BEGIN
    ALTER TABLE dbo.TB_TURMA_PROFESSORES ADD MOTIVO nvarchar(300) NULL;
    PRINT '  + TB_TURMA_PROFESSORES.MOTIVO criada';
END
ELSE
    PRINT '  = TB_TURMA_PROFESSORES.MOTIVO já existe';
GO

/* O turno de quem já está na equipe passa a ser o turno da própria turma:
   é a melhor informação disponível, e mantém o responsável atual responsável
   pelo turno em que a turma funciona. */
UPDATE tp
SET tp.TURNO = p.TURNO
FROM dbo.TB_TURMA_PROFESSORES tp
INNER JOIN dbo.TB_TURMAS t   ON t.ID = tp.ID_TURMA
INNER JOIN dbo.TB_PERIODOS p ON p.ID = t.ID_PERIODO
WHERE tp.TURNO = 'INTEGRAL' AND p.TURNO <> 'INTEGRAL';
PRINT '  + turno da equipe atual herdado do período da turma';
GO

/* ---------------------------------------------------------------- restrições */

/* PAPEL ganha SUBSTITUTO. A CHECK antiga é derrubada e recriada. */
IF EXISTS (SELECT 1 FROM sys.check_constraints WHERE name = 'CK_TURMAPROF_PAPEL')
BEGIN
    ALTER TABLE dbo.TB_TURMA_PROFESSORES DROP CONSTRAINT CK_TURMAPROF_PAPEL;
    PRINT '  - CK_TURMAPROF_PAPEL antiga removida';
END
GO

ALTER TABLE dbo.TB_TURMA_PROFESSORES
    ADD CONSTRAINT CK_TURMAPROF_PAPEL CHECK (PAPEL IN ('REGENTE', 'AUXILIAR', 'SUBSTITUTO'));
GO
PRINT '  + CK_TURMAPROF_PAPEL: REGENTE / AUXILIAR / SUBSTITUTO';
GO

IF NOT EXISTS (SELECT 1 FROM sys.check_constraints WHERE name = 'CK_TURMAPROF_TURNO')
BEGIN
    ALTER TABLE dbo.TB_TURMA_PROFESSORES
        ADD CONSTRAINT CK_TURMAPROF_TURNO CHECK (TURNO IN ('MANHA', 'TARDE', 'INTEGRAL'));
    PRINT '  + CK_TURMAPROF_TURNO criada';
END
GO

/* Substituto tem prazo fechado e diz quem cobre. Quem não é substituto não
   carrega esses campos — assim não sobra prazo órfão de uma escala antiga. */
IF NOT EXISTS (SELECT 1 FROM sys.check_constraints WHERE name = 'CK_TURMAPROF_SUBSTITUTO')
BEGIN
    ALTER TABLE dbo.TB_TURMA_PROFESSORES
        ADD CONSTRAINT CK_TURMAPROF_SUBSTITUTO CHECK (
            (PAPEL = 'SUBSTITUTO'
                AND ID_SUBSTITUIDO IS NOT NULL
                AND DATA_INICIO IS NOT NULL
                AND DATA_FIM IS NOT NULL
                AND DATA_FIM >= DATA_INICIO)
            OR
            (PAPEL <> 'SUBSTITUTO'
                AND ID_SUBSTITUIDO IS NULL
                AND DATA_INICIO IS NULL
                AND DATA_FIM IS NULL)
        );
    PRINT '  + CK_TURMAPROF_SUBSTITUTO criada';
END
GO

/* Ninguém substitui a si mesmo. */
IF NOT EXISTS (SELECT 1 FROM sys.check_constraints WHERE name = 'CK_TURMAPROF_NAO_SI_MESMO')
BEGIN
    ALTER TABLE dbo.TB_TURMA_PROFESSORES
        ADD CONSTRAINT CK_TURMAPROF_NAO_SI_MESMO CHECK (
            ID_SUBSTITUIDO IS NULL OR ID_SUBSTITUIDO <> ID_USUARIO
        );
    PRINT '  + CK_TURMAPROF_NAO_SI_MESMO criada';
END
GO

IF NOT EXISTS (SELECT 1 FROM sys.foreign_keys WHERE name = 'FK_TURMAPROF_SUBSTITUIDO')
BEGIN
    ALTER TABLE dbo.TB_TURMA_PROFESSORES
        ADD CONSTRAINT FK_TURMAPROF_SUBSTITUIDO
            FOREIGN KEY (ID_SUBSTITUIDO) REFERENCES dbo.TB_USUARIOS (ID);
    PRINT '  + FK_TURMAPROF_SUBSTITUIDO criada';
END
GO

/* ------------------------------------------------- um responsável por turno */

/* O índice antigo permitia UM regente por turma. Cai. */
IF EXISTS (SELECT 1 FROM sys.indexes
           WHERE name = 'UX_TURMAPROF_REGENTE'
             AND object_id = OBJECT_ID(N'dbo.TB_TURMA_PROFESSORES'))
BEGIN
    DROP INDEX UX_TURMAPROF_REGENTE ON dbo.TB_TURMA_PROFESSORES;
    PRINT '  - UX_TURMAPROF_REGENTE removido (era um por turma)';
END
GO

/* Um responsável por TURMA + TURNO.
   ATENÇÃO ao que este índice NÃO cobre: ele impede dois responsáveis da manhã,
   mas não impede um INTEGRAL ao lado de um da manhã — e o integral já responde
   pela manhã. Essa sobreposição entre turnos é conferida em
   inc/equipe.php (eq_validar_equipe), que é por onde toda escrita passa.
   Fica registrado aqui para ninguém supor que o banco resolve sozinho. */
IF NOT EXISTS (SELECT 1 FROM sys.indexes
               WHERE name = 'UX_TURMAPROF_RESP_TURNO'
                 AND object_id = OBJECT_ID(N'dbo.TB_TURMA_PROFESSORES'))
BEGIN
    CREATE UNIQUE INDEX UX_TURMAPROF_RESP_TURNO
        ON dbo.TB_TURMA_PROFESSORES (ID_TURMA, TURNO)
        WHERE PAPEL = 'REGENTE' AND ATIVO = 1;
    PRINT '  + UX_TURMAPROF_RESP_TURNO criado (um responsável por turno)';
END
ELSE
    PRINT '  = UX_TURMAPROF_RESP_TURNO já existe';
GO

IF NOT EXISTS (SELECT 1 FROM sys.indexes
               WHERE name = 'IX_TURMAPROF_SUBSTITUTO'
                 AND object_id = OBJECT_ID(N'dbo.TB_TURMA_PROFESSORES'))
BEGIN
    CREATE INDEX IX_TURMAPROF_SUBSTITUTO
        ON dbo.TB_TURMA_PROFESSORES (ID_TURMA, DATA_INICIO, DATA_FIM)
        WHERE PAPEL = 'SUBSTITUTO';
    PRINT '  + IX_TURMAPROF_SUBSTITUTO criado';
END
ELSE
    PRINT '  = IX_TURMAPROF_SUBSTITUTO já existe';
GO

/* ============================================================================
   4. TB_ALUNOS — saúde
      Texto livre de propósito: a escola escreve como o laudo diz. Cada campo
      até 1000 caracteres.
   ============================================================================ */
IF NOT EXISTS (SELECT 1 FROM sys.columns
               WHERE object_id = OBJECT_ID(N'dbo.TB_ALUNOS') AND name = 'ALERGIAS')
BEGIN
    ALTER TABLE dbo.TB_ALUNOS ADD ALERGIAS nvarchar(1000) NULL;
    PRINT '  + TB_ALUNOS.ALERGIAS criada';
END
ELSE
    PRINT '  = TB_ALUNOS.ALERGIAS já existe';
GO

IF NOT EXISTS (SELECT 1 FROM sys.columns
               WHERE object_id = OBJECT_ID(N'dbo.TB_ALUNOS') AND name = 'RESTRICOES_ALIMENTARES')
BEGIN
    ALTER TABLE dbo.TB_ALUNOS ADD RESTRICOES_ALIMENTARES nvarchar(1000) NULL;
    PRINT '  + TB_ALUNOS.RESTRICOES_ALIMENTARES criada';
END
ELSE
    PRINT '  = TB_ALUNOS.RESTRICOES_ALIMENTARES já existe';
GO

IF NOT EXISTS (SELECT 1 FROM sys.columns
               WHERE object_id = OBJECT_ID(N'dbo.TB_ALUNOS') AND name = 'MEDICACAO')
BEGIN
    ALTER TABLE dbo.TB_ALUNOS ADD MEDICACAO nvarchar(1000) NULL;
    PRINT '  + TB_ALUNOS.MEDICACAO criada';
END
ELSE
    PRINT '  = TB_ALUNOS.MEDICACAO já existe';
GO

/* ============================================================================
   20. SUBSTITUTO E OBSERVAÇÕES DO PROFESSOR
   ----------------------------------------------------------------------------
   O substituto sempre vê as observações que ele mesmo registrou. As dos outros
   ficam escondidas até a gestão liberar, e a liberação é POR SUBSTITUIÇÃO:
   cada nova substituição começa bloqueada.

   As três colunas só fazem sentido para PAPEL = 'SUBSTITUTO'; o CHECK impede
   sobra de escala antiga em membro fixo. Ver ATUALIZAR_OBS_SUBSTITUTO.sql para
   a explicação completa e a lista de telas onde o filtro é aplicado.
   ============================================================================ */
PRINT '';
PRINT '20. Substituto e observações do professor...';
GO

IF NOT EXISTS (SELECT 1 FROM sys.columns
               WHERE object_id = OBJECT_ID('dbo.TB_TURMA_PROFESSORES')
                 AND name = 'VER_OBSERVACOES')
BEGIN
    ALTER TABLE dbo.TB_TURMA_PROFESSORES
        ADD VER_OBSERVACOES bit NOT NULL
            CONSTRAINT DF_TURMAPROF_VEROBS DEFAULT (0);
    PRINT '  + VER_OBSERVACOES criada (0 = bloqueado)';
END
GO

IF NOT EXISTS (SELECT 1 FROM sys.columns
               WHERE object_id = OBJECT_ID('dbo.TB_TURMA_PROFESSORES')
                 AND name = 'OBS_LIBERADA_POR')
BEGIN
    ALTER TABLE dbo.TB_TURMA_PROFESSORES ADD OBS_LIBERADA_POR int NULL;
    PRINT '  + OBS_LIBERADA_POR criada';
END
GO

IF NOT EXISTS (SELECT 1 FROM sys.columns
               WHERE object_id = OBJECT_ID('dbo.TB_TURMA_PROFESSORES')
                 AND name = 'OBS_LIBERADA_EM')
BEGIN
    ALTER TABLE dbo.TB_TURMA_PROFESSORES ADD OBS_LIBERADA_EM datetime NULL;
    PRINT '  + OBS_LIBERADA_EM criada';
END
GO

IF NOT EXISTS (SELECT 1 FROM sys.foreign_keys WHERE name = 'FK_TURMAPROF_OBSLIB_POR')
BEGIN
    ALTER TABLE dbo.TB_TURMA_PROFESSORES
        ADD CONSTRAINT FK_TURMAPROF_OBSLIB_POR
            FOREIGN KEY (OBS_LIBERADA_POR) REFERENCES dbo.TB_USUARIOS (ID);
    PRINT '  + FK_TURMAPROF_OBSLIB_POR criada';
END
GO

IF NOT EXISTS (SELECT 1 FROM sys.check_constraints WHERE name = 'CK_TURMAPROF_VEROBS')
BEGIN
    ALTER TABLE dbo.TB_TURMA_PROFESSORES
        ADD CONSTRAINT CK_TURMAPROF_VEROBS CHECK (
            PAPEL = 'SUBSTITUTO'
            OR (VER_OBSERVACOES = 0 AND OBS_LIBERADA_POR IS NULL AND OBS_LIBERADA_EM IS NULL)
        );
    PRINT '  + CK_TURMAPROF_VEROBS criada';
END
GO

IF NOT EXISTS (SELECT 1 FROM sys.check_constraints WHERE name = 'CK_TURMAPROF_OBSLIB_JUNTAS')
BEGIN
    ALTER TABLE dbo.TB_TURMA_PROFESSORES
        ADD CONSTRAINT CK_TURMAPROF_OBSLIB_JUNTAS CHECK (
            (OBS_LIBERADA_POR IS NULL AND OBS_LIBERADA_EM IS NULL)
            OR (OBS_LIBERADA_POR IS NOT NULL AND OBS_LIBERADA_EM IS NOT NULL)
        );
    PRINT '  + CK_TURMAPROF_OBSLIB_JUNTAS criada';
END
GO

IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'IX_TURMAPROF_OBS_SUBST')
BEGIN
    CREATE INDEX IX_TURMAPROF_OBS_SUBST
        ON dbo.TB_TURMA_PROFESSORES (ID_USUARIO, PAPEL, ATIVO)
        INCLUDE (ID_TURMA, DATA_INICIO, DATA_FIM, VER_OBSERVACOES);
    PRINT '  + IX_TURMAPROF_OBS_SUBST criado';
END
GO

/* ============================================================================
   21. VERIFICAÇÃO
   ============================================================================ */
PRINT '';
PRINT '================================================================';
PRINT 'RELATÓRIO DE VERIFICAÇÃO';
PRINT '================================================================';

SELECT
    t.name AS Tabela,
    (SELECT COUNT(*) FROM sys.columns c WHERE c.object_id = t.object_id) AS Colunas,
    (SELECT COUNT(*) FROM sys.foreign_keys f WHERE f.parent_object_id = t.object_id) AS FKs,
    (SELECT COUNT(*) FROM sys.indexes i WHERE i.object_id = t.object_id AND i.is_unique = 1) AS IndicesUnicos
FROM sys.tables t
WHERE t.name IN ('TB_UNIDADES','TB_USUARIOS','TB_ACESSO','TB_PERIODOS','TB_TURMAS',
                 'TB_ALUNOS','TB_FERIADOS','TB_CHAMADA','TB_OBSERVACOES','TB_RELATORIOS_ANUAIS',
                 'TB_HISTORICO_OBSERVACOES','TB_HISTORICO_RELATORIOS','TB_PROMOCOES',
                 'TB_TURMA_PROFESSORES','TB_CHAMADA_RETIFICACOES','TB_TRANSFERENCIAS',
                 'TB_ALUNO_SAIDAS_EXTERNAS',
                 'TB_TURMA_DISCIPLINAS','TB_NOTAS','TB_SECRETARIO_RELATORIOS',
                 'TB_SECRETARIO_OBSERVACOES','TB_LOGIN_TENTATIVAS')
ORDER BY t.name;

SELECT
    (SELECT COUNT(*) FROM dbo.TB_UNIDADES)  AS Unidades,
    (SELECT COUNT(*) FROM dbo.TB_USUARIOS)  AS Usuarios,
    (SELECT COUNT(*) FROM dbo.TB_ACESSO)    AS Vinculos,
    (SELECT COUNT(*) FROM dbo.TB_PERIODOS)  AS Periodos,
    (SELECT COUNT(*) FROM dbo.TB_TURMAS)    AS Turmas,
    (SELECT COUNT(*) FROM dbo.TB_ALUNOS)    AS Alunos,
    (SELECT COUNT(*) FROM dbo.TB_CHAMADA)   AS Chamadas,
    (SELECT COUNT(*) FROM dbo.TB_TURMA_PROFESSORES)   AS EquipeTurmas,
    (SELECT COUNT(*) FROM dbo.TB_CHAMADA_RETIFICACOES) AS Retificacoes;

IF OBJECT_ID(N'dbo.VIEW_ACESSOS_USUARIOS', N'V') IS NULL
    PRINT 'ATENÇÃO: a VIEW_ACESSOS_USUARIOS não foi criada!';
ELSE
    PRINT 'VIEW_ACESSOS_USUARIOS: OK';

IF OBJECT_ID(N'dbo.VW_ALUNO_VINCULO', N'V') IS NULL
    PRINT 'ATENÇÃO: a VW_ALUNO_VINCULO não foi criada!';
ELSE
    PRINT 'VW_ALUNO_VINCULO: OK';

IF (SELECT COUNT(*) FROM sys.tables WHERE name IN
      ('TB_UNIDADES','TB_USUARIOS','TB_ACESSO','TB_PERIODOS','TB_TURMAS',
       'TB_ALUNOS','TB_FERIADOS','TB_CHAMADA','TB_OBSERVACOES','TB_RELATORIOS_ANUAIS',
       'TB_HISTORICO_OBSERVACOES','TB_HISTORICO_RELATORIOS','TB_PROMOCOES',
       'TB_TURMA_PROFESSORES','TB_CHAMADA_RETIFICACOES','TB_TRANSFERENCIAS',
       'TB_ALUNO_SAIDAS_EXTERNAS',
       'TB_TURMA_DISCIPLINAS','TB_NOTAS','TB_SECRETARIO_RELATORIOS',
       'TB_SECRETARIO_OBSERVACOES','TB_LOGIN_TENTATIVAS')) = 22
    PRINT 'As 22 tabelas estão presentes. Instalação concluída.';
ELSE
    PRINT 'ATENÇÃO: alguma tabela está faltando. Revise as mensagens acima.';
GO

/* ============================================================================
   PRÓXIMOS PASSOS
   ----------------------------------------------------------------------------
   1. Crie um login específico para a aplicação e dê a ele apenas o necessário:

        USE [db_chamada];
        CREATE USER [user_chamada] FOR LOGIN [user_chamada];
        ALTER ROLE db_datareader ADD MEMBER [user_chamada];
        ALTER ROLE db_datawriter ADD MEMBER [user_chamada];
        GRANT EXECUTE TO [user_chamada];

   2. Ajuste config.php:
        DB_NAME     -> db_chamada
        DB_USER     -> user_chamada
        DB_PASSWORD -> senha do login
        DB_HOST     -> servidor (ex.: 127.0.0.1 ou SERVER\INSTANCIA)
        DB_PORT     -> 1433
        BASEURL     -> caminho no servidor (ex.: /Chamada/)

   3. Acesse /login.php e troque a senha do administrador.

   4. Cadastre, nesta ordem: Períodos (já vem pronto) -> Unidades (já vem com a
      rede municipal) -> Usuários (professores, com vínculo de unidade) ->
      Turmas -> Alunos -> Feriados. Depois é só lançar a chamada.

   5. Para transferir um aluno de escola: Alunos -> o aluno -> Transferir. O
      diretor indica a escola de destino; a escola de destino abre
      Transferências -> Aguardando esta escola, escolhe a turma e a data de
      início. A chamada do dia passa a considerar a data automaticamente.
   ============================================================================ */
