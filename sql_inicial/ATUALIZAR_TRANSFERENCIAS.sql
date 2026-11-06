/* ============================================================================
   TRANSFERÊNCIA DE ALUNO ENTRE ESCOLAS — atualização do banco
   ----------------------------------------------------------------------------
   Sistema de Chamada · Prefeitura Municipal de Angatuba
   SQL Server 2016 ou mais novo · executar em db_chamada

   PARA QUEM JÁ TEM O SISTEMA INSTALADO.
   Este script é SEGURO e pode ser executado mais de uma vez: tudo é conferido
   antes de criar. Não altera nem apaga nenhum dado existente.

   Quem está começando do zero pode executar apenas INSTALACAO_COMPLETA.sql,
   que já contém esta mesma seção.

   ----------------------------------------------------------------------------
   COMO A TRANSFERÊNCIA FUNCIONA (duas etapas)

     1. O diretor da escola de ORIGEM indica a escola de destino e informa o
        motivo. A transferência fica PENDENTE. O aluno CONTINUA na turma atual,
        e a chamada segue normal — nada muda até a escola de destino aceitar.

     2. A escola de DESTINO escolhe a turma e a DATA DE INÍCIO. Só então o aluno
        muda de turma e a transferência passa a CONCLUIDA.

     Uma transferência pendente pode ser cancelada, com motivo obrigatório.

   A DATA DE INÍCIO é o que separa os dois períodos:
     - até o dia ANTERIOR, o aluno pertence à turma de origem;
     - daquele dia em diante, pertence à turma de destino.
   Assim nenhum registro de chamada passado "muda de escola".

   O histórico de chamada, observações e pareceres NÃO é movido nem apagado:
   tudo continua ligado ao aluno (as três tabelas referenciam ID_ALUNO, não a
   turma). O relatório anual mostra o ano inteiro, nas duas escolas.
   ============================================================================ */

USE [db_chamada];
GO

SET NOCOUNT ON;
GO

PRINT '================================================================';
PRINT 'TRANSFERÊNCIA DE ALUNOS — atualização';
PRINT '================================================================';
GO

/* ============================================================================
   1. TB_TRANSFERENCIAS
      Uma linha por transferência. A linha nasce PENDENTE, com a escola de
      destino indicada, e só ganha turma e data quando o destino aceita.
      As três CHECK abaixo impedem, no próprio banco, um estado inconsistente.
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

/* ============================================================================
   2. VW_ALUNO_VINCULO — em que turma o aluno estava, e em que período
      ----------------------------------------------------------------------------
      A tabela TB_ALUNOS guarda só a turma ATUAL. Esta visão reconstrói a linha
      do tempo a partir das transferências concluídas, sem duplicar informação:

        DATA_INICIO NULL = desde o início (primeiro período do aluno)
        DATA_FIM    NULL = até hoje (período em aberto)

      Um aluno sem transferência tem uma única linha, com a turma atual.
      Quem foi transferido uma vez tem duas; duas vezes, três; e assim por diante.

      É desta visão que a chamada do dia decide quem aparece em cada turma, e é
      dela que sai o quadro de escolas/turmas do relatório anual.
   ============================================================================ */
CREATE OR ALTER VIEW dbo.VW_ALUNO_VINCULO
AS
WITH transf AS (
    SELECT
        ID_ALUNO,
        ID_TURMA_ORIGEM,
        ID_TURMA_DESTINO,
        DATA_INICIO,
        ROW_NUMBER() OVER (PARTITION BY ID_ALUNO ORDER BY DATA_INICIO, ID) AS ORDEM
    FROM dbo.TB_TRANSFERENCIAS
    WHERE SITUACAO = 'CONCLUIDA'
)
SELECT v.ID_ALUNO, v.ID_TURMA, v.DATA_INICIO, v.DATA_FIM
FROM (
    /* nunca transferido: um vínculo em aberto com a turma atual */
    SELECT
        a.ID           AS ID_ALUNO,
        a.ID_TURMA     AS ID_TURMA,
        CAST(NULL AS date) AS DATA_INICIO,
        CAST(NULL AS date) AS DATA_FIM
    FROM dbo.TB_ALUNOS a
    WHERE NOT EXISTS (SELECT 1 FROM transf f WHERE f.ID_ALUNO = a.ID)

    UNION ALL

    /* período anterior à primeira transferência: turma de origem */
    SELECT
        f.ID_ALUNO,
        f.ID_TURMA_ORIGEM,
        CAST(NULL AS date),
        DATEADD(DAY, -1, f.DATA_INICIO)
    FROM transf f
    WHERE f.ORDEM = 1

    UNION ALL

    /* cada transferência abre um período na turma de destino, que fecha na
       véspera da transferência seguinte (ou fica em aberto, se for a última) */
    SELECT
        f.ID_ALUNO,
        f.ID_TURMA_DESTINO,
        f.DATA_INICIO,
        DATEADD(DAY, -1, g.DATA_INICIO)
    FROM transf f
    LEFT JOIN transf g ON g.ID_ALUNO = f.ID_ALUNO AND g.ORDEM = f.ORDEM + 1
) v;
GO
PRINT '  + VW_ALUNO_VINCULO criada/atualizada';
GO

/* ============================================================================
   3. VERIFICAÇÃO
   ============================================================================ */
PRINT '';
PRINT '----------------------------------------------------------------';
PRINT 'CONFERÊNCIA';
PRINT '----------------------------------------------------------------';

IF OBJECT_ID(N'dbo.TB_TRANSFERENCIAS', N'U') IS NULL
    PRINT 'ATENÇÃO: TB_TRANSFERENCIAS não foi criada!';
ELSE
    PRINT 'TB_TRANSFERENCIAS: OK';

IF OBJECT_ID(N'dbo.VW_ALUNO_VINCULO', N'V') IS NULL
    PRINT 'ATENÇÃO: VW_ALUNO_VINCULO não foi criada!';
ELSE
    PRINT 'VW_ALUNO_VINCULO: OK';
GO

/* Todo aluno ativo precisa ter exatamente UM vínculo em aberto. Se esta
   consulta devolver alguma linha, há algo errado — não deveria devolver nada. */
SELECT
    a.ID,
    a.NOME,
    COUNT(v.ID_ALUNO) AS VinculosEmAberto
FROM dbo.TB_ALUNOS a
LEFT JOIN dbo.VW_ALUNO_VINCULO v ON v.ID_ALUNO = a.ID AND v.DATA_FIM IS NULL
WHERE a.ATIVO = 1
GROUP BY a.ID, a.NOME
HAVING COUNT(v.ID_ALUNO) <> 1;
GO

DECLARE @Alunos int;
DECLARE @Vinculos int;
SELECT @Alunos = COUNT(*) FROM dbo.TB_ALUNOS WHERE ATIVO = 1;
SELECT @Vinculos = COUNT(*) FROM dbo.VW_ALUNO_VINCULO v
    INNER JOIN dbo.TB_ALUNOS a ON a.ID = v.ID_ALUNO
    WHERE a.ATIVO = 1 AND v.DATA_FIM IS NULL;
PRINT 'Alunos ativos: ' + CAST(@Alunos AS varchar(10))
    + ' | vínculos em aberto: ' + CAST(@Vinculos AS varchar(10));
PRINT 'Os dois números devem ser iguais.';
GO

PRINT '';
PRINT '================================================================';
PRINT 'Atualização concluída.';
PRINT 'Agora copie os arquivos PHP novos por cima da instalação.';
PRINT 'Nenhum dado existente foi alterado.';
PRINT '================================================================';
GO