/* Chamada por aula — atualização idempotente.
   SQL Server 2016 ou mais novo. Executar no banco db_chamada.

   Antes existia no máximo um registro por aluno por data. Agora a mesma data
   pode ter a 1ª e a 2ª aula, cada uma com sua própria situação. Os registros
   antigos recebem AULA_SEQ = 1 (1ª aula) e continuam valendo exatamente como
   antes: uma presença/falta por aluno naquele dia.

   Pode ser executado mais de uma vez sem apagar histórico. */
USE [db_chamada];
GO

PRINT '';
PRINT 'Chamada por aula (1ª e 2ª aula)';
GO

IF OBJECT_ID(N'dbo.TB_CHAMADA', N'U') IS NULL
BEGIN
    PRINT '  ! TB_CHAMADA não existe. Rode antes sql_inicial/INSTALACAO_COMPLETA.sql.';
END
ELSE
BEGIN
    IF COL_LENGTH(N'dbo.TB_CHAMADA', N'AULA_SEQ') IS NULL
    BEGIN
        ALTER TABLE dbo.TB_CHAMADA
            ADD AULA_SEQ int NOT NULL
                CONSTRAINT DF_CHAMADA_AULA DEFAULT (1);
        PRINT '  + TB_CHAMADA.AULA_SEQ criada (registros antigos = 1ª aula)';
    END
    ELSE
        PRINT '  = TB_CHAMADA.AULA_SEQ já existe';
END
GO

/* A chave antiga impedia duas aulas no mesmo dia. Ela é substituída por uma
   chave que inclui o número da aula; nenhum registro é apagado. */
IF OBJECT_ID(N'dbo.TB_CHAMADA', N'U') IS NOT NULL
BEGIN
    IF EXISTS (SELECT 1 FROM sys.objects
                WHERE parent_object_id = OBJECT_ID(N'dbo.TB_CHAMADA')
                  AND name = N'UQ_CHAMADA_ALUNO_DATA' AND type = N'UQ')
        ALTER TABLE dbo.TB_CHAMADA DROP CONSTRAINT UQ_CHAMADA_ALUNO_DATA;
    ELSE IF EXISTS (SELECT 1 FROM sys.indexes
                     WHERE object_id = OBJECT_ID(N'dbo.TB_CHAMADA')
                       AND name = N'UQ_CHAMADA_ALUNO_DATA')
        DROP INDEX UQ_CHAMADA_ALUNO_DATA ON dbo.TB_CHAMADA;

    IF COL_LENGTH(N'dbo.TB_CHAMADA', N'AULA_SEQ') IS NOT NULL
    BEGIN
        IF NOT EXISTS (SELECT 1 FROM sys.indexes
                        WHERE object_id = OBJECT_ID(N'dbo.TB_CHAMADA')
                          AND name = N'UQ_CHAMADA_ALUNO_DATA_AULA')
        BEGIN
            ALTER TABLE dbo.TB_CHAMADA
                ADD CONSTRAINT UQ_CHAMADA_ALUNO_DATA_AULA
                    UNIQUE (ID_ALUNO, DATA, AULA_SEQ);
            PRINT '  + UQ_CHAMADA_ALUNO_DATA_AULA criada';
        END

        IF NOT EXISTS (SELECT 1 FROM sys.check_constraints
                        WHERE parent_object_id = OBJECT_ID(N'dbo.TB_CHAMADA')
                          AND name = N'CK_CHAMADA_AULA')
        BEGIN
            ALTER TABLE dbo.TB_CHAMADA
                ADD CONSTRAINT CK_CHAMADA_AULA CHECK (AULA_SEQ IN (1, 2));
            PRINT '  + CK_CHAMADA_AULA criada';
        END

        IF NOT EXISTS (SELECT 1 FROM sys.indexes
                        WHERE object_id = OBJECT_ID(N'dbo.TB_CHAMADA')
                          AND name = N'IX_CHAMADA_DATA_AULA')
        BEGIN
            CREATE INDEX IX_CHAMADA_DATA_AULA ON dbo.TB_CHAMADA (DATA, AULA_SEQ);
            PRINT '  + IX_CHAMADA_DATA_AULA criado';
        END
    END
END
GO

PRINT '  = chamada por aula pronta';
GO
