/* ============================================================================
   SUBSTITUTO E OBSERVAÇÕES DO PROFESSOR — atualização do banco
   ----------------------------------------------------------------------------
   Sistema de Chamada · Prefeitura Municipal de Angatuba
   SQL Server 2016 ou mais novo · executar em db_chamada

   PARA QUEM JÁ TEM O SISTEMA INSTALADO.
   Seguro e re-executável: tudo é conferido antes de criar. Não apaga dado
   nenhum. Quem instala do zero pode rodar só INSTALACAO_COMPLETA.sql, que já
   contém esta mesma seção.

   ----------------------------------------------------------------------------
   O QUE MUDA

   Até aqui o substituto, dentro do prazo, tinha os mesmos poderes de quem
   substituía — inclusive ler as observações que o professor escreveu sobre os
   alunos. Agora a leitura é separada:

     - O substituto SEMPRE vê e edita as observações que ELE mesmo registrou.
     - As observações dos OUTROS ficam escondidas até a gestão liberar.
     - A liberação é POR SUBSTITUIÇÃO: cada nova substituição começa bloqueada,
       mesmo que a pessoa já tenha sido liberada em outra turma ou outro prazo.
     - Ele continua lançando chamada e registrando observação normalmente.
       O que muda é só a leitura do que os outros escreveram.

   A liberação vale enquanto a substituição estiver vigente. Fora do prazo ele
   já não alcança a turma, então não há o que liberar.

   ----------------------------------------------------------------------------
   ONDE O BLOQUEIO VALE

   O texto da observação aparece em vários lugares; esconder só a listagem
   deixaria o conteúdo acessível por outro caminho. O filtro é aplicado em:

     observacoes/index.php          listagem e busca
     alunos/view.php                ficha do aluno
     chamada/index.php              contador de observações do dia
     relatorios/ver.php             relatório anual, inclusive na impressão
     relatorios/index.php           contagem de observações por aluno
     index.php (dashboard)          observações recentes e total do mês

   ----------------------------------------------------------------------------
   COLUNAS NOVAS EM TB_TURMA_PROFESSORES

     VER_OBSERVACOES   bit       0 = bloqueado (padrão), 1 = liberado
     OBS_LIBERADA_POR  int       quem liberou (TB_USUARIOS)
     OBS_LIBERADA_EM   datetime  quando liberou

   As duas de auditoria são preenchidas juntas ou ficam ambas nulas. Ao
   bloquear de volta, voltam a nulo — o histórico de quem mexeu por último fica
   no texto da tela, e a liberação corrente é sempre rastreável.

   As três só fazem sentido para PAPEL = 'SUBSTITUTO'. Um CHECK garante que
   membro fixo não fique com sobra de escala antiga.
   ============================================================================ */

USE [db_chamada];
GO

SET NOCOUNT ON;
SET XACT_ABORT ON;
GO

PRINT '================================================================';
PRINT ' SUBSTITUTO E OBSERVACOES DO PROFESSOR';
PRINT '================================================================';
GO

/* ----------------------------------------------------------------------------
   1. A coluna da permissão
   ---------------------------------------------------------------------------- */
IF NOT EXISTS (SELECT 1 FROM sys.columns
               WHERE object_id = OBJECT_ID('dbo.TB_TURMA_PROFESSORES')
                 AND name = 'VER_OBSERVACOES')
BEGIN
    ALTER TABLE dbo.TB_TURMA_PROFESSORES
        ADD VER_OBSERVACOES bit NOT NULL
            CONSTRAINT DF_TURMAPROF_VEROBS DEFAULT (0);
    PRINT '  + TB_TURMA_PROFESSORES.VER_OBSERVACOES criada (0 = bloqueado)';
END
ELSE
    PRINT '  = TB_TURMA_PROFESSORES.VER_OBSERVACOES ja existia';
GO

/* ----------------------------------------------------------------------------
   2. Auditoria: quem liberou
   ---------------------------------------------------------------------------- */
IF NOT EXISTS (SELECT 1 FROM sys.columns
               WHERE object_id = OBJECT_ID('dbo.TB_TURMA_PROFESSORES')
                 AND name = 'OBS_LIBERADA_POR')
BEGIN
    ALTER TABLE dbo.TB_TURMA_PROFESSORES ADD OBS_LIBERADA_POR int NULL;
    PRINT '  + TB_TURMA_PROFESSORES.OBS_LIBERADA_POR criada';
END
ELSE
    PRINT '  = TB_TURMA_PROFESSORES.OBS_LIBERADA_POR ja existia';
GO

IF NOT EXISTS (SELECT 1 FROM sys.columns
               WHERE object_id = OBJECT_ID('dbo.TB_TURMA_PROFESSORES')
                 AND name = 'OBS_LIBERADA_EM')
BEGIN
    ALTER TABLE dbo.TB_TURMA_PROFESSORES ADD OBS_LIBERADA_EM datetime NULL;
    PRINT '  + TB_TURMA_PROFESSORES.OBS_LIBERADA_EM criada';
END
ELSE
    PRINT '  = TB_TURMA_PROFESSORES.OBS_LIBERADA_EM ja existia';
GO

IF NOT EXISTS (SELECT 1 FROM sys.foreign_keys WHERE name = 'FK_TURMAPROF_OBSLIB_POR')
BEGIN
    ALTER TABLE dbo.TB_TURMA_PROFESSORES
        ADD CONSTRAINT FK_TURMAPROF_OBSLIB_POR
            FOREIGN KEY (OBS_LIBERADA_POR) REFERENCES dbo.TB_USUARIOS (ID);
    PRINT '  + FK_TURMAPROF_OBSLIB_POR criada';
END
ELSE
    PRINT '  = FK_TURMAPROF_OBSLIB_POR ja existia';
GO

/* ----------------------------------------------------------------------------
   3. Sobra de escala antiga não pode ficar em membro fixo.

      Antes de criar o CHECK, zera o que estiver incompatível — a coluna acabou
      de nascer com 0, mas o script pode estar rodando sobre uma base em que
      alguém já mexeu.
   ---------------------------------------------------------------------------- */
UPDATE dbo.TB_TURMA_PROFESSORES
   SET VER_OBSERVACOES = 0, OBS_LIBERADA_POR = NULL, OBS_LIBERADA_EM = NULL
 WHERE PAPEL <> 'SUBSTITUTO'
   AND (VER_OBSERVACOES <> 0 OR OBS_LIBERADA_POR IS NOT NULL OR OBS_LIBERADA_EM IS NOT NULL);
GO

UPDATE dbo.TB_TURMA_PROFESSORES
   SET OBS_LIBERADA_POR = NULL, OBS_LIBERADA_EM = NULL
 WHERE VER_OBSERVACOES = 0
   AND (OBS_LIBERADA_POR IS NOT NULL OR OBS_LIBERADA_EM IS NOT NULL);
GO

IF NOT EXISTS (SELECT 1 FROM sys.check_constraints WHERE name = 'CK_TURMAPROF_VEROBS')
BEGIN
    ALTER TABLE dbo.TB_TURMA_PROFESSORES
        ADD CONSTRAINT CK_TURMAPROF_VEROBS CHECK (
            PAPEL = 'SUBSTITUTO'
            OR (VER_OBSERVACOES = 0 AND OBS_LIBERADA_POR IS NULL AND OBS_LIBERADA_EM IS NULL)
        );
    PRINT '  + CK_TURMAPROF_VEROBS criada (membro fixo nao guarda permissao)';
END
ELSE
    PRINT '  = CK_TURMAPROF_VEROBS ja existia';
GO

IF NOT EXISTS (SELECT 1 FROM sys.check_constraints WHERE name = 'CK_TURMAPROF_OBSLIB_JUNTAS')
BEGIN
    ALTER TABLE dbo.TB_TURMA_PROFESSORES
        ADD CONSTRAINT CK_TURMAPROF_OBSLIB_JUNTAS CHECK (
            (OBS_LIBERADA_POR IS NULL AND OBS_LIBERADA_EM IS NULL)
            OR (OBS_LIBERADA_POR IS NOT NULL AND OBS_LIBERADA_EM IS NOT NULL)
        );
    PRINT '  + CK_TURMAPROF_OBSLIB_JUNTAS criada (autor e data juntos)';
END
ELSE
    PRINT '  = CK_TURMAPROF_OBSLIB_JUNTAS ja existia';
GO

/* ----------------------------------------------------------------------------
   4. Índice para a consulta que decide o bloqueio.

      A pergunta é sempre "em quais turmas este usuário é substituto vigente e
      sem liberação?". O índice cobre usuário + papel e traz as colunas do
      prazo e da permissão, para não voltar à tabela.
   ---------------------------------------------------------------------------- */
IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'IX_TURMAPROF_OBS_SUBST')
BEGIN
    CREATE INDEX IX_TURMAPROF_OBS_SUBST
        ON dbo.TB_TURMA_PROFESSORES (ID_USUARIO, PAPEL, ATIVO)
        INCLUDE (ID_TURMA, DATA_INICIO, DATA_FIM, VER_OBSERVACOES);
    PRINT '  + IX_TURMAPROF_OBS_SUBST criado';
END
ELSE
    PRINT '  = IX_TURMAPROF_OBS_SUBST ja existia';
GO

/* ----------------------------------------------------------------------------
   5. Conferência
   ---------------------------------------------------------------------------- */
PRINT '';
PRINT '----------------------------------------------------------------';
PRINT ' CONFERENCIA';
PRINT '----------------------------------------------------------------';
GO

DECLARE @qtdSubst int;
SELECT @qtdSubst = COUNT(*) FROM dbo.TB_TURMA_PROFESSORES WHERE PAPEL = 'SUBSTITUTO';
PRINT '  Substituicoes cadastradas: ' + CAST(@qtdSubst AS varchar(10));
GO

DECLARE @qtdLib int;
SELECT @qtdLib = COUNT(*) FROM dbo.TB_TURMA_PROFESSORES
 WHERE PAPEL = 'SUBSTITUTO' AND VER_OBSERVACOES = 1;
PRINT '  Ja liberadas para ler observacoes: ' + CAST(@qtdLib AS varchar(10));
GO

DECLARE @qtdBloq int;
SELECT @qtdBloq = COUNT(*) FROM dbo.TB_TURMA_PROFESSORES
 WHERE PAPEL = 'SUBSTITUTO' AND VER_OBSERVACOES = 0;
PRINT '  Bloqueadas (padrao): ' + CAST(@qtdBloq AS varchar(10));
GO

PRINT '';
PRINT '================================================================';
PRINT ' ATUALIZACAO CONCLUIDA';
PRINT '';
PRINT ' Toda substituicao comeca BLOQUEADA para ler observacao dos outros.';
PRINT ' Para liberar: Turmas -> editar a turma -> na linha do substituto,';
PRINT ' marque "Pode ler as observacoes do professor" e salve.';
PRINT '';
PRINT ' O substituto continua vendo as observacoes que ele mesmo escrever,';
PRINT ' lancando chamada e registrando observacao normalmente.';
PRINT '================================================================';
GO
