/* ============================================================================
   SISTEMA DE CHAMADA — POPULAR O BANCO COM DADOS DE DEMONSTRAÇÃO
   Prefeitura Municipal de Angatuba

   ----------------------------------------------------------------------------
   O QUE ESTE SCRIPT FAZ
   ----------------------------------------------------------------------------
   Cria dados FICTÍCIOS para você exercitar o sistema: diretores, professores,
   turmas com equipe e regente, alunos, feriados (inclusive parciais), chamada de
   vários meses, retificações, observações de desenvolvimento e relatórios anuais.

   ----------------------------------------------------------------------------
   IMPORTANTE — LEIA ANTES DE RODAR
   ----------------------------------------------------------------------------
   1. Rode ANTES o INSTALACAO_COMPLETA.sql. Este script não cria tabela do
      sistema, só insere dados.
   2. Todos os nomes de alunos, responsáveis e professores são INVENTADOS.
      Telefones e e-mails não são reais.
   3. Nada que já existe é alterado ou apagado: o script só insere.
   4. TODO registro criado fica anotado em dbo.TB_DEMO_IDS. Para desfazer, rode
      LIMPAR_DEMONSTRACAO.sql — ele remove os dados de demonstração e nada mais.
   5. Rodar duas vezes NÃO duplica: se já houver dados de demonstração, o script
      avisa e sai. Para repopular, rode a limpeza antes.
   6. A chamada vai até ONTEM. Hoje fica em aberto de propósito, para você testar
      o lançamento e a confirmação pela tela.
   7. É tudo ou nada: se qualquer passo falhar, a transação é desfeita e o banco
      fica como estava.

   ----------------------------------------------------------------------------
   COMO USAR
   ----------------------------------------------------------------------------
   SSMS / Azure Data Studio: abra o arquivo, confira o nome do banco no USE,
   revise a CONFIGURAÇÃO e execute (F5).
   sqlcmd:
     sqlcmd -S 127.0.0.1,1433 -U sa -P SuaSenha -i POPULAR_DEMONSTRACAO.sql

   ----------------------------------------------------------------------------
   USUÁRIOS CRIADOS — senha de todos: Demo@2026
   ----------------------------------------------------------------------------
   Os CPFs ficam na faixa reservada 900.000.0xx-xx, com números inválidos de
   propósito para nunca colidir com um CPF de verdade. A lista completa com o
   CPF de cada um aparece no final da execução.
   ============================================================================ */

USE [db_chamada];
GO

SET NOCOUNT ON;
SET XACT_ABORT ON;
GO

/* ----------------------------------------------------------------------------
   Tabela de marcação: a lista do que o script criou.
   Fica em lote separado para já existir quando os INSERT ... OUTPUT rodarem.
   ---------------------------------------------------------------------------- */
IF OBJECT_ID('dbo.TB_DEMO_IDS', 'U') IS NULL
    CREATE TABLE dbo.TB_DEMO_IDS (
        TABELA      varchar(40) NOT NULL,
        REGISTRO_ID int         NOT NULL,
        CONSTRAINT PK_TB_DEMO_IDS PRIMARY KEY CLUSTERED (TABELA, REGISTRO_ID)
    );
GO

/* ============================================================================
   CONFIGURAÇÃO — ajuste aqui antes de executar
   ============================================================================ */
DECLARE @AnoLetivo        int = YEAR(GETDATE());  -- ano letivo das turmas
DECLARE @QtdUnidades      int = 3;    -- em quantas unidades criar turmas (1 a 20)
DECLARE @TurmasPorUnidade int = 4;    -- turmas por unidade (1 a 6)
DECLARE @AlunosPorTurma   int = 18;   -- alunos por turma (1 a 40)
DECLARE @MesesHistorico   int = 3;    -- meses de chamada para trás (0 a 12)
DECLARE @GerarRelatorios  bit = 1;    -- 1 = relatório anual para metade dos alunos

/* Senha dos usuários de demonstração: Demo@2026
   Para trocar, substitua o MD5 abaixo. No PHP: md5('SuaSenha').
   Atenção: o sistema compara o MD5 em minúsculas. */
DECLARE @SenhaDemo nvarchar(255) = N'793239beed23cff0a99388438be89eae';

/* ============================================================================
   TRAVAS
   ============================================================================ */
IF EXISTS (SELECT 1 FROM dbo.TB_DEMO_IDS)
BEGIN
    PRINT '================================================================';
    PRINT 'JÁ EXISTEM DADOS DE DEMONSTRAÇÃO NESTE BANCO.';
    PRINT 'Nada foi feito, para não duplicar.';
    PRINT 'Para começar de novo, execute antes: LIMPAR_DEMONSTRACAO.sql';
    PRINT '================================================================';
    SELECT TABELA AS Tabela, COUNT(*) AS Registros
    FROM dbo.TB_DEMO_IDS GROUP BY TABELA ORDER BY TABELA;
    RETURN;
END

IF @QtdUnidades       NOT BETWEEN 1 AND 20
   OR @TurmasPorUnidade NOT BETWEEN 1 AND 6
   OR @AlunosPorTurma   NOT BETWEEN 1 AND 40
   OR @MesesHistorico   NOT BETWEEN 0 AND 12
   OR @AnoLetivo        NOT BETWEEN 2000 AND 2100
BEGIN
    RAISERROR('Configuração inválida: unidades 1-20, turmas 1-6, alunos 1-40, meses 0-12, ano 2000-2100.', 16, 1);
    RETURN;
END

IF NOT EXISTS (SELECT 1 FROM dbo.TB_UNIDADES)
BEGIN
    RAISERROR('Não há unidades cadastradas. Execute INSTALACAO_COMPLETA.sql primeiro.', 16, 1);
    RETURN;
END

/* ----------------------------------------------------------------------------
   Tabelas de trabalho. Declaradas todas aqui, fora dos IF, para existirem
   mesmo quando algum passo for pulado.
   ---------------------------------------------------------------------------- */
DECLARE @unidades    TABLE (ordem int IDENTITY(1,1), id int, nome nvarchar(255));
DECLARE @tipos       TABLE (ordem int IDENTITY(1,1), nome nvarchar(80), idade_min int,
                            idade_max int, periodo nvarchar(60), capacidade int);
DECLARE @periodos    TABLE (nome nvarchar(60), ini nvarchar(5), fim nvarchar(5));
DECLARE @planoTurma  TABLE (id int IDENTITY(1,1), id_unidade int, nome nvarchar(80),
                            id_periodo int, capacidade int, idade_min int, idade_max int);
DECLARE @turmas      TABLE (ordem int IDENTITY(1,1), id int, id_unidade int, nome nvarchar(80),
                            idade_min int, idade_max int, regente int, auxiliar int);
DECLARE @nomes       TABLE (i int IDENTITY(1,1), nome nvarchar(40));
DECLARE @sobrenomes  TABLE (i int IDENTITY(1,1), nome nvarchar(40));
DECLARE @planoProf   TABLE (seq int IDENTITY(1,1), cpf nvarchar(14), nome nvarchar(255),
                            email nvarchar(150), turma_ordem int, papel nvarchar(10));
DECLARE @planoDir    TABLE (seq int IDENTITY(1,1), cpf nvarchar(14), nome nvarchar(255),
                            email nvarchar(150), id_unidade int);
DECLARE @seqAluno    TABLE (n int IDENTITY(1,1), dummy int);
DECLARE @planoAluno  TABLE (seq int IDENTITY(1,1), id_turma int, matricula nvarchar(30),
                            nome nvarchar(150), nascimento date, responsavel nvarchar(150),
                            telefone nvarchar(30), email nvarchar(150));
DECLARE @feriados    TABLE (data date, descricao nvarchar(150), tipo nvarchar(10),
                            ini nvarchar(5), fim nvarchar(5));
DECLARE @dias        TABLE (data date PRIMARY KEY);
DECLARE @alunos      TABLE (id int, id_turma int, id_unidade int, regente int);
DECLARE @chamada     TABLE (id_aluno int, data date, status nvarchar(1),
                            justificativa nvarchar(500), id_usuario int);
DECLARE @retificar   TABLE (id_chamada int, anterior nvarchar(1), nova nvarchar(1),
                            just_ant nvarchar(500), just_nova nvarchar(500),
                            motivo nvarchar(500), id_usuario int);
DECLARE @cat         TABLE (i int IDENTITY(1,1), categoria nvarchar(40), texto nvarchar(max));
DECLARE @quatro      TABLE (n int);

DECLARE @qtdNomes int, @qtdSobre int, @qtdCat int;
DECLARE @hoje date = CAST(GETDATE() AS date);
DECLARE @fim  date = DATEADD(DAY, -1, @hoje);
DECLARE @ini  date = DATEADD(MONTH, -@MesesHistorico, DATEADD(DAY, -1, CAST(GETDATE() AS date)));
DECLARE @totalDias int;
DECLARE @corteIdade date = DATEFROMPARTS(@AnoLetivo, 3, 31);
DECLARE @diaUm date = DATEFROMPARTS(YEAR(GETDATE()), MONTH(GETDATE()), 1);

BEGIN TRY
BEGIN TRANSACTION;

PRINT '================================================================';
PRINT 'POPULANDO O BANCO COM DADOS DE DEMONSTRAÇÃO';
PRINT 'Ano letivo: ' + CAST(@AnoLetivo AS varchar(4));
PRINT '================================================================';

/* ----------------------------------------------------------------------------
   1. Períodos — aproveita os existentes, cria só o que falta
   ---------------------------------------------------------------------------- */
INSERT INTO @periodos (nome, ini, fim) VALUES
    (N'Manhã',    '07:00', '12:00'),
    (N'Tarde',    '13:00', '18:00'),
    (N'Integral', '07:00', '18:00');

INSERT INTO dbo.TB_PERIODOS (NOME, HORA_INICIO, HORA_FIM)
OUTPUT 'TB_PERIODOS', INSERTED.ID INTO dbo.TB_DEMO_IDS (TABELA, REGISTRO_ID)
SELECT p.nome, p.ini, p.fim
FROM @periodos p
WHERE NOT EXISTS (SELECT 1 FROM dbo.TB_PERIODOS t WHERE t.NOME = p.nome);
PRINT '  + períodos novos: ' + CAST(@@ROWCOUNT AS varchar(10));

/* ----------------------------------------------------------------------------
   2. Unidades — usa as que já existem, preferindo as de educação infantil
   ---------------------------------------------------------------------------- */
INSERT INTO @unidades (id, nome)
SELECT TOP (@QtdUnidades) u.id, u.unidade
FROM dbo.TB_UNIDADES u
ORDER BY CASE WHEN u.unidade LIKE '%CMEI%'   THEN 0
              WHEN u.unidade LIKE '%CEMEIF%' THEN 1
              WHEN u.unidade LIKE '%EMEIF%'  THEN 2
              WHEN u.unidade LIKE '%NISC%'   THEN 3
              ELSE 4 END,
         u.id;
PRINT '  + unidades usadas: ' + CAST(@@ROWCOUNT AS varchar(10)) + ' (já cadastradas, nada criado)';

/* ----------------------------------------------------------------------------
   3. Tipos de turma da creche, com a faixa de idade
   ---------------------------------------------------------------------------- */
INSERT INTO @tipos (nome, idade_min, idade_max, periodo, capacidade) VALUES
    (N'Berçário I',  0, 1, N'Integral', 15),
    (N'Berçário II', 1, 2, N'Integral', 18),
    (N'Maternal I',  2, 3, N'Manhã',    20),
    (N'Maternal II', 3, 4, N'Tarde',    22),
    (N'Pré I',       4, 5, N'Manhã',    24),
    (N'Pré II',      5, 6, N'Tarde',    25);

/* ----------------------------------------------------------------------------
   4. Turmas
   ---------------------------------------------------------------------------- */
INSERT INTO @planoTurma (id_unidade, nome, id_periodo, capacidade, idade_min, idade_max)
SELECT u.id, t.nome,
       (SELECT TOP (1) p.ID FROM dbo.TB_PERIODOS p WHERE p.NOME = t.periodo ORDER BY p.ID),
       t.capacidade, t.idade_min, t.idade_max
FROM @unidades u
CROSS JOIN @tipos t
WHERE t.ordem <= @TurmasPorUnidade
ORDER BY u.ordem, t.ordem;

INSERT INTO dbo.TB_TURMAS (ID_UNIDADE, NOME, ANO_LETIVO, ID_PERIODO, CAPACIDADE, ATIVO)
OUTPUT 'TB_TURMAS', INSERTED.ID INTO dbo.TB_DEMO_IDS (TABELA, REGISTRO_ID)
SELECT p.id_unidade, p.nome, @AnoLetivo, p.id_periodo, p.capacidade, 1
FROM @planoTurma p
WHERE p.id_periodo IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM dbo.TB_TURMAS t
                  WHERE t.ID_UNIDADE = p.id_unidade AND t.NOME = p.nome AND t.ANO_LETIVO = @AnoLetivo);
PRINT '  + turmas criadas: ' + CAST(@@ROWCOUNT AS varchar(10));

/* Amarra o plano aos IDs reais pela chave única (unidade, nome, ano) */
INSERT INTO @turmas (id, id_unidade, nome, idade_min, idade_max)
SELECT t.ID, p.id_unidade, p.nome, p.idade_min, p.idade_max
FROM @planoTurma p
INNER JOIN dbo.TB_TURMAS t
        ON t.ID_UNIDADE = p.id_unidade AND t.NOME = p.nome AND t.ANO_LETIVO = @AnoLetivo
ORDER BY p.id;

/* ----------------------------------------------------------------------------
   5. Nomes fictícios, combinados para dar variedade
   ---------------------------------------------------------------------------- */
INSERT INTO @nomes (nome) VALUES
    (N'Ana'),(N'Bernardo'),(N'Cecília'),(N'Davi'),(N'Elisa'),(N'Felipe'),
    (N'Gabriela'),(N'Heitor'),(N'Isadora'),(N'Joaquim'),(N'Lara'),(N'Lorenzo'),
    (N'Manuela'),(N'Nicolas'),(N'Olívia'),(N'Pedro'),(N'Helena'),(N'Rafael'),
    (N'Sofia'),(N'Theodoro'),(N'Valentina'),(N'Vicente'),(N'Aurora'),(N'Bento'),
    (N'Clara'),(N'Daniel'),(N'Emanuelly'),(N'Gustavo'),(N'Isabel'),(N'Lucas'),
    (N'Mariana'),(N'Murilo'),(N'Natália'),(N'Otávio'),(N'Pietra'),(N'Rodrigo'),
    (N'Sarah'),(N'Tiago'),(N'Yasmin'),(N'Arthur');

INSERT INTO @sobrenomes (nome) VALUES
    (N'Silva'),(N'Santos'),(N'Oliveira'),(N'Souza'),(N'Pereira'),(N'Costa'),
    (N'Rodrigues'),(N'Almeida'),(N'Nascimento'),(N'Lima'),(N'Araújo'),(N'Fernandes'),
    (N'Carvalho'),(N'Gomes'),(N'Martins'),(N'Rocha'),(N'Ribeiro'),(N'Alves'),
    (N'Monteiro'),(N'Mendes'),(N'Barros'),(N'Freitas'),(N'Barbosa'),(N'Pinto'),
    (N'Moura'),(N'Cavalcanti'),(N'Dias'),(N'Castro'),(N'Campos'),(N'Cardoso');

SET @qtdNomes = (SELECT COUNT(*) FROM @nomes);
SET @qtdSobre = (SELECT COUNT(*) FROM @sobrenomes);

/* ----------------------------------------------------------------------------
   6. Professores: um regente e um auxiliar por turma
   ---------------------------------------------------------------------------- */
INSERT INTO @planoProf (cpf, nome, email, turma_ordem, papel)
SELECT
    N'900.000.' + RIGHT('000' + CAST(((t.ordem - 1) * 2 + v.n) AS varchar(3)), 3)
                + N'-' + RIGHT('00' + CAST(((t.ordem - 1) * 2 + v.n) % 100 AS varchar(2)), 2),
    n.nome + N' ' + s.nome,
    N'demo.prof' + RIGHT('00' + CAST(((t.ordem - 1) * 2 + v.n) AS varchar(3)), 2) + N'@exemplo.com',
    t.ordem,
    CASE WHEN v.n = 1 THEN N'REGENTE' ELSE N'AUXILIAR' END
FROM @turmas t
CROSS JOIN (VALUES (1), (2)) v(n)
INNER JOIN @nomes n      ON n.i = (((t.ordem - 1) * 2 + v.n) % @qtdNomes) + 1
INNER JOIN @sobrenomes s ON s.i = (((t.ordem - 1) * 2 + v.n) * 7 % @qtdSobre) + 1
ORDER BY t.ordem, v.n;

INSERT INTO dbo.TB_USUARIOS (NOME, SENHA, NIVEL, CPF, EMAIL, ATIVO)
OUTPUT 'TB_USUARIOS', INSERTED.ID INTO dbo.TB_DEMO_IDS (TABELA, REGISTRO_ID)
SELECT p.nome, @SenhaDemo, 3, p.cpf, p.email, 1
FROM @planoProf p
WHERE NOT EXISTS (SELECT 1 FROM dbo.TB_USUARIOS u WHERE u.CPF = p.cpf);
PRINT '  + professores criados: ' + CAST(@@ROWCOUNT AS varchar(10));

UPDATE t SET t.regente = u.ID
FROM @turmas t
INNER JOIN @planoProf p ON p.turma_ordem = t.ordem AND p.papel = N'REGENTE'
INNER JOIN dbo.TB_USUARIOS u ON u.CPF = p.cpf;

UPDATE t SET t.auxiliar = u.ID
FROM @turmas t
INNER JOIN @planoProf p ON p.turma_ordem = t.ordem AND p.papel = N'AUXILIAR'
INNER JOIN dbo.TB_USUARIOS u ON u.CPF = p.cpf;

/* ----------------------------------------------------------------------------
   7. Um diretor por unidade, com a unidade liberada em TB_ACESSO
   ---------------------------------------------------------------------------- */
INSERT INTO @planoDir (cpf, nome, email, id_unidade)
SELECT
    N'900.000.' + RIGHT('000' + CAST(900 + u.ordem AS varchar(3)), 3)
                + N'-' + RIGHT('00' + CAST((900 + u.ordem) % 100 AS varchar(2)), 2),
    n.nome + N' ' + s.nome,
    N'demo.diretor' + CAST(u.ordem AS varchar(2)) + N'@exemplo.com',
    u.id
FROM @unidades u
INNER JOIN @nomes n      ON n.i = ((u.ordem * 3) % @qtdNomes) + 1
INNER JOIN @sobrenomes s ON s.i = ((u.ordem * 11) % @qtdSobre) + 1
ORDER BY u.ordem;

INSERT INTO dbo.TB_USUARIOS (NOME, SENHA, NIVEL, CPF, EMAIL, ATIVO)
OUTPUT 'TB_USUARIOS', INSERTED.ID INTO dbo.TB_DEMO_IDS (TABELA, REGISTRO_ID)
SELECT p.nome, @SenhaDemo, 2, p.cpf, p.email, 1
FROM @planoDir p
WHERE NOT EXISTS (SELECT 1 FROM dbo.TB_USUARIOS u WHERE u.CPF = p.cpf);
PRINT '  + diretores criados: ' + CAST(@@ROWCOUNT AS varchar(10));

INSERT INTO dbo.TB_ACESSO (id_usuario, id_unidade, ativo)
OUTPUT 'TB_ACESSO', INSERTED.id INTO dbo.TB_DEMO_IDS (TABELA, REGISTRO_ID)
SELECT u.ID, p.id_unidade, 1
FROM @planoDir p
INNER JOIN dbo.TB_USUARIOS u ON u.CPF = p.cpf
WHERE NOT EXISTS (SELECT 1 FROM dbo.TB_ACESSO a
                  WHERE a.id_usuario = u.ID AND a.id_unidade = p.id_unidade);
PRINT '  + vínculos de acesso: ' + CAST(@@ROWCOUNT AS varchar(10));

/* ----------------------------------------------------------------------------
   8. Equipe da turma — no máximo um regente ativo por turma
      Se a turma já tinha regente, o nosso entra como auxiliar.
   ---------------------------------------------------------------------------- */
INSERT INTO dbo.TB_TURMA_PROFESSORES (ID_TURMA, ID_USUARIO, PAPEL, ATIVO)
OUTPUT 'TB_TURMA_PROFESSORES', INSERTED.ID INTO dbo.TB_DEMO_IDS (TABELA, REGISTRO_ID)
SELECT t.id, x.id_usuario,
       CASE WHEN x.papel = N'REGENTE'
                 AND EXISTS (SELECT 1 FROM dbo.TB_TURMA_PROFESSORES e2
                             WHERE e2.ID_TURMA = t.id AND e2.PAPEL = N'REGENTE' AND e2.ATIVO = 1)
            THEN N'AUXILIAR' ELSE x.papel END,
       1
FROM @turmas t
CROSS APPLY (VALUES (t.regente, N'REGENTE'), (t.auxiliar, N'AUXILIAR')) x(id_usuario, papel)
WHERE x.id_usuario IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM dbo.TB_TURMA_PROFESSORES e
                  WHERE e.ID_TURMA = t.id AND e.ID_USUARIO = x.id_usuario);
PRINT '  + vínculos de equipe: ' + CAST(@@ROWCOUNT AS varchar(10));

/* O campo antigo TB_TURMAS.ID_PROFESSOR passa a apontar para o regente de fato */
UPDATE tt SET tt.ID_PROFESSOR = r.ID_USUARIO
FROM dbo.TB_TURMAS tt
INNER JOIN @turmas t ON t.id = tt.ID
INNER JOIN dbo.TB_TURMA_PROFESSORES r
        ON r.ID_TURMA = tt.ID AND r.PAPEL = N'REGENTE' AND r.ATIVO = 1;

/* ----------------------------------------------------------------------------
   9. Alunos
      Matrícula na faixa reservada <ano>9xxxx, para não colidir com as reais.
      Idade calculada em 31/03 do ano letivo.
      OBS: ABS(CHECKSUM(...) % N) e não ABS(CHECKSUM(...)) % N — o CHECKSUM pode
      devolver -2147483648, cujo ABS estoura o int.
   ---------------------------------------------------------------------------- */
INSERT INTO @seqAluno (dummy)
SELECT TOP (@AlunosPorTurma) 1
FROM (VALUES (0),(1),(2),(3),(4),(5),(6),(7),(8),(9)) a(n)
CROSS JOIN (VALUES (0),(1),(2),(3),(4)) b(n);

INSERT INTO @planoAluno (id_turma, matricula, nome, nascimento, responsavel, telefone, email)
SELECT
    t.id,
    CAST(@AnoLetivo AS varchar(4))
        + RIGHT('00000' + CAST(90000 + (t.ordem - 1) * @AlunosPorTurma + q.n AS varchar(6)), 5),
    n.nome + N' ' + s.nome,
    DATEADD(DAY, -ABS(CHECKSUM(CONCAT(N'nasc', t.ordem, N'-', q.n)) % 365),
        DATEADD(YEAR, -(t.idade_min
                + ABS(CHECKSUM(CONCAT(N'idade', t.ordem, N'-', q.n)) % (t.idade_max - t.idade_min + 1))),
            @corteIdade)),
    rn.nome + N' ' + s.nome,
    N'(15) 9' + RIGHT('00000000' + CAST(ABS(CHECKSUM(CONCAT(N'fone', t.ordem, N'-', q.n)) % 100000000) AS varchar(8)), 8),
    N'demo.aluno' + CAST((t.ordem - 1) * @AlunosPorTurma + q.n AS varchar(5)) + N'@exemplo.com'
FROM @turmas t
CROSS JOIN @seqAluno q
INNER JOIN @nomes n      ON n.i  = (((t.ordem - 1) * @AlunosPorTurma + q.n) % @qtdNomes) + 1
INNER JOIN @sobrenomes s ON s.i  = (((t.ordem - 1) * @AlunosPorTurma + q.n) * 13 % @qtdSobre) + 1
INNER JOIN @nomes rn     ON rn.i = (((t.ordem - 1) * @AlunosPorTurma + q.n) * 17 % @qtdNomes) + 1
ORDER BY t.ordem, q.n;

INSERT INTO dbo.TB_ALUNOS (ID_TURMA, MATRICULA, NOME, NASCIMENTO, DATA_MATRICULA,
                           RESPONSAVEL, TELEFONE, EMAIL, ATIVO)
OUTPUT 'TB_ALUNOS', INSERTED.ID INTO dbo.TB_DEMO_IDS (TABELA, REGISTRO_ID)
SELECT p.id_turma, p.matricula, p.nome, p.nascimento, DATEFROMPARTS(@AnoLetivo, 2, 1),
       p.responsavel, p.telefone, p.email, 1
FROM @planoAluno p
WHERE NOT EXISTS (SELECT 1 FROM dbo.TB_ALUNOS a WHERE a.MATRICULA = p.matricula);
PRINT '  + alunos criados: ' + CAST(@@ROWCOUNT AS varchar(10));

/* Alunos das turmas de demonstração, com o regente de cada um */
INSERT INTO @alunos (id, id_turma, id_unidade, regente)
SELECT a.ID, a.ID_TURMA, t.id_unidade,
       (SELECT TOP (1) e.ID_USUARIO FROM dbo.TB_TURMA_PROFESSORES e
        WHERE e.ID_TURMA = a.ID_TURMA AND e.PAPEL = N'REGENTE' AND e.ATIVO = 1
        ORDER BY e.ID)
FROM dbo.TB_ALUNOS a
INNER JOIN @turmas t ON t.id = a.ID_TURMA
WHERE a.ATIVO = 1;

/* ----------------------------------------------------------------------------
   10. Feriados do ano — nacionais e dois parciais de exemplo
       ID_UNIDADE NULL = vale para toda a rede.
   ---------------------------------------------------------------------------- */
/* Páscoa (Meeus/Jones/Butcher), para Carnaval, Sexta-feira Santa e Corpus Christi */
DECLARE @a int = @AnoLetivo % 19, @b int = @AnoLetivo / 100, @c int = @AnoLetivo % 100;
DECLARE @d int = @b / 4, @e int = @b % 4, @f int = (@b + 8) / 25;
DECLARE @g int = (@b - @f + 1) / 3, @h int = (19 * @a + @b - @d - @g + 15) % 30;
DECLARE @i int = @c / 4, @k int = @c % 4, @l int = (32 + 2 * @e + 2 * @i - @h - @k) % 7;
DECLARE @m int = (@a + 11 * @h + 22 * @l) / 451;
DECLARE @mes int = (@h + @l - 7 * @m + 114) / 31;
DECLARE @dia int = ((@h + @l - 7 * @m + 114) % 31) + 1;
DECLARE @pascoa date = DATEFROMPARTS(@AnoLetivo, @mes, @dia);

INSERT INTO @feriados (data, descricao, tipo, ini, fim) VALUES
    (DATEFROMPARTS(@AnoLetivo, 1, 1),   N'Confraternização Universal', N'INTEGRAL', NULL, NULL),
    (DATEADD(DAY, -48, @pascoa),        N'Carnaval',                   N'INTEGRAL', NULL, NULL),
    (DATEADD(DAY, -47, @pascoa),        N'Carnaval',                   N'INTEGRAL', NULL, NULL),
    (DATEADD(DAY, -2,  @pascoa),        N'Sexta-feira Santa',          N'INTEGRAL', NULL, NULL),
    (DATEFROMPARTS(@AnoLetivo, 4, 21),  N'Tiradentes',                 N'INTEGRAL', NULL, NULL),
    (DATEFROMPARTS(@AnoLetivo, 5, 1),   N'Dia do Trabalho',            N'INTEGRAL', NULL, NULL),
    (DATEADD(DAY, 60, @pascoa),         N'Corpus Christi',             N'INTEGRAL', NULL, NULL),
    (DATEFROMPARTS(@AnoLetivo, 9, 7),   N'Independência do Brasil',    N'INTEGRAL', NULL, NULL),
    (DATEFROMPARTS(@AnoLetivo, 10, 12), N'Nossa Senhora Aparecida',    N'INTEGRAL', NULL, NULL),
    (DATEFROMPARTS(@AnoLetivo, 11, 2),  N'Finados',                    N'INTEGRAL', NULL, NULL),
    (DATEFROMPARTS(@AnoLetivo, 11, 15), N'Proclamação da República',   N'INTEGRAL', NULL, NULL),
    (DATEFROMPARTS(@AnoLetivo, 11, 20), N'Dia da Consciência Negra',   N'INTEGRAL', NULL, NULL),
    (DATEFROMPARTS(@AnoLetivo, 12, 25), N'Natal',                      N'INTEGRAL', NULL, NULL),
    (DATEFROMPARTS(@AnoLetivo, 6, 12),  N'Reunião de pais — expediente até o meio-dia',    N'PARCIAL', '12:00', '18:00'),
    (DATEFROMPARTS(@AnoLetivo, 8, 14),  N'Conselho de classe — expediente até o meio-dia', N'PARCIAL', '12:00', '18:00');

INSERT INTO dbo.TB_FERIADOS (DATA, DESCRICAO, TIPO, HORA_INICIO, HORA_FIM, ID_UNIDADE)
OUTPUT 'TB_FERIADOS', INSERTED.ID INTO dbo.TB_DEMO_IDS (TABELA, REGISTRO_ID)
SELECT f.data, f.descricao, f.tipo, f.ini, f.fim, NULL
FROM @feriados f
WHERE NOT EXISTS (SELECT 1 FROM dbo.TB_FERIADOS x
                  WHERE x.DATA = f.data AND x.ID_UNIDADE IS NULL);
PRINT '  + feriados criados: ' + CAST(@@ROWCOUNT AS varchar(10)) + ' (2 deles parciais)';

/* ----------------------------------------------------------------------------
   11. Chamada — dias úteis de @MesesHistorico até ONTEM
       O dia da semana não depende de idioma nem de SET DATEFIRST:
       01/01/1900 foi segunda, então DATEDIFF % 7 dá 0=seg ... 6=dom.
   ---------------------------------------------------------------------------- */
SET @totalDias = DATEDIFF(DAY, @ini, @fim) + 1;

IF @totalDias > 0 AND @totalDias <= 1000
BEGIN
    ;WITH dez AS (SELECT n FROM (VALUES (0),(1),(2),(3),(4),(5),(6),(7),(8),(9)) v(n)),
    nums AS (SELECT ROW_NUMBER() OVER (ORDER BY (SELECT NULL)) - 1 AS n
             FROM dez a CROSS JOIN dez b CROSS JOIN dez c)
    INSERT INTO @dias (data)
    SELECT DATEADD(DAY, n, @ini)
    FROM nums
    WHERE n < @totalDias
      AND DATEDIFF(DAY, CAST('19000101' AS date), DATEADD(DAY, n, @ini)) % 7 < 5;

    PRINT '  + dias letivos no período: ' + CAST(@@ROWCOUNT AS varchar(10));

    INSERT INTO @chamada (id_aluno, data, status, justificativa, id_usuario)
    SELECT
        al.id, d.data,
        CASE WHEN r.r < 86 THEN N'P'
             WHEN r.r < 93 THEN N'F'
             WHEN r.r < 97 THEN N'J'
             ELSE N'A' END,
        CASE WHEN r.r >= 93 AND r.r < 97 THEN
            CASE r.r % 4
                WHEN 0 THEN N'Consulta médica agendada, avisado pela família.'
                WHEN 1 THEN N'Exame de rotina no período da manhã.'
                WHEN 2 THEN N'Fisioterapia, chega após o início da aula.'
                ELSE        N'Compromisso com a família comunicado à coordenação.'
            END
        ELSE NULL END,
        al.regente
    FROM @alunos al
    CROSS JOIN @dias d
    CROSS APPLY (SELECT ABS(CHECKSUM(CONCAT(N'ch', al.id, N'|', CONVERT(varchar(10), d.data, 23))) % 100) AS r) r
    WHERE al.regente IS NOT NULL
      AND NOT EXISTS (SELECT 1 FROM dbo.TB_FERIADOS f
                      WHERE f.DATA = d.data AND f.TIPO = N'INTEGRAL'
                        AND (f.ID_UNIDADE IS NULL OR f.ID_UNIDADE = al.id_unidade))
      AND NOT EXISTS (SELECT 1 FROM dbo.TB_CHAMADA c
                      WHERE c.ID_ALUNO = al.id AND c.DATA = d.data);

    INSERT INTO dbo.TB_CHAMADA (ID_ALUNO, DATA, STATUS, JUSTIFICATIVA, ID_USUARIO,
                                CONFIRMADA_EM, CONFIRMADA_POR)
    OUTPUT 'TB_CHAMADA', INSERTED.ID INTO dbo.TB_DEMO_IDS (TABELA, REGISTRO_ID)
    SELECT c.id_aluno, c.data, c.status, c.justificativa, c.id_usuario,
           DATEADD(MINUTE, 510, CAST(c.data AS datetime)),   /* 08:30 do mesmo dia */
           c.id_usuario
    FROM @chamada c;
    PRINT '  + registros de chamada: ' + CAST(@@ROWCOUNT AS varchar(10));

    /* ------------------------------------------------------------------------
       12. Retificações: 12 faltas viram falta justificada, com histórico
       ------------------------------------------------------------------------ */
    INSERT INTO @retificar (id_chamada, anterior, nova, just_ant, just_nova, motivo, id_usuario)
    SELECT TOP (12) c.ID, c.STATUS, N'J', c.JUSTIFICATIVA,
           N'Família apresentou atestado após o fechamento da chamada.',
           N'Correção após apresentação de atestado pela família.',
           c.ID_USUARIO
    FROM dbo.TB_CHAMADA c
    INNER JOIN dbo.TB_DEMO_IDS d ON d.TABELA = 'TB_CHAMADA' AND d.REGISTRO_ID = c.ID
    WHERE c.STATUS = N'F'
    ORDER BY c.ID;

    INSERT INTO dbo.TB_CHAMADA_RETIFICACOES
        (ID_CHAMADA, STATUS_ANTERIOR, STATUS_NOVO, JUSTIFICATIVA_ANTERIOR,
         JUSTIFICATIVA_NOVA, MOTIVO, ID_USUARIO)
    OUTPUT 'TB_CHAMADA_RETIFICACOES', INSERTED.ID INTO dbo.TB_DEMO_IDS (TABELA, REGISTRO_ID)
    SELECT r.id_chamada, r.anterior, r.nova, r.just_ant, r.just_nova, r.motivo, r.id_usuario
    FROM @retificar r;
    PRINT '  + retificações: ' + CAST(@@ROWCOUNT AS varchar(10));

    UPDATE c SET c.STATUS = r.nova, c.JUSTIFICATIVA = r.just_nova, c.MODIFIED = GETDATE()
    FROM dbo.TB_CHAMADA c
    INNER JOIN @retificar r ON r.id_chamada = c.ID;
END
ELSE
    PRINT '  = chamada não gerada (período vazio ou maior que 1000 dias)';

/* ----------------------------------------------------------------------------
   13. Observações de desenvolvimento — uma por mês, 4 meses, categorias variadas
       A tela de Observações abre filtrando o MÊS CORRENTE. Por isso o registro
       mais recente é datado do dia 1º deste mês: assim a tela já abre com
       conteúdo, em vez de parecer vazia.
   ---------------------------------------------------------------------------- */
INSERT INTO @cat (categoria, texto) VALUES
    (N'Desenvolvimento geral',
     N'Apresenta progresso consistente nas propostas do período. Participa das rodas de conversa, demonstra curiosidade pelos materiais e conclui as atividades com apoio pontual da professora.'),
    (N'Linguagem e comunicação',
     N'Ampliou o vocabulário e passou a narrar pequenas situações do dia a dia. Reconhece o próprio nome escrito e demonstra interesse por histórias, pedindo releituras dos livros preferidos.'),
    (N'Coordenação motora',
     N'Avançou no traçado e no uso da tesoura. Corre, salta e sobe nos brinquedos do parque com segurança, alternando os pés ao subir a escada.'),
    (N'Convívio social',
     N'Relaciona-se bem com os colegas e já resolve pequenos conflitos com diálogo, recorrendo à professora quando necessário. Costuma acolher as crianças que chegam chorando.'),
    (N'Alimentação',
     N'Aceita bem as refeições oferecidas e passou a experimentar frutas que antes recusava. Alimenta-se sozinha com o uso da colher, com pouca necessidade de auxílio.'),
    (N'Sono e descanso',
     N'Adormece com tranquilidade no horário de descanso e cumpre o período previsto. Nos dias de maior agitação, precisa de mediação para se acalmar antes de dormir.'),
    (N'Saúde e higiene',
     N'Lava as mãos sem que seja lembrada e já avisa quando precisa ir ao banheiro. Demonstra cuidado ao guardar os objetos de uso pessoal na mochila.'),
    (N'Autonomia',
     N'Organiza os próprios pertences ao chegar e escolhe os brinquedos com que quer brincar. Pede ajuda quando encontra dificuldade, em vez de abandonar a atividade.'),
    (N'Contato com a família',
     N'A responsável compareceu à reunião e relatou avanços semelhantes em casa. Foi orientada a manter a rotina de leitura antes de dormir.'),
    (N'Outro',
     N'Registro complementar do acompanhamento da criança no período, para compor o parecer do fim do ano.');

SET @qtdCat = (SELECT COUNT(*) FROM @cat);
INSERT INTO @quatro (n) VALUES (1),(2),(3),(4);

INSERT INTO dbo.TB_OBSERVACOES (ID_ALUNO, DATA, CATEGORIA, TEXTO, ID_USUARIO)
OUTPUT 'TB_OBSERVACOES', INSERTED.ID INTO dbo.TB_DEMO_IDS (TABELA, REGISTRO_ID)
SELECT al.id, DATEADD(MONTH, -(q.n - 1), @diaUm), c.categoria, c.texto, al.regente
FROM @alunos al
CROSS JOIN @quatro q
INNER JOIN @cat c ON c.i = ((al.id + q.n * 3) % @qtdCat) + 1
WHERE al.regente IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM dbo.TB_OBSERVACOES o
                  WHERE o.ID_ALUNO = al.id
                    AND o.DATA = DATEADD(MONTH, -(q.n - 1), @diaUm)
                    AND o.CATEGORIA = c.categoria);
PRINT '  + observações: ' + CAST(@@ROWCOUNT AS varchar(10));

/* ----------------------------------------------------------------------------
   14. Relatório anual de metade dos alunos
   ---------------------------------------------------------------------------- */
IF @GerarRelatorios = 1
BEGIN
    INSERT INTO dbo.TB_RELATORIOS_ANUAIS (ID_ALUNO, ANO, PARECER, ENCAMINHAMENTOS, ID_USUARIO)
    OUTPUT 'TB_RELATORIOS_ANUAIS', INSERTED.ID INTO dbo.TB_DEMO_IDS (TABELA, REGISTRO_ID)
    SELECT al.id, @AnoLetivo,
        N'Ao longo do ano letivo a criança frequentou a unidade com regularidade e apresentou desenvolvimento adequado à faixa etária. '
      + N'Na linguagem, ampliou o vocabulário, passou a formular perguntas e a recontar histórias conhecidas. '
      + N'Na coordenação motora, ganhou firmeza no traçado e segurança nos movimentos amplos. '
      + N'No convívio, estabelece vínculos com os colegas e com as professoras, participa das atividades coletivas e aceita combinar regras. '
      + N'Na alimentação e na higiene, avançou na autonomia, alimentando-se sozinha e cuidando dos próprios pertences. '
      + N'As observações registradas ao longo do ano detalham cada um desses aspectos.',
        N'Dar continuidade às propostas de leitura diária e às brincadeiras de faz de conta. '
      + N'Manter o acompanhamento da autonomia nas refeições. '
      + N'Reavaliar o desenvolvimento no início do próximo ano letivo.',
        al.regente
    FROM @alunos al
    WHERE al.regente IS NOT NULL AND al.id % 2 = 0
      AND NOT EXISTS (SELECT 1 FROM dbo.TB_RELATORIOS_ANUAIS r
                      WHERE r.ID_ALUNO = al.id AND r.ANO = @AnoLetivo);
    PRINT '  + relatórios anuais: ' + CAST(@@ROWCOUNT AS varchar(10));
END

COMMIT TRANSACTION;
PRINT '';
PRINT 'Concluído sem erros.';
END TRY
BEGIN CATCH
    IF @@TRANCOUNT > 0 ROLLBACK TRANSACTION;
    PRINT '';
    PRINT '*** ERRO — NADA FOI GRAVADO (a transação foi desfeita) ***';
    PRINT 'Mensagem: ' + ERROR_MESSAGE();
    PRINT 'Linha: '   + CAST(ERROR_LINE() AS varchar(10));
    THROW;
END CATCH
GO

/* ============================================================================
   RESUMO
   ============================================================================ */
IF EXISTS (SELECT 1 FROM dbo.TB_DEMO_IDS)
BEGIN
    PRINT '';
    PRINT '================================================================';
    PRINT 'RESUMO DO QUE FOI CRIADO';
    PRINT '================================================================';

    SELECT TABELA AS Tabela, COUNT(*) AS Registros
    FROM dbo.TB_DEMO_IDS GROUP BY TABELA ORDER BY TABELA;

    PRINT '';
    PRINT 'USUÁRIOS DE DEMONSTRAÇÃO — senha de todos: Demo@2026';

    SELECT u.NOME AS Nome,
           u.CPF AS [CPF para entrar],
           CASE u.NIVEL WHEN 1 THEN N'Administrador'
                        WHEN 2 THEN N'Diretor'
                        ELSE N'Professor' END AS Nivel,
           u.EMAIL AS Email
    FROM dbo.TB_USUARIOS u
    INNER JOIN dbo.TB_DEMO_IDS d ON d.TABELA = 'TB_USUARIOS' AND d.REGISTRO_ID = u.ID
    ORDER BY u.NIVEL, u.NOME;

    PRINT '';
    PRINT 'TURMAS';

    SELECT un.unidade AS Unidade, t.NOME AS Turma, t.ANO_LETIVO AS Ano,
           (SELECT COUNT(*) FROM dbo.TB_ALUNOS a WHERE a.ID_TURMA = t.ID) AS Alunos,
           (SELECT COUNT(*) FROM dbo.TB_CHAMADA c
             INNER JOIN dbo.TB_ALUNOS a ON a.ID = c.ID_ALUNO
            WHERE a.ID_TURMA = t.ID) AS Chamadas
    FROM dbo.TB_TURMAS t
    INNER JOIN dbo.TB_UNIDADES un ON un.id = t.ID_UNIDADE
    INNER JOIN dbo.TB_DEMO_IDS d ON d.TABELA = 'TB_TURMAS' AND d.REGISTRO_ID = t.ID
    ORDER BY un.unidade, t.NOME;

    PRINT '';
    PRINT 'Hoje ficou sem chamada, para você lançar pela tela.';
    PRINT 'Para desfazer tudo: execute LIMPAR_DEMONSTRACAO.sql';
END
GO
