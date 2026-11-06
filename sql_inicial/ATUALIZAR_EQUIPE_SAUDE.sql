/* ============================================================================
   EQUIPE POR TURNO, SUBSTITUTOS E SAÚDE DO ALUNO — atualização do banco
   ----------------------------------------------------------------------------
   Sistema de Chamada · Prefeitura Municipal de Angatuba
   SQL Server 2016 ou mais novo · executar em db_chamada

   PARA QUEM JÁ TEM O SISTEMA INSTALADO.
   Seguro e re-executável: tudo é conferido antes de criar. Não apaga dado
   nenhum. Quem instala do zero pode rodar só INSTALACAO_COMPLETA.sql, que já
   contém esta mesma seção.

   ----------------------------------------------------------------------------
   O QUE MUDA

   1. TURNO (MANHA / TARDE / INTEGRAL) em três lugares, com propósitos distintos:
        TB_PERIODOS.TURNO          -> classifica o horário de funcionamento
        TB_USUARIOS.TURNO          -> o turno de trabalho do professor
        TB_TURMA_PROFESSORES.TURNO -> o turno que ele cobre NAQUELA turma

      O turno do professor é só o PADRÃO ao montar a equipe: a gestão pode mudar
      caso a caso (professor da manhã cobrindo uma tarde, por exemplo).

      Por que um turno separado da tabela de períodos: TB_PERIODOS é cadastrável
      e guarda horas; o turno é o conceito "que parte do dia". Separar os dois
      permite dizer "um responsável pela manhã e outro pela tarde" sem depender
      do nome que a escola deu ao horário. TURNO é um valor fechado, NÃO é chave
      estrangeira de TB_PERIODOS.

   2. Responsável POR TURNO. Antes havia no máximo um regente por turma; agora
      pode haver um pela manhã e outro pela tarde. Quem é INTEGRAL responde
      pelos dois turnos.

   3. SUBSTITUTO com prazo. PAPEL passa a aceitar SUBSTITUTO, com
      ID_SUBSTITUIDO (quem está sendo coberto), DATA_INICIO e DATA_FIM
      obrigatórias. Fora do prazo o substituto não vê a turma; dentro dele tem
      os mesmos poderes de quem substitui.

   4. SAÚDE DO ALUNO: ALERGIAS, RESTRICOES_ALIMENTARES e MEDICACAO. Aparecem na
      ficha, num alerta na tela de chamada e no relatório anual (inclusive na
      impressão).
   ============================================================================ */

USE [db_chamada];
GO

SET NOCOUNT ON;
GO

PRINT '================================================================';
PRINT 'EQUIPE POR TURNO, SUBSTITUTOS E SAÚDE — atualização';
PRINT '================================================================';
GO

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
   5. VERIFICAÇÃO
   ============================================================================ */
PRINT '';
PRINT '----------------------------------------------------------------';
PRINT 'CONFERÊNCIA';
PRINT '----------------------------------------------------------------';

SELECT
    p.NOME,
    p.HORA_INICIO,
    p.HORA_FIM,
    p.TURNO
FROM dbo.TB_PERIODOS p
ORDER BY p.HORA_INICIO;
GO

/* Não deve devolver nenhuma linha: nenhum responsável integral dividindo a
   turma com responsável de turno específico. */
SELECT
    t.ID,
    t.NOME AS TURMA,
    COUNT(*) AS RESPONSAVEIS
FROM dbo.TB_TURMA_PROFESSORES tp
INNER JOIN dbo.TB_TURMAS t ON t.ID = tp.ID_TURMA
WHERE tp.PAPEL = 'REGENTE' AND tp.ATIVO = 1
GROUP BY t.ID, t.NOME
HAVING COUNT(*) > 1
   AND SUM(CASE WHEN tp.TURNO = 'INTEGRAL' THEN 1 ELSE 0 END) > 0;
GO

DECLARE @Professores int;
DECLARE @SemTurno int;
SELECT @Professores = COUNT(*) FROM dbo.TB_USUARIOS WHERE NIVEL = 3 AND ISNULL(ATIVO, 1) = 1;
SELECT @SemTurno = COUNT(*) FROM dbo.TB_USUARIOS WHERE NIVEL = 3 AND ISNULL(ATIVO, 1) = 1 AND TURNO IS NULL;
PRINT 'Professores ativos: ' + CAST(@Professores AS varchar(10));
PRINT 'Ainda sem turno definido: ' + CAST(@SemTurno AS varchar(10))
    + ' (defina em Usuários; até lá a equipe usa o turno da turma)';
GO

DECLARE @Substitutos int;
SELECT @Substitutos = COUNT(*) FROM dbo.TB_TURMA_PROFESSORES WHERE PAPEL = 'SUBSTITUTO' AND ATIVO = 1;
PRINT 'Substitutos na equipe: ' + CAST(@Substitutos AS varchar(10));
GO

DECLARE @ComAlergia int;
SELECT @ComAlergia = COUNT(*) FROM dbo.TB_ALUNOS WHERE ALERGIAS IS NOT NULL AND LTRIM(RTRIM(ALERGIAS)) <> '';
PRINT 'Alunos com alergia registrada: ' + CAST(@ComAlergia AS varchar(10));
GO

PRINT '';
PRINT '================================================================';
PRINT 'Atualização concluída. Nenhum dado existente foi apagado.';
PRINT 'Agora copie os arquivos PHP novos por cima da instalação.';
PRINT '================================================================';
GO