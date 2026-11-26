/* Calendário escolar — atualização idempotente.
   SQL Server 2016 ou mais novo. Executar no banco db_chamada.

   Cria TB_CALENDARIOS (início e fim do ano letivo da creche e do ensino
   fundamental), TB_CALENDARIO_EXCECOES (dias letivos/não letivos) e
   acrescenta CALENDARIO em TB_FERIADOS. Feriados antigos viram AMBOS,
   mantendo exatamente o comportamento que tinham.

   Pode ser executado mais de uma vez sem apagar histórico. */
USE [db_chamada];
GO

/* ---------------------------------------------------------- TB_CALENDARIOS */
IF OBJECT_ID(N'dbo.TB_CALENDARIOS', N'U') IS NULL
BEGIN
    CREATE TABLE dbo.TB_CALENDARIOS (
        ID          int IDENTITY(1,1) NOT NULL,
        ANO_LETIVO  int           NOT NULL,
        CALENDARIO  nvarchar(20)  NOT NULL,
        INICIO_ANO  date          NOT NULL,
        CREATED     datetime      NOT NULL CONSTRAINT DF_CALENDARIOS_CREATED DEFAULT (GETDATE()),
        UPDATED     datetime      NULL,
        CONSTRAINT PK_TB_CALENDARIOS PRIMARY KEY CLUSTERED (ID),
        CONSTRAINT UQ_CALENDARIO_ANO UNIQUE (ANO_LETIVO, CALENDARIO),
        CONSTRAINT CK_CALENDARIO_TIPO CHECK (CALENDARIO IN ('CRECHE', 'FUNDAMENTAL')),
        CONSTRAINT CK_CALENDARIO_ANO CHECK (ANO_LETIVO BETWEEN 2000 AND 2100)
    );
    PRINT '  + TB_CALENDARIOS criada';
END
ELSE
    PRINT '  = TB_CALENDARIOS já existe';
GO

/* ------------------------------------------------------- FIM_ANO */
IF OBJECT_ID(N'dbo.TB_CALENDARIOS', N'U') IS NOT NULL
   AND COL_LENGTH(N'dbo.TB_CALENDARIOS', N'FIM_ANO') IS NULL
BEGIN
    ALTER TABLE dbo.TB_CALENDARIOS ADD FIM_ANO date NULL;
    PRINT '  + TB_CALENDARIOS.FIM_ANO criada';
END
GO

IF OBJECT_ID(N'dbo.TB_CALENDARIOS', N'U') IS NOT NULL
   AND COL_LENGTH(N'dbo.TB_CALENDARIOS', N'FIM_ANO') IS NOT NULL
BEGIN
    /* Instalações antigas passam a terminar em 31/12 do próprio ano letivo. */
    UPDATE dbo.TB_CALENDARIOS
       SET FIM_ANO = CONVERT(date,
               CONVERT(varchar(4), CASE WHEN ANO_LETIVO BETWEEN 2000 AND 2100
                                        THEN ANO_LETIVO ELSE YEAR(GETDATE()) END)
               + '-12-31', 23)
     WHERE FIM_ANO IS NULL;

    IF EXISTS (SELECT 1 FROM sys.columns
                WHERE object_id = OBJECT_ID(N'dbo.TB_CALENDARIOS')
                  AND name = N'FIM_ANO' AND is_nullable = 1)
        ALTER TABLE dbo.TB_CALENDARIOS ALTER COLUMN FIM_ANO date NOT NULL;

    PRINT '  = TB_CALENDARIOS.FIM_ANO preenchida (31/12 do ano letivo)';
END
GO

/* ------------------------------------------- TB_CALENDARIO_EXCECOES */
IF OBJECT_ID(N'dbo.TB_CALENDARIO_EXCECOES', N'U') IS NULL
   AND OBJECT_ID(N'dbo.TB_CALENDARIOS', N'U') IS NOT NULL
BEGIN
    CREATE TABLE dbo.TB_CALENDARIO_EXCECOES (
        ID             int IDENTITY(1,1) NOT NULL,
        ID_CALENDARIO  int           NOT NULL,
        DATA           date          NOT NULL,
        TIPO           nvarchar(12)  NOT NULL,
        MOTIVO         nvarchar(150) NULL,
        CREATED        datetime      NOT NULL CONSTRAINT DF_CAL_EXC_CREATED DEFAULT (GETDATE()),
        CONSTRAINT PK_TB_CALENDARIO_EXCECOES PRIMARY KEY CLUSTERED (ID),
        CONSTRAINT UQ_CAL_EXC_DATA UNIQUE (ID_CALENDARIO, DATA),
        CONSTRAINT FK_CAL_EXC_CALENDARIO FOREIGN KEY (ID_CALENDARIO)
            REFERENCES dbo.TB_CALENDARIOS (ID) ON DELETE CASCADE,
        CONSTRAINT CK_CAL_EXC_TIPO CHECK (TIPO IN ('LETIVO', 'NAO_LETIVO'))
    );
    PRINT '  + TB_CALENDARIO_EXCECOES criada';
END
GO

/* ------------------------------------------------------- TB_FERIADOS.CALENDARIO */
IF OBJECT_ID(N'dbo.TB_FERIADOS', N'U') IS NOT NULL
   AND COL_LENGTH(N'dbo.TB_FERIADOS', N'CALENDARIO') IS NULL
BEGIN
    ALTER TABLE dbo.TB_FERIADOS
        ADD CALENDARIO nvarchar(20) NOT NULL
            CONSTRAINT DF_FERIADOS_CALENDARIO DEFAULT (N'AMBOS');
    PRINT '  + TB_FERIADOS.CALENDARIO criada (feriados antigos = AMBOS)';
END
GO

IF OBJECT_ID(N'dbo.TB_FERIADOS', N'U') IS NOT NULL
   AND NOT EXISTS (SELECT 1 FROM sys.check_constraints
                    WHERE parent_object_id = OBJECT_ID(N'dbo.TB_FERIADOS')
                      AND name = 'CK_FERIADOS_CALENDARIO')
BEGIN
    ALTER TABLE dbo.TB_FERIADOS
        ADD CONSTRAINT CK_FERIADOS_CALENDARIO
            CHECK (CALENDARIO IN ('AMBOS', 'CRECHE', 'FUNDAMENTAL'));
    PRINT '  + CK_FERIADOS_CALENDARIO criada';
END
GO

/* Os índices antigos não distinguiam calendário. Recriados com a nova coluna na
   chave para permitir um feriado de creche e outro de fundamental na mesma
   data e abrangência. */
IF EXISTS (SELECT 1 FROM sys.indexes
            WHERE object_id = OBJECT_ID(N'dbo.TB_FERIADOS')
              AND name = 'UQ_FERIADOS_DATA_TODAS')
    DROP INDEX UQ_FERIADOS_DATA_TODAS ON dbo.TB_FERIADOS;
GO

IF EXISTS (SELECT 1 FROM sys.indexes
            WHERE object_id = OBJECT_ID(N'dbo.TB_FERIADOS')
              AND name = 'UQ_FERIADOS_DATA_UNIDADE')
    DROP INDEX UQ_FERIADOS_DATA_UNIDADE ON dbo.TB_FERIADOS;
GO

IF OBJECT_ID(N'dbo.TB_FERIADOS', N'U') IS NOT NULL
   AND COL_LENGTH(N'dbo.TB_FERIADOS', N'CALENDARIO') IS NOT NULL
BEGIN
    IF NOT EXISTS (SELECT 1 FROM sys.indexes
                    WHERE object_id = OBJECT_ID(N'dbo.TB_FERIADOS')
                      AND name = 'UQ_FERIADOS_DATA_TODAS')
        CREATE UNIQUE INDEX UQ_FERIADOS_DATA_TODAS
            ON dbo.TB_FERIADOS (DATA, CALENDARIO) WHERE ID_UNIDADE IS NULL;

    IF NOT EXISTS (SELECT 1 FROM sys.indexes
                    WHERE object_id = OBJECT_ID(N'dbo.TB_FERIADOS')
                      AND name = 'UQ_FERIADOS_DATA_UNIDADE')
        CREATE UNIQUE INDEX UQ_FERIADOS_DATA_UNIDADE
            ON dbo.TB_FERIADOS (DATA, ID_UNIDADE, CALENDARIO) WHERE ID_UNIDADE IS NOT NULL;

    PRINT '  = índices únicos de feriados atualizados';
END
GO
