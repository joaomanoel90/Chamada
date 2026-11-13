/* Séries estruturadas e registro de promoção/retenção.
   SQL Server 2016 ou mais novo. Executar no banco db_chamada. */
USE [db_chamada];
GO

IF OBJECT_ID(N'dbo.TB_TURMAS', N'U') IS NULL
    THROW 51101, 'TB_TURMAS não existe. Execute primeiro a instalação do sistema.', 1;
GO

IF COL_LENGTH('dbo.TB_TURMAS', 'SERIE') IS NULL
    ALTER TABLE dbo.TB_TURMAS ADD SERIE nvarchar(20) NULL;
GO

IF NOT EXISTS (
    SELECT 1 FROM sys.check_constraints
    WHERE parent_object_id = OBJECT_ID(N'dbo.TB_TURMAS') AND name = N'CK_TURMAS_SERIE'
)
    ALTER TABLE dbo.TB_TURMAS ADD CONSTRAINT CK_TURMAS_SERIE CHECK (SERIE IS NULL OR SERIE IN
        ('BERCARIO_I', 'BERCARIO_II', 'MATERNAL_I', 'MATERNAL_II', 'PRE_I', 'PRE_II',
         'ANO_1', 'ANO_2', 'ANO_3', 'ANO_4', 'ANO_5', 'ANO_6', 'ANO_7', 'ANO_8', 'ANO_9'));
GO

IF EXISTS (
    SELECT 1 FROM sys.key_constraints kc
    WHERE kc.parent_object_id = OBJECT_ID(N'dbo.TB_TURMAS') AND kc.name = N'UQ_TURMAS'
      AND NOT EXISTS (
          SELECT 1 FROM sys.index_columns ic
          INNER JOIN sys.columns c ON c.object_id = ic.object_id AND c.column_id = ic.column_id
          WHERE ic.object_id = kc.parent_object_id AND ic.index_id = kc.unique_index_id
            AND ic.key_ordinal = 2 AND c.name = N'SERIE'
      )
)
    ALTER TABLE dbo.TB_TURMAS DROP CONSTRAINT UQ_TURMAS;
GO
IF NOT EXISTS (SELECT 1 FROM sys.key_constraints
               WHERE parent_object_id = OBJECT_ID(N'dbo.TB_TURMAS') AND name = N'UQ_TURMAS')
    ALTER TABLE dbo.TB_TURMAS ADD CONSTRAINT UQ_TURMAS UNIQUE (ID_UNIDADE, SERIE, NOME, ANO_LETIVO);
GO

IF OBJECT_ID(N'dbo.TB_TRANSFERENCIAS', N'U') IS NULL
    THROW 51102, 'TB_TRANSFERENCIAS não existe. Execute primeiro ATUALIZAR_TRANSFERENCIAS.sql.', 1;
GO

IF COL_LENGTH('dbo.TB_TRANSFERENCIAS', 'RESULTADO') IS NULL
    ALTER TABLE dbo.TB_TRANSFERENCIAS ADD RESULTADO nvarchar(20) NULL;
GO
IF COL_LENGTH('dbo.TB_TRANSFERENCIAS', 'MOTIVO_RESULTADO') IS NULL
    ALTER TABLE dbo.TB_TRANSFERENCIAS ADD MOTIVO_RESULTADO nvarchar(20) NULL;
GO

IF NOT EXISTS (
    SELECT 1 FROM sys.check_constraints
    WHERE parent_object_id = OBJECT_ID(N'dbo.TB_TRANSFERENCIAS') AND name = N'CK_TRANSFER_RESULTADO'
)
    ALTER TABLE dbo.TB_TRANSFERENCIAS ADD CONSTRAINT CK_TRANSFER_RESULTADO
        CHECK (RESULTADO IS NULL OR RESULTADO IN ('PROMOVIDO', 'RETIDO', 'TRANSFERENCIA'));
GO
IF NOT EXISTS (
    SELECT 1 FROM sys.check_constraints
    WHERE parent_object_id = OBJECT_ID(N'dbo.TB_TRANSFERENCIAS') AND name = N'CK_TRANSFER_MOTIVO_RESULTADO'
)
    ALTER TABLE dbo.TB_TRANSFERENCIAS ADD CONSTRAINT CK_TRANSFER_MOTIVO_RESULTADO
        CHECK (MOTIVO_RESULTADO IS NULL OR MOTIVO_RESULTADO IN ('FALTAS', 'NOTAS', 'FALTAS_NOTAS'));
GO

IF OBJECT_ID(N'dbo.TB_PROMOCOES', N'U') IS NULL
    THROW 51103, 'TB_PROMOCOES não existe. Execute primeiro a atualização de histórico de promoções.', 1;
GO

IF COL_LENGTH('dbo.TB_PROMOCOES', 'RESULTADO') IS NULL
    ALTER TABLE dbo.TB_PROMOCOES ADD RESULTADO nvarchar(10) NULL;
GO
IF COL_LENGTH('dbo.TB_PROMOCOES', 'MOTIVO_RESULTADO') IS NULL
    ALTER TABLE dbo.TB_PROMOCOES ADD MOTIVO_RESULTADO nvarchar(20) NULL;
GO

IF NOT EXISTS (
    SELECT 1 FROM sys.check_constraints
    WHERE parent_object_id = OBJECT_ID(N'dbo.TB_PROMOCOES') AND name = N'CK_PROMOCAO_RESULTADO'
)
    ALTER TABLE dbo.TB_PROMOCOES ADD CONSTRAINT CK_PROMOCAO_RESULTADO
        CHECK (RESULTADO IS NULL OR RESULTADO IN ('PROMOVIDO', 'RETIDO'));
GO
IF NOT EXISTS (
    SELECT 1 FROM sys.check_constraints
    WHERE parent_object_id = OBJECT_ID(N'dbo.TB_PROMOCOES') AND name = N'CK_PROMOCAO_MOTIVO_RESULTADO'
)
    ALTER TABLE dbo.TB_PROMOCOES ADD CONSTRAINT CK_PROMOCAO_MOTIVO_RESULTADO
        CHECK (MOTIVO_RESULTADO IS NULL OR MOTIVO_RESULTADO IN ('FALTAS', 'NOTAS', 'FALTAS_NOTAS'));
GO

PRINT 'Séries estruturadas e resultados escolares habilitados.';
PRINT 'Edite as turmas antigas e classifique a série antes de movimentar alunos.';
GO
