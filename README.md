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
sql_inicial/            scripts de criação do banco
demo/                   ambiente de teste sem SQL Server (não vai para produção)
```

## Instalação

1. Crie a base `db_chamada` no SQL Server.
2. Execute, nesta ordem:
   ```
   sql_inicial/TB_UNIDADES.sql
   sql_inicial/TB_USUARIOS.sql
   sql_inicial/TB_ACESSO.sql
   sql_inicial/VIEW_ACESSOS_USUARIOS.sql
   sql_inicial/Modulo Chamada/MODULO_CHAMADA.sql
   ```
3. Ajuste `config.php` com host, usuário, senha e `BASEURL`.
4. Publique a pasta no IIS/Apache com PHP 8 e o driver `sqlsrv` habilitados
   (`php_sqlsrv` + `php_pdo_sqlsrv`, versão Thread Safe conforme o servidor).
5. Acesse `/login.php`.

> **Não publique a pasta `demo/`** em produção.

## Perfis

| Nível | Quem | Acesso |
|---|---|---|
| 1 e 2 | Gestão | todas as unidades e todos os cadastros |
| 3 | Professor / unidade | apenas as unidades vinculadas em `TB_ACESSO` |

Para um professor enxergar as turmas, cadastre o vínculo em
**Usuários → Acesso Unidades**.

## Primeiro uso

1. **Períodos** — confira Manhã, Tarde e Integral
2. **Unidades** — cadastre as creches e escolas
3. **Usuários** — cadastre os professores e vincule às unidades
4. **Turmas** — turma por unidade, ano letivo, período e professor
5. **Alunos** — matrícula dos alunos nas turmas
6. **Feriados** — feriados do ano letivo
7. **Chamada do Dia** — lançamento diário

## Desenvolvimento sem SQL Server

A pasta `demo/` emula o driver `sqlsrv` sobre SQLite para testes locais:

```bash
php demo/seed.php
php -d auto_prepend_file=$PWD/demo/bootstrap.php \
    -d session.save_path=$PWD/demo \
    -S 0.0.0.0:8001 -t . demo/router.php
```

Acesse `http://localhost:8001/Chamada/login.php` — CPF `111.111.111-11`, senha `demo123`.

Testes: `python3 demo/testes.py 8001` (40 verificações).

## Documentação adicional

- [`README_CHAMADA.md`](README_CHAMADA.md) — regras de negócio detalhadas do módulo
- [`LIMPEZA.md`](LIMPEZA.md) — o que foi removido do repositório e por quê

## Pendências de segurança conhecidas

Herdadas do sistema original e **ainda não corrigidas**:

1. Senhas em **MD5 sem salt** (`valida.php`) — migrar para `password_hash()`
2. Formulários sem **token CSRF**
3. Credenciais do banco em texto plano no `config.php` versionado
