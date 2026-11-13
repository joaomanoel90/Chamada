# Scripts do banco — ordem de execução

Todos os scripts são para **SQL Server** e podem ser abertos no SSMS ou no Azure
Data Studio. Confira sempre a linha `USE [db_chamada];` no começo de cada arquivo
se o seu banco tiver outro nome.

| # | Arquivo | Quando usar |
|---|---------|-------------|
| 1 | `INSTALACAO_COMPLETA.sql` | **Obrigatório, uma vez.** Cria as tabelas, índices, as duas views, os períodos, as 20 unidades da rede e o usuário administrador. Já inclui transferências, promoção de alunos, histórico de alterações e diário escolar. |
| 2 | `CRIAR_LOGIN_APLICACAO.sql` | **Obrigatório, uma vez.** Cria o login `user_chamada` usado pelo sistema e dá as permissões mínimas. |
| 3 | `DIAGNOSTICO_LOGIN.sql` | Só se aparecer o erro **18456** ao entrar. Não altera nada, só investiga. |
| 4 | `POPULAR_DEMONSTRACAO.sql` | **Opcional.** Enche o banco com dados fictícios para você testar o sistema. |
| 5 | `LIMPAR_DEMONSTRACAO.sql` | **Opcional.** Remove os dados de demonstração quando você for começar de verdade. Em bancos antigos, execute primeiro `ATUALIZAR_DIARIO_CLASSE.sql`. |
| 6 | `ATUALIZAR_TRANSFERENCIAS.sql` | **Só para quem já tinha o sistema instalado** antes da transferência de alunos. Cria as tabelas e views de transferências, promoção, saída da rede e retorno pelo mesmo cadastro. Não altera os dados existentes. |
| 7 | `ATUALIZAR_EQUIPE_SAUDE.sql` | **Só para quem já tinha o sistema instalado** antes do turno, do substituto e da ficha de saúde. Acrescenta colunas e troca a regra de responsável. Não apaga nenhum dado. |
| 8 | `ATUALIZAR_FOTO_ALUNO.sql` | **Só para instalações existentes** que ainda não têm as colunas de imagem comprimida do aluno. |
| 9 | `ATUALIZAR_CPF_ALUNO.sql` | **Só para instalações existentes.** Adiciona CPF e unicidade; alunos antigos ficam sem CPF até serem atualizados. |
| 10 | `ATUALIZAR_DIARIO_CLASSE.sql` | **Só para instalações existentes.** Adiciona a classificação das turmas e a atribuição do professor por disciplina, além do diário e das notas por bimestre. Notas ficam desativadas por padrão. |
| 11 | `ATUALIZAR_NIVEIS_ACESSO.sql` | **Só para instalações existentes.** Habilita os níveis 4 (Secretaria de Educação) e 5 (Secretário Escolar), sem alterar os perfis existentes. |
| 12 | `ATUALIZAR_PERMISSAO_RELATORIO_SECRETARIO.sql` | **Só para instalações existentes.** Cria a tabela de autorização por Diretor, Secretário Escolar e unidade; sem liberação explícita, relatórios não ficam visíveis ao Secretário. |
| 13 | `ATUALIZAR_PERMISSAO_OBSERVACOES_SECRETARIO.sql` | **Só para instalações existentes.** Cria autorização por unidade: sem acesso, somente leitura ou leitura e inserção. Sem autorização explícita, as observações não ficam visíveis ao Secretário. |
| 14 | `ATUALIZAR_SEGURANCA_LOGIN.sql` | **Obrigatório antes de publicar o novo código de autenticação em instalações existentes.** Amplia a coluna de senha e cria a tabela persistente de limitação de tentativas. |
| 15 | `ATUALIZAR_HISTORICO_PROMOCOES.sql` | **Instalações existentes.** Cria o histórico de versões de observações e relatórios e habilita a promoção de alunos com preservação da linha do tempo. Rode depois de `ATUALIZAR_TRANSFERENCIAS.sql`. |
| 16 | `ATUALIZAR_PROGRESSAO_ESCOLAR.sql` | **Instalações existentes.** Adiciona as séries estruturadas e o resultado de promoção/retenção; rode depois dos scripts de transferência e histórico. |

Os scripts 1, 2, 4 e 6 a 16 podem ser executados mais de uma vez sem duplicar nada.

> **Já tem o sistema em uso?** Rode o `ATUALIZAR_TRANSFERENCIAS.sql` e o
> `ATUALIZAR_EQUIPE_SAUDE.sql` (nesta ordem) e copie **todos** os arquivos PHP
> novos, não só as telas. Para habilitar foto e crachá, rode também
> `ATUALIZAR_FOTO_ALUNO.sql`. Ele adiciona `FOTO_DADOS` e `FOTO_MIME` sem apagar
> outras colunas. Os scripts são conferidos antes de criar, podem
> rodar de novo sem medo, e não mexem em aluno, chamada, observação nem parecer.
> Quem está instalando do zero não precisa deles: o script 1 já traz essas
> colunas. Rode também `ATUALIZAR_CPF_ALUNO.sql` para instalações existentes;
> CPFs dos alunos antigos serão informados ao editar cada cadastro. O script 1
> já exige CPF nos novos bancos. Para o diário, execute também
> `ATUALIZAR_DIARIO_CLASSE.sql`. Em **Turmas → Adicionar/Editar**, classifique
> cada turma. Habilite notas somente nas etapas escolares; a configuração é por
> turma, não por unidade. No diário, a gestão cadastra cada disciplina e atribui
> um professor da equipe; só esse professor pode lançar as notas. O mesmo
> professor pode ficar responsável por várias disciplinas.
> Turmas antigas ficam sem classificação até serem editadas; disciplinas já
> cadastradas ficam sem responsável até a gestão atribuí-lo no diário.
> A limpeza dos dados de demonstração também remove notas e disciplinas criadas
> dentro das turmas de demonstração; em bancos antigos, execute a migração do
> diário antes de rodar `LIMPAR_DEMONSTRACAO.sql`.
> Para usar os novos perfis em uma base existente, execute também
> `ATUALIZAR_NIVEIS_ACESSO.sql`, `ATUALIZAR_PERMISSAO_RELATORIO_SECRETARIO.sql`
> e `ATUALIZAR_PERMISSAO_OBSERVACOES_SECRETARIO.sql`.
> Antes de atualizar o código de autenticação, execute também
> `ATUALIZAR_SEGURANCA_LOGIN.sql`. Ele preserva as senhas existentes; hashes MD5
> são substituídos após o primeiro login bem-sucedido.
> Para usar a progressão escolar, execute também `ATUALIZAR_PROGRESSAO_ESCOLAR.sql`.
> Em **Turmas → Editar**, classifique as turmas que já existem; o sistema não
> tenta adivinhar a série pelo nome. Promoções e transferências exigem séries
> classificadas. Transferências pendentes antigas sem resultado precisam ser
> canceladas e solicitadas novamente para informar o resultado escolar.
> Para registrar alterações nos relatórios e observações e habilitar a promoção
> de alunos, execute `ATUALIZAR_HISTORICO_PROMOCOES.sql` depois de
> `ATUALIZAR_TRANSFERENCIAS.sql`. Na promoção, escolha a turma de destino do ano
> seguinte e informe a data de início definida para o calendário escolar. É
> possível promover uma turma inteira ou cada aluno individualmente.
> A Secretaria de Educação alcança a rede toda e vincula Diretores e Secretários
> Escolares às unidades em **Vínculos de unidades**, sem criar ou editar contas.
> O Secretário Escolar opera alunos e só consulta transferências das unidades
> liberadas. A leitura de relatórios exige autorização explícita do Diretor em
> **Permissões de relatórios**. Observações exigem autorização por unidade em
> **Permissões de observações**, que pode ser somente leitura ou leitura e
> inserção de novos registros. Transferências são somente para consulta.
> Para registrar a saída para escola fora da rede, use **Registrar saída** na
> ficha do aluno. Quando ele voltar, use **Retorno à rede**, localize o cadastro
> pelo CPF e selecione a nova unidade/turma. O sistema preserva o mesmo aluno e
> a linha do tempo escolar. Instalações existentes precisam executar novamente
> `ATUALIZAR_TRANSFERENCIAS.sql` para criar a tabela de saídas externas.

> **Não sabe o que falta?** Entre como administrador e abra
> `verifica_instalacao.php` (menu **Verificar instalação**). A página lista
> arquivo faltando, arquivo de versão antiga e script de banco não executado,
> e diz o que fazer em cada caso. É o caminho mais curto quando aparece
> `Call to undefined function` depois de uma atualização.

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
| Professores (1 responsável + 1 apoio por turma, todos com turno) | 24 |
| Turma com responsável de manhã **e** responsável de tarde | 1 |
| Substituto em andamento (com prazo e motivo) | 1 |
| Alunos com alergia / restrição alimentar / medicação | 12 / 9 / 7 |
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

Os alunos de demonstração recebem CPFs fictícios com dígitos verificadores
válidos e únicos, calculados a partir da matrícula. Eles não representam dados
reais.

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
- O Diário de Classe permite lançar notas de 0 a 10 por disciplina e bimestre
  nas turmas escolares habilitadas; chamada e frequência continuam no módulo
  Chamada.
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

## Turno, substituto e saúde do aluno

### Turno

O turno — **manhã**, **tarde** ou **integral** — aparece em três lugares, e cada
um serve para uma coisa:

| Onde | Para que serve |
|------|----------------|
| **Períodos** | Classifica o horário de funcionamento da escola. |
| **Usuários** (professor) | O turno de trabalho da pessoa. É o **padrão** ao montar a equipe. |
| **Equipe da turma** | O turno que ela cobre **naquela turma**. Pode ser diferente do turno de trabalho. |

O turno do professor não é uma trava: a gestão pode colocar uma professora da
manhã para cobrir uma tarde. Diretor e administrador não têm turno.

### Um responsável por turno

Antes havia um único regente por turma. Agora a equipe é uma lista, e cada linha
diz **quem**, com que **papel** e em que **turno**:

- **Responsável** — responde pela turma naquele turno. Pode haver **um pela manhã
  e outro pela tarde**. Quem é **integral** responde pelos dois turnos, e por
  isso não pode dividir a responsabilidade com alguém de turno específico.
- **Apoio** — acompanha a turma sem ser o responsável. É o caso do professor de
  apoio que fica **o dia todo**: basta deixar o turno dele em integral.
- **Substituto** — ver abaixo.

Todos os que estão na equipe lançam chamada e retificam presença, como antes.

### Substituto com prazo

O substituto entra na mesma lista, com **data de início**, **data de fim**, o
**professor que está cobrindo** e o **motivo**. As regras:

- Ele cobre o **turno de quem substitui**, não o turno dele.
- **Dentro** do prazo, vê a turma e tem os mesmos poderes de quem está cobrindo.
- **Fora** do prazo — antes de começar ou depois de terminar — volta a não ver a
  turma. Não é preciso lembrar de remover ninguém da equipe.
- Quem está sendo substituído **continua** na equipe: a substituição soma, não
  troca. O histórico dele não é afetado.
- Duas substituições do mesmo professor não podem ter datas que se cruzem.

O prazo é conferido no ponto único por onde o acesso do professor passa, então
vale de uma vez para chamada, observações, relatório e retificação.

### Saúde do aluno

A ficha do aluno ganhou três campos de texto livre, para escrever como a família
ou o laudo informou:

- **Alergias**
- **Restrições alimentares**
- **Medicação e cuidados de saúde**

O que estiver preenchido aparece:

1. num **aviso vermelho no alto da tela de chamada**, com a lista dos alunos que
   precisam de atenção — antes de a professora começar;
2. dentro do **cartão de cada aluno** na chamada;
3. na **ficha** do aluno;
4. no **relatório anual**, inclusive **na impressão**, com moldura própria.

Aluno sem nada registrado não gera aviso nenhum.

> **Por que a saúde do relatório não usa o alerta colorido do Bootstrap:** o CSS
> de impressão esconde `.alert` de propósito (é interface, não conteúdo). Se a
> saúde usasse `.alert`, ela desapareceria do papel. Por isso existe um bloco
> próprio, `.eq-saude-ficha`, que sai na tela e no PDF.

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
