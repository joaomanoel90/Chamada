# Scripts do banco — ordem de execução

Todos os scripts são para **SQL Server** e podem ser abertos no SSMS ou no Azure
Data Studio. Confira sempre a linha `USE [db_chamada];` no começo de cada arquivo
se o seu banco tiver outro nome.

| # | Arquivo | Quando usar |
|---|---------|-------------|
| 1 | `INSTALACAO_COMPLETA.sql` | **Obrigatório, uma vez.** Cria as 13 tabelas, índices, as duas views, os períodos, as 20 unidades da rede e o usuário administrador. Já inclui a transferência de alunos. |
| 2 | `CRIAR_LOGIN_APLICACAO.sql` | **Obrigatório, uma vez.** Cria o login `user_chamada` usado pelo sistema e dá as permissões mínimas. |
| 3 | `DIAGNOSTICO_LOGIN.sql` | Só se aparecer o erro **18456** ao entrar. Não altera nada, só investiga. |
| 4 | `POPULAR_DEMONSTRACAO.sql` | **Opcional.** Enche o banco com dados fictícios para você testar o sistema. |
| 5 | `LIMPAR_DEMONSTRACAO.sql` | **Opcional.** Remove os dados de demonstração quando você for começar de verdade. |
| 6 | `ATUALIZAR_TRANSFERENCIAS.sql` | **Só para quem já tinha o sistema instalado** antes da transferência de alunos. Cria a tabela e a view que faltam. Não altera nenhum dado existente. |

Os scripts 1, 2, 4 e 6 podem ser executados mais de uma vez sem duplicar nada.

> **Já tem o sistema em uso?** Rode só o `ATUALIZAR_TRANSFERENCIAS.sql` e copie os
> arquivos PHP novos. Ele é conferido antes de criar, pode rodar de novo sem
> medo, e não mexe em aluno, chamada, observação nem parecer. Quem está
> instalando do zero não precisa dele: o script 1 já traz a mesma seção.

> **Se você recriar ou restaurar o banco**, rode o `CRIAR_LOGIN_APLICACAO.sql` de
> novo. O login pertence ao servidor, mas o usuário pertence ao banco: depois de
> um `DROP DATABASE` ou de restaurar um backup em outra instância, o
> `user_chamada` existe no servidor e não no banco, e o sistema mostra
> *"Erro na conexão com SQL Server"*.

> **Driver ODBC:** o sistema foi testado com o **ODBC Driver 17**. O Driver 18
> liga a criptografia por padrão e exige `TrustServerCertificate` no
> `inc/database.php`, que hoje não é enviado. Se você usa o 18 e a conexão falhar
> com erro de certificado, é esse o motivo.

---

## Dados de demonstração

### O que o script cria

Com a configuração padrão (3 unidades, 4 turmas por unidade, 18 alunos por turma,
3 meses de histórico):

| Item | Quantidade |
|------|-----------|
| Turmas (Berçário I e II, Maternal I e II) | 12 |
| Alunos | 216 |
| Professores (1 regente + 1 auxiliar por turma) | 24 |
| Diretores (1 por unidade) | 3 |
| Feriados (2 deles **parciais**) | 15 |
| Registros de chamada | ~13.000 |
| Retificações com histórico | 12 |
| Observações de desenvolvimento | 864 |
| Relatórios anuais | ~108 |

As unidades **não** são criadas: o script usa as que a instalação já cadastrou,
dando preferência às de educação infantil (CMEI, CEMEIF, EMEIF).

### Configuração

Abra o arquivo e ajuste o bloco `CONFIGURAÇÃO`, logo no começo:

```sql
DECLARE @AnoLetivo        int = YEAR(GETDATE());
DECLARE @QtdUnidades      int = 3;    -- 1 a 20
DECLARE @TurmasPorUnidade int = 4;    -- 1 a 6
DECLARE @AlunosPorTurma   int = 18;   -- 1 a 40
DECLARE @MesesHistorico   int = 3;    -- 0 a 12
DECLARE @GerarRelatorios  bit = 1;
```

Quanto mais unidades, turmas, alunos e meses, mais registros de chamada. Com
20 unidades × 6 turmas × 40 alunos × 12 meses o script passa de **1 milhão** de
linhas de chamada e leva alguns minutos. Comece pequeno.

### Entrar no sistema com os dados de demonstração

Senha de todos os usuários criados: **`Demo@2026`**

Os CPFs ficam na faixa reservada `900.000.0xx-xx`, com números inválidos de
propósito para nunca colidir com um CPF verdadeiro:

- **Diretores:** `900.000.901-01`, `900.000.902-02`, `900.000.903-03`, …
- **Professores:** `900.000.001-01`, `900.000.002-02`, `900.000.003-03`, …

O administrador continua sendo o seu, criado pela instalação. No fim da execução
o script mostra uma tabela com o nome e o CPF de cada usuário criado.

### O que esperar nas telas

- **Chamada de hoje está em aberto.** O histórico vai até ontem, de propósito,
  para você testar o lançamento e a confirmação pela tela.
- Os dias anteriores já aparecem como **confirmados às 08:30**, pelo regente.
- **Feriado integral** não tem chamada; **feriado parcial** tem — é assim que o
  sistema trata os dois casos.
- **Observações** são mensais, com a mais recente no dia 1º do mês corrente. A
  tela abre filtrando o mês atual, então já abre com conteúdo.
- Metade dos alunos tem **relatório anual** preenchido, para você ver o documento
  pronto e testar a exportação.
- A **idade** de cada aluno é sorteada dentro da faixa da turma usando o corte de
  **31/03 do ano letivo**, como manda a regra de matrícula. Por isso, em novembro,
  uma criança do Berçário I pode aparecer com 1 ou 2 anos: ela tinha até 1 ano na
  data de corte e fez aniversário durante o ano. Não é erro.

### Foi testado em SQL Server de verdade

Os cinco scripts foram executados num **SQL Server 2022** real, lote por lote, e o
sistema foi exercitado nele pelo driver **`sqlsrv`** com **ODBC Driver 17**:
cadastrar turma com equipe, trocar regente, lançar e confirmar chamada, retificar
com motivo, observação, relatório, usuário, liberação de unidade, feriado parcial
e exportação de CSV. Tudo passou.

### Como desfazer

Execute `LIMPAR_DEMONSTRACAO.sql`. Ele apaga apenas o que o script de
demonstração criou, usando a lista guardada na tabela `dbo.TB_DEMO_IDS`, e no fim
remove a própria tabela de marcação.

Também remove o que você tiver lançado **dentro das turmas de demonstração**
durante os testes — chamada, observações, relatórios, alunos novos — porque esses
registros dependem das turmas que serão apagadas.

**Não toca em dado real.** Unidade, período em uso, usuário real e tudo que
pertence a turma real ficam intactos. Se algum usuário de demonstração tiver
lançado registro em aluno de turma **real**, o script para e mostra a lista em
vez de apagar: resolva aqueles registros e rode de novo.

> Faça backup antes de rodar a limpeza em produção. O script é tudo-ou-nada e
> desfaz a transação em caso de erro, mas backup é sempre o caminho seguro.

---

## Transferência de aluno entre escolas

O fluxo tem duas etapas, e o banco guarda as duas:

1. **A escola de origem indica o destino.** Em *Alunos → o aluno → Transferir*, o
   diretor escolhe a escola e informa o motivo (obrigatório). A transferência
   fica `PENDENTE`. **O aluno não sai da turma** — a chamada continua normal.
2. **A escola de destino recebe.** Em *Transferências → Aguardando*, a escola
   escolhe a turma e a **data de início**. Só então o aluno muda de turma e a
   transferência passa a `CONCLUIDA`.

Uma pendência pode ser cancelada por qualquer uma das duas escolas, com motivo
obrigatório (`CANCELADA`).

**A data de início é o que separa os períodos.** Até a véspera, o aluno pertence à
turma de origem; daquele dia em diante, à de destino. A chamada do dia, o CSV e o
histórico de retificações usam essa data — um registro antigo nunca "muda de
escola".

**Nada é movido nem apagado.** `TB_CHAMADA`, `TB_OBSERVACOES` e
`TB_RELATORIOS_ANUAIS` referenciam `ID_ALUNO`, não a turma. O relatório anual
continua sendo do ano inteiro, e ganha um quadro com as escolas e turmas de cada
período.

**`VW_ALUNO_VINCULO`** reconstrói essa linha do tempo a partir das transferências
concluídas. Não há uma segunda tabela de matrícula para manter em sincronia: a
turma atual fica em `TB_ALUNOS.ID_TURMA` e o resto é derivado. Todo aluno ativo
tem exatamente um vínculo em aberto — o script de atualização confere isso no
final e não deve devolver nenhuma linha.

Depois que o aluno sai, a escola de origem continua **consultando** a ficha e o
relatório de quem foi seu aluno, mas não edita mais: cadastro, observação e
parecer ficam com a escola atual.

---

## Depois de testar

1. Rode `LIMPAR_DEMONSTRACAO.sql`.
2. Troque a senha do administrador.
3. Cadastre as turmas, os professores e os alunos reais.

Se você já tem os alunos numa planilha e quer importar em vez de digitar um por
um, isso é outro script — peça que eu monto a partir do formato da sua planilha.
