/* ============================================================================
   SISTEMA DE CHAMADA — RESETAR E POPULAR COM DADOS FICTÍCIOS DE TESTE
   Banco-alvo: db_chamada (SQL Server)

   ATENÇÃO — SCRIPT DESTRUTIVO
   --------------------------------------------------------------------------
   - Apaga os registros das 26 tabelas do sistema em dbo e limpa
     dbo.TB_DEMO_IDS se ela existir, mantendo a linha dbo.TB_USUARIOS.ID = 1.
   - Mantém a estrutura: não remove tabelas, colunas, índices, views ou schema.
     As FKs ficam ativas; a limpeza é feita em ordem de dependência.
   - Preserva apenas a linha do usuário 1; seus vínculos em TB_ACESSO também
     são zerados. O diretor DEMO recebe acesso às unidades fictícias novas.
   - Depois cria um cenário fictício com 17 contas, 3 unidades, 10 turmas do
     ano atual + 2 turmas históricas inativas, 20 alunos e 10 famílias, cobrindo
     Creche/Apoio, Pré-escola, matérias, substituições, saúde/AEE, chamada,
     notas, observações, relatórios e movimentações escolares.
   - As contas de teste usam a senha Demo@2026. Nomes, telefones e e-mails são
     fictícios; os CPFs de teste são gerados com dígitos verificadores para
     permitir validar o formulário. Use SOMENTE em desenvolvimento/teste.
   - Este arquivo NÃO foi executado. Faça backup antes de executá-lo.

   PRÉ-REQUISITO
   --------------------------------------------------------------------------
   Execute depois de sql_inicial/INSTALACAO_COMPLETA.sql em bancos novos. Em
   bancos existentes, aplique todas as migrações da versão atual. ATUALIZAR_EQUIPE_SAUDE.sql
   é obrigatória para as colunas TURNO de períodos, usuários e equipe; também
   aplique ATUALIZAR_CRECHE_FAMILIA_CRACHA.sql para os vínculos familiares. O
   script verifica tabelas e colunas requeridas antes de apagar dados.

   COMO EXECUTAR
   --------------------------------------------------------------------------
   1. Confira que o banco selecionado é db_chamada e faça backup.
   2. Altere @CONFIRMAR_RESET de 0 para 1 abaixo.
   3. Execute o arquivo inteiro no SSMS/Azure Data Studio (F5).
   4. As contas novas usam a senha Demo@2026; o usuário ID 1 e sua senha não
      são alterados.

   IMPORTANTE: as identidades não são resemeadas. IDs existentes nas tabelas
   podem continuar avançados; isso não afeta o funcionamento do sistema.
   ============================================================================ */

USE [db_chamada];
GO

SET NOCOUNT ON;
SET XACT_ABORT ON;

/* Troque para 1 conscientemente antes de executar. */
DECLARE @CONFIRMAR_RESET bit = 0;
IF DB_NAME() <> N'db_chamada'
BEGIN
    ;THROW 51000, N'Banco incorreto. Este script só pode ser executado em db_chamada.', 1;
END;
IF @CONFIRMAR_RESET <> 1
BEGIN
    ;THROW 51001, N'Reset bloqueado. Confira o banco/backup e altere @CONFIRMAR_RESET para 1.', 1;
END;
IF NOT EXISTS (SELECT 1 FROM dbo.TB_USUARIOS WHERE ID = 1)
BEGIN
    ;THROW 51002, N'Não existe dbo.TB_USUARIOS.ID = 1; nada foi apagado.', 1;
END;

/* Exige o schema atualizado antes de qualquer limpeza. */
DECLARE @TabelasObrigatorias TABLE (NOME sysname NOT NULL PRIMARY KEY);
INSERT INTO @TabelasObrigatorias (NOME) VALUES
    (N'TB_UNIDADES'), (N'TB_USUARIOS'), (N'TB_ACESSO'), (N'TB_PERIODOS'),
    (N'TB_TURMAS'), (N'TB_ALUNOS'), (N'TB_FERIADOS'), (N'TB_CHAMADA'),
    (N'TB_TURMA_PROFESSORES'), (N'TB_CHAMADA_RETIFICACOES'),
    (N'TB_OBSERVACOES'), (N'TB_RELATORIOS_ANUAIS'),
    (N'TB_HISTORICO_OBSERVACOES'), (N'TB_HISTORICO_RELATORIOS'),
    (N'TB_TRANSFERENCIAS'), (N'TB_ALUNO_SAIDAS_EXTERNAS'), (N'TB_PROMOCOES'),
    (N'TB_TURMA_DISCIPLINAS'), (N'TB_NOTAS'),
    (N'TB_SECRETARIO_RELATORIOS'), (N'TB_SECRETARIO_OBSERVACOES'),
    (N'TB_LOGIN_TENTATIVAS'), (N'TB_PROFESSOR_MATERIAS'), (N'TB_ATRIBUICOES'),
    (N'TB_RESPONSAVEIS'), (N'TB_ALUNO_RESPONSAVEIS');

IF EXISTS (
    SELECT 1 FROM @TabelasObrigatorias r
    WHERE OBJECT_ID(N'dbo.' + r.NOME, N'U') IS NULL
)
BEGIN
    SELECT r.NOME AS TabelaAusente
    FROM @TabelasObrigatorias r
    WHERE OBJECT_ID(N'dbo.' + r.NOME, N'U') IS NULL;
    ;THROW 51003, N'Faltam tabelas do schema atual. Rode a instalação/migração indicada no cabeçalho.', 1;
END;

IF COL_LENGTH('dbo.TB_PERIODOS', 'TURNO') IS NULL
   OR COL_LENGTH('dbo.TB_USUARIOS', 'TURNO') IS NULL
   OR COL_LENGTH('dbo.TB_TURMA_PROFESSORES', 'TURNO') IS NULL
   OR COL_LENGTH('dbo.TB_USUARIOS', 'CATEGORIA') IS NULL
   OR COL_LENGTH('dbo.TB_TURMA_PROFESSORES', 'VINCULO_DIRETO') IS NULL
   OR COL_LENGTH('dbo.TB_ALUNOS', 'PASSA_AEE') IS NULL
   OR COL_LENGTH('dbo.TB_ALUNOS', 'ID_PROFESSOR_AEE') IS NULL
   OR COL_LENGTH('dbo.TB_TURMAS', 'NOTAS_ATIVAS') IS NULL
   OR COL_LENGTH('dbo.TB_TURMA_DISCIPLINAS', 'AULAS') IS NULL
   OR COL_LENGTH('dbo.TB_OBSERVACOES', 'RESTRITA_AEE') IS NULL
BEGIN
    ;THROW 51004, N'Colunas requeridas ausentes. Em banco existente, aplique ATUALIZAR_EQUIPE_SAUDE.sql e as demais migrações antes deste reset.', 1;
END;

DECLARE @HOJE date = CONVERT(date, GETDATE());
DECLARE @ANO int = YEAR(@HOJE);
DECLARE @SENHA_DEMO nvarchar(255) = N'$2y$12$rSXmiuAySvAk0jeK.VdI4eQ6WGBwp92VANeDeHBxsm4pe.OviYD/O';

/* CPFs fictícios distintos para as contas de teste; o ID 1 não é alterado. */
DECLARE @UsuariosPlano TABLE (
    COD        varchar(24)   NOT NULL PRIMARY KEY,
    NOME       nvarchar(255) NOT NULL,
    NIVEL      int           NOT NULL,
    CPF        nvarchar(14)  NOT NULL UNIQUE,
    EMAIL      nvarchar(150) NULL,
    TURNO      nvarchar(10)  NULL,
    CATEGORIA  nvarchar(20)  NULL
);
INSERT INTO @UsuariosPlano (COD, NOME, NIVEL, CPF, EMAIL, TURNO, CATEGORIA) VALUES
    ('DIRETOR',     N'DEMO — Diretor Escolar',                 2, N'900.000.901-40', N'diretor@example.invalid',    NULL,       NULL),
    ('PDI_MANHA',   N'DEMO — PDI Creche Manhã',                3, N'900.000.101-38', N'pdi.manha@example.invalid',  N'MANHA',   N'PDI_CRECHE'),
    ('PDI_TARDE',   N'DEMO — PDI Creche Tarde',                3, N'900.000.102-19', N'pdi.tarde@example.invalid',  N'TARDE',   N'PDI_CRECHE'),
    ('APOIO_A',     N'DEMO — Apoio Creche A',                  3, N'900.000.201-09', N'apoio.a@example.invalid',    N'INTEGRAL',N'APOIO_CRECHE'),
    ('APOIO_B',     N'DEMO — Apoio Creche B',                  3, N'900.000.202-81', N'apoio.b@example.invalid',    N'INTEGRAL',N'APOIO_CRECHE'),
    ('PEBI_PRE_M',  N'DEMO — PEB I Pré Manhã',                 3, N'900.000.301-63', N'pebi.pre.m@example.invalid', N'MANHA',   N'PEB_I'),
    ('PEBI_PRE_T',  N'DEMO — PEB I Pré Tarde',                 3, N'900.000.302-44', N'pebi.pre.t@example.invalid', N'TARDE',   N'PEB_I'),
    ('PEBI_FUND',   N'DEMO — PEB I Anos Iniciais',             3, N'900.000.303-25', N'pebi.fund@example.invalid',  N'MANHA',   N'PEB_I'),
    ('PEBII_LP',    N'DEMO — PEB II Língua Portuguesa',         3, N'900.000.401-26', N'pebii.lp@example.invalid',   N'TARDE',   N'PEB_II'),
    ('PEBII_MAT',   N'DEMO — PEB II Matemática',               3, N'900.000.402-07', N'pebii.mat@example.invalid',  N'TARDE',   N'PEB_II'),
    ('PEBII_CI',    N'DEMO — PEB II Ciências',                 3, N'900.000.403-98', N'pebii.ci@example.invalid',   N'TARDE',   N'PEB_II'),
    ('ESP_EF',      N'DEMO — Especialista Educação Física',    3, N'900.000.501-99', N'esp.ef@example.invalid',     N'INTEGRAL',N'PEB_II'),
    ('ESP_INGLES',  N'DEMO — Especialista Inglês',             3, N'900.000.502-70', N'esp.ingles@example.invalid', N'INTEGRAL',N'PEB_II'),
    ('ESP_ARTES',   N'DEMO — Especialista Artes',              3, N'900.000.503-50', N'esp.artes@example.invalid',  N'INTEGRAL',N'PEB_II'),
    ('AEE',         N'DEMO — Professor de AEE',                3, N'900.000.601-51', N'aee@example.invalid',        N'INTEGRAL',N'AEE'),
    ('SUB_MANHA',   N'DEMO — Substituto Creche Manhã',         3, N'900.000.701-14', N'sub.manha@example.invalid',  N'MANHA',   N'PEB_I'),
    ('SUB_TARDE',   N'DEMO — Substituto Creche Tarde',         3, N'900.000.702-03', N'sub.tarde@example.invalid',  N'TARDE',   N'PEB_I');

IF EXISTS (
    SELECT 1 FROM dbo.TB_USUARIOS u
    INNER JOIN @UsuariosPlano p ON p.CPF = u.CPF
    WHERE u.ID = 1
)
BEGIN
    ;THROW 51005, N'Um CPF fictício de demonstração conflita com o usuário ID 1. Altere os CPFs no arquivo antes de executar.', 1;
END;

DECLARE @UnidadesPlano TABLE (
    COD varchar(12) NOT NULL PRIMARY KEY,
    NOME nvarchar(255) NOT NULL,
    ID int NULL
);
INSERT INTO @UnidadesPlano (COD, NOME) VALUES
    ('CMEI',  N'UNIDADE DEMO — CMEI ESTAÇÃO'),
    ('EMEF',  N'UNIDADE DEMO — EMEF ESTAÇÃO'),
    ('EMEF2', N'UNIDADE DEMO — EMEF ESTAÇÃO II');

DECLARE @PeriodosPlano TABLE (
    COD varchar(12) NOT NULL PRIMARY KEY,
    NOME nvarchar(60) NOT NULL,
    HORA_INICIO nvarchar(5) NOT NULL,
    HORA_FIM nvarchar(5) NOT NULL,
    TURNO nvarchar(10) NOT NULL,
    ID int NULL
);
INSERT INTO @PeriodosPlano (COD, NOME, HORA_INICIO, HORA_FIM, TURNO) VALUES
    ('MANHA',    N'Manhã',    N'07:00', N'12:00', N'MANHA'),
    ('TARDE',    N'Tarde',    N'13:00', N'18:00', N'TARDE'),
    ('INTEGRAL', N'Integral', N'07:00', N'18:00', N'INTEGRAL');

DECLARE @TurmasPlano TABLE (
    ORDEM int NOT NULL PRIMARY KEY,
    COD varchar(20) NOT NULL UNIQUE,
    UNIDADE_COD varchar(12) NOT NULL,
    NOME nvarchar(80) NOT NULL,
    SERIE nvarchar(20) NOT NULL,
    ETAPA nvarchar(30) NOT NULL,
    PERIODO_COD varchar(12) NOT NULL,
    IDADE_BASE int NOT NULL,
    CAPACIDADE int NOT NULL,
    NOTAS_ATIVAS bit NOT NULL
);
INSERT INTO @TurmasPlano
    (ORDEM, COD, UNIDADE_COD, NOME, SERIE, ETAPA, PERIODO_COD, IDADE_BASE, CAPACIDADE, NOTAS_ATIVAS)
VALUES
    (1, 'CRE_BI',   'CMEI', N'A', N'BERCARIO_I',  N'INFANTIL_CRECHE',     'INTEGRAL',  0, 20, 0),
    (2, 'CRE_BII',  'CMEI', N'A', N'BERCARIO_II', N'INFANTIL_CRECHE',     'INTEGRAL',  1, 20, 0),
    (3, 'CRE_MI',   'CMEI', N'A', N'MATERNAL_I',  N'INFANTIL_CRECHE',     'INTEGRAL',  2, 20, 0),
    (4, 'CRE_MII',  'CMEI', N'A', N'MATERNAL_II', N'INFANTIL_CRECHE',     'INTEGRAL',  3, 20, 0),
    (5, 'PRE_I',    'CMEI', N'A', N'PRE_I',       N'INFANTIL_PRE_ESCOLA', 'MANHA',     4, 24, 0),
    (6, 'PRE_II',   'CMEI', N'A', N'PRE_II',      N'INFANTIL_PRE_ESCOLA', 'TARDE',     5, 24, 0),
    (7, 'FUND_1',   'EMEF',  N'A', N'ANO_1',       N'FUNDAMENTAL_1_5',     'MANHA',     6, 30, 1),
    (8, 'FUND_6',   'EMEF',  N'A', N'ANO_6',       N'FUNDAMENTAL_6_9',     'TARDE',    11, 30, 1),
    (9, 'FUND_9',   'EMEF',  N'A', N'ANO_9',       N'FUNDAMENTAL_6_9',     'TARDE',    14, 30, 1),
    (10,'FUND_6_B', 'EMEF2', N'A', N'ANO_6',       N'FUNDAMENTAL_6_9',     'TARDE',    11, 30, 1);

DECLARE @Unidades TABLE (COD varchar(12) NOT NULL PRIMARY KEY, ID int NOT NULL);
DECLARE @Periodos TABLE (
    COD varchar(12) NOT NULL PRIMARY KEY,
    ID int NOT NULL,
    TURNO nvarchar(10) NOT NULL
);
DECLARE @Usuarios TABLE (COD varchar(24) NOT NULL PRIMARY KEY, ID int NOT NULL);
DECLARE @Turmas TABLE (
    ORDEM int NOT NULL,
    COD varchar(20) NOT NULL PRIMARY KEY,
    ID int NOT NULL,
    SERIE nvarchar(20) NOT NULL,
    ETAPA nvarchar(30) NOT NULL,
    TURNO nvarchar(10) NOT NULL,
    IDADE_BASE int NOT NULL,
    NOTAS_ATIVAS bit NOT NULL
);
DECLARE @TurmasHistoricas TABLE (COD varchar(20) NOT NULL PRIMARY KEY, ID int NOT NULL);
DECLARE @Disciplinas TABLE (
    ID_TURMA int NOT NULL,
    COD_TURMA varchar(20) NOT NULL,
    NOME nvarchar(80) NOT NULL,
    AULAS int NOT NULL,
    COD_PROFESSOR varchar(24) NOT NULL,
    ID int NULL
);
DECLARE @Alunos TABLE (
    SEQ int NOT NULL PRIMARY KEY,
    COD_TURMA varchar(20) NOT NULL,
    ID_TURMA int NOT NULL,
    ID_FAMILIA int NOT NULL,
    MATRICULA nvarchar(30) NOT NULL,
    CPF char(11) NOT NULL,
    NOME nvarchar(150) NOT NULL,
    NASCIMENTO date NOT NULL,
    ID int NULL
);
DECLARE @Familias TABLE (NUMERO int NOT NULL PRIMARY KEY);
DECLARE @ResponsaveisPlano TABLE (
    SEQ int NOT NULL,
    FAMILIA int NOT NULL,
    PARENTESCO varchar(15) NOT NULL,
    NOME nvarchar(150) NOT NULL,
    TELEFONE nvarchar(30) NOT NULL,
    EMAIL nvarchar(150) NULL,
    CPF char(11) NOT NULL,
    ID int NULL
);
DECLARE @TurmaLancador TABLE (COD_TURMA varchar(20) NOT NULL PRIMARY KEY, ID_TURMA int NOT NULL, ID_USUARIO int NOT NULL);
DECLARE @ProfessorMateria TABLE (COD_PROFESSOR varchar(24) NOT NULL, NOME nvarchar(80) NOT NULL);
DECLARE @DisciplinasCriadas TABLE (ID int NOT NULL, ID_TURMA int NOT NULL, NOME nvarchar(80) NOT NULL);
DECLARE @UsuariosCriados TABLE (CPF nvarchar(14) NOT NULL, ID int NOT NULL);
DECLARE @AlunosCriados TABLE (MATRICULA nvarchar(30) NOT NULL, ID int NOT NULL);
DECLARE @ResponsaveisCriados TABLE (CPF char(11) NOT NULL, ID int NOT NULL);

BEGIN TRY
    BEGIN TRANSACTION;

    PRINT N'Iniciando reset transacional do banco de desenvolvimento...';
    PRINT N'Limpando as tabelas do sistema em ordem de chave estrangeira.';
    PRINT N'A linha dbo.TB_USUARIOS.ID = 1 será preservada.';

    /* Marcações antigas do populador seletivo; a tabela permanece no schema.
       SQL dinâmico apenas aqui evita resolução de nome se ela não existir. */
    IF OBJECT_ID(N'dbo.TB_DEMO_IDS', N'U') IS NOT NULL
        EXEC sys.sp_executesql N'DELETE FROM dbo.TB_DEMO_IDS;';

    /* Tabelas-filhas primeiro. As FKs permanecem ativas durante todo o reset. */
    DELETE FROM dbo.TB_HISTORICO_OBSERVACOES;
    DELETE FROM dbo.TB_HISTORICO_RELATORIOS;
    DELETE FROM dbo.TB_CHAMADA_RETIFICACOES;
    DELETE FROM dbo.TB_NOTAS;
    DELETE FROM dbo.TB_ATRIBUICOES;
    DELETE FROM dbo.TB_ALUNO_RESPONSAVEIS;
    DELETE FROM dbo.TB_TRANSFERENCIAS;
    DELETE FROM dbo.TB_ALUNO_SAIDAS_EXTERNAS;
    DELETE FROM dbo.TB_PROMOCOES;
    DELETE FROM dbo.TB_CHAMADA;
    DELETE FROM dbo.TB_OBSERVACOES;
    DELETE FROM dbo.TB_RELATORIOS_ANUAIS;
    DELETE FROM dbo.TB_TURMA_PROFESSORES;
    DELETE FROM dbo.TB_TURMA_DISCIPLINAS;
    DELETE FROM dbo.TB_FERIADOS;
    DELETE FROM dbo.TB_SECRETARIO_RELATORIOS;
    DELETE FROM dbo.TB_SECRETARIO_OBSERVACOES;
    DELETE FROM dbo.TB_ACESSO;
    DELETE FROM dbo.TB_PROFESSOR_MATERIAS;
    DELETE FROM dbo.TB_ALUNOS;
    DELETE FROM dbo.TB_TURMAS;
    DELETE FROM dbo.TB_RESPONSAVEIS;
    DELETE FROM dbo.TB_PERIODOS;
    DELETE FROM dbo.TB_UNIDADES;
    DELETE FROM dbo.TB_LOGIN_TENTATIVAS;
    DELETE FROM dbo.TB_USUARIOS WHERE ID <> 1;

    /* Unidades e períodos de demonstração. */
    INSERT INTO dbo.TB_UNIDADES (unidade)
    SELECT NOME FROM @UnidadesPlano;
    UPDATE p SET p.ID = u.id
    FROM @UnidadesPlano p
    INNER JOIN dbo.TB_UNIDADES u ON u.unidade = p.NOME;
    INSERT INTO @Unidades (COD, ID) SELECT COD, ID FROM @UnidadesPlano;

    INSERT INTO dbo.TB_PERIODOS (NOME, HORA_INICIO, HORA_FIM, TURNO)
    SELECT NOME, HORA_INICIO, HORA_FIM, TURNO FROM @PeriodosPlano;
    UPDATE p SET p.ID = db.ID
    FROM @PeriodosPlano p
    INNER JOIN dbo.TB_PERIODOS db ON db.NOME = p.NOME;
    INSERT INTO @Periodos (COD, ID, TURNO)
    SELECT COD, ID, TURNO FROM @PeriodosPlano;

    /* Contas fictícias. A conta real ID=1 e sua senha/campos ficam intactos. */
    INSERT INTO dbo.TB_USUARIOS
        (NOME, SENHA, NIVEL, CPF, EMAIL, ATIVO, TURNO, CATEGORIA)
    OUTPUT INSERTED.CPF, INSERTED.ID INTO @UsuariosCriados (CPF, ID)
    SELECT NOME, @SENHA_DEMO, NIVEL, CPF, EMAIL, 1, TURNO, CATEGORIA
    FROM @UsuariosPlano;
    INSERT INTO @Usuarios (COD, ID)
    SELECT p.COD, c.ID
    FROM @UsuariosPlano p
    INNER JOIN @UsuariosCriados c ON c.CPF = p.CPF;

    /* Libera o diretor fictício para todas as unidades de demonstração. */
    INSERT INTO dbo.TB_ACESSO (id_usuario, id_unidade, ativo)
    SELECT u.ID, un.ID, 1
    FROM @Unidades un
    CROSS JOIN @Usuarios u
    WHERE u.COD = 'DIRETOR';

    /* Turmas: as quatro séries de Creche ficam TODAS em Integral. */
    INSERT INTO dbo.TB_TURMAS
        (ID_UNIDADE, NOME, ETAPA_ENSINO, SERIE, ANO_LETIVO, ID_PERIODO,
         CAPACIDADE, NOTAS_ATIVAS, ATIVO)
    SELECT un.ID, p.NOME, p.ETAPA, p.SERIE, @ANO, per.ID,
           p.CAPACIDADE, p.NOTAS_ATIVAS, 1
    FROM @TurmasPlano p
    INNER JOIN @Unidades un ON un.COD = p.UNIDADE_COD
    INNER JOIN @Periodos per ON per.COD = p.PERIODO_COD;

    INSERT INTO @Turmas (ORDEM, COD, ID, SERIE, ETAPA, TURNO, IDADE_BASE, NOTAS_ATIVAS)
    SELECT p.ORDEM, p.COD, t.ID, p.SERIE, p.ETAPA, per.TURNO, p.IDADE_BASE, p.NOTAS_ATIVAS
    FROM @TurmasPlano p
    INNER JOIN @Unidades un ON un.COD = p.UNIDADE_COD
    INNER JOIN @Periodos per ON per.COD = p.PERIODO_COD
    INNER JOIN dbo.TB_TURMAS t ON t.ID_UNIDADE = un.ID AND t.NOME = p.NOME
                              AND t.SERIE = p.SERIE AND t.ANO_LETIVO = @ANO;

    /* Duas turmas encerradas do ano anterior sustentam exemplos coerentes de
       promoção (Pré I → Pré II) e retenção (1º ano → 1º ano) no histórico. */
    INSERT INTO dbo.TB_TURMAS
        (ID_UNIDADE, NOME, ETAPA_ENSINO, SERIE, ANO_LETIVO, ID_PERIODO,
         CAPACIDADE, NOTAS_ATIVAS, ATIVO)
    SELECT un.ID, p.NOME, p.ETAPA, p.SERIE, @ANO - 1, per.ID,
           p.CAPACIDADE, p.NOTAS_ATIVAS, 0
    FROM @TurmasPlano p
    INNER JOIN @Unidades un ON un.COD = p.UNIDADE_COD
    INNER JOIN @Periodos per ON per.COD = p.PERIODO_COD
    WHERE p.COD IN ('PRE_I', 'FUND_1');
    INSERT INTO @TurmasHistoricas (COD, ID)
    SELECT p.COD, t.ID
    FROM @TurmasPlano p
    INNER JOIN @Unidades un ON un.COD = p.UNIDADE_COD
    INNER JOIN dbo.TB_TURMAS t ON t.ID_UNIDADE = un.ID AND t.NOME = p.NOME
                              AND t.SERIE = p.SERIE AND t.ANO_LETIVO = @ANO - 1
    WHERE p.COD IN ('PRE_I', 'FUND_1');

    /* Categorias e matérias que os professores podem lecionar. */
    INSERT INTO @ProfessorMateria (COD_PROFESSOR, NOME) VALUES
        ('PEBI_FUND', N'Língua Portuguesa'), ('PEBI_FUND', N'Matemática'),
        ('PEBII_LP', N'Língua Portuguesa'), ('PEBII_MAT', N'Matemática'),
        ('PEBII_CI', N'Ciências'), ('ESP_EF', N'Educação Física'),
        ('ESP_INGLES', N'Inglês'), ('ESP_ARTES', N'Artes');
    INSERT INTO dbo.TB_PROFESSOR_MATERIAS (ID_USUARIO, NOME)
    SELECT u.ID, pm.NOME
    FROM @ProfessorMateria pm
    INNER JOIN @Usuarios u ON u.COD = pm.COD_PROFESSOR;

    /* Regentes de Creche por período: um PDI de manhã e outro à tarde.
       Apoio Creche fica vinculado o dia todo (Integral). */
    INSERT INTO dbo.TB_TURMA_PROFESSORES
        (ID_TURMA, ID_USUARIO, PAPEL, TURNO, ATIVO, VINCULO_DIRETO)
    SELECT t.ID, u.ID, N'REGENTE', N'MANHA', 1, 1
    FROM @Turmas t CROSS JOIN @Usuarios u
    WHERE t.ETAPA = N'INFANTIL_CRECHE' AND u.COD = 'PDI_MANHA';

    INSERT INTO dbo.TB_TURMA_PROFESSORES
        (ID_TURMA, ID_USUARIO, PAPEL, TURNO, ATIVO, VINCULO_DIRETO)
    SELECT t.ID, u.ID, N'REGENTE', N'TARDE', 1, 1
    FROM @Turmas t CROSS JOIN @Usuarios u
    WHERE t.ETAPA = N'INFANTIL_CRECHE' AND u.COD = 'PDI_TARDE';

    INSERT INTO dbo.TB_TURMA_PROFESSORES
        (ID_TURMA, ID_USUARIO, PAPEL, TURNO, ATIVO, VINCULO_DIRETO)
    SELECT t.ID, u.ID, N'AUXILIAR', N'INTEGRAL', 1, 1
    FROM @Turmas t
    CROSS APPLY (SELECT CASE WHEN t.COD IN ('CRE_BI', 'CRE_BII') THEN 'APOIO_A' ELSE 'APOIO_B' END AS COD) a
    INNER JOIN @Usuarios u ON u.COD = a.COD
    WHERE t.ETAPA = N'INFANTIL_CRECHE';

    /* Regente PEB I direto em Pré I e Pré II. */
    INSERT INTO dbo.TB_TURMA_PROFESSORES
        (ID_TURMA, ID_USUARIO, PAPEL, TURNO, ATIVO, VINCULO_DIRETO)
    SELECT t.ID, u.ID, N'REGENTE', t.TURNO, 1, 1
    FROM @Turmas t
    INNER JOIN @Usuarios u ON u.COD = CASE t.COD WHEN 'PRE_I' THEN 'PEBI_PRE_M' ELSE 'PEBI_PRE_T' END
    WHERE t.ETAPA = N'INFANTIL_PRE_ESCOLA';

    /* Matérias específicas em Creche/Pré e matérias regulares no Fundamental.
       Os especialistas ficam em equipe via TB_ATRIBUICOES (VINCULO_DIRETO=0). */
    INSERT INTO @Disciplinas (ID_TURMA, COD_TURMA, NOME, AULAS, COD_PROFESSOR)
    SELECT t.ID, t.COD, v.NOME, v.AULAS, v.COD_PROFESSOR
    FROM @Turmas t
    CROSS APPLY (VALUES
        (N'Educação Física', 2, 'ESP_EF', 1),
        (N'Artes',           2, 'ESP_ARTES', 1),
        (N'Inglês',          2, 'ESP_INGLES', 1),
        (N'Língua Portuguesa', 5,
            CASE WHEN t.COD = 'FUND_1' THEN 'PEBI_FUND' ELSE 'PEBII_LP' END,
            CASE WHEN t.COD LIKE 'FUND_%' THEN 1 ELSE 0 END),
        (N'Matemática', 5,
            CASE WHEN t.COD = 'FUND_1' THEN 'PEBI_FUND' ELSE 'PEBII_MAT' END,
            CASE WHEN t.COD LIKE 'FUND_%' THEN 1 ELSE 0 END),
        (N'Ciências', 3, 'PEBII_CI', CASE WHEN t.COD IN ('FUND_6','FUND_9','FUND_6_B') THEN 1 ELSE 0 END)
    ) v(NOME, AULAS, COD_PROFESSOR, INCLUIR)
    WHERE v.INCLUIR = 1;

    INSERT INTO dbo.TB_TURMA_DISCIPLINAS (ID_TURMA, NOME, AULAS)
    OUTPUT INSERTED.ID, INSERTED.ID_TURMA, INSERTED.NOME
        INTO @DisciplinasCriadas (ID, ID_TURMA, NOME)
    SELECT ID_TURMA, NOME, AULAS FROM @Disciplinas;

    INSERT INTO dbo.TB_ATRIBUICOES (ID_DISCIPLINA, ID_PROFESSOR, AULAS)
    SELECT dc.ID, u.ID, p.AULAS
    FROM @Disciplinas p
    INNER JOIN @DisciplinasCriadas dc ON dc.ID_TURMA = p.ID_TURMA AND dc.NOME = p.NOME
    INNER JOIN @Usuarios u ON u.COD = p.COD_PROFESSOR;

    /* Mantém a coluna legada sincronizada com o professor de maior carga
       naquela matéria, como faz a tela de atribuição. */
    UPDATE td
       SET ID_PROFESSOR = (
           SELECT TOP (1) a.ID_PROFESSOR
           FROM dbo.TB_ATRIBUICOES a
           WHERE a.ID_DISCIPLINA = td.ID
           ORDER BY a.AULAS DESC, a.ID_PROFESSOR
       )
    FROM dbo.TB_TURMA_DISCIPLINAS td
    INNER JOIN @DisciplinasCriadas dc ON dc.ID = td.ID;

    INSERT INTO dbo.TB_TURMA_PROFESSORES
        (ID_TURMA, ID_USUARIO, PAPEL, TURNO, ATIVO, VINCULO_DIRETO)
    SELECT DISTINCT d.ID_TURMA, a.ID_PROFESSOR, N'AUXILIAR', t.TURNO, 1, 0
    FROM dbo.TB_ATRIBUICOES a
    INNER JOIN @DisciplinasCriadas d ON d.ID = a.ID_DISCIPLINA
    INNER JOIN @Turmas t ON t.ID = d.ID_TURMA
    WHERE NOT EXISTS (
        SELECT 1 FROM dbo.TB_TURMA_PROFESSORES tp
        WHERE tp.ID_TURMA = d.ID_TURMA AND tp.ID_USUARIO = a.ID_PROFESSOR
    );

    /* Mantém o campo legado apontando para um regente, quando há regência direta. */
    UPDATE t
       SET ID_PROFESSOR = (
           SELECT TOP (1) tp.ID_USUARIO
           FROM dbo.TB_TURMA_PROFESSORES tp
           WHERE tp.ID_TURMA = t.ID AND tp.ATIVO = 1 AND tp.PAPEL = N'REGENTE'
           ORDER BY CASE tp.TURNO WHEN N'MANHA' THEN 1 WHEN N'TARDE' THEN 2 ELSE 3 END, tp.ID
       )
    FROM dbo.TB_TURMAS t
    INNER JOIN @Turmas st ON st.ID = t.ID;

    /* Duas substituições simultâneas: apoio Integral coberto por um substituto
       de manhã e outro à tarde, sem sobrepor os períodos. */
    DECLARE @TurmaComSubstituicao int = (SELECT ID FROM @Turmas WHERE COD = 'CRE_BI');
    DECLARE @ApoioAusente int = (SELECT ID FROM @Usuarios WHERE COD = 'APOIO_A');
    DECLARE @SubManha int = (SELECT ID FROM @Usuarios WHERE COD = 'SUB_MANHA');
    DECLARE @SubTarde int = (SELECT ID FROM @Usuarios WHERE COD = 'SUB_TARDE');
    INSERT INTO dbo.TB_TURMA_PROFESSORES
        (ID_TURMA, ID_USUARIO, PAPEL, TURNO, ID_SUBSTITUIDO, DATA_INICIO, DATA_FIM,
         MOTIVO, VER_OBSERVACOES, VINCULO_DIRETO, ATIVO)
    VALUES
        (@TurmaComSubstituicao, @SubManha, N'SUBSTITUTO', N'MANHA', @ApoioAusente,
         DATEADD(DAY, -3, @HOJE), DATEADD(DAY, 14, @HOJE), N'Licença — cobertura de teste', 0, 0, 1),
        (@TurmaComSubstituicao, @SubTarde, N'SUBSTITUTO', N'TARDE', @ApoioAusente,
         DATEADD(DAY, -3, @HOJE), DATEADD(DAY, 14, @HOJE), N'Licença — cobertura de teste', 0, 0, 1);

    /* Responsáveis fictícios compartilhados entre pares de irmãos. Os CPFs
       são gerados com dígitos verificadores; não correspondem a dados pessoais
       fornecidos pelo usuário. */
    INSERT INTO @Familias (NUMERO) VALUES (1),(2),(3),(4),(5),(6),(7),(8),(9),(10);

    DECLARE @ResponsaveisBrutos TABLE (
        SEQ int NOT NULL,
        FAMILIA int NOT NULL,
        PARENTESCO varchar(15) NOT NULL,
        NOME nvarchar(150) NOT NULL,
        TELEFONE nvarchar(30) NOT NULL,
        EMAIL nvarchar(150) NULL
    );
    INSERT INTO @ResponsaveisBrutos (SEQ, FAMILIA, PARENTESCO, NOME, TELEFONE, EMAIL)
    SELECT (f.NUMERO - 1) * 2 + 1, f.NUMERO, 'MAE',
           N'MÃE DEMO ' + RIGHT(N'00' + CONVERT(nvarchar(2), f.NUMERO), 2),
           N'(15) 90000-' + RIGHT(N'0000' + CONVERT(nvarchar(4), f.NUMERO), 4),
           N'mae.' + RIGHT(N'00' + CONVERT(nvarchar(2), f.NUMERO), 2) + N'@example.invalid'
    FROM @Familias f
    UNION ALL
    SELECT (f.NUMERO - 1) * 2 + 2, f.NUMERO, 'PAI',
           N'PAI DEMO ' + RIGHT(N'00' + CONVERT(nvarchar(2), f.NUMERO), 2),
           N'(15) 90001-' + RIGHT(N'0000' + CONVERT(nvarchar(4), f.NUMERO), 4),
           N'pai.' + RIGHT(N'00' + CONVERT(nvarchar(2), f.NUMERO), 2) + N'@example.invalid'
    FROM @Familias f
    UNION ALL
    SELECT 21, 1, 'OUTRO', N'AVÓ DEMO 01', N'(15) 90002-0001', N'avo.01@example.invalid';

    ;WITH Bases AS (
        SELECT r.*, RIGHT('000000000' + CONVERT(varchar(9), 800000000 + r.SEQ), 9) AS BASE_CPF
        FROM @ResponsaveisBrutos r
    )
    INSERT INTO @ResponsaveisPlano (SEQ, FAMILIA, PARENTESCO, NOME, TELEFONE, EMAIL, CPF)
    SELECT b.SEQ, b.FAMILIA, b.PARENTESCO, b.NOME, b.TELEFONE, b.EMAIL,
           b.BASE_CPF + CONVERT(varchar(1), d1.DIGITO) + CONVERT(varchar(1), d2.DIGITO)
    FROM Bases b
    CROSS APPLY (
        SELECT
            CONVERT(int, SUBSTRING(b.BASE_CPF, 1, 1)) * 10
          + CONVERT(int, SUBSTRING(b.BASE_CPF, 2, 1)) * 9
          + CONVERT(int, SUBSTRING(b.BASE_CPF, 3, 1)) * 8
          + CONVERT(int, SUBSTRING(b.BASE_CPF, 4, 1)) * 7
          + CONVERT(int, SUBSTRING(b.BASE_CPF, 5, 1)) * 6
          + CONVERT(int, SUBSTRING(b.BASE_CPF, 6, 1)) * 5
          + CONVERT(int, SUBSTRING(b.BASE_CPF, 7, 1)) * 4
          + CONVERT(int, SUBSTRING(b.BASE_CPF, 8, 1)) * 3
          + CONVERT(int, SUBSTRING(b.BASE_CPF, 9, 1)) * 2 AS SOMA
    ) s1
    CROSS APPLY (
        SELECT CASE WHEN s1.SOMA % 11 < 2 THEN 0 ELSE 11 - (s1.SOMA % 11) END AS DIGITO
    ) d1
    CROSS APPLY (
        SELECT
            CONVERT(int, SUBSTRING(b.BASE_CPF, 1, 1)) * 11
          + CONVERT(int, SUBSTRING(b.BASE_CPF, 2, 1)) * 10
          + CONVERT(int, SUBSTRING(b.BASE_CPF, 3, 1)) * 9
          + CONVERT(int, SUBSTRING(b.BASE_CPF, 4, 1)) * 8
          + CONVERT(int, SUBSTRING(b.BASE_CPF, 5, 1)) * 7
          + CONVERT(int, SUBSTRING(b.BASE_CPF, 6, 1)) * 6
          + CONVERT(int, SUBSTRING(b.BASE_CPF, 7, 1)) * 5
          + CONVERT(int, SUBSTRING(b.BASE_CPF, 8, 1)) * 4
          + CONVERT(int, SUBSTRING(b.BASE_CPF, 9, 1)) * 3
          + d1.DIGITO * 2 AS SOMA
    ) s2
    CROSS APPLY (
        SELECT CASE WHEN s2.SOMA % 11 < 2 THEN 0 ELSE 11 - (s2.SOMA % 11) END AS DIGITO
    ) d2;

    INSERT INTO dbo.TB_RESPONSAVEIS (CPF, NOME, TELEFONE, EMAIL)
    OUTPUT INSERTED.CPF, INSERTED.ID INTO @ResponsaveisCriados (CPF, ID)
    SELECT CPF, NOME, TELEFONE, EMAIL FROM @ResponsaveisPlano;
    UPDATE rp SET rp.ID = rc.ID
    FROM @ResponsaveisPlano rp
    INNER JOIN @ResponsaveisCriados rc ON rc.CPF = rp.CPF;

    /* Dois alunos por turma; irmãos consecutivos compartilham Mãe e Pai. */
    ;WITH Brutos AS (
        SELECT ROW_NUMBER() OVER (ORDER BY t.ORDEM, n.N) AS SEQ,
               t.COD AS COD_TURMA, t.ID AS ID_TURMA, t.IDADE_BASE,
               ((ROW_NUMBER() OVER (ORDER BY t.ORDEM, n.N) + 1) / 2) AS ID_FAMILIA
        FROM @Turmas t
        CROSS JOIN (VALUES (1),(2)) n(N)
    ), Bases AS (
        SELECT b.*, RIGHT('000000000' + CONVERT(varchar(9), 900000000 + b.SEQ), 9) AS BASE_CPF,
               CONVERT(nvarchar(4), @ANO) + RIGHT(N'00000' + CONVERT(nvarchar(5), b.SEQ), 5) AS MATRICULA,
               N'ALUNO DEMO ' + RIGHT(N'000' + CONVERT(nvarchar(3), b.SEQ), 3) AS NOME,
               DATEADD(DAY, -(30 + (b.SEQ % 60)),
                   DATEADD(YEAR, -b.IDADE_BASE, DATEFROMPARTS(@ANO, 3, 31))) AS NASCIMENTO
        FROM Brutos b
    )
    INSERT INTO @Alunos (SEQ, COD_TURMA, ID_TURMA, ID_FAMILIA, MATRICULA, CPF, NOME, NASCIMENTO)
    SELECT b.SEQ, b.COD_TURMA, b.ID_TURMA, b.ID_FAMILIA, b.MATRICULA,
           b.BASE_CPF + CONVERT(varchar(1), d1.DIGITO) + CONVERT(varchar(1), d2.DIGITO),
           b.NOME, b.NASCIMENTO
    FROM Bases b
    CROSS APPLY (
        SELECT
            CONVERT(int, SUBSTRING(b.BASE_CPF, 1, 1)) * 10
          + CONVERT(int, SUBSTRING(b.BASE_CPF, 2, 1)) * 9
          + CONVERT(int, SUBSTRING(b.BASE_CPF, 3, 1)) * 8
          + CONVERT(int, SUBSTRING(b.BASE_CPF, 4, 1)) * 7
          + CONVERT(int, SUBSTRING(b.BASE_CPF, 5, 1)) * 6
          + CONVERT(int, SUBSTRING(b.BASE_CPF, 6, 1)) * 5
          + CONVERT(int, SUBSTRING(b.BASE_CPF, 7, 1)) * 4
          + CONVERT(int, SUBSTRING(b.BASE_CPF, 8, 1)) * 3
          + CONVERT(int, SUBSTRING(b.BASE_CPF, 9, 1)) * 2 AS SOMA
    ) s1
    CROSS APPLY (
        SELECT CASE WHEN s1.SOMA % 11 < 2 THEN 0 ELSE 11 - (s1.SOMA % 11) END AS DIGITO
    ) d1
    CROSS APPLY (
        SELECT
            CONVERT(int, SUBSTRING(b.BASE_CPF, 1, 1)) * 11
          + CONVERT(int, SUBSTRING(b.BASE_CPF, 2, 1)) * 10
          + CONVERT(int, SUBSTRING(b.BASE_CPF, 3, 1)) * 9
          + CONVERT(int, SUBSTRING(b.BASE_CPF, 4, 1)) * 8
          + CONVERT(int, SUBSTRING(b.BASE_CPF, 5, 1)) * 7
          + CONVERT(int, SUBSTRING(b.BASE_CPF, 6, 1)) * 6
          + CONVERT(int, SUBSTRING(b.BASE_CPF, 7, 1)) * 5
          + CONVERT(int, SUBSTRING(b.BASE_CPF, 8, 1)) * 4
          + CONVERT(int, SUBSTRING(b.BASE_CPF, 9, 1)) * 3
          + d1.DIGITO * 2 AS SOMA
    ) s2
    CROSS APPLY (
        SELECT CASE WHEN s2.SOMA % 11 < 2 THEN 0 ELSE 11 - (s2.SOMA % 11) END AS DIGITO
    ) d2;

    INSERT INTO dbo.TB_ALUNOS
        (ID_TURMA, MATRICULA, CPF, NOME, NASCIMENTO, DATA_MATRICULA,
         RESPONSAVEL, TELEFONE, EMAIL, ATIVO)
    OUTPUT INSERTED.MATRICULA, INSERTED.ID INTO @AlunosCriados (MATRICULA, ID)
    SELECT a.ID_TURMA, a.MATRICULA, a.CPF, a.NOME, a.NASCIMENTO,
           CASE WHEN a.NASCIMENTO >= DATEFROMPARTS(@ANO, 2, 1)
                THEN DATEADD(DAY, 1, a.NASCIMENTO)
                ELSE DATEFROMPARTS(@ANO, 2, 1) END,
           mae.NOME, mae.TELEFONE, mae.EMAIL, 1
    FROM @Alunos a
    INNER JOIN @ResponsaveisPlano mae ON mae.FAMILIA = a.ID_FAMILIA AND mae.PARENTESCO = 'MAE';
    UPDATE a SET a.ID = c.ID
    FROM @Alunos a
    INNER JOIN @AlunosCriados c ON c.MATRICULA = a.MATRICULA;

    INSERT INTO dbo.TB_ALUNO_RESPONSAVEIS
        (ID_ALUNO, ID_RESPONSAVEL, PARENTESCO, DESCRICAO_OUTRO, CONTATO_PRINCIPAL)
    SELECT a.ID, r.ID, r.PARENTESCO, NULL,
           CASE WHEN r.PARENTESCO = 'MAE' THEN 1 ELSE 0 END
    FROM @Alunos a
    INNER JOIN @ResponsaveisPlano r
        ON r.FAMILIA = a.ID_FAMILIA AND r.PARENTESCO IN ('MAE', 'PAI')
    UNION ALL
    SELECT a.ID, r.ID, 'OUTRO', N'Avó', 0
    FROM @Alunos a
    INNER JOIN @ResponsaveisPlano r ON r.FAMILIA = 1 AND r.PARENTESCO = 'OUTRO'
    WHERE a.SEQ = 1;

    /* AEE e saúde: registros exemplares, sem incluir informação médica no crachá. */
    UPDATE a
       SET PASSA_AEE = 1,
           ID_PROFESSOR_AEE = (SELECT ID FROM @Usuarios WHERE COD = 'AEE'),
           AEE_OBSERVACOES = N'Atendimento de demonstração — terça-feira, 14h.'
    FROM dbo.TB_ALUNOS a
    INNER JOIN @Alunos st ON st.ID = a.ID
    WHERE st.SEQ IN (2, 12);
    UPDATE a SET ALERGIAS = N'DADO FICTÍCIO DE TESTE — alergia alimentar; confirmar protocolo com a família.'
    FROM dbo.TB_ALUNOS a INNER JOIN @Alunos st ON st.ID = a.ID WHERE st.SEQ = 1;
    UPDATE a SET RESTRICOES_ALIMENTARES = N'DADO FICTÍCIO DE TESTE — merenda adaptada.'
    FROM dbo.TB_ALUNOS a INNER JOIN @Alunos st ON st.ID = a.ID WHERE st.SEQ = 3;
    UPDATE a SET MEDICACAO = N'DADO FICTÍCIO DE TESTE — medicação conforme orientação da família.'
    FROM dbo.TB_ALUNOS a INNER JOIN @Alunos st ON st.ID = a.ID WHERE st.SEQ = 4;
    UPDATE a SET CONDICAO_ESPECIFICA = N'DADO FICTÍCIO DE TESTE — acompanhamento pedagógico individual.'
    FROM dbo.TB_ALUNOS a INNER JOIN @Alunos st ON st.ID = a.ID WHERE st.SEQ = 5;
    UPDATE a SET OUTROS_ACOMPANHAMENTOS = N'DADO FICTÍCIO DE TESTE — acompanhamento de fonoaudiologia.'
    FROM dbo.TB_ALUNOS a INNER JOIN @Alunos st ON st.ID = a.ID WHERE st.SEQ = 6;

    /* Professor que lança registros em cada turma. */
    INSERT INTO @TurmaLancador (COD_TURMA, ID_TURMA, ID_USUARIO)
    SELECT t.COD, t.ID,
           u.ID
    FROM @Turmas t
    INNER JOIN @Usuarios u ON u.COD = CASE
        WHEN t.ETAPA = N'INFANTIL_CRECHE' THEN 'PDI_MANHA'
        WHEN t.COD = 'PRE_I' THEN 'PEBI_PRE_M'
        WHEN t.COD = 'PRE_II' THEN 'PEBI_PRE_T'
        WHEN t.COD = 'FUND_1' THEN 'PEBI_FUND'
        ELSE 'PEBII_LP'
    END;

    /* Duas datas recentes de chamada, com presença, atraso, falta e justificada. */
    DECLARE @DiasChamada TABLE (ORDEM int NOT NULL, DATA date NOT NULL);
    INSERT INTO @DiasChamada (ORDEM, DATA) VALUES
        (1, DATEADD(DAY, -1, @HOJE)),
        (2, DATEADD(DAY, -2, @HOJE));
    INSERT INTO dbo.TB_CHAMADA
        (ID_ALUNO, DATA, STATUS, JUSTIFICATIVA, ID_USUARIO, CONFIRMADA_EM, CONFIRMADA_POR)
    SELECT a.ID, d.DATA,
           CASE WHEN a.SEQ % 7 = 0 THEN N'F'
                WHEN a.SEQ % 11 = 0 THEN N'J'
                WHEN a.SEQ % 5 = 0 THEN N'A'
                ELSE N'P' END,
           CASE WHEN a.SEQ % 11 = 0 THEN N'JUSTIFICATIVA FICTÍCIA — comunicado pela família.'
                WHEN a.SEQ % 7 = 0 THEN N'FALTA DE TESTE — sem justificativa registrada.'
                ELSE NULL END,
           tl.ID_USUARIO,
           DATEADD(MINUTE, 510, CONVERT(datetime, d.DATA)),
           tl.ID_USUARIO
    FROM @Alunos a
    INNER JOIN @TurmaLancador tl ON tl.COD_TURMA = a.COD_TURMA
    CROSS JOIN @DiasChamada d;

    /* Uma retificação de exemplo: uma falta de teste passa a justificada. */
    DECLARE @ChamadaRetificar int;
    SELECT TOP (1) @ChamadaRetificar = ID
    FROM dbo.TB_CHAMADA WHERE STATUS = N'F' ORDER BY ID;
    IF @ChamadaRetificar IS NOT NULL
    BEGIN
        INSERT INTO dbo.TB_CHAMADA_RETIFICACOES
            (ID_CHAMADA, STATUS_ANTERIOR, STATUS_NOVO, JUSTIFICATIVA_ANTERIOR,
             JUSTIFICATIVA_NOVA, MOTIVO, ID_USUARIO)
        SELECT c.ID, c.STATUS, N'J', c.JUSTIFICATIVA,
               N'JUSTIFICATIVA FICTÍCIA — documento recebido posteriormente.',
               N'Retificação de demonstração.', (SELECT ID FROM @Usuarios WHERE COD = 'DIRETOR')
        FROM dbo.TB_CHAMADA c WHERE c.ID = @ChamadaRetificar;
        UPDATE dbo.TB_CHAMADA
           SET STATUS = N'J', JUSTIFICATIVA = N'JUSTIFICATIVA FICTÍCIA — documento recebido posteriormente.',
               MODIFIED = GETDATE()
        WHERE ID = @ChamadaRetificar;
    END;

    /* Observações recentes e uma observação restrita ao AEE. */
    INSERT INTO dbo.TB_OBSERVACOES (ID_ALUNO, DATA, CATEGORIA, TEXTO, ID_USUARIO, RESTRITA_AEE)
    SELECT a.ID, @HOJE, N'Desenvolvimento geral',
           N'REGISTRO FICTÍCIO DE DEMONSTRAÇÃO — participação e progresso no período.',
           tl.ID_USUARIO, 0
    FROM @Alunos a
    INNER JOIN @TurmaLancador tl ON tl.COD_TURMA = a.COD_TURMA
    WHERE a.SEQ % 2 = 0;
    INSERT INTO dbo.TB_OBSERVACOES (ID_ALUNO, DATA, CATEGORIA, TEXTO, ID_USUARIO, RESTRITA_AEE)
    SELECT a.ID, @HOJE, N'Acompanhamento AEE',
           N'REGISTRO FICTÍCIO RESTRITO AO AEE — observação de demonstração.',
           (SELECT ID FROM @Usuarios WHERE COD = 'AEE'), 1
    FROM @Alunos a WHERE a.SEQ = 2;

    /* Notas de exemplo somente nas turmas do Fundamental com NOTAS_ATIVAS. */
    INSERT INTO dbo.TB_NOTAS (ID_ALUNO, ID_DISCIPLINA, BIMESTRE, NOTA, ID_USUARIO)
    SELECT a.ID, dc.ID, 1,
           CONVERT(decimal(4,2), 6.5 + (a.SEQ % 30) / 10.0), atb.ID_PROFESSOR
    FROM @Alunos a
    INNER JOIN @Turmas t ON t.COD = a.COD_TURMA AND t.NOTAS_ATIVAS = 1
    INNER JOIN @DisciplinasCriadas dc ON dc.ID_TURMA = t.ID
    INNER JOIN dbo.TB_ATRIBUICOES atb ON atb.ID_DISCIPLINA = dc.ID;

    /* Pareceres de demonstração no bimestre 1. */
    INSERT INTO dbo.TB_RELATORIOS_ANUAIS
        (ID_ALUNO, ANO, PERIODO, PARECER, ENCAMINHAMENTOS, ID_USUARIO)
    SELECT a.ID, @ANO, 'B1',
           N'PARECER FICTÍCIO DE TESTE — desenvolvimento registrado para validar a tela do relatório.',
           N'ENCAMINHAMENTO FICTÍCIO DE TESTE — manter acompanhamento pedagógico.',
           tl.ID_USUARIO
    FROM @Alunos a
    INNER JOIN @TurmaLancador tl ON tl.COD_TURMA = a.COD_TURMA
    WHERE a.SEQ IN (1, 7, 13);

    /* Eventos de progressão do ano anterior: promoção no Pré e retenção no
       Fundamental. A ID_TURMA atual de cada aluno já é a turma de destino. */
    INSERT INTO dbo.TB_PROMOCOES
        (ID_ALUNO, ID_TURMA_ORIGEM, ID_TURMA_DESTINO, DATA_INICIO,
         ID_USUARIO, RESULTADO, MOTIVO_RESULTADO)
    SELECT a.ID, origem.ID, destino.ID, DATEFROMPARTS(@ANO, 2, 1), gestor.ID,
           N'PROMOVIDO', NULL
    FROM @Alunos a
    INNER JOIN @TurmasHistoricas origem ON origem.COD = 'PRE_I'
    INNER JOIN @Turmas destino ON destino.COD = 'PRE_II'
    CROSS JOIN @Usuarios gestor
    WHERE a.SEQ = 11 AND gestor.COD = 'DIRETOR'
    UNION ALL
    SELECT a.ID, origem.ID, destino.ID, DATEFROMPARTS(@ANO, 2, 1), gestor.ID,
           N'RETIDO', N'NOTAS'
    FROM @Alunos a
    INNER JOIN @TurmasHistoricas origem ON origem.COD = 'FUND_1'
    INNER JOIN @Turmas destino ON destino.COD = 'FUND_1'
    CROSS JOIN @Usuarios gestor
    WHERE a.SEQ = 13 AND gestor.COD = 'DIRETOR';

    /* Uma transferência pendente entre duas unidades fictícias: o estudante
       segue na turma de origem até a escola de destino concluir o recebimento. */
    INSERT INTO dbo.TB_TRANSFERENCIAS
        (ID_ALUNO, ID_TURMA_ORIGEM, ID_UNIDADE_DESTINO, RESULTADO,
         MOTIVO_RESULTADO, MOTIVO, DOCUMENTO, ID_USUARIO_SOLIC, SITUACAO)
    SELECT a.ID, a.ID_TURMA, destino.ID, N'TRANSFERENCIA', NULL,
           N'Mudança de endereço — registro fictício para demonstração.',
           N'DEMO-' + CONVERT(nvarchar(4), @ANO) + N'-001', gestor.ID, N'PENDENTE'
    FROM @Alunos a
    INNER JOIN @Unidades destino ON destino.COD = 'EMEF2'
    CROSS JOIN @Usuarios gestor
    WHERE a.SEQ = 15 AND gestor.COD = 'DIRETOR';

    /* Uma saída externa já encerrada, para demonstrar o histórico de saída e
       retorno sem deixar aluno fictício fora da turma no presente. */
    INSERT INTO dbo.TB_ALUNO_SAIDAS_EXTERNAS
        (ID_ALUNO, ID_TURMA_ORIGEM, DATA_SAIDA, ESCOLA_DESTINO,
         MUNICIPIO_DESTINO, UF_DESTINO, MOTIVO, ID_USUARIO_SAIDA,
         DATA_RETORNO, ID_TURMA_RETORNO, ID_USUARIO_RETORNO, RETORNADA_EM)
    SELECT a.ID, a.ID_TURMA, DATEFROMPARTS(@ANO, 3, 10),
           N'ESCOLA EXTERNA DEMO', N'Itapeva', 'SP',
           N'Mudança temporária — registro fictício de demonstração.', gestor.ID,
           DATEFROMPARTS(@ANO, 4, 1), a.ID_TURMA, gestor.ID,
           CONVERT(datetime, DATEFROMPARTS(@ANO, 4, 1))
    FROM @Alunos a
    CROSS JOIN @Usuarios gestor
    WHERE a.SEQ = 19 AND gestor.COD = 'DIRETOR';

    /* Feriados fictícios futuros: um parcial por unidade e um integral da rede. */
    INSERT INTO dbo.TB_FERIADOS (DATA, DESCRICAO, TIPO, HORA_INICIO, HORA_FIM, ID_UNIDADE)
    SELECT DATEADD(DAY, 14, @HOJE), N'DEMO — reunião de teste (parcial)', N'PARCIAL', N'12:00', N'18:00', ID
    FROM @Unidades WHERE COD = 'CMEI'
    UNION ALL
    SELECT DATEADD(DAY, 45, @HOJE), N'DEMO — feriado de teste (rede)', N'INTEGRAL', NULL, NULL, NULL;

    /* As FKs e CHECKs nunca foram desativadas. Qualquer inconsistência
       encontrada durante a limpeza ou carga provoca rollback da transação. */
    COMMIT TRANSACTION;
    PRINT N'Reset e carga de demonstração concluídos.';
    PRINT N'Usuário dbo.TB_USUARIOS.ID=1 preservado sem alterações.';
    PRINT N'Senha comum das contas DEMO: Demo@2026';
END TRY
BEGIN CATCH
    IF XACT_STATE() <> 0 ROLLBACK TRANSACTION;
    PRINT N'ERRO: o reset foi revertido; revise a mensagem abaixo.';
    PRINT ERROR_MESSAGE();
    THROW;
END CATCH;

/* Resumo após o COMMIT. */
SELECT
    (SELECT COUNT(*) FROM dbo.TB_USUARIOS) AS Usuarios,
    (SELECT COUNT(*) FROM dbo.TB_UNIDADES) AS Unidades,
    (SELECT COUNT(*) FROM dbo.TB_PERIODOS) AS Periodos,
    (SELECT COUNT(*) FROM dbo.TB_TURMAS) AS Turmas,
    (SELECT COUNT(*) FROM dbo.TB_ALUNOS) AS Alunos,
    (SELECT COUNT(*) FROM dbo.TB_RESPONSAVEIS) AS Responsaveis,
    (SELECT COUNT(*) FROM dbo.TB_ALUNO_RESPONSAVEIS) AS VinculosFamilia,
    (SELECT COUNT(*) FROM dbo.TB_TURMA_DISCIPLINAS) AS Disciplinas,
    (SELECT COUNT(*) FROM dbo.TB_ATRIBUICOES) AS Atribuicoes,
    (SELECT COUNT(*) FROM dbo.TB_TURMA_PROFESSORES WHERE PAPEL = N'SUBSTITUTO') AS Substituicoes,
    (SELECT COUNT(*) FROM dbo.TB_CHAMADA) AS Chamadas,
    (SELECT COUNT(*) FROM dbo.TB_NOTAS) AS Notas,
    (SELECT COUNT(*) FROM dbo.TB_OBSERVACOES) AS Observacoes,
    (SELECT COUNT(*) FROM dbo.TB_RELATORIOS_ANUAIS) AS Relatorios,
    (SELECT COUNT(*) FROM dbo.TB_PROMOCOES) AS PromocoesRetencoes,
    (SELECT COUNT(*) FROM dbo.TB_TRANSFERENCIAS) AS Transferencias,
    (SELECT COUNT(*) FROM dbo.TB_ALUNO_SAIDAS_EXTERNAS) AS SaidasExternas;

SELECT NOME, CPF, NIVEL, CATEGORIA, EMAIL,
       N'Demo@2026' AS SenhaDemo
FROM dbo.TB_USUARIOS
WHERE CPF IN (SELECT CPF FROM @UsuariosPlano)
ORDER BY NIVEL, NOME;
GO
