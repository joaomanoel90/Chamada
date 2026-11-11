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
- **Foto do aluno e crachá**: envie uma foto no cadastro e imprima o crachá da
  ficha do aluno ou salve em PDF
- **Feriados** de dia inteiro ou parciais (por faixa de horário e por unidade)
- **Observações** por aluno, organizadas por área do desenvolvimento
- **Relatório anual** com frequência mês a mês, observações do ano, parecer
  descritivo, encaminhamentos, versão para impressão e exportação CSV
- **Cadastros** de alunos, turmas, períodos, unidades e usuários
- **Perfis de acesso**: administração (rede toda), diretor (sua unidade) e
  professor (só as turmas da sua equipe)
- **Layout** com identidade de educação infantil

## Atualizando uma instalação existente

Execute **o mesmo** `sql_inicial/INSTALACAO_COMPLETA.sql` novamente. Ele é
idempotente e faz o upgrade sem apagar dados:

- cria `TB_TURMA_PROFESSORES` e `TB_CHAMADA_RETIFICACOES`;
- acrescenta `CONFIRMADA_EM` e `CONFIRMADA_POR` em `TB_CHAMADA`;
- migra o professor único de cada turma para **regente** da equipe;
- preenche a confirmação dos registros antigos com quem os lançou;
- adiciona `TB_ALUNOS.FOTO_DADOS` e `FOTO_MIME` para armazenar as fotos no banco.

Depois do script, revise as equipes em **Turmas → Editar**: cada turma começa
com um professor (o antigo responsável) e você acrescenta os demais.
Instalações que ainda não receberam as colunas `FOTO_DADOS` e `FOTO_MIME` também
podem executar `sql_inicial/ATUALIZAR_FOTO_ALUNO.sql`; o script é idempotente.

## Instalação

### 1. Banco de dados

Execute **um único script** no SQL Server 2019+ (SSMS ou Azure Data Studio):

```
sql_inicial/INSTALACAO_COMPLETA.sql
```

Ele cria o banco `db_chamada`, as 10 tabelas, chaves, índices, a view
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
4. **Turmas** — unidade, ano letivo, período, professor e capacidade
5. **Alunos** — matrícula dos alunos nas turmas
6. **Feriados** — feriados do ano letivo
7. **Chamada do Dia** — lançamento diário

## Estrutura

```
config.php              conexão e trava global de autenticação
login.php  valida.php   login por CPF
index.php               dashboard da chamada
processa/               dados do dashboard

inc/                    layout, conexão e funções compartilhadas
  database.php          open_database() via sqlsrv
  chamada.php           acesso por unidade, datas, regras de feriado
  tema.php header.php header2.php footer.php

chamada/                lançamento e exportação da chamada
alunos/                 cadastro e ficha do aluno
turmas/  periodos/      cadastro de turmas e períodos
feriados/               feriados integrais e parciais
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
| **1** | Administração da rede | **todas** as unidades, turmas e cadastros; administra usuários e vínculos |
| **2** | Diretor | **tudo da unidade** a que está vinculado em `TB_ACESSO`; não vê as outras unidades |
| **3** | Professor | **somente as turmas** em que está na equipe (`TB_TURMA_PROFESSORES`) |

O vínculo do **diretor** é por unidade: **Usuários → Acesso Unidades**.
O vínculo do **professor** é por turma: **Turmas → Editar → Equipe da turma**.
Ao incluir o professor na equipe, ele já alcança a turma — não é obrigatório
vínculo em `TB_ACESSO` para isso.

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
A foto só é exibida para usuários com acesso à ficha.

## Chamada: confirmar uma vez, depois retificar

A presença de cada aluno é **confirmada uma única vez por dia**.

1. **Aguardando confirmação** — o professor escolhe Presente / Falta /
   Justificada / Atraso e confirma. Há um aviso de confirmação antes de gravar.
2. **Presença confirmada** — o cartão fica **travado**, mostrando a situação,
   quem confirmou e quando. Não existe mais botão de confirmar para esse aluno
   no dia.
3. **Retificar** — única forma de corrigir. Abre uma janela que exige:
   - a nova situação;
   - a justificativa, se for falta justificada;
   - **o motivo da retificação** (obrigatório).

Cada retificação grava em `TB_CHAMADA_RETIFICACOES` o status anterior, o novo,
as justificativas, o motivo, o autor e a data/hora. Consulte em
**Chamada → Histórico de retificações**.

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
| `TB_FERIADOS` | feriados integrais e parciais |
| `TB_CHAMADA` | presença por aluno/dia, com data e autor da confirmação |
| `TB_TURMA_PROFESSORES` | equipe da turma: papel, turno e prazo do substituto |
| `TB_CHAMADA_RETIFICACOES` | histórico de retificações, com motivo e autor |
| `TB_OBSERVACOES` | observações de desenvolvimento |
| `TB_RELATORIOS_ANUAIS` | parecer e encaminhamentos por aluno/ano |
| `VIEW_ACESSOS_USUARIOS` | view de apoio às permissões |

## Documentação adicional

- [`README_CHAMADA.md`](README_CHAMADA.md) — regras de negócio detalhadas
- [`LIMPEZA.md`](LIMPEZA.md) — histórico do que foi removido do repositório

## Pendências de segurança conhecidas

1. Senhas em **MD5 sem salt** (`valida.php`) — migrar para `password_hash()`
2. Formulários sem **token CSRF**
3. Credenciais do banco em texto plano no `config.php` versionado

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
