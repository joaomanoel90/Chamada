/* Adiciona perfis de Secretaria de Educação e Secretário Escolar.
   Preserva os níveis e usuários existentes. */
USE [db_chamada];
GO

IF OBJECT_ID(N'dbo.TB_USUARIOS', N'U') IS NULL
BEGIN
    ;THROW 50001, 'A tabela dbo.TB_USUARIOS não existe. Execute a instalação completa primeiro.', 1;
END;
GO

IF EXISTS (SELECT 1 FROM sys.check_constraints
           WHERE parent_object_id = OBJECT_ID('dbo.TB_USUARIOS')
             AND name = 'CK_USUARIOS_NIVEL'
             AND (definition NOT LIKE '%4%' OR definition NOT LIKE '%5%'))
    ALTER TABLE dbo.TB_USUARIOS DROP CONSTRAINT CK_USUARIOS_NIVEL;
GO

IF NOT EXISTS (SELECT 1 FROM sys.check_constraints
               WHERE parent_object_id = OBJECT_ID('dbo.TB_USUARIOS')
                 AND name = 'CK_USUARIOS_NIVEL')
    ALTER TABLE dbo.TB_USUARIOS ADD CONSTRAINT CK_USUARIOS_NIVEL
        CHECK (NIVEL IN (1, 2, 3, 4, 5));
GO

PRINT 'Níveis de acesso atualizados: 1 a 5.';
GO
