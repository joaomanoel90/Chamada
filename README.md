# Chamada de Alunos — Prefeitura Municipal de Angatuba

Sistema web de chamada, observações de desenvolvimento e relatório anual de alunos
da rede municipal (creches e escolas).

**Stack:** PHP 8 + SQL Server (driver `sqlsrv`) + Bootstrap 5, jQuery, DataTables e Chart.js.

---

## Funcionalidades

- **Chamada diária** por turma, com Presente, Falta, Falta justificada e Atraso
- **Feriados** de dia inteiro ou parciais (por faixa de horário e por unidade)
- **Observações** por aluno, organizadas por área do desenvolvimento
- **Relatório anual** com frequência mês a mês, observações do ano, parecer
  descritivo, encaminhamentos, versão para impressão e exportação CSV
- **Cadastros** de alunos, turmas, períodos, unidades e usuários
- **Perfis de acesso**: gestão (tudo) e professor (só as suas unidades)

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

## Perfis

| Nível | Quem | Acesso |
|---|---|---|
| 1 e 2 | Gestão | todas as unidades e todos os cadastros |
| 3 | Professor / unidade | apenas as unidades vinculadas em `TB_ACESSO` |

Para um professor enxergar as turmas, cadastre o vínculo em
**Usuários → Acesso Unidades**. A gestão também precisa de vínculo — o script
já cria isso para o administrador inicial.

## Banco de dados

| Tabela | Conteúdo |
|---|---|
| `TB_UNIDADES` | creches e escolas |
| `TB_USUARIOS` | gestão e professores |
| `TB_ACESSO` | vínculo usuário ↔ unidade |
| `TB_PERIODOS` | Manhã, Tarde, Integral |
| `TB_TURMAS` | turmas por unidade e ano letivo |
| `TB_ALUNOS` | alunos e responsáveis |
| `TB_FERIADOS` | feriados integrais e parciais |
| `TB_CHAMADA` | presença por aluno/dia |
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
