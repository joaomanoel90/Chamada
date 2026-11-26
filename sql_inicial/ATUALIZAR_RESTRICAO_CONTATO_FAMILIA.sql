/* Restrição familiar de contato — atualização idempotente.
   SQL Server 2016 ou mais novo. Executar no banco db_chamada.

   Acrescenta a flag RESTRITO_CONTATO no vínculo aluno/responsável. Um mesmo
   CPF pode atender vários alunos; por isso a restrição é do vínculo, não do
   cadastro do responsável. Contatos restritos não aparecem no crachá.

   A restrição é aplicada pela tela própria do aluno (alunos/restricao.php),
   fora da edição do cadastro. Cada restringir/liberar grava histórico com
   data, usuário e motivo; ao restringir, uma observação orienta a emitir
   outro crachá sem essa informação.

   Pode ser executado mais de uma vez sem apagar histórico. */
USE [db_chamada];
GO

PRINT '';
PRINT 'Restrição familiar de contato no crachá';
GO

IF OBJECT_ID(N'dbo.TB_ALUNO_RESPONSAVEIS', N'U') IS NULL
BEGIN
    PRINT '  ! TB_ALUNO_RESPONSAVEIS não existe. Rode antes sql_inicial/INSTALACAO_COMPLETA.sql e ATUALIZAR_CRECHE_FAMILIA_CRACHA.sql.';
END
ELSE
BEGIN
    IF COL_LENGTH(N'dbo.TB_ALUNO_RESPONSAVEIS', N'RESTRITO_CONTATO') IS NULL
    BEGIN
        ALTER TABLE dbo.TB_ALUNO_RESPONSAVEIS
            ADD RESTRITO_CONTATO bit NOT NULL
                CONSTRAINT DF_ALUNORESP_RESTRITO_CONTATO DEFAULT (0);
        PRINT '  + TB_ALUNO_RESPONSAVEIS.RESTRITO_CONTATO adicionada (padrão = liberado)';
    END
    ELSE
        PRINT '  = TB_ALUNO_RESPONSAVEIS.RESTRITO_CONTATO já existe';
END
GO

IF OBJECT_ID(N'dbo.TB_ALUNO_RESPONSAVEIS', N'U') IS NOT NULL
   AND NOT EXISTS (
       SELECT 1 FROM sys.check_constraints
        WHERE name = N'CK_ALUNORESP_RESTRITO_CONTATO'
          AND parent_object_id = OBJECT_ID(N'dbo.TB_ALUNO_RESPONSAVEIS')
   )
BEGIN
    ALTER TABLE dbo.TB_ALUNO_RESPONSAVEIS
        ADD CONSTRAINT CK_ALUNORESP_RESTRITO_CONTATO CHECK (RESTRITO_CONTATO IN (0, 1));
    PRINT '  + CK_ALUNORESP_RESTRITO_CONTATO criada';
END
GO

/* Histórico append-only: cada restringir/liberar guarda quando, quem e o
   motivo. O ID do vínculo é apenas snapshot — a edição do cadastro pode
   recriar o vínculo e o histórico precisa continuar íntegro. */
IF OBJECT_ID(N'dbo.TB_RESTRICAO_FAMILIA_HISTORICO', N'U') IS NULL
BEGIN
    CREATE TABLE dbo.TB_RESTRICAO_FAMILIA_HISTORICO (
        ID                    int IDENTITY(1,1) NOT NULL,
        ID_ALUNO              int           NOT NULL,
        ID_ALUNO_RESPONSAVEIS int           NOT NULL,
        PARENTESCO            varchar(15)   NOT NULL,
        DESCRICAO_OUTRO       nvarchar(60)  NULL,
        NOME_RESPONSAVEL      nvarchar(150) NOT NULL,
        ACAO                  varchar(10)   NOT NULL,
        MOTIVO                nvarchar(500) NOT NULL,
        ID_USUARIO            int           NOT NULL,
        CRIADO_EM             datetime2(0)  NOT NULL
            CONSTRAINT DF_RESTFAM_CRIADO DEFAULT (SYSDATETIME()),
        CONSTRAINT PK_TB_RESTFAM_HIST PRIMARY KEY CLUSTERED (ID),
        CONSTRAINT FK_RESTFAM_ALUNO FOREIGN KEY (ID_ALUNO)
            REFERENCES dbo.TB_ALUNOS (ID),
        CONSTRAINT FK_RESTFAM_USUARIO FOREIGN KEY (ID_USUARIO)
            REFERENCES dbo.TB_USUARIOS (ID),
        CONSTRAINT CK_RESTFAM_ACAO CHECK (ACAO IN ('RESTRINGIR', 'LIBERAR'))
    );
    CREATE INDEX IX_RESTFAM_ALUNO
        ON dbo.TB_RESTRICAO_FAMILIA_HISTORICO (ID_ALUNO, CRIADO_EM DESC);
    PRINT '  + TB_RESTRICAO_FAMILIA_HISTORICO criada';
END
ELSE
    PRINT '  = TB_RESTRICAO_FAMILIA_HISTORICO já existe';
GO

IF OBJECT_ID(N'dbo.TB_RESTRICAO_FAMILIA_HISTORICO', N'U') IS NOT NULL
   AND NOT EXISTS (
       SELECT 1 FROM sys.check_constraints
        WHERE name = N'CK_RESTFAM_ACAO'
          AND parent_object_id = OBJECT_ID(N'dbo.TB_RESTRICAO_FAMILIA_HISTORICO')
   )
BEGIN
    ALTER TABLE dbo.TB_RESTRICAO_FAMILIA_HISTORICO
        ADD CONSTRAINT CK_RESTFAM_ACAO CHECK (ACAO IN ('RESTRINGIR', 'LIBERAR'));
    PRINT '  + CK_RESTFAM_ACAO criada';
END
GO

IF OBJECT_ID(N'dbo.TB_RESTRICAO_FAMILIA_HISTORICO', N'U') IS NOT NULL
   AND COL_LENGTH(N'dbo.TB_RESTRICAO_FAMILIA_HISTORICO', N'DESCRICAO_OUTRO') IS NULL
BEGIN
    ALTER TABLE dbo.TB_RESTRICAO_FAMILIA_HISTORICO
        ADD DESCRICAO_OUTRO nvarchar(60) NULL;
    PRINT '  + TB_RESTRICAO_FAMILIA_HISTORICO.DESCRICAO_OUTRO adicionada';
END
GO

PRINT 'Atualização de restrição familiar concluída.';
GO
