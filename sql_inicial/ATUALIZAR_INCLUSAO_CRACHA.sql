/* ============================================================================
   ATUALIZAR_INCLUSAO_CRACHA.sql
   ----------------------------------------------------------------------------
   Adiciona categorias de necessidades de inclusão para uso interno, preferência
   do símbolo para TEA e registro de autorização para o marcador visual frontal
   no crachá. As categorias são armazenadas como JSON e não são impressas.

   Idempotente: pode ser executado mais de uma vez.
   ============================================================================ */
SET NOCOUNT ON;
GO

IF OBJECT_ID(N'dbo.TB_ALUNOS', N'U') IS NULL
    THROW 50101, 'A tabela dbo.TB_ALUNOS não existe; rode primeiro a instalação do sistema.', 1;
GO

IF COL_LENGTH('dbo.TB_ALUNOS', 'DEFICIENCIAS_INCLUSAO') IS NULL
BEGIN
    ALTER TABLE dbo.TB_ALUNOS ADD DEFICIENCIAS_INCLUSAO nvarchar(1000) NULL;
    PRINT '  + TB_ALUNOS.DEFICIENCIAS_INCLUSAO adicionada';
END
ELSE
    PRINT '  = TB_ALUNOS.DEFICIENCIAS_INCLUSAO já existe';
GO

IF COL_LENGTH('dbo.TB_ALUNOS', 'SIMBOLO_TEA_INCLUSAO') IS NULL
BEGIN
    ALTER TABLE dbo.TB_ALUNOS ADD SIMBOLO_TEA_INCLUSAO nvarchar(20) NULL;
    PRINT '  + TB_ALUNOS.SIMBOLO_TEA_INCLUSAO adicionada';
END
ELSE
    PRINT '  = TB_ALUNOS.SIMBOLO_TEA_INCLUSAO já existe';
GO

IF COL_LENGTH('dbo.TB_ALUNOS', 'AUTORIZA_MARCADOR_INCLUSAO') IS NULL
BEGIN
    ALTER TABLE dbo.TB_ALUNOS ADD AUTORIZA_MARCADOR_INCLUSAO bit NOT NULL
        CONSTRAINT DF_ALUNOS_AUTORIZA_MARCADOR_INCLUSAO DEFAULT (0);
    PRINT '  + TB_ALUNOS.AUTORIZA_MARCADOR_INCLUSAO adicionada (padrão: sem autorização)';
END
ELSE
    PRINT '  = TB_ALUNOS.AUTORIZA_MARCADOR_INCLUSAO já existe';
GO

IF NOT EXISTS (SELECT 1 FROM sys.check_constraints
               WHERE name = 'CK_ALUNOS_SIMBOLO_TEA_INCLUSAO'
                 AND parent_object_id = OBJECT_ID('dbo.TB_ALUNOS'))
BEGIN
    ALTER TABLE dbo.TB_ALUNOS ADD CONSTRAINT CK_ALUNOS_SIMBOLO_TEA_INCLUSAO
        CHECK (SIMBOLO_TEA_INCLUSAO IS NULL
               OR SIMBOLO_TEA_INCLUSAO IN (N'INFINITO', N'QUEBRA_CABECA'));
    PRINT '  + CK_ALUNOS_SIMBOLO_TEA_INCLUSAO criada';
END
ELSE
    PRINT '  = CK_ALUNOS_SIMBOLO_TEA_INCLUSAO já existe';
GO

IF NOT EXISTS (SELECT 1 FROM sys.check_constraints
               WHERE name = 'CK_ALUNOS_DEFICIENCIAS_INCLUSAO_JSON'
                 AND parent_object_id = OBJECT_ID('dbo.TB_ALUNOS'))
BEGIN
    ALTER TABLE dbo.TB_ALUNOS ADD CONSTRAINT CK_ALUNOS_DEFICIENCIAS_INCLUSAO_JSON
        CHECK (DEFICIENCIAS_INCLUSAO IS NULL OR ISJSON(DEFICIENCIAS_INCLUSAO) = 1);
    PRINT '  + CK_ALUNOS_DEFICIENCIAS_INCLUSAO_JSON criada';
END
ELSE
    PRINT '  = CK_ALUNOS_DEFICIENCIAS_INCLUSAO_JSON já existe';
GO

PRINT 'Atualização de inclusão e marcador de crachá concluída.';
GO
