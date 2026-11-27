# Chamada de Alunos — Prefeitura Municipal de Angatuba

Sistema web de chamada, observações de desenvolvimento e relatório anual de alunos
da rede municipal (creches e escolas).

**Stack:** PHP 8 + SQL Server (driver `sqlsrv`) + Bootstrap 5, jQuery, DataTables e Chart.js.

---

## Funcionalidades

- **Chamada diária** por turma, com Presente, Falta, Falta justificada e Atraso
- **Confirmação única** por aluno/dia: depois de confirmada, a presença só muda por
  **retificação com motivo obrigatório** e histórico auditável
- **Equipe por turma** com vários professores, **um responsável por turno**
  (manhã e tarde podem ser pessoas diferentes) e apoio que pode ficar o dia todo
- **Substituto com prazo**: entra com data de início, data de fim e o professor
  que está cobrindo; fora do prazo não vê a turma
- **Saúde do aluno**: alergias, restrições alimentares e medicação, com alerta na
  tela de chamada e no relatório impresso
- **Inclusão**: categorias na ficha interna e marcadores opcionais na frente dos
  crachás individual e da turma, somente com autorização do responsável
- **Foto do aluno e crachá**: envie uma foto no cadastro e imprima o crachá da
  ficha do aluno ou salve em PDF
- **CPF obrigatório e único do aluno**: o sistema valida os dígitos verificadores
  e impede a repetição do CPF, inclusive em cadastros simultâneos
- **Diário escolar**: notas de 0 a 10 por disciplina e bimestre, com habilitação
  por turma; a frequência continua integrada à chamada existente
- **Calendário Escolar**: início e fim do ano letivo, contador de dias letivos,
  exceções (sábado/feriado letivo ou dia útil dispensado) e feriados de dia
  inteiro ou parciais (por faixa de horário, calendário e unidade)
- **Observações** por aluno, organizadas por área do desenvolvimento
- **Histórico de alterações** das observações e dos pareceres, com versões anteriores,
  usuário responsável e data/hora
- **Progressão escolar** com séries ordenadas de Berçário I ao 9º ano, promoção
  ou retenção (por faltas, notas ou ambos) para o próximo ano letivo, salas
  paralelas e histórico preservado
- **Transferência entre escolas** com resultado escolar informado pela origem e
  escolha de uma turma compatível pela escola de destino
- **Relatório anual** com frequência mês a mês, observações do ano, parecer
  descritivo, encaminhamentos, versão para impressão e exportação CSV
- **Cadastros** de alunos, turmas, períodos, unidades e usuários
- **Perfis de acesso**: administrador do sistema, Secretaria de Educação,
  Diretor Escolar, Secretário Escolar e Professor, com escopos distintos
- **Layout** com identidade de educação infantil

## Atualizando uma instalação existente

Execute **o mesmo** `sql_inicial/INSTALACAO_COMPLETA.sql` novamente. Ele é
idempotente e faz o upgrade sem apagar dados:

- cria `TB_TURMA_PROFESSORES` e `TB_CHAMADA_RETIFICACOES`;
- acrescenta `CONFIRMADA_EM` e `CONFIRMADA_POR` em `TB_CHAMADA`;
- migra o professor único de cada turma para **regente** da equipe;
- preenche a confirmação dos registros antigos com quem os lançou;
- adiciona `TB_ALUNOS.FOTO_DADOS` e `FOTO_MIME` para armazenar as fotos no banco;
- adiciona `TB_ALUNOS.CPF` com unicidade; alunos antigos ficam sem CPF até que
  o dado correto seja informado ao editar cada cadastro;
- adiciona o Diário de Classe, a etapa de ensino das turmas e o professor
  responsável por disciplina.
- adiciona a série escolar estruturada e o resultado de promoção/retenção nas
  movimentações.
- amplia a coluna de senha e instala a tabela de limitação de tentativas.

Depois do script, revise as equipes em **Turmas → Editar**: cada turma começa
com um professor (o antigo responsável) e você acrescenta os demais.
Instalações que ainda não receberam as colunas `FOTO_DADOS` e `FOTO_MIME` também
podem executar `sql_inicial/ATUALIZAR_FOTO_ALUNO.sql`; o script é idempotente.
Para o CPF, execute `sql_inicial/ATUALIZAR_CPF_ALUNO.sql` em instalações
existentes. Não preencha CPFs antigos com números inventados.
Para o Diário de Classe, execute `sql_inicial/ATUALIZAR_DIARIO_CLASSE.sql` nas
instalações existentes.
Antes de publicar em uma instalação existente, execute
`sql_inicial/ATUALIZAR_INCLUSAO_CRACHA.sql`. A autorização do responsável fica
negada por padrão. As categorias não são impressas: o crachá mostra somente os
símbolos e a faixa colorida autorizados. O mapeamento de pictogramas é uma
convenção prática deste projeto, não uma classificação oficial universal.
Para habilitar os níveis de Secretaria de Educação e Secretário Escolar,
execute `sql_inicial/ATUALIZAR_NIVEIS_ACESSO.sql` nas instalações existentes.
Para habilitar o histórico de alterações e a promoção de alunos em instalações
existentes, execute `sql_inicial/ATUALIZAR_HISTORICO_PROMOCOES.sql` depois da
atualização de transferências. O script é idempotente e atualiza a linha do tempo
escolar sem alterar dados anteriores.
Para habilitar a progressão por série e o registro de promoção/retenção, execute
`sql_inicial/ATUALIZAR_PROGRESSAO_ESCOLAR.sql` após as atualizações de transferência
e histórico. Depois, classifique as turmas existentes em **Turmas → Editar**; o
sistema não infere a série pelo nome. Transferências pendentes antigas sem resultado
devem ser canceladas e solicitadas novamente.
Para autorizar Secretários Escolares a consultar relatórios, execute
`sql_inicial/ATUALIZAR_PERMISSAO_RELATORIO_SECRETARIO.sql`; o Diretor libera
por unidade em **Permissões de relatórios**, e o acesso fica negado por padrão.
Para autorizar a consulta de observações por um Secretário, execute
`sql_inicial/ATUALIZAR_PERMISSAO_OBSERVACOES_SECRETARIO.sql`; o Diretor libera
por unidade em **Permissões de observações**, escolhendo entre **Somente ler**
e **Ler e inserir**. Sem liberação, as observações não ficam visíveis.
Antes de publicar as melhorias de autenticação em uma instalação existente,
execute `sql_inicial/ATUALIZAR_SEGURANCA_LOGIN.sql`.
Para o log de operações sensíveis, execute `sql_inicial/ATUALIZAR_AUDITORIA.sql`.
Ele cria `TB_AUDITORIA`, a FK para `TB_USUARIOS` e pode ser rodado de novo sem
apagar histórico. Usuários são **desativados**, nunca excluídos.
Para o Calendário Escolar, execute `sql_inicial/ATUALIZAR_CALENDARIO_ESCOLAR.sql`.
Ele cria `TB_CALENDARIOS` (início e fim), `TB_CALENDARIO_EXCECOES`, acrescenta
`CALENDARIO` em `TB_FERIADOS` e converte os feriados existentes em **Ambos**, sem
alterar o comportamento anterior. Em bancos já migrados, rode o script novamente
para receber `FIM_ANO` e as exceções; ele é idempotente.
Para dar abrangência às exceções, execute
`sql_inicial/ATUALIZAR_EXCECOES_POR_UNIDADE_TURMA.sql` depois dele. Ele acrescenta
`ID_UNIDADE` e `ID_TURMA`, troca a unicidade antiga por índices por abrangência e
mantém as exceções existentes como globais. Também pode ser rodado mais de uma vez.
Para lançar 1ª e 2ª aula no mesmo dia, execute
`sql_inicial/ATUALIZAR_CHAMADA_POR_AULA.sql`. Ele acrescenta `AULA_SEQ`,
substitui a chave única `ID_ALUNO + DATA` por `ID_ALUNO + DATA + AULA_SEQ` e
marca todos os registros existentes como **1ª aula**. Também pode ser rodado
mais de uma vez.

## Instalação

### 1. Banco de dados

Execute **um único script** no SQL Server 2019+ (SSMS ou Azure Data Studio):

```
sql_inicial/INSTALACAO_COMPLETA.sql
```

Ele cria o banco `db_chamada`, as tabelas, chaves, índices, a view
`VIEW_ACESSOS_USUARIOS` e os dados iniciais:

- os 3 períodos (Manhã, Tarde, Integral)
- as 20 unidades da rede municipal
- o usuário administrador, com acesso a todas as unidades

O script é **idempotente**: pode rodar de novo sem apagar nada. Ao final ele
imprime um relatório de verificação.

> **Senha inicial do administrador: `Trocar@2026`** — troque no primeiro acesso,
> em *Alterar Senha*. O CPF cadastrado é `398.114.718-94`; edite o bloco 15 do
> script se quiser outro.

### 2. Login do banco

Crie um login só para a aplicação, com o mínimo necessário:

```sql
USE [db_chamada];
CREATE USER [user_chamada] FOR LOGIN [user_chamada];
ALTER ROLE db_datareader ADD MEMBER [user_chamada];
ALTER ROLE db_datawriter ADD MEMBER [user_chamada];
```

### 3. Aplicação

1. Publique a pasta no IIS ou Apache com **PHP 8.x**.
2. Habilite o driver **`sqlsrv`** (`php_sqlsrv` + `php_pdo_sqlsrv`), na versão
   *Thread Safe* ou *Non-Thread Safe* conforme o servidor, e a arquitetura
   correta (x64/x86).
3. Ajuste `config.php`: `DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASSWORD`,
   `DB_PORT` e `BASEURL`.
4. Acesse `/login.php`.

### 4. Primeiro uso

1. **Períodos** — já vêm cadastrados; confira os horários
2. **Unidades** — já vem a rede municipal; ajuste se precisar
3. **Usuários** — cadastre os professores e vincule às unidades
4. **Turmas** — unidade, ano letivo, período, capacidade e **aulas por dia**
   (definidas manualmente: creche integral, turma da manhã/tarde etc.)
5. **Modelos de Turma** — padrões nomeados com aulas por dia e matérias para
   aplicar a várias turmas da mesma unidade ou de outras
6. **Alunos** — matrícula dos alunos nas turmas
7. **Calendário Escolar** — defina o início e o fim do ano letivo da creche e do
   fundamental, cadastre feriados e marque exceções (dias letivos ou não letivos)
8. **Grade Diária** — defina, para cada turma e dia da semana, a disciplina de
   cada posição de aula (até a quantidade configurada na turma); só aparecem as
   matérias com atribuição, e cada uma é limitada ao total de aulas atribuídas;
   o select mostra as restantes e esconde a matéria quando a cota acaba. O
   professor vem da Atribuição, aparece abaixo da disciplina e não é editável
   na grade. O sistema bloqueia o mesmo professor na mesma posição em turmas
   diferentes, e a grade fica travada após salvar até você clicar em **Editar grade**
9. **Chamada do Dia** — lançamento diário; a **Ficha de Chamada** (no mesmo
   menu) mostra a turma inteira em uma tabela de alunos × datas do mês, com as
   aulas configuradas identificadas no cabeçalho (professor abaixo da disciplina),
   os totais por aluno e a opção
   de imprimir em A4 paisagem
10. **Diário de Classe** — classifique a turma, habilite notas somente nas
   etapas escolares, cadastre as disciplinas com seus professores responsáveis
   e lance as notas por bimestre

## Calendário Escolar

A tela **Calendário Escolar** (endereço `/feriados/`) reúne os dois calendários
globais de cada ano letivo: **Creche** (Berçário I/II e Maternal I/II) e
**Ensino Fundamental** (Pré-Escola e 1º ao 9º ano). Somente a gestão da rede
(níveis 1 e 4) define início e fim; a pré-escola segue o calendário do
fundamental. Exceções podem ser criadas para **todas as unidades** (só gestão da
rede), para **uma unidade** ou para **uma turma** — Diretores podem criar e
alterar exceções dentro das unidades vinculadas, e nunca de outras escolas. A
chamada recusa datas fora do período, fins de semana e feriados, salvo quando a
data estiver marcada como **Letivo**. Quando há mais de uma exceção na mesma
data, vale a mais específica: **turma &gt; unidade &gt; global**.

O contador parte dos dias úteis entre início e fim, desconta os feriados
integrais **globais** do calendário (feriados de uma única unidade não entram na
contagem) e aplica as exceções: **Letivo** acrescenta um sábado, domingo ou
feriado; **Não letivo** dispensa um dia útil e bloqueia o lançamento.

O painel **Dias do calendário** reúne feriados e exceções em uma única lista e
mostra a situação efetiva de cada dia: feriado integral aparece como
**Não letivo**, feriado parcial como **Letivo parcial**, e uma exceção pode
mudar a data para **Letivo** ou **Não letivo**. A coluna **Abrangência** diz se a
regra vale para todas as unidades, para qual unidade ou para qual turma. Um
feriado pode receber uma exceção diretamente na sua linha, sem sair da tela; a
exceção criada ali herda a unidade do feriado. Editar ou excluir só é oferecido
quando a regra está no alcance do usuário.

O dashboard (`index.php`) traz um **Calendário escolar** com as marcações de
início e fim do ano letivo, feriados integrais e parciais, e exceções letivas ou
não letivas. A grade usa as regras do alcance do usuário, começa no domingo,
mostra o total de dias letivos de cada calendário e lista os detalhes de cada
marcação abaixo da grade, com a abrangência de cada regra. O filtro de mês aceita
**Ano todo**: nesse modo o card apresenta as doze mini-grades do ano civil, uma
ao lado da outra, sem sair da tela. Os filtros de ano, mês e calendário, e os
botões de mês anterior/próximo (disponíveis no modo mensal), atualizam **somente
o card**, via `dashboard_calendario.php`; o restante do painel permanece intacto
e a URL é atualizada sem recarregar a página. O endpoint é somente leitura e
exige login.

Cada feriado é cadastrado para **Ambos**, **Creche** ou **Ensino Fundamental** e
pode continuar restrito a uma unidade. Quando há mais de uma regra na mesma data,
a da unidade prevalece sobre a global e um feriado integral prevalece sobre um
parcial. A opção **Abrir novo ano letivo** cria, de uma vez, o período da creche
e do ensino fundamental de um ano ainda não cadastrado. Período, exceções e
alterações de feriados são registrados em `TB_AUDITORIA`.

## Estrutura

```
config.php              conexão e trava global de autenticação
login.php  valida.php   login por CPF
index.php               dashboard da chamada
dashboard_calendario.php  endpoint somente leitura do card do calendário
processa/               dados do dashboard
  dashboard_calendario_processa.php  regras do card (reuso no endpoint)

inc/                    layout, conexão e funções compartilhadas
  database.php          open_database() via sqlsrv
  chamada.php           acesso por unidade, calendário, datas e feriados
  dashboard_calendario.php  HTML do card no dashboard e no endpoint
  tema.php header.php header2.php footer.php

chamada/                lançamento e exportação da chamada
diario/                 notas escolares por disciplina e bimestre
alunos/                 cadastro e ficha do aluno
turmas/  periodos/      cadastro de turmas e períodos
feriados/               Calendário Escolar: período, contador, exceções e feriados
observacoes/            observações por aluno
relatorios/             relatório anual de desenvolvimento

unidades/               creches e escolas
usuarios/               usuários e vínculo com unidades
conta/                  alteração de senha

css/  js/  images/      estilos, scripts e brasão
sql_inicial/            INSTALACAO_COMPLETA.sql (script único do banco)
```

## Perfis e alcance

| Nível | Quem | O que vê |
|---|---|---|
| **1** | Administrador do sistema | Rede toda e administração de usuários, acessos e configuração |
| **2** | Diretor Escolar | Gestão escolar nas unidades vinculadas em `TB_ACESSO` |
| **3** | Professor | Somente turmas em que está na equipe; lança chamada e notas autorizadas |
| **4** | Secretaria de Educação | Gestão escolar em toda a rede; vincula Diretores e Secretários Escolares às unidades em **Vínculos de unidades**. Não cria/edita contas nem configura o sistema |
| **5** | Secretário Escolar | Cadastro e gestão de alunos; consulta transferências somente das unidades vinculadas e não as altera. Consulta observações apenas nas unidades autorizadas pelo Diretor, com opção de liberar também a inserção. Só vê relatórios autorizados e não edita pareceres |

Os vínculos do **Diretor Escolar** e do **Secretário Escolar** são por unidade:
**Usuários → Acesso Unidades**. A Secretaria de Educação alcança toda a rede.
Ela pode configurar os vínculos dos dois perfis pela opção **Vínculos de unidades**,
sem acesso à criação ou edição das contas.
O vínculo do **professor** é por turma: **Turmas → Editar → Equipe da turma**.
Ao incluir o professor na equipe, ele já alcança a turma — não é obrigatório
vínculo em `TB_ACESSO` para isso.

### Professor com mais de uma escola ou turma

Quem leciona em mais de uma escola ou tem mais de uma turma escolhe, ao entrar,
**onde está trabalhando** (escola + turma). A partir daí o sistema mostra só
esse recorte em todas as telas, e o menu lateral mostra onde ele está, com o
botão **Trocar escola/turma** para mudar a qualquer momento. Quem tem uma só
turma entra direto.

As turmas de **anos anteriores ficam ocultas automaticamente**: o padrão é o
ano corrente, e quando a secretaria cria as turmas do ano seguinte, as antigas
deixam de aparecer — sem precisar inativar nada. Se o professor ainda não tem
turma no ano atual, vale o ano mais recente em que ele tem turma, para terminar
os relatórios do ano anterior. Na tela de escolha há um filtro por ano letivo
para voltar a uma turma antiga quando for preciso.

## Equipe da turma (por turno, com substituto)

Uma turma pode ter **quantos professores forem necessários**. Em
**Turmas → Editar → Equipe da turma**, cada linha diz quem é, o papel e o turno:

| Papel | Regra |
|---|---|
| **Responsável** | Um por turno. Pode haver um pela **manhã** e outro pela **tarde**. Quem é **integral** responde pelos dois, e por isso não divide a responsabilidade com alguém de turno específico. |
| **Apoio** | Acompanha a turma sem ser o responsável. Em **integral**, é o professor de apoio que fica o dia todo. |
| **Substituto** | Cobre um professor da equipe entre duas datas, com motivo. Herda o turno de quem substitui. |

- O turno vem preenchido com o **turno de trabalho do professor** (cadastrado em
  *Usuários*), mas pode ser mudado por turma: é padrão, não trava.
- Todos os que estão na equipe lançam chamada, registram observações e retificam
  presenças; quem não está **não vê** a turma.
- O **substituto** só vê a turma dentro do prazo. Antes de começar e depois de
  terminar, volta a não ver — sem precisar remover ninguém da equipe. Quem está
  sendo coberto continua na equipe.

Ao remover alguém da equipe, o vínculo é **desativado**, não apagado — o
histórico do que a pessoa lançou permanece intacto.

## Saúde do aluno

Em **Alunos → Editar → Saúde e alimentação**: **alergias**, **restrições
alimentares** e **medicação e cuidados de saúde**. O que estiver preenchido
aparece no alto da tela de chamada, dentro do cartão do aluno, na ficha e no
relatório anual impresso. Aluno sem registro não gera aviso.

## Foto do aluno e crachá

Na inclusão ou edição do aluno, envie uma foto JPEG, PNG ou WebP de até 5 MB.
O sistema reduz a imagem para no máximo 640 × 800 pixels, comprime em JPEG e
armazena os dados binários em `TB_ALUNOS.FOTO_DADOS` (até 512 KB), sem criar
arquivo permanente no servidor. A extensão GD do PHP precisa estar ativada.
Na ficha, **Gerar crachá** abre a versão para impressão ou para salvar como PDF.
A lista de **Turmas** também permite gerar os crachás de todos os alunos ativos
da turma, organizados em folhas A4 (até 9 por página). Professores também
encontram a ação na tela da chamada da turma.
A foto só é exibida para usuários com acesso à ficha.

### Restrição familiar no crachá

A restrição **não** fica no formulário de edição do aluno. Na ficha, o botão
**Restrição familiar** abre `alunos/restricao.php`, onde cada contato vinculado
pode ser restringido ou liberado por uma ação própria, com **motivo obrigatório**.
A restrição é do vínculo com aquele aluno (o mesmo CPF pode atender outros
alunos normalmente). O contato continua no cadastro e na ficha, mas é omitido
no crachá individual e no crachá em lote; se todos os contatos estiverem
restritos, o crachá orienta a procurar a unidade escolar.

Cada restringir/liberar grava um **histórico** com data, ação, motivo e usuário
responsável, além da auditoria do sistema. Ao restringir, o sistema grava
também uma observação na categoria **Contato com a família** orientando a
**gerar outro crachá suprimindo a informação do contato restrito** e substituir
os crachás anteriores. A observação só é criada na mudança do estado, não se
repetindo em ações seguintes. Se o usuário não puder escrever observações no
aluno, a restrição é salva e o aviso aparece no flash.

A edição do cadastro preserva a restrição já registrada para cada CPF; trocar
o CPF do contato cria um vínculo novo, liberado. Para instalações existentes,
rode `sql_inicial/ATUALIZAR_RESTRICAO_CONTATO_FAMILIA.sql`; a tela
**Verificar instalação** confere a coluna, a tabela de histórico e as
constraints.

### Suspensão do aluno

Na ficha do aluno, o botão **Suspensão** abre `alunos/suspensao.php`. A gestão
escolar informa **início, fim e motivo**; o aluno passa a ter **falta em todas as
aulas letivas do período**, inclusive as já lançadas. Quando já havia presença,
atraso ou falta justificada, a mudança vira falta com **retificação automática**
(antes/depois e motivo ficam no histórico de retificações). Falta registrada pelo
professor é preservada.

Só entram no cálculo as datas em que há aula para a turma vigente naquele dia:
o calendário escolar, fins de semana, feriados e exceções são respeitados. Se a
turma mudar durante o período, cada data usa o vínculo da turma correspondente.
A justificativa das faltas geradas traz o texto `Suspensão: <motivo>`.

Cada suspensão fica no histórico com período, motivo, data do registro, usuário
que determinou e aulas ainda suspensas. Para **tirar dias**, marque na tela as
datas e as aulas (1ª e/ou 2ª) que devem voltar ao normal e informe o motivo
obrigatório. Nos dias que já passaram, escolha **lançar presença** (com
retificação) ou **remover a falta e deixar a chamada pendente**. Nas aulas de
hoje em diante, a falta apenas é removida. Cada data/aula liberada gera uma linha
no histórico com motivo e usuário; a suspensão continua ativa nas demais e só é
encerrada quando não sobra nenhuma aula ligada a ela. As liberações continuam
aparecendo na tela mesmo depois do encerramento. Retificações feitas à mão pelo
professor permanecem. A ação também vai para a auditoria (módulo **Alunos**).

Para instalações existentes, rode
`sql_inicial/ATUALIZAR_SUSPENSAO_ALUNOS.sql`. A tela **Verificar instalação**
confere as tabelas de suspensões e liberações, as constraints e a coluna
`TB_CHAMADA.ID_SUSPENSAO`.

## CPF do aluno

O CPF é obrigatório no cadastro e na edição, validado pelo sistema e armazenado
somente com 11 dígitos. Um índice único no SQL Server impede duplicidades mesmo
quando dois cadastros são enviados ao mesmo tempo. Em instalações já existentes,
rode `sql_inicial/ATUALIZAR_CPF_ALUNO.sql`: alunos antigos ficam temporariamente
sem CPF e precisarão ter o dado informado ao serem editados. A migração não
inventa nem altera CPFs de alunos já cadastrados.

## Saída da rede e retorno

Se o aluno for para uma escola fora da rede, registre **Registrar saída** na
ficha: informe o último dia frequentado e os dados da escola externa. O aluno
fica inativo para chamadas, mas o cadastro e o histórico são preservados.
Quando voltar, use **Alunos → Retorno à rede**, localize pelo CPF e indique a
nova turma e data de início. O retorno atualiza o cadastro existente, mesmo que
a nova escola seja diferente da anterior.

## Diário de classe

A chamada diária e o controle de frequência continuam no módulo **Chamada do
Dia**. O **Diário de Classe** acrescenta lançamento de notas de 0 a 10 por
disciplina e por um dos quatro bimestres. A gestão cadastra disciplinas;
cada disciplina recebe um professor responsável da equipe, e somente ele lança
as notas daquela matéria. O mesmo professor pode responder por várias
disciplinas. A equipe da turma e a gestão consultam os registros.

Em **Turmas → Adicionar/Editar**, classifique cada turma como Infantil — Creche,
Infantil — Pré-Escola, Fundamental — 1º ao 5º Ano ou Fundamental — 6º ao 9º Ano.
Essa configuração é por turma, permitindo que uma unidade com creche e escola
tenha classificações diferentes. O diário de notas não pode ser habilitado para
Creche. Para as demais etapas, habilite as notas individualmente na turma e, no
diário, cadastre cada disciplina com seu professor responsável.

Nas instalações existentes, execute `sql_inicial/ATUALIZAR_DIARIO_CLASSE.sql`.
Ela preserva turmas e disciplinas atuais: classifique as turmas ao editá-las e
atribua o professor às disciplinas existentes no diário antes de lançar notas.

## Chamada: por aula, confirmar uma vez, depois retificar

A presença é confirmada **uma vez por aluno e por aula**. Na chamada, o
professor escolhe **1ª aula** ou **2ª aula**; as duas são independentes no mesmo
dia. Assim ele pode dar falta na primeira e presença na segunda, ou o contrário.
Os registros antigos passaram a valer como **1ª aula**.

1. **Aguardando confirmação** — o professor escolhe Presente / Falta /
   Justificada / Atraso e confirma a aula aberta. Há um aviso antes de gravar.
2. **Presença confirmada** — o cartão fica **travado** naquela aula, mostrando a
   situação, quem confirmou e quando. A outra aula continua disponível.
3. **Retificar** — única forma de corrigir uma aula já confirmada. Abre uma
   janela que exige:
   - a nova situação;
   - a justificativa, se for falta justificada;
   - **o motivo da retificação** (obrigatório).

Cada retificação grava em `TB_CHAMADA_RETIFICACOES` o status anterior, o novo,
as justificativas, o motivo, o autor e a data/hora; a aula vem do registro
vinculado em `TB_CHAMADA`. Consulte em **Chamada → Histórico de retificações**.

Quem pode retificar: a gestão da unidade e qualquer professor da equipe da turma.
`CONFIRMADA_POR` e `CONFIRMADA_EM` continuam apontando para quem confirmou
originalmente, mesmo depois de retificações.

> Retificar para a mesma situação e a mesma justificativa é recusado — não gera
> registro vazio no histórico.

## Layout

Tema visual de educação infantil em `css/escola.css`: paleta alegre, cantos
arredondados, avatares com iniciais, cartões por aluno, painel da turma com
placar de presença e linha do tempo para o histórico. É carregado por último no
`inc/tema.php`, então prevalece sobre o `style.css` original sem alterar a
geometria do menu. Inclui ajustes para celular, impressão e
`prefers-reduced-motion`.

## Banco de dados

| Tabela | Conteúdo |
|---|---|
| `TB_UNIDADES` | creches e escolas |
| `TB_USUARIOS` | gestão e professores |
| `TB_ACESSO` | vínculo usuário ↔ unidade |
| `TB_PERIODOS` | Manhã, Tarde, Integral |
| `TB_TURMAS` | turmas por unidade e ano letivo (`ID_PROFESSOR` é legado, espelha um dos responsáveis) |
| `TB_ALUNOS` | alunos e responsáveis |
| `TB_ALUNO_SAIDAS_EXTERNAS` | saídas da rede e retorno dos alunos pelo cadastro original |
| `TB_CALENDARIOS` | início e fim do ano letivo da creche e do ensino fundamental |
| `TB_CALENDARIO_EXCECOES` | dias letivos/não letivos por calendário, com motivo e abrangência (global, unidade ou turma) |
| `TB_FERIADOS` | feriados integrais e parciais, por calendário e unidade |
| `TB_CHAMADA` | presença por aluno/aula (`AULA_SEQ` 1 ou 2), com data e autor da confirmação |
| `TB_TURMA_PROFESSORES` | equipe da turma: papel, turno e prazo do substituto |
| `TB_CHAMADA_RETIFICACOES` | histórico de retificações, com motivo e autor |
| `TB_OBSERVACOES` | observações de desenvolvimento |
| `TB_HISTORICO_OBSERVACOES` | versões anteriores das observações, com autores e data/hora |
| `TB_RELATORIOS_ANUAIS` | parecer e encaminhamentos por aluno/ano |
| `TB_HISTORICO_RELATORIOS` | versões anteriores dos pareceres, com autores e data/hora |
| `TB_PROMOCOES` | mudanças de turma para o ano seguinte, com vigência e responsável |
| `TB_AUDITORIA` | quem, quando e o quê de permissões, AEE, feriados, unidades e usuários |
| `VIEW_ACESSOS_USUARIOS` | view de apoio às permissões |

## Documentação adicional

- [`README_CHAMADA.md`](README_CHAMADA.md) — regras de negócio detalhadas
- [`LIMPEZA.md`](LIMPEZA.md) — histórico do que foi removido do repositório

## Segurança e operação

As senhas novas são armazenadas com `password_hash()` e verificadas com
`password_verify()`. Contas antigas em MD5 são atualizadas para o algoritmo
atual no primeiro login bem-sucedido. Senhas novas ou alteradas precisam ter ao
menos 12 caracteres. O limitador registra 10 falhas em 15 minutos por IP e
identificador, bloqueando novas tentativas dessa combinação por 15 minutos; não
desativa a conta. Tentativas antigas são removidas em lotes.
Com o bcrypt padrão do PHP, senhas novas também têm limite de 72 bytes para
evitar a truncagem silenciosa do algoritmo.
O identificador de limitação é armazenado como HMAC (sem CPF/IP em texto
plano), usando `CHAMADA_AUTH_PEPPER` quando definido ou `DB_PASSWORD` como chave;
mantenha a mesma chave entre servidores e saiba que trocá-la reinicia os limites.

O ID da sessão é renovado no login e a sessão expira após 30 minutos sem
atividade. As respostas recebem cabeçalhos contra MIME sniffing e clickjacking,
política de referência e restrições a objetos e enquadramento. HSTS só é enviado
em HTTPS.

Formulários de alteração, login e logout usam token CSRF de sessão; as
operações de escrita são enviadas por POST. Handlers de interface (confirmação,
impressão, envio automático de filtros e caixa alta) ficam em
`js/csp-handlers.js` como atributos `data-confirm`, `data-print`,
`data-auto-submit` e `data-uppercase` — nenhum `onclick`/`onchange` inline.

A **Content-Security-Policy** é enviada em todas as respostas e fixa
`default-src 'self'`, `script-src 'self' 'nonce-…'` mais os CDs efetivamente
usados, `object-src 'none'`, `base-uri 'self'`, `frame-ancestors 'none'` e
`form-action 'self'`. Cada `<script>` inline carrega o nonce gerado por
requisição em `config.php`. `style-src` ainda mantém `'unsafe-inline'` por
causa dos blocos `<style>` das telas.

`ch_caminho_interno()` (`inc/chamada.php`) é o único filtro para parâmetros de
retorno: só aceita caminhos relativos internos, rejeita esquemas, `//`, `..`,
barras invertidas, caracteres de controle e separadores codificados (`%2f`,
`%5c`). O valor é inspecionado **decodificado** e devolvido no formato original.
Nunca use um parâmetro de retorno diretamente em `header('Location: …')`.

Antes de publicar, rode o verificador dos invariantes de POST/CSRF:

```
php ferramentas/testa_post_seguro.php
```

Ele confere se todo formulário POST emite `ch_csrf_campo()`, se todo action é
interno e se todo processador que grava chama `ch_csrf_ok()`. Processadores
somente leitura precisam declarar `ch-somente-leitura` no cabeçalho; o código
retorna 1 se houver qualquer falha. Gere também o manifesto com
`php ferramentas/gera_manifesto.php`.

As operações sensíveis são registradas em `TB_AUDITORIA` e consultadas em
**Gestão → Auditoria** (somente administrador). A tabela tem FK para
`TB_USUARIOS`: a conta é inativada, nunca removida, para preservar o histórico.

**Configuração operacional ainda necessária:** `config.php` foi preservado e
continua contendo as credenciais atuais. Em produção, mova-as para fora do
controle de versão, rotacione-as e use privilégio mínimo no SQL Server. Configure
backups protegidos. O `config.php` já define as opções de cookie antes de
`session_start()`: `session.use_strict_mode=1`,
`session.use_only_cookies=1`, `session.cookie_httponly=1` e
`session.cookie_samesite=Lax`; habilite `session.cookie_secure=1` somente com
HTTPS. Se houver proxy reverso, configure-o para informar HTTPS corretamente.

## Solução de problemas

### Erro 18456 — "Falha de logon do usuário 'user_chamada'"

O servidor foi alcançado, mas recusou a autenticação. Rode, **como
administrador** no SSMS, o diagnóstico:

```
sql_inicial/DIAGNOSTICO_LOGIN.sql
```

Ele é somente leitura e aponta a causa. As três mais comuns:

| Causa | Como identificar | Correção |
|---|---|---|
| **O LOGIN não existe** (só criaram o USER no banco) | seção 3 do diagnóstico; ERRORLOG com `State: 5` | rode `CRIAR_LOGIN_APLICACAO.sql` |
| **Senha vazia ou errada no `config.php`** | `DB_PASSWORD` está `''`; ERRORLOG com `State: 8` | preencha com a mesma senha do login |
| **Servidor em modo "Somente Windows"** | seção 2: `SomenteWindows = 1`; `State: 58` | mude para Modo Misto e **reinicie o serviço** |

> **LOGIN ≠ USER.** `LOGIN` é do servidor e autentica; `USER` é do banco e
> autoriza. `CREATE USER ... FOR LOGIN` exige que o LOGIN já exista — criar só o
> USER gera exatamente este erro.

Para criar/corrigir o login e as permissões:

```
sql_inicial/CRIAR_LOGIN_APLICACAO.sql
```

Edite a senha no bloco 2 antes de executar e depois coloque **a mesma** no
`config.php`.

### Modo Misto (se o diagnóstico apontar `SomenteWindows = 1`)

1. SSMS → botão direito no servidor → **Properties** → **Security**
2. **SQL Server and Windows Authentication mode** → OK
3. **Reinicie o serviço**: SQL Server Configuration Manager → SQL Server
   Services → botão direito em `SQL Server (INSTANCIA)` → **Restart**

### Instância nomeada ou porta diferente

`DB_HOST` aceita três formas:

| Situação | `DB_HOST` | `DB_PORT` |
|---|---|---|
| Instância padrão | `127.0.0.1` | `1433` |
| Instância nomeada | `SERVIDOR\SQLEXPRESS` | `''` (vazio) |
| Porta já no host | `SERVIDOR,1435` | ignorado |

### Outros erros

| Erro | Causa provável |
|---|---|
| `Could not find driver` | extensão `sqlsrv` não habilitada no `php.ini` |
| `Login timeout expired` | host/porta errados, firewall, ou serviço parado |
| `Cannot open database "db_chamada"` | banco não criado, ou login sem mapeamento |
| `Invalid object name 'VIEW_ACESSOS_USUARIOS'` | rode o `INSTALACAO_COMPLETA.sql` atualizado |
| `Invalid column name 'EMAIL'` | idem — o script novo cria essa coluna |

## Backup

```sql
BACKUP DATABASE [db_chamada]
TO DISK = N'C:\Backup\db_chamada.bak'
WITH INIT, COMPRESSION, STATS = 10;
```

Os dados de alunos são dados pessoais de menores (LGPD). Restrinja o acesso ao
banco e aos backups, e mantenha rotina de cópia testada.
