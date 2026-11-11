/* ============================================================================
   SISTEMA DE CHAMADA — REMOVER OS DADOS DE DEMONSTRAÇÃO
   Prefeitura Municipal de Angatuba

   ----------------------------------------------------------------------------
   O QUE ESTE SCRIPT FAZ
   ----------------------------------------------------------------------------
   Desfaz o POPULAR_DEMONSTRACAO.sql. Apaga somente o que aquele script criou,
   usando a lista de registros guardada em dbo.TB_DEMO_IDS, e no final remove a
   própria tabela de marcação.

   Além dos registros marcados, também remove o que você tiver lançado DURANTE
   OS TESTES dentro das turmas de demonstração (chamada, notas, observações,
   relatórios, alunos novos, professores acrescentados à equipe). Isso é
   necessário porque esses registros dependem das turmas que vão ser apagadas.

   ----------------------------------------------------------------------------
   O QUE ELE NUNCA FAZ
   ----------------------------------------------------------------------------
   - Não apaga unidade, período em uso, nem usuário real.
   - Não apaga aluno, chamada, observação ou relatório de turma real.
   - Se algum usuário de demonstração tiver lançado registro ou for responsável
     por disciplina em turma REAL, o script PARA e avisa, em vez de apagar dado
     real ou quebrar a integridade.
     Nesse caso, resolva o registro apontado e rode de novo.
   - É tudo ou nada: qualquer erro desfaz a transação inteira.

   ----------------------------------------------------------------------------
   COMO USAR
   ----------------------------------------------------------------------------
   Em bancos existentes, rode ATUALIZAR_DIARIO_CLASSE.sql antes deste script.
   A limpeza também remove dados do diário e requer as tabelas correspondentes.
   SSMS / Azure Data Studio: confira o nome do banco no USE e execute (F5).
   sqlcmd:
     sqlcmd -S 127.0.0.1,1433 -U sa -P SuaSenha -i LIMPAR_DEMONSTRACAO.sql

   ANTES DE RODAR EM PRODUÇÃO, FAÇA BACKUP. O script mostra a contagem do que
   será removido e só então apaga, mas backup é sempre o caminho seguro.
   ============================================================================ */

USE [db_chamada];
GO

SET NOCOUNT ON;
SET XACT_ABORT ON;
GO

IF OBJECT_ID('dbo.TB_DEMO_IDS', 'U') IS NULL
BEGIN
    PRINT 'Não há tabela de marcação: nenhum dado de demonstração para remover.';
    RETURN;
END

IF NOT EXISTS (SELECT 1 FROM dbo.TB_DEMO_IDS)
BEGIN
    PRINT 'A tabela de marcação está vazia: nada a remover.';
    DROP TABLE dbo.TB_DEMO_IDS;
    PRINT 'Tabela de marcação removida.';
    RETURN;
END

DECLARE @turmas TABLE (id int PRIMARY KEY);
DECLARE @alunos TABLE (id int PRIMARY KEY);
DECLARE @usuarios TABLE (id int PRIMARY KEY);

/* Turmas e usuários criados pelo script de demonstração */
INSERT INTO @turmas (id)
SELECT REGISTRO_ID FROM dbo.TB_DEMO_IDS WHERE TABELA = 'TB_TURMAS';

INSERT INTO @usuarios (id)
SELECT REGISTRO_ID FROM dbo.TB_DEMO_IDS WHERE TABELA = 'TB_USUARIOS';

/* Alunos marcados + alunos que você cadastrou dentro das turmas de demonstração */
INSERT INTO @alunos (id)
SELECT REGISTRO_ID FROM dbo.TB_DEMO_IDS WHERE TABELA = 'TB_ALUNOS';

INSERT INTO @alunos (id)
SELECT a.ID FROM dbo.TB_ALUNOS a
INNER JOIN @turmas t ON t.id = a.ID_TURMA
WHERE NOT EXISTS (SELECT 1 FROM @alunos x WHERE x.id = a.ID);

/* ----------------------------------------------------------------------------
   TRAVA DE SEGURANÇA
   Um usuário de demonstração pode ter lançado registros em aluno de turma REAL
   ou ser responsável por uma disciplina fora das turmas de demonstração.
   Esses vínculos não são nossos para apagar, e o usuário não pode ser removido.
   ---------------------------------------------------------------------------- */
DECLARE @fora int =
      (SELECT COUNT(*) FROM dbo.TB_CHAMADA c
        INNER JOIN @usuarios u ON u.id = c.ID_USUARIO
        WHERE NOT EXISTS (SELECT 1 FROM @alunos a WHERE a.id = c.ID_ALUNO))
    + (SELECT COUNT(*) FROM dbo.TB_CHAMADA c
        INNER JOIN @usuarios u ON u.id = c.CONFIRMADA_POR
        WHERE NOT EXISTS (SELECT 1 FROM @alunos a WHERE a.id = c.ID_ALUNO))
    + (SELECT COUNT(*) FROM dbo.TB_OBSERVACOES o
        INNER JOIN @usuarios u ON u.id = o.ID_USUARIO
        WHERE NOT EXISTS (SELECT 1 FROM @alunos a WHERE a.id = o.ID_ALUNO))
    + (SELECT COUNT(*) FROM dbo.TB_RELATORIOS_ANUAIS r
        INNER JOIN @usuarios u ON u.id = r.ID_USUARIO
        WHERE NOT EXISTS (SELECT 1 FROM @alunos a WHERE a.id = r.ID_ALUNO))
    + (SELECT COUNT(*) FROM dbo.TB_NOTAS n
        INNER JOIN @usuarios u ON u.id = n.ID_USUARIO
        WHERE NOT EXISTS (SELECT 1 FROM @alunos a WHERE a.id = n.ID_ALUNO))
    + (SELECT COUNT(*) FROM dbo.TB_TURMA_DISCIPLINAS d
        INNER JOIN @usuarios u ON u.id = d.ID_PROFESSOR
        WHERE NOT EXISTS (SELECT 1 FROM @turmas t WHERE t.id = d.ID_TURMA))
    + (SELECT COUNT(*) FROM dbo.TB_CHAMADA_RETIFICACOES rf
        INNER JOIN @usuarios u ON u.id = rf.ID_USUARIO
        WHERE NOT EXISTS (SELECT 1 FROM dbo.TB_CHAMADA c WHERE c.ID = rf.ID_CHAMADA
                          AND EXISTS (SELECT 1 FROM @alunos a WHERE a.id = c.ID_ALUNO)));

IF @fora > 0
BEGIN
    PRINT '================================================================';
    PRINT 'NADA FOI APAGADO.';
    PRINT 'Usuários de demonstração têm ' + CAST(@fora AS varchar(10))
        + ' vínculo(s) com registros de alunos ou disciplinas de turmas REAIS.';
    PRINT 'Apagar esses usuários agora quebraria a integridade do banco.';
    PRINT 'Veja a lista abaixo, reatribua ou remova esses registros e rode de novo.';
    PRINT '================================================================';

    /* @usuarios guarda só o id: o nome vem de TB_USUARIOS */
    SELECT 'Chamada (lançou)' AS Origem, c.ID, c.ID_ALUNO AS ID_ALUNO_OU_TURMA,
           CONVERT(varchar(10), c.DATA, 23) AS DATA, us.NOME AS Usuario
    FROM dbo.TB_CHAMADA c
    INNER JOIN @usuarios u ON u.id = c.ID_USUARIO
    INNER JOIN dbo.TB_USUARIOS us ON us.ID = u.id
    WHERE NOT EXISTS (SELECT 1 FROM @alunos a WHERE a.id = c.ID_ALUNO)
    UNION ALL
    SELECT 'Chamada (confirmou)', c.ID, c.ID_ALUNO,
           CONVERT(varchar(10), c.DATA, 23), us.NOME
    FROM dbo.TB_CHAMADA c
    INNER JOIN @usuarios u ON u.id = c.CONFIRMADA_POR
    INNER JOIN dbo.TB_USUARIOS us ON us.ID = u.id
    WHERE NOT EXISTS (SELECT 1 FROM @alunos a WHERE a.id = c.ID_ALUNO)
    UNION ALL
    SELECT 'Observação', o.ID, o.ID_ALUNO,
           CONVERT(varchar(10), o.DATA, 23), us.NOME
    FROM dbo.TB_OBSERVACOES o
    INNER JOIN @usuarios u ON u.id = o.ID_USUARIO
    INNER JOIN dbo.TB_USUARIOS us ON us.ID = u.id
    WHERE NOT EXISTS (SELECT 1 FROM @alunos a WHERE a.id = o.ID_ALUNO)
    UNION ALL
    SELECT 'Relatório anual', r.ID, r.ID_ALUNO, NULL, us.NOME
    FROM dbo.TB_RELATORIOS_ANUAIS r
    INNER JOIN @usuarios u ON u.id = r.ID_USUARIO
    INNER JOIN dbo.TB_USUARIOS us ON us.ID = u.id
    WHERE NOT EXISTS (SELECT 1 FROM @alunos a WHERE a.id = r.ID_ALUNO)
    UNION ALL
    SELECT 'Nota escolar', n.ID, n.ID_ALUNO, NULL, us.NOME
    FROM dbo.TB_NOTAS n
    INNER JOIN @usuarios u ON u.id = n.ID_USUARIO
    INNER JOIN dbo.TB_USUARIOS us ON us.ID = u.id
    WHERE NOT EXISTS (SELECT 1 FROM @alunos a WHERE a.id = n.ID_ALUNO)
    UNION ALL
    SELECT 'Responsável por disciplina', d.ID, d.ID_TURMA, NULL, us.NOME
    FROM dbo.TB_TURMA_DISCIPLINAS d
    INNER JOIN @usuarios u ON u.id = d.ID_PROFESSOR
    INNER JOIN dbo.TB_USUARIOS us ON us.ID = u.id
    WHERE NOT EXISTS (SELECT 1 FROM @turmas t WHERE t.id = d.ID_TURMA)
    ORDER BY Origem, ID;
    RETURN;
END

/* ----------------------------------------------------------------------------
   PRÉVIA — o que será removido
   ---------------------------------------------------------------------------- */
PRINT '================================================================';
PRINT 'REMOVENDO DADOS DE DEMONSTRAÇÃO';
PRINT '================================================================';
/* PRINT só aceita expressão escalar — subconsulta não é permitida.
   Por isso a contagem vai primeiro para uma variável. */
DECLARE @nTurmas int, @nAlunos int, @nUsuarios int;
SELECT @nTurmas = COUNT(*) FROM @turmas;
SELECT @nAlunos = COUNT(*) FROM @alunos;
SELECT @nUsuarios = COUNT(*) FROM @usuarios;
PRINT 'Turmas: '   + CAST(@nTurmas   AS varchar(10));
PRINT 'Alunos: '   + CAST(@nAlunos   AS varchar(10));
PRINT 'Usuários: ' + CAST(@nUsuarios AS varchar(10));

BEGIN TRY
BEGIN TRANSACTION;

/* 1. Notas e disciplinas das turmas de demonstração */
DELETE n
FROM dbo.TB_NOTAS n
INNER JOIN @alunos a ON a.id = n.ID_ALUNO;
PRINT '  - notas: ' + CAST(@@ROWCOUNT AS varchar(10));

DELETE d
FROM dbo.TB_TURMA_DISCIPLINAS d
INNER JOIN @turmas t ON t.id = d.ID_TURMA;
PRINT '  - disciplinas: ' + CAST(@@ROWCOUNT AS varchar(10));

/* 2. Retificações das chamadas desses alunos */
DELETE rf
FROM dbo.TB_CHAMADA_RETIFICACOES rf
INNER JOIN dbo.TB_CHAMADA c ON c.ID = rf.ID_CHAMADA
INNER JOIN @alunos a ON a.id = c.ID_ALUNO;
PRINT '  - retificações: ' + CAST(@@ROWCOUNT AS varchar(10));

/* 3. Chamada */
DELETE c
FROM dbo.TB_CHAMADA c INNER JOIN @alunos a ON a.id = c.ID_ALUNO;
PRINT '  - chamada: ' + CAST(@@ROWCOUNT AS varchar(10));

/* 4. Observações */
DELETE o
FROM dbo.TB_OBSERVACOES o INNER JOIN @alunos a ON a.id = o.ID_ALUNO;
PRINT '  - observações: ' + CAST(@@ROWCOUNT AS varchar(10));

/* 5. Relatórios anuais */
DELETE r
FROM dbo.TB_RELATORIOS_ANUAIS r INNER JOIN @alunos a ON a.id = r.ID_ALUNO;
PRINT '  - relatórios anuais: ' + CAST(@@ROWCOUNT AS varchar(10));

/* 6. Alunos */
DELETE al
FROM dbo.TB_ALUNOS al INNER JOIN @alunos a ON a.id = al.ID;
PRINT '  - alunos: ' + CAST(@@ROWCOUNT AS varchar(10));

/* 7. Equipe das turmas de demonstração */
DELETE e
FROM dbo.TB_TURMA_PROFESSORES e INNER JOIN @turmas t ON t.id = e.ID_TURMA;
PRINT '  - vínculos de equipe: ' + CAST(@@ROWCOUNT AS varchar(10));

/* 8. Solta a referência antiga de regente antes de apagar os professores.
      Vale para qualquer turma, inclusive real, que aponte para um demo. */
UPDATE tt SET tt.ID_PROFESSOR = NULL
FROM dbo.TB_TURMAS tt INNER JOIN @usuarios u ON u.id = tt.ID_PROFESSOR;
PRINT '  - referências de regente liberadas: ' + CAST(@@ROWCOUNT AS varchar(10));

/* 9. Turmas */
DELETE tt
FROM dbo.TB_TURMAS tt INNER JOIN @turmas t ON t.id = tt.ID;
PRINT '  - turmas: ' + CAST(@@ROWCOUNT AS varchar(10));

/* 10. Feriados criados pelo script */
DELETE f
FROM dbo.TB_FERIADOS f
INNER JOIN dbo.TB_DEMO_IDS d ON d.TABELA = 'TB_FERIADOS' AND d.REGISTRO_ID = f.ID;
PRINT '  - feriados: ' + CAST(@@ROWCOUNT AS varchar(10));

/* 11. Acessos dos usuários de demonstração */
DELETE ac
FROM dbo.TB_ACESSO ac INNER JOIN @usuarios u ON u.id = ac.id_usuario;
PRINT '  - vínculos de acesso: ' + CAST(@@ROWCOUNT AS varchar(10));

/* 12. Usuários — nunca toca em administrador */
DELETE us
FROM dbo.TB_USUARIOS us
INNER JOIN @usuarios u ON u.id = us.ID
WHERE us.NIVEL <> 1;
PRINT '  - usuários: ' + CAST(@@ROWCOUNT AS varchar(10));

/* 13. Períodos criados pelo script, só se ninguém mais estiver usando */
DELETE p
FROM dbo.TB_PERIODOS p
INNER JOIN dbo.TB_DEMO_IDS d ON d.TABELA = 'TB_PERIODOS' AND d.REGISTRO_ID = p.ID
WHERE NOT EXISTS (SELECT 1 FROM dbo.TB_TURMAS t WHERE t.ID_PERIODO = p.ID);
PRINT '  - períodos: ' + CAST(@@ROWCOUNT AS varchar(10));

/* 14. A própria tabela de marcação */
DROP TABLE dbo.TB_DEMO_IDS;
PRINT '  - tabela de marcação removida';

COMMIT TRANSACTION;
PRINT '';
PRINT 'Limpeza concluída. Os dados reais não foram tocados.';
END TRY
BEGIN CATCH
    IF @@TRANCOUNT > 0 ROLLBACK TRANSACTION;
    PRINT '';
    PRINT '*** ERRO — NADA FOI APAGADO (a transação foi desfeita) ***';
    PRINT 'Mensagem: ' + ERROR_MESSAGE();
    PRINT 'Linha: '   + CAST(ERROR_LINE() AS varchar(10));
    THROW;
END CATCH
GO

/* Conferência: o que sobrou no banco */
SELECT
    (SELECT COUNT(*) FROM dbo.TB_UNIDADES)          AS Unidades,
    (SELECT COUNT(*) FROM dbo.TB_USUARIOS)          AS Usuarios,
    (SELECT COUNT(*) FROM dbo.TB_PERIODOS)          AS Periodos,
    (SELECT COUNT(*) FROM dbo.TB_TURMAS)            AS Turmas,
    (SELECT COUNT(*) FROM dbo.TB_ALUNOS)            AS Alunos,
    (SELECT COUNT(*) FROM dbo.TB_FERIADOS)          AS Feriados,
    (SELECT COUNT(*) FROM dbo.TB_CHAMADA)           AS Chamadas,
    (SELECT COUNT(*) FROM dbo.TB_OBSERVACOES)       AS Observacoes,
    (SELECT COUNT(*) FROM dbo.TB_RELATORIOS_ANUAIS) AS Relatorios;
GO
